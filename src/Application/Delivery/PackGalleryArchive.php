<?php
declare( strict_types=1 );

namespace Kadr\Application\Delivery;

use Kadr\Domain\Audit\AuditEvent;
use Kadr\Domain\Delivery\ArchiveEntry;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Queue\Job;
use Kadr\Domain\Queue\Queue;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Infrastructure\Delivery\ZipPacker;

/**
 * Pakowanie galerii do pobrania.
 *
 * TO JEST TRZECI Z CZTERECH ETAPÓW, NA KTÓRYCH PRODUKT ZARABIA — i ten,
 * który generuje polecenia (skill photography-workflow §2). Klientka
 * opowiada znajomym o momencie, w którym dostała zdjęcia, nie o tym,
 * w którym je wybierała.
 *
 * Dziś kończy się to Dyskiem Google i linkiem, który wygasa, zanim
 * klientka zdąży pobrać.
 *
 * Klasa robi jedną porcję i mówi, czy zostało coś jeszcze. Zapętlanie
 * należy do kolejki — patrz `ZipPacker` po powody.
 */
final readonly class PackGalleryArchive {

	/**
	 * Ile zdjęć na jedno przejście.
	 *
	 * Dobrane pod najgorszy przypadek, jaki umiemy sobie wyobrazić: pięćdziesiąt
	 * plików RAW po 80 MB to cztery gigabajty przepisane w jednym przebiegu.
	 * Na wolnym dysku VPS-a to grubo poniżej minuty — mieści się w limicie
	 * czasu wykonania z zapasem, a przy zdjęciach JPEG jest wręcz szybko.
	 */
	public const BATCH = 50;

	public function __construct(
		private GalleryRepository $galleries,
		private AssetRepository $assets,
		private SelectionRepository $selections,
		private SelectionItemRepository $items,
		private ArchiveRepository $archives,
		private AuditLogRepository $audit,
		private ZipPacker $packer,
		private Queue $queue,
		private int $tenantId,
	) {}

	/**
	 * Zlecenie paczki — liczy zawartość i zakłada wiersz stanu.
	 */
	public function request( Ulid $galleryId, ArchiveScope $scope ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono galerii.' );
		}

		if ( ! $this->packer->isSupported() ) {
			return Result::failure(
				'kadr_zip_unavailable',
				'Ten serwer nie ma rozszerzenia PHP „zip”. Skontaktuj się z hostingiem — bez niego nie da się przygotować paczki.'
			);
		}

		$entries = $this->entriesFor( $gallery, $scope );

		if ( array() === $entries ) {
			return Result::failure(
				'kadr_archive_empty',
				ArchiveScope::Selected === $scope
					? 'Klientka nie wybrała jeszcze żadnego zdjęcia — nie ma czego pakować.'
					: 'Ta galeria nie ma jeszcze zdjęć.'
			);
		}

		$path = StoragePath::archive( $this->tenantId, $galleryId, $scope->value );

		// Zlecenie zaczyna paczkę od zera, więc stary plik musi zniknąć —
		// inaczej dopisywalibyśmy do archiwum z poprzedniego wydania.
		$this->packer->reset( $path );

		$id = $this->archives->request( (int) $gallery['id'], $scope, count( $entries ), (string) $path );

		$this->audit->record(
			AuditEvent::ArchiveRequested,
			'user',
			null,
			'gallery',
			(string) $galleryId,
			array(
				'scope' => $scope->value,
				'items' => count( $entries ),
			)
		);

		// Pakowanie idzie do kolejki. Wesele to kilkanaście minut pracy —
		// fotograf ma zamknąć kartę i wrócić, a nie patrzeć na kręciołek.
		$this->queue->dispatch(
			new Job(
				'PackArchive',
				array( 'archive_id' => (string) $id )
			)
		);

		return Result::success(
			array(
				'archive_id' => (string) $id,
				'total'      => count( $entries ),
				'status'     => 'pending',
			)
		);
	}

	/**
	 * Jedna porcja pakowania.
	 *
	 * @return Result Wartość zawiera `done` — czy paczka jest kompletna.
	 */
	public function pack( Ulid $archiveId ): Result {
		$archive = $this->archives->findByPublicId( $archiveId );

		if ( null === $archive ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono paczki.' );
		}

		$gallery = $this->assetsGallery( (int) $archive['gallery_id'] );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Galeria zniknęła w trakcie pakowania.' );
		}

		$scope   = ArchiveScope::from( (string) $archive['scope'] );
		$entries = $this->entriesFor( $gallery, $scope );
		$path    = StoragePath::fromString( (string) $archive['storage_path'] );

		// Ile już leży w archiwum — pytamy PLIK, nie licznik w bazie.
		// Zapis pliku i zapis wiersza to dwie osobne operacje; gdy serwer
		// padnie między nimi, wiarygodny jest plik.
		$done  = $this->packer->count( $path );
		$batch = array_slice( $entries, $done, self::BATCH );

		if ( array() === $batch ) {
			return $this->finish( $archiveId, $archive, $path, $done );
		}

		try {
			$result = $this->packer->append( $path, $batch );
		} catch ( \Throwable $error ) {
			$this->archives->markFailed( $archiveId, $error->getMessage() );

			$this->audit->record(
				AuditEvent::ArchiveFailed,
				'system',
				null,
				'archive',
				(string) $archiveId,
				array( 'reason' => mb_substr( $error->getMessage(), 0, 120 ) )
			);

			return Result::failure( 'kadr_archive_failed', 'Nie udało się spakować plików.' );
		}

		$packed = $done + $result['added'];

		if ( $packed >= count( $entries ) ) {
			return $this->finish( $archiveId, $archive, $path, $packed );
		}

		$this->archives->advance( $archiveId, $packed, $result['bytes'] );

		return Result::success(
			array(
				'done'   => false,
				'packed' => $packed,
				'total'  => count( $entries ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $archive
	 */
	private function finish( Ulid $archiveId, array $archive, StoragePath $path, int $packed ): Result {
		$bytes   = $this->packer->size( $path );
		$expires = gmdate(
			'Y-m-d H:i:s',
			time() + ( AccessGrant::downloadLifetimeHours() * 3600 )
		);

		$this->archives->markReady( $archiveId, $bytes, $expires );

		$this->audit->record(
			AuditEvent::ArchiveReady,
			'system',
			null,
			'archive',
			(string) $archiveId,
			array(
				'items' => $packed,
				'bytes' => $bytes,
			)
		);

		return Result::success(
			array(
				'done'   => true,
				'packed' => $packed,
				'total'  => (int) $archive['total_items'],
				'bytes'  => $bytes,
			)
		);
	}

	/**
	 * Zawartość paczki w kolejności galerii.
	 *
	 * Kolejność jest decyzją fotografa (ADR-027) i musi przeżyć rozpakowanie,
	 * dlatego numer wpisu bierze się stąd, a nie z `sort_order` w bazie:
	 * przy zakresie „wybrane" numery mają iść 1, 2, 3, a nie 4, 17, 233.
	 *
	 * @param array<string, mixed> $gallery
	 * @return list<ArchiveEntry>
	 */
	private function entriesFor( array $gallery, ArchiveScope $scope ): array {
		$selected = null;

		if ( ArchiveScope::Selected === $scope ) {
			$selection = $this->selections->forGallery( (int) $gallery['id'] );

			if ( null === $selection ) {
				return array();
			}

			$selected = array();

			foreach ( $this->items->forSelection( (int) $selection['id'], SelectionState::Selected ) as $item ) {
				$selected[ (int) $item['asset_id'] ] = true;
			}
		}

		$entries  = array();
		$position = 0;
		$offset   = 0;
		$page     = 500;

		do {
			$rows = $this->assets->forGallery( (int) $gallery['id'], $page, $offset );

			foreach ( $rows as $row ) {
				if ( null !== $selected && ! isset( $selected[ (int) $row['id'] ] ) ) {
					continue;
				}

				// Zdjęcie, którego przetwarzanie nie doszło do końca, nie ma
				// czego oddać — a paczka z pustym plikiem jest gorsza niż
				// paczka bez niego.
				if ( 'ready' !== (string) $row['status'] ) {
					continue;
				}

				$entries[] = ArchiveEntry::of(
					(string) $row['storage_path'],
					(string) $row['original_name'],
					++$position
				);
			}

			$offset += $page;
		} while ( count( $rows ) === $page );

		return $entries;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function assetsGallery( int $galleryId ): ?array {
		return $this->galleries->findById( $galleryId );
	}

}
