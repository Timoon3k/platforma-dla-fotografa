<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Client;

use Kadr\Application\Gallery\OpenSharedGallery;
use Kadr\Domain\Shared\SystemClock;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Platform\GalleryLookup;
use Kadr\Infrastructure\Security\WpCacheThrottle;

defined( 'ABSPATH' ) || exit;

/**
 * Bramka dostępu klientki: token z adresu plus przejdziony PIN.
 *
 * Istnieje jako osobna klasa, bo tej samej odpowiedzi potrzebują dwa różne
 * miejsca: strona galerii i endpointy wyboru zdjęć. Gdyby każde z nich miało
 * własną kopię tej logiki, rozjechałyby się przy pierwszej zmianie — a jest
 * to jedyna rzecz stojąca między obcą osobą a czyimiś prywatnymi zdjęciami.
 */
final class GalleryGate {

	public static function useCase(): OpenSharedGallery {
		$db = Connection::get();

		return new OpenSharedGallery( $db, new GalleryLookup( $db ), new WpCacheThrottle(), new SystemClock() );
	}

	/**
	 * Otwarcie galerii tokenem, z uwzględnieniem PIN-u.
	 *
	 * @return array<string, mixed>|null Kontekst galerii albo `null`, gdy
	 *                                   dostępu nie ma — z JAKIEGOKOLWIEK
	 *                                   powodu. Wywołujący nie rozróżnia
	 *                                   powodów, bo klient też nie może.
	 */
	public static function context( string $token ): ?array {
		$opened = self::useCase()->open( $token );

		if ( $opened->isFailure() ) {
			return null;
		}

		$context = $opened->value;

		if ( $context['needs_pin'] && ! self::pinAccepted( $context ) ) {
			return null;
		}

		return $context;
	}

	/**
	 * Czy PIN został już podany w tej przeglądarce.
	 *
	 * Ciasteczko jest podpisane kluczem instalacji, więc nie da się go
	 * podrobić, i nie zawiera ani PIN-u, ani tokenu — wyłącznie dowód,
	 * że bramka została przejdziona.
	 *
	 * @param array<string, mixed> $context
	 */
	public static function pinAccepted( array $context ): bool {
		$name = self::cookieName( $context );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt własnego, podpisanego ciasteczka.
		$given = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $name ] ) ) : '';

		return '' !== $given && hash_equals( self::cookieValue( $context ), $given );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function remember( array $context ): void {
		setcookie(
			self::cookieName( $context ),
			self::cookieValue( $context ),
			array(
				'expires'  => time() + ( 12 * HOUR_IN_SECONDS ),
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function cookieName( array $context ): string {
		return 'kadr_g_' . substr( hash( 'sha256', (string) $context['access']['token_hash'] ), 0, 12 );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function cookieValue( array $context ): string {
		return hash_hmac( 'sha256', (string) $context['access']['token_hash'], wp_salt( 'auth' ) );
	}
}
