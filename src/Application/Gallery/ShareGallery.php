<?php
declare( strict_types=1 );

namespace Kadr\Application\Gallery;

use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Shared\Clock;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Udostępnienie galerii klientowi i weryfikacja dostępu.
 *
 * Link jest jedyną rzeczą, którą klientka dostaje — nie zakłada konta, nie
 * instaluje niczego, nie pamięta hasła (ADR-003). Cała ochrona sprowadza się
 * więc do trzech rzeczy:
 *
 *  1. **token o 256 bitach entropii**, w bazie wyłącznie jako hash,
 *  2. **opcjonalny PIN** — cztery cyfry, które klientka poda bez wysiłku,
 *     a które zatrzymają kogoś, kto przypadkiem zobaczył link przez ramię,
 *  3. **limit prób PIN-u** — bez niego cztery cyfry są do zgadnięcia
 *     w kilkanaście minut.
 *
 * Ta klasa obsługuje stronę FOTOGRAFA: wydanie i unieważnienie linku.
 * Otwarcie linku przez klientkę jest w `OpenSharedGallery`, bo tam nie ma
 * ani tenanta, ani zalogowanego użytkownika.
 */
final readonly class ShareGallery {

	/** PIN ma dokładnie tyle cyfr. Więcej nikt nie przepisze z SMS-a. */
	public const PIN_LENGTH = 4;

	public function __construct(
		private GalleryRepository $galleries,
		private GalleryAccessRepository $access,
		private Clock $clock,
	) {}

	/**
	 * Utworzenie linku do galerii.
	 *
	 * Jawny token zwracamy TYLKO TUTAJ i tylko raz — z bazy nie da się go
	 * odtworzyć. Fotograf, który zgubi link, wygeneruje nowy.
	 */
	public function issue( Ulid $galleryId, ?string $pin = null, ?int $lifetimeDays = null ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		if ( null !== $pin && 1 !== preg_match( '/^\d{' . self::PIN_LENGTH . '}$/', $pin ) ) {
			return Result::failure(
				'kadr_invalid_input',
				'Popraw zaznaczone pola.',
				array( 'params' => array( 'pin' => sprintf( 'PIN musi mieć dokładnie %d cyfry.', self::PIN_LENGTH ) ) )
			);
		}

		$token = SecureToken::generate();
		$days  = $lifetimeDays ?? AccessGrant::galleryLifetimeDays();

		$id = $this->access->create(
			(int) $gallery['id'],
			$token->hash,
			null === $pin ? null : password_hash( $pin, PASSWORD_DEFAULT ),
			$this->clock->now()->modify( sprintf( '+%d days', max( 1, $days ) ) )->format( 'Y-m-d H:i:s' )
		);

		return Result::success(
			array(
				'id'         => $id,
				'token'      => $token->plain,
				'slug'       => (string) $gallery['slug'],
				'pin'        => null !== $pin,
				'expires_in' => $days,
			)
		);
	}

	public function revoke( Ulid $galleryId, int $accessId ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		return 0 === $this->access->revoke( $accessId )
			? Result::failure( 'kadr_not_found', 'Nie znaleziono.' )
			: Result::success( array( 'id' => $accessId ) );
	}
}
