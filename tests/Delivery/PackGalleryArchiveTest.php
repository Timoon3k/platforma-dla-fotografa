<?php
declare( strict_types=1 );

namespace Kadr\Tests\Delivery;

use Kadr\Application\Delivery\PackGalleryArchive;
use Kadr\Application\Selection\SelectionRoom;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Infrastructure\Delivery\ZipPacker;
use Kadr\Infrastructure\Queue\DatabaseQueue;
use Kadr\Infrastructure\Storage\LocalStorage;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Pakowanie galerii do pobrania.
 *
 * Testy pakują PRAWDZIWE archiwum na dysku i je rozpakowują. Sprawdzanie
 * samych liczników nie powiedziałoby nic o tym, czy klientka otworzy plik,
 * który dostanie — a to jest jedyne pytanie, które ma tu znaczenie.
 */
final class PackGalleryArchiveTest extends TestCase {

	private string $base = '';

	public function testPackingSelectedPhotosProducesAnArchiveThatOpens(): void {
		$world = $this->world( 6 );
		$world->select( array( 0, 2, 4 ) );

		$request = $world->pack->request( $world->gallery, ArchiveScope::Selected );

		$this->assertTrue( $request->ok );
		$this->assertSame( 3, $request->value['total'] );

		$archiveId = Ulid::fromString( $request->value['archive_id'] );
		$result    = $world->pack->pack( $archiveId );

		$this->assertTrue( $result->ok );
		$this->assertTrue( $result->value['done'] );

		$names = $world->namesInArchive( ArchiveScope::Selected );

		$this->assertSame( 3, count( $names ) );
		// Numeracja idzie 1, 2, 3 — nie 1, 3, 5. Klientka ma dostać wybór,
		// a nie ślad po tym, których zdjęć nie wybrała.
		$this->assertSame(
			array( '001-kadr-0.jpg', '002-kadr-2.jpg', '003-kadr-4.jpg' ),
			$names
		);
	}

	/**
	 * Zawartość musi być prawdziwa, nie sama nazwa wpisu.
	 */
	public function testFilesInsideTheArchiveHaveTheirRealContent(): void {
		$world = $this->world( 2 );

		$world->pack->request( $world->gallery, ArchiveScope::Everything );
		$world->packAll( ArchiveScope::Everything );

		$zip = new \ZipArchive();
		$this->assertTrue( true === $zip->open( $world->archiveFile( ArchiveScope::Everything ) ) );

		$this->assertSame( 'zawartosc-0', $zip->getFromName( '001-kadr-0.jpg' ) );
		$this->assertSame( 'zawartosc-1', $zip->getFromName( '002-kadr-1.jpg' ) );

		$zip->close();
	}

	/**
	 * Pakowanie ma przeżyć przerwanie.
	 *
	 * Wesele to 30–80 GB i nie zmieści się w jednym przebiegu PHP. Zadanie
	 * pakuje porcję i wraca do kolejki — więc kolejne wywołanie MUSI dołożyć
	 * dalszy ciąg, a nie zacząć od nowa ani zdublować wpisów.
	 */
	public function testPackingResumesWhereItStopped(): void {
		$world = $this->world( 5 );

		$world->pack->request( $world->gallery, ArchiveScope::Everything );

		$first = $world->packSmallBatch( ArchiveScope::Everything, 2 );
		$this->assertSame( 2, $first );

		$second = $world->packSmallBatch( ArchiveScope::Everything, 2 );
		$this->assertSame( 4, $second );

		$world->packAll( ArchiveScope::Everything );

		$names = $world->namesInArchive( ArchiveScope::Everything );

		$this->assertSame( 5, count( $names ) );
		// Żaden wpis nie może się powtórzyć.
		$this->assertSame( $names, array_values( array_unique( $names ) ) );
	}

	/**
	 * Ponowne zlecenie zaczyna od zera.
	 *
	 * Gdyby dopisywało do starego pliku, klientka dostałaby zdjęcia,
	 * których fotograf już z wyboru usunął.
	 */
	public function testRequestingAgainStartsFromAnEmptyArchive(): void {
		$world = $this->world( 4 );
		$world->select( array( 0, 1, 2, 3 ) );

		$world->pack->request( $world->gallery, ArchiveScope::Selected );
		$world->packAll( ArchiveScope::Selected );
		$this->assertSame( 4, count( $world->namesInArchive( ArchiveScope::Selected ) ) );

		// Klientka zawęża wybór do dwóch kadrów.
		$world->clearSelection();
		$world->select( array( 0, 1 ) );

		$world->pack->request( $world->gallery, ArchiveScope::Selected );
		$world->packAll( ArchiveScope::Selected );

		$this->assertSame( 2, count( $world->namesInArchive( ArchiveScope::Selected ) ) );
	}

	public function testPackingAnEmptySelectionIsRefusedWithAHumanReason(): void {
		$world = $this->world( 3 );

		$result = $world->pack->request( $world->gallery, ArchiveScope::Selected );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_archive_empty', $result->code );
		$this->assertTrue( str_contains( $result->message, 'nie wybrała' ) );
	}

	/**
	 * Zdjęcie, którego przetwarzanie nie doszło do końca, nie ma czego oddać.
	 */
	public function testPhotosStillBeingProcessedAreLeftOut(): void {
		$world = $this->world( 3 );
		$world->markPending( 1 );

		$request = $world->pack->request( $world->gallery, ArchiveScope::Everything );

		$this->assertSame( 2, $request->value['total'] );
	}

	/**
	 * Plik usunięty między zleceniem a pakowaniem nie może wywrócić paczki.
	 * Reszta sesji jest dla klientki warta więcej niż błąd.
	 */
	public function testAMissingFileDoesNotRuinTheWholeArchive(): void {
		$world = $this->world( 4 );

		$world->pack->request( $world->gallery, ArchiveScope::Everything );
		$world->deleteFileOnDisk( 2 );
		$world->packAll( ArchiveScope::Everything );

		$this->assertSame( 3, count( $world->namesInArchive( ArchiveScope::Everything ) ) );
	}

	/**
	 * Gotowa paczka wygasa. Trzymanie osiemdziesięciu gigabajtów w nieskończoność
	 * to koszt, którego nikt nie zamówił.
	 */
	public function testAReadyArchiveGetsAnExpiryDate(): void {
		$world = $this->world( 2 );

		$request = $world->pack->request( $world->gallery, ArchiveScope::Everything );
		$world->packAll( ArchiveScope::Everything );

		$row = $world->archives->findByPublicId( Ulid::fromString( $request->value['archive_id'] ) );

		$this->assertSame( 'ready', $row['status'] );
		$this->assertTrue( is_string( $row['expires_at'] ) && '' !== $row['expires_at'] );
		$this->assertTrue( (int) $row['bytes'] > 0 );
	}

	/**
	 * Wydanie i gotowość paczki trafiają do dziennika (docs/SECURITY.md §6).
	 */
	public function testRequestAndCompletionAreAudited(): void {
		$world = $this->world( 2 );

		$world->pack->request( $world->gallery, ArchiveScope::Everything );
		$world->packAll( ArchiveScope::Everything );

		$actions = array_map(
			static fn( array $row ): string => (string) $row['action'],
			$world->audit->latest()
		);

		$this->assertTrue( in_array( 'archive.requested', $actions, true ) );
		$this->assertTrue( in_array( 'archive.ready', $actions, true ) );
	}

	/**
	 * Izolacja tenantów (ryzyko R2): cudza galeria nie istnieje.
	 */
	public function testAnotherPhotographersGalleryCannotBePacked(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 2, 1, 'moja' );
		$other = $this->worldOn( $db, 2, 2, 'cudza' );

		$result = $mine->pack->request( $other->gallery, ArchiveScope::Everything );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );
	}

	private function world( int $photos ): object {
		return $this->worldOn( TestDatabase::migrated(), $photos, 1 );
	}

	private function worldOn( mixed $db, int $photos, int $tenantId, string $slug = 'wesele' ): object {
		$this->base = sys_get_temp_dir() . '/kadr-zip-' . bin2hex( random_bytes( 6 ) );
		$storage    = new LocalStorage( $this->base, 'https://example.test/d' );
		$tenant     = TestDatabase::tenant( $tenantId );

		$galleries  = new GalleryRepository( $db, $tenant );
		$assets     = new AssetRepository( $db, $tenant );
		$selections = new SelectionRepository( $db, $tenant );
		$items      = new SelectionItemRepository( $db, $tenant );
		$archives   = new ArchiveRepository( $db, $tenant );
		$audit      = new AuditLogRepository( $db, $tenant );
		$packer     = new ZipPacker( $storage );

		$gallery = $galleries->create( 'Ślub Marty', $slug );
		$row     = $galleries->findByPublicId( $gallery );
		$ids     = array();

		for ( $i = 0; $i < $photos; $i++ ) {
			$assetId = Ulid::generate();
			$path    = StoragePath::original( $tenantId, $gallery, $assetId, 'jpg' );

			$storage->put( $path, "zawartosc-$i" );

			$ids[] = $assets->create(
				(int) $row['id'],
				array(
					'original_name' => "kadr-$i.jpg",
					'storage_path'  => (string) $path,
					'content_hash'  => hash( 'sha256', "$slug-$i" ),
					'bytes'         => 11,
					'width'         => 3000,
					'height'        => 2000,
					'sort_order'    => $i,
					'status'        => 'ready',
				),
				$assetId
			);
		}

		return new class(
			$gallery,
			$ids,
			$assets,
			$selections,
			$items,
			$archives,
			$audit,
			$storage,
			$packer,
			$tenantId,
			new SelectionRoom( $galleries, $assets, $selections, $items ),
			new PackGalleryArchive(
				$galleries,
				$assets,
				$selections,
				$items,
				$archives,
				$audit,
				$packer,
				new DatabaseQueue( $db, $tenantId ),
				$tenantId
			)
		) {
			/**
			 * @param list<Ulid> $assetIds
			 */
			public function __construct(
				public Ulid $gallery,
				public array $assetIds,
				public AssetRepository $assets,
				public SelectionRepository $selections,
				public SelectionItemRepository $items,
				public ArchiveRepository $archives,
				public AuditLogRepository $audit,
				public LocalStorage $storage,
				public ZipPacker $packer,
				public int $tenantId,
				public SelectionRoom $room,
				public PackGalleryArchive $pack,
			) {}

			/**
			 * @param list<int> $indexes
			 */
			public function select( array $indexes ): void {
				foreach ( $indexes as $index ) {
					$this->room->mark( $this->gallery, $this->assetIds[ $index ], SelectionState::Selected );
				}
			}

			public function clearSelection(): void {
				foreach ( $this->assetIds as $id ) {
					$this->room->mark( $this->gallery, $id, null );
				}
			}

			public function markPending( int $index ): void {
				$this->assets->update( $this->assetIds[ $index ], array( 'status' => 'pending' ) );
			}

			public function deleteFileOnDisk( int $index ): void {
				$row = $this->assets->findByPublicId( $this->assetIds[ $index ] );

				$this->storage->delete( StoragePath::fromString( (string) $row['storage_path'] ) );
			}

			public function archivePath( ArchiveScope $scope ): StoragePath {
				return StoragePath::archive( $this->tenantId, $this->gallery, $scope->value );
			}

			public function archiveFile( ArchiveScope $scope ): string {
				return (string) $this->storage->localPath( $this->archivePath( $scope ) );
			}

			/**
			 * Pakowanie do skutku — tak, jak robi to kolejka.
			 */
			public function packAll( ArchiveScope $scope ): void {
				$archive = $this->archives->forGallery(
					(int) $this->assets->findByPublicId( $this->assetIds[0] )['gallery_id'],
					$scope
				);

				$id = Ulid::fromString( (string) $archive['public_id'] );

				for ( $pass = 0; $pass < 50; $pass++ ) {
					$result = $this->pack->pack( $id );

					if ( ! $result->ok || true === ( $result->value['done'] ?? false ) ) {
						return;
					}
				}
			}

			/**
			 * Jedna porcja o rozmiarze mniejszym niż produkcyjny — po to,
			 * żeby dało się sprawdzić wznawianie bez pakowania pięćdziesięciu
			 * zdjęć na przebieg.
			 *
			 * @return int Ile wpisów leży w archiwum po tej porcji.
			 */
			public function packSmallBatch( ArchiveScope $scope, int $size ): int {
				$path    = $this->archivePath( $scope );
				$done    = $this->packer->count( $path );
				$entries = array();
				$rows    = $this->assets->forGallery(
					(int) $this->assets->findByPublicId( $this->assetIds[0] )['gallery_id'],
					500
				);

				$position = 0;

				foreach ( $rows as $row ) {
					++$position;

					if ( $position <= $done || count( $entries ) >= $size ) {
						continue;
					}

					$entries[] = \Kadr\Domain\Delivery\ArchiveEntry::of(
						(string) $row['storage_path'],
						(string) $row['original_name'],
						$position
					);
				}

				$this->packer->append( $path, $entries );

				return $this->packer->count( $path );
			}

			/**
			 * @return list<string>
			 */
			public function namesInArchive( ArchiveScope $scope ): array {
				$zip = new \ZipArchive();

				if ( true !== $zip->open( $this->archiveFile( $scope ) ) ) {
					return array();
				}

				$names = array();

				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$names[] = (string) $zip->getNameIndex( $i );
				}

				$zip->close();
				sort( $names );

				return $names;
			}
		};
	}
}
