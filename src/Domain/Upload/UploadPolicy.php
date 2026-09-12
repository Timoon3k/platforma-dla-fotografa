<?php
declare( strict_types=1 );

namespace Kadr\Domain\Upload;

/**
 * Reguły przyjmowania plików (docs/SECURITY.md §4).
 *
 * Lista DOZWOLONYCH rozszerzeń, nie zakazanych — lista zakazanych zawsze
 * ma dziurę, bo nie da się wymienić wszystkiego, czego jeszcze nie znamy.
 */
final readonly class UploadPolicy {

	/** 200 MB — pojedyncze zdjęcie ślubne w RAW-ie bywa duże. */
	public const MAX_BYTES = 209_715_200;

	/** 5 MB na fragment — kompromis między liczbą żądań a kosztem ponowienia. */
	public const CHUNK_BYTES = 5_242_880;

	/** @var list<string> */
	private const ALLOWED_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'tif', 'tiff', 'dng', 'avif' );

	/** @var list<string> */
	private const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/heic',
		'image/heif',
		'image/tiff',
		'image/avif',
		'image/x-adobe-dng',
	);

	public static function isAllowedExtension( string $filename ): bool {
		return in_array( self::extensionOf( $filename ), self::ALLOWED_EXTENSIONS, true );
	}

	/**
	 * Typ odczytany Z ZAWARTOŚCI pliku, nie z nagłówka żądania.
	 */
	public static function isAllowedMimeType( string $mimeType ): bool {
		return in_array( strtolower( trim( $mimeType ) ), self::ALLOWED_MIME_TYPES, true );
	}

	public static function isAllowedSize( int $bytes ): bool {
		return $bytes > 0 && $bytes <= self::MAX_BYTES;
	}

	/**
	 * Rozszerzenie pliku — wyłącznie do zbudowania NASZEJ nazwy.
	 * Nazwa od klienta nigdy nie trafia na dysk.
	 */
	public static function extensionOf( string $filename ): string {
		$name = strtolower( trim( $filename ) );

		// Bierzemy ostatni człon: „zdjecie.jpg.php” daje „php”, a nie „jpg”.
		$extension = pathinfo( $name, PATHINFO_EXTENSION );

		return is_string( $extension ) ? $extension : '';
	}

	public static function chunkCountFor( int $bytes ): int {
		return (int) max( 1, (int) ceil( $bytes / self::CHUNK_BYTES ) );
	}

	/**
	 * Powód odrzucenia albo `null`, gdy plik jest w porządku.
	 *
	 * Sprawdzenie deklaracji przed wysłaniem czegokolwiek — żeby klient nie
	 * wysyłał 200 MB po to, by dowiedzieć się, że format jest nieobsługiwany.
	 */
	public static function rejectionReason( string $filename, int $bytes ): ?string {
		if ( ! self::isAllowedSize( $bytes ) ) {
			return 'size';
		}

		if ( ! self::isAllowedExtension( $filename ) ) {
			return 'extension';
		}

		return null;
	}
}
