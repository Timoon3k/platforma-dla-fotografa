<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Slug studia — fragment publicznego adresu rezerwacji (`/b/{studio}`).
 *
 * Trafia do adresu, który fotograf wysyła klientom i wkleja w social media,
 * więc musi być czytelny i stabilny. Polskie znaki zamieniamy na ich
 * odpowiedniki ASCII, a nie wycinamy: „Fotografia Łąka” ma dać `fotografia-laka`,
 * a nie `fotografia-ka`.
 */
final readonly class StudioSlug {

	public const MAX_LENGTH = 60;

	/** Nazwy zarezerwowane dla tras aplikacji i adresów systemowych. */
	private const RESERVED = array(
		'app', 'k', 'g', 'b', 'd', 'admin', 'wp-admin', 'api', 'rest',
		'login', 'logowanie', 'rejestracja', 'register', 'kadr', 'www',
	);

	private const TRANSLITERATION = array(
		'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
		'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
		'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
		'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
		'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e',
	);

	private function __construct( public string $value ) {}

	/**
	 * Slug wyprowadzony z nazwy studia.
	 *
	 * Zwraca `null`, gdy z nazwy nie da się zrobić żadnego sensownego sluga
	 * (np. sama interpunkcja) — wywołujący prosi wtedy o inną nazwę zamiast
	 * podstawiać losowy ciąg, którego fotograf nie rozpozna we własnym adresie.
	 */
	public static function fromName( string $name ): ?self {
		$ascii = strtr( $name, self::TRANSLITERATION );
		$ascii = mb_strtolower( $ascii, 'UTF-8' );

		// Wszystko, co nie jest literą ASCII ani cyfrą, staje się myślnikiem.
		$slug = preg_replace( '~[^a-z0-9]+~', '-', $ascii ) ?? '';
		$slug = trim( $slug, '-' );

		if ( strlen( $slug ) > self::MAX_LENGTH ) {
			$slug = rtrim( substr( $slug, 0, self::MAX_LENGTH ), '-' );
		}

		if ( '' === $slug ) {
			return null;
		}

		return new self( $slug );
	}

	public function isReserved(): bool {
		return in_array( $this->value, self::RESERVED, true );
	}

	/**
	 * Kolejny wariant sluga, gdy poprzedni jest zajęty: `studio-2`, `studio-3`…
	 *
	 * Numer, nie losowy ciąg — fotograf ma rozpoznać własny adres.
	 */
	public function withSuffix( int $number ): self {
		$suffix = '-' . $number;
		$base   = $this->value;

		if ( strlen( $base ) + strlen( $suffix ) > self::MAX_LENGTH ) {
			$base = rtrim( substr( $base, 0, self::MAX_LENGTH - strlen( $suffix ) ), '-' );
		}

		return new self( $base . $suffix );
	}

	public function __toString(): string {
		return $this->value;
	}
}
