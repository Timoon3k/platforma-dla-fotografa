<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

use Kadr\Domain\Shared\Ulid;

/**
 * Ścieżka obiektu w magazynie plików.
 *
 * Nazwy plików generujemy sami — nazwa od klienta trafia wyłącznie do kolumny
 * opisowej w bazie (docs/SECURITY.md §4). Ścieżka składa się z ULID-ów,
 * więc nie da się jej zgadnąć ani wyliczyć z sąsiedniej.
 *
 * Układ przestrzeni nazw (ADR-011):
 *   originals/{tenant}/{gallery}/{asset}.{ext}   NIGDY nie serwowane bezpośrednio
 *   previews/{tenant}/{gallery}/{asset}/{wariant}.{format}
 *   thumbs/{tenant}/{gallery}/{asset}/{wariant}.{format}
 *   finals/{tenant}/{gallery}/{asset}.{ext}      tylko przez token pobrania
 *   brand/{tenant}/{plik}                        publiczne: logo, okładka
 */
final readonly class StoragePath implements \Stringable {

	public const ORIGINALS = 'originals';
	public const PREVIEWS  = 'previews';
	public const THUMBS    = 'thumbs';
	public const FINALS    = 'finals';
	public const BRAND     = 'brand';

	/** Przestrzenie, których zawartość nie może wyjść publicznym adresem. */
	private const PRIVATE_PREFIXES = array( self::ORIGINALS, self::PREVIEWS, self::THUMBS, self::FINALS );

	private function __construct( public string $value ) {}

	public static function original( int $tenantId, Ulid $gallery, Ulid $asset, string $extension ): self {
		return new self(
			sprintf( '%s/%d/%s/%s.%s', self::ORIGINALS, $tenantId, $gallery, $asset, self::extension( $extension ) )
		);
	}

	public static function variant(
		int $tenantId,
		Ulid $gallery,
		Ulid $asset,
		VariantSpec $spec,
		ImageFormat $format
	): self {
		$prefix = $spec->isThumbnail() ? self::THUMBS : self::PREVIEWS;

		return new self(
			sprintf( '%s/%d/%s/%s/%s.%s', $prefix, $tenantId, $gallery, $asset, $spec->name, $format->value )
		);
	}

	public static function final( int $tenantId, Ulid $gallery, Ulid $asset, string $extension ): self {
		return new self(
			sprintf( '%s/%d/%s/%s.%s', self::FINALS, $tenantId, $gallery, $asset, self::extension( $extension ) )
		);
	}

	public static function brand( int $tenantId, string $filename ): self {
		return new self( sprintf( '%s/%d/%s', self::BRAND, $tenantId, self::sanitizeSegment( $filename ) ) );
	}

	/**
	 * Odtworzenie ścieżki zapisanej w bazie.
	 *
	 * @throws \InvalidArgumentException Gdy ścieżka próbuje wyjść poza magazyn.
	 */
	public static function fromString( string $path ): self {
		// Walidujemy WEJŚCIE, zanim cokolwiek z niego obetniemy.
		// Wcześniejsza wersja robiła `trim( $path, '/' )` najpierw, przez co
		// ścieżka bezwzględna była po cichu zamieniana na względną zamiast
		// odrzucona — a ciche naprawianie złych danych wejściowych ukrywa błędy.
		$path = trim( $path );

		if ( '' === $path ) {
			throw new \InvalidArgumentException( 'Pusta ścieżka.' );
		}

		if (
			str_contains( $path, '..' )
			|| str_contains( $path, "\0" )
			|| str_starts_with( $path, '/' )
			|| str_contains( $path, '//' )
			|| 1 === preg_match( '~^[A-Za-z]:~', $path )   // ścieżka windowsowa
		) {
			throw new \InvalidArgumentException( 'Ścieżka zawiera niedozwolone znaki.' );
		}

		$path = rtrim( $path, '/' );

		if ( '' === $path ) {
			throw new \InvalidArgumentException( 'Pusta ścieżka.' );
		}

		if ( 1 !== preg_match( '~^[A-Za-z0-9][A-Za-z0-9/._-]*$~', $path ) ) {
			throw new \InvalidArgumentException( 'Ścieżka zawiera niedozwolone znaki.' );
		}

		return new self( $path );
	}

	public function prefix(): string {
		return explode( '/', $this->value )[0];
	}

	/**
	 * Czy obiekt wolno wystawić pod publicznym adresem.
	 *
	 * Oryginały, podglądy, miniatury i pliki finalne — nigdy.
	 * Wyłącznie branding (logo, okładka) jest publiczny.
	 */
	public function isPubliclyServable(): bool {
		return ! in_array( $this->prefix(), self::PRIVATE_PREFIXES, true );
	}

	public function belongsToTenant( int $tenantId ): bool {
		$segments = explode( '/', $this->value );

		return isset( $segments[1] ) && $segments[1] === (string) $tenantId;
	}

	public function __toString(): string {
		return $this->value;
	}

	private static function extension( string $extension ): string {
		$extension = strtolower( ltrim( trim( $extension ), '.' ) );

		if ( 1 !== preg_match( '/^[a-z0-9]{1,8}$/', $extension ) ) {
			throw new \InvalidArgumentException( 'Niedozwolone rozszerzenie pliku.' );
		}

		return $extension;
	}

	private static function sanitizeSegment( string $segment ): string {
		$segment = preg_replace( '/[^A-Za-z0-9._-]/', '-', $segment ) ?? '';
		$segment = trim( (string) $segment, '-.' );

		if ( '' === $segment ) {
			throw new \InvalidArgumentException( 'Pusta nazwa pliku.' );
		}

		return $segment;
	}
}
