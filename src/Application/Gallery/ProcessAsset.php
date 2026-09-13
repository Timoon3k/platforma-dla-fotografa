<?php
declare( strict_types=1 );

namespace Kadr\Application\Gallery;

use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\ImageFormat;
use Kadr\Domain\Storage\ImageProcessor;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Storage\StorageProvider;
use Kadr\Domain\Storage\VariantSpec;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AssetVariantRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Przetworzenie wysłanego zdjęcia: metadane → warianty → gotowe.
 *
 * Wykonywane WYŁĄCZNIE w kolejce. Jedno zadanie na zdjęcie, nie na galerię —
 * dzięki temu ponowienie po awarii nie powtarza ośmiuset operacji
 * (docs/PERFORMANCE.md §7).
 *
 * Operacja jest idempotentna: ponowne uruchomienie nadpisuje warianty
 * zamiast tworzyć duplikaty.
 */
final readonly class ProcessAsset {

	public function __construct(
		private GalleryRepository $galleries,
		private AssetRepository $assets,
		private AssetVariantRepository $variants,
		private StorageProvider $storage,
		private ImageProcessor $processor,
		private int $tenantId,
	) {}

	public function __invoke( Ulid $assetId, Ulid $galleryId ): Result {
		$asset = $this->assets->findByPublicId( $assetId );

		if ( null === $asset ) {
			// Zdjęcie usunięte, zanim zadanie zdążyło się wykonać —
			// to nie jest błąd, tylko wyścig z użytkownikiem.
			return Result::success( array( 'skipped' => 'asset_gone' ) );
		}

		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::success( array( 'skipped' => 'gallery_gone' ) );
		}

		$original = $this->localPathFor( (string) $asset['storage_path'] );

		if ( null === $original ) {
			$this->assets->markFailed( $assetId );

			return Result::failure( 'kadr_original_missing', 'Brak pliku źródłowego.' );
		}

		try {
			$metadata = $this->processor->readMetadata( $original );
		} catch ( \Throwable $e ) {
			$this->assets->markFailed( $assetId );

			return Result::failure( 'kadr_unreadable_image', $e->getMessage() );
		}

		$this->assets->update(
			$assetId,
			array(
				'width'    => $metadata->effectiveWidth(),
				'height'   => $metadata->effectiveHeight(),
				'status'   => 'processing',
				'taken_at' => $metadata->takenAt?->format( 'Y-m-d H:i:s' ),
			)
		);

		$withWatermark = (bool) ( $gallery['watermark'] ?? false );
		$formats       = array_values(
			array_filter(
				ImageFormat::eager(),
				fn( ImageFormat $format ): bool => in_array( $format, $this->processor->supportedFormats(), true )
			)
		);

		$written = 0;

		foreach ( VariantSpec::forGallery( $withWatermark ) as $spec ) {
			foreach ( $formats as $format ) {
				$variantPath = StoragePath::variant( $this->tenantId, $galleryId, $assetId, $spec, $format );
				$target      = $this->absolute( $variantPath );

				try {
					$bytes = $this->processor->createVariant( $original, $target, $spec, $format, $metadata );
				} catch ( \Throwable $e ) {
					// Pojedynczy nieudany wariant nie przekreśla pozostałych —
					// zdjęcie z trzema wariantami zamiast czterech nadal działa.
					continue;
				}

				$this->variants->upsert(
					assetId:     (int) $asset['id'],
					variant:     $spec->name,
					format:      $format->value,
					storagePath: (string) $variantPath,
					bytes:       $bytes,
					width:       min( $spec->width, $metadata->effectiveWidth() ),
				);

				++$written;
			}
		}

		if ( 0 === $written ) {
			$this->assets->markFailed( $assetId );

			return Result::failure( 'kadr_no_variants', 'Nie udało się wygenerować żadnego wariantu.' );
		}

		$this->assets->update( $assetId, array( 'lqip' => $this->placeholder( $original, $metadata ) ) );

		$this->assets->markReady( $assetId );

		return Result::success(
			array(
				'variants' => $written,
				'width'    => $metadata->effectiveWidth(),
				'height'   => $metadata->effectiveHeight(),
				'had_gps'  => $metadata->hasGps,
			)
		);
	}

	/**
	 * Miniatura zastępcza zapisana jako `data:`.
	 *
	 * Powstaje w pliku tymczasowym, bo procesor obrazów pisze do pliku,
	 * a nie do pamięci — ale w magazynie nie zostaje nic. Kilkaset bajtów
	 * w bazie jest tańsze niż kolejne żądanie sieciowe na telefonie z 4G,
	 * i tylko dlatego ta kolumna istnieje.
	 *
	 * Zwraca `null`, gdy się nie uda: galeria bez LQIP-u wygląda gorzej
	 * przez chwilę, ale działa. To nie jest powód, żeby uznać zdjęcie
	 * za nieprzetworzone.
	 */
	private function placeholder( string $original, \Kadr\Domain\Storage\ImageMetadata $metadata ): ?string {
		$formats = $this->processor->supportedFormats();
		$format  = in_array( ImageFormat::Webp, $formats, true ) ? ImageFormat::Webp : ImageFormat::Jpeg;

		$temporary = tempnam( sys_get_temp_dir(), 'kadr-lqip-' );

		if ( false === $temporary ) {
			return null;
		}

		try {
			$bytes = $this->processor->createVariant(
				$original,
				$temporary,
				VariantSpec::lqip(),
				$format,
				$metadata
			);

			// Kilobajt to już nie jest „placeholder" — coś poszło nie tak
			// z kompresją i nie ma po co wozić tego w każdym dokumencie.
			if ( $bytes <= 0 || $bytes > 2048 ) {
				return null;
			}

			$contents = file_get_contents( $temporary );

			if ( false === $contents ) {
				return null;
			}

			return sprintf( 'data:%s;base64,%s', $format->mimeType(), base64_encode( $contents ) );
		} catch ( \Throwable ) {
			return null;
		} finally {
			@unlink( $temporary );
		}
	}

	/**
	 * Ścieżka pliku na dysku.
	 *
	 * Przetwarzanie obrazów wymaga pliku, nie strumienia, więc magazyn
	 * zdalny będzie musiał pobrać obiekt do lokalnego pliku tymczasowego.
	 * Dziś działa wyłącznie magazyn lokalny (ADR-017).
	 */
	private function localPathFor( string $storagePath ): ?string {
		$path = StoragePath::fromString( $storagePath );

		if ( ! $this->storage->exists( $path ) ) {
			return null;
		}

		return $this->absolute( $path );
	}

	private function absolute( StoragePath $path ): string {
		if ( method_exists( $this->storage, 'basePath' ) ) {
			return rtrim( $this->storage->basePath(), '/' ) . '/' . (string) $path;
		}

		throw new \RuntimeException(
			'Przetwarzanie obrazów wymaga magazynu udostępniającego ścieżkę lokalną.'
		);
	}
}
