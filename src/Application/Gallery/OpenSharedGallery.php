<?php
declare( strict_types=1 );

namespace Kadr\Application\Gallery;

use Kadr\Domain\Persistence\Database;
use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Security\RateLimit;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Security\Throttle;
use Kadr\Domain\Shared\Clock;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Tenancy\Role;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Tenancy\TenantId;
use Kadr\Infrastructure\Database\Platform\GalleryLookup;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Otwarcie galerii z publicznego linku `/g/{token}`.
 *
 * Klientka nie jest nikim zalogowanym: nie ma sesji, konta ani tenanta.
 * Przynosi wyłącznie token. Ten use case zamienia go na tenanta — i od tego
 * momentu **wszystko idzie przez zwykłe repozytoria z `TenantContext`**,
 * czyli przez tę samą warstwę izolacji, co panel fotografa.
 *
 * Jedyne wyjście poza tenanta to `GalleryLookup`, po globalnie unikalnym
 * hashu tokenu o 256 bitach entropii.
 *
 * Każdy powód odmowy — zły token, wygaśnięcie, unieważnienie, wycofanie
 * galerii z publikacji — daje ten SAM komunikat. Rozróżnianie ich
 * powiedziałoby zgadującemu, że trafił w istniejący link.
 */
final readonly class OpenSharedGallery {

	public function __construct(
		private Database $db,
		private GalleryLookup $lookup,
		private Throttle $throttle,
		private Clock $clock,
	) {}

	/**
	 * @return Result Sukces niesie `tenant`, `gallery`, `access`, `needs_pin`.
	 */
	public function open( string $plainToken ): Result {
		if ( ! SecureToken::looksValid( $plainToken ) ) {
			return $this->denied();
		}

		$found = $this->lookup->byTokenHash( SecureToken::hash( $plainToken ) );

		if ( null === $found ) {
			return $this->denied();
		}

		// Kontekst tenanta zbudowany z tego, co znalazł token. Rola `Member`
		// bez uprawnień: klientka nie jest członkiem zespołu i nie ma prawa
		// do niczego poza odczytem tej jednej galerii.
		$tenant = TenantContext::for(
			TenantId::fromInt( $found['tenant_id'] ),
			0,
			Role::Member,
			array()
		);

		$access = ( new GalleryAccessRepository( $this->db, $tenant ) )
			->findByTokenHash( SecureToken::hash( $plainToken ) );

		if ( null === $access || ! AccessGrant::fromRow( $access )->isUsableAt( $this->clock ) ) {
			return $this->denied();
		}

		$gallery = ( new GalleryRepository( $this->db, $tenant ) )->findById( $found['gallery_id'] );

		// Wycofanie galerii z publikacji odcina dostęp natychmiast, nawet gdy
		// link jeszcze żyje. Fotograf ma jeden przełącznik, nie dwa.
		if ( null === $gallery || 'published' !== (string) $gallery['status'] ) {
			return $this->denied();
		}

		return Result::success(
			array(
				'tenant'    => $tenant,
				'gallery'   => $gallery,
				'access'    => $access,
				'needs_pin' => null !== $access['pin_hash'] && '' !== (string) $access['pin_hash'],
			)
		);
	}

	/**
	 * Weryfikacja PIN-u.
	 *
	 * Limit prób liczony po hashu tokenu, nie po adresie IP: klientka
	 * i osoba zgadująca mogą siedzieć za tym samym adresem, a bronimy linku.
	 * Cztery cyfry bez limitu są do zgadnięcia w kilkanaście minut.
	 */
	public function verifyPin( string $plainToken, string $pin ): Result {
		$limit = RateLimit::galleryPin( SecureToken::hash( $plainToken ) );

		if ( ! $this->throttle->isAllowed( $limit ) ) {
			return Result::failure(
				'kadr_rate_limited',
				'Zbyt wiele prób. Spróbuj ponownie za godzinę.',
				array( 'retry_after' => $this->throttle->retryAfter( $limit ) )
			);
		}

		$this->throttle->record( $limit );

		$opened = $this->open( $plainToken );

		if ( $opened->isFailure() ) {
			return $opened;
		}

		$hash = (string) $opened->value['access']['pin_hash'];

		if ( '' === $hash || ! password_verify( $pin, $hash ) ) {
			return Result::failure( 'kadr_invalid_pin', 'Nieprawidłowy PIN.' );
		}

		return Result::success( $opened->value );
	}

	private function denied(): Result {
		return Result::failure(
			'kadr_invalid_link',
			'Ten link nie działa. Poproś fotografa o nowy.'
		);
	}
}
