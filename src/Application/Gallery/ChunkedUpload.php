<?php
declare( strict_types=1 );

namespace Kadr\Application\Gallery;

use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Queue\Job;
use Kadr\Domain\Queue\Queue;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Storage\StorageProvider;
use Kadr\Domain\Upload\UploadPolicy;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Wysyłanie zdjęcia fragmentami.
 *
 * Trzy kroki: zgłoszenie → fragmenty → scalenie. Podział istnieje z trzech
 * powodów, z których każdy jest realnym problemem fotografa:
 *  - limity `upload_max_filesize` na hostingach bywają niskie,
 *  - domowe łącze wysyłające zrywa w połowie wesela i trzeba wznowić,
 *  - o odrzuceniu pliku klient ma się dowiedzieć PRZED wysłaniem 200 MB.
 *
 * Limit planu i duplikat sprawdzamy na etapie zgłoszenia, nie po transferze.
 */
final readonly class ChunkedUpload {

	public function __construct(
		private GalleryRepository $galleries,
		private AssetRepository $assets,
		private StorageProvider $storage,
		private Queue $queue,
		private Entitlements $entitlements,
		private int $tenantId,
	) {}

	/**
	 * Zgłoszenie zamiaru wysłania pliku.
	 */
	public function begin( Ulid $galleryId, string $filename, int $bytes, string $contentHash ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			// Cudza galeria i galeria nieistniejąca dają ten sam wynik.
			return Result::failure( 'kadr_not_found', 'Galeria nie istnieje.' );
		}

		$rejection = UploadPolicy::rejectionReason( $filename, $bytes );

		if ( null !== $rejection ) {
			return Result::failure(
				'kadr_upload_rejected',
				'size' === $rejection
					? 'Plik jest za duży.'
					: 'Ten format pliku nie jest obsługiwany.',
				array( 'reason' => $rejection, 'max_bytes' => UploadPolicy::MAX_BYTES )
			);
		}

		// Sprawdzenie limitu PRZED transferem, nie po.
		$storageLimit = $this->entitlements->limit( 'storage_limit_bytes' );

		if ( ! $storageLimit->isUnlimited() ) {
			$used = (int) $this->usageBytes();

			if ( $used + $bytes > (int) $storageLimit->value ) {
				return Result::failure(
					'kadr_storage_exceeded',
					'Brakuje miejsca w Twoim planie.',
					array( 'used' => $used, 'limit' => $storageLimit->value, 'needed' => $bytes )
				);
			}
		}

		// Duplikat wykrywamy po hashu policzonym u klienta — plik, który już
		// jest w tym tenancie, nie musi być przesyłany po raz drugi.
		$duplicate = $this->assets->findDuplicate( $contentHash );

		if ( null !== $duplicate ) {
			return Result::success(
				array(
					'duplicate_of' => (string) $duplicate['public_id'],
					'upload_id'    => null,
				)
			);
		}

		return Result::success(
			array(
				'upload_id'    => (string) Ulid::generate(),
				'gallery_id'   => (string) $galleryId,
				'chunk_bytes'  => UploadPolicy::CHUNK_BYTES,
				'chunk_count'  => UploadPolicy::chunkCountFor( $bytes ),
				'duplicate_of' => null,
			)
		);
	}

	/**
	 * Zapis pojedynczego fragmentu.
	 *
	 * Fragment o tym samym numerze można wysłać ponownie — nadpisuje
	 * poprzedni, dzięki czemu ponowienie po zerwaniu połączenia jest
	 * bezpieczne i nie wymaga zaczynania od nowa.
	 *
	 * @param resource|string $contents
	 */
	public function appendChunk( Ulid $uploadId, int $index, mixed $contents ): Result {
		if ( $index < 0 ) {
			return Result::failure( 'kadr_invalid_chunk', 'Numer fragmentu jest niepoprawny.' );
		}

		$this->storage->put( StoragePath::chunk( $this->tenantId, $uploadId, $index ), $contents );

		return Result::success( array( 'index' => $index ) );
	}

	/**
	 * Scalenie fragmentów i utworzenie zdjęcia.
	 *
	 * @param int $chunkCount Liczba fragmentów zadeklarowana przy zgłoszeniu.
	 */
	public function complete(
		Ulid $uploadId,
		Ulid $galleryId,
		string $filename,
		int $chunkCount,
		string $contentHash
	): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			$this->discard( $uploadId, $chunkCount );

			return Result::failure( 'kadr_not_found', 'Galeria nie istnieje.' );
		}

		$assetId   = Ulid::generate();
		$extension = UploadPolicy::extensionOf( $filename );
		$target    = StoragePath::original( $this->tenantId, $galleryId, $assetId, $extension );

		$merged = fopen( 'php://temp/maxmemory:' . ( 8 * 1024 * 1024 ), 'r+b' );

		if ( false === $merged ) {
			return Result::failure( 'kadr_upload_failed', 'Nie udało się scalić pliku.' );
		}

		try {
			for ( $index = 0; $index < $chunkCount; $index++ ) {
				$chunkPath = StoragePath::chunk( $this->tenantId, $uploadId, $index );

				if ( ! $this->storage->exists( $chunkPath ) ) {
					return Result::failure(
						'kadr_upload_incomplete',
						'Brakuje fragmentu pliku. Wyślij go ponownie.',
						array( 'missing_chunk' => $index )
					);
				}

				$chunk = $this->storage->readStream( $chunkPath );
				stream_copy_to_stream( $chunk, $merged );
				fclose( $chunk );
			}

			rewind( $merged );

			// Weryfikacja spójności: to, co zapisujemy, musi być tym,
			// co klient zadeklarował.
			$actualHash = hash_init( 'sha256' );
			hash_update_stream( $actualHash, $merged );
			$actual = hash_final( $actualHash );

			if ( '' !== $contentHash && ! hash_equals( $contentHash, $actual ) ) {
				return Result::failure(
					'kadr_upload_corrupted',
					'Plik nie dotarł w całości. Wyślij go ponownie.'
				);
			}

			rewind( $merged );
			$this->storage->put( $target, $merged );

			$bytes = $this->storage->size( $target );
		} finally {
			fclose( $merged );
			$this->discard( $uploadId, $chunkCount );
		}

		$this->assets->create(
			(int) $gallery['id'],
			array(
				'original_name' => mb_substr( $filename, 0, 255 ),
				'storage_path'  => (string) $target,
				'content_hash'  => $actual,
				'bytes'         => $bytes,
				'width'         => 0,
				'height'        => 0,
				'sort_order'    => $this->assets->countForGallery( (int) $gallery['id'] ),
				'status'        => 'pending',
			),
			// Ten sam identyfikator, z którego zbudowano ścieżkę pliku.
			$assetId
		);

		// Przetwarzanie idzie do kolejki — 800 zdjęć nie może blokować żądania.
		$this->queue->dispatch(
			new Job(
				'ProcessAsset',
				array(
					'asset_id'   => (string) $assetId,
					'gallery_id' => (string) $galleryId,
				)
			)
		);

		return Result::success(
			array(
				'asset_id' => (string) $assetId,
				'bytes'    => $bytes,
				'status'   => 'pending',
			)
		);
	}

	/**
	 * Sprzątanie po porzuconej wysyłce.
	 */
	public function discard( Ulid $uploadId, int $chunkCount ): void {
		for ( $index = 0; $index < $chunkCount; $index++ ) {
			$this->storage->delete( StoragePath::chunk( $this->tenantId, $uploadId, $index ) );
		}
	}

	private function usageBytes(): int {
		return $this->assets->totalBytes();
	}
}
