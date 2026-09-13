<?php
declare( strict_types=1 );

namespace Kadr\Application\Studio;

use Kadr\Domain\Security\RateLimit;
use Kadr\Domain\Security\Throttle;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Tenancy\StudioSlug;
use Kadr\Domain\Tenancy\StudioStore;
use Kadr\Domain\Tenancy\UserDirectory;

/**
 * Rejestracja fotografa: konto + studio w jednym kroku.
 *
 * Fotograf zakłada JEDNO konto i od razu ma studio. Rozdzielenie tych dwóch
 * rzeczy („załóż konto", potem „utwórz studio") to dodatkowy ekran, na którym
 * część ludzi odpada, a korzyść byłaby żadna: konto bez studia nie ma czym
 * zarządzać.
 *
 * Kolejność operacji ma znaczenie i jest odwracana przy błędzie — patrz
 * komentarz przy `rollback`.
 */
final readonly class RegisterStudio {

	public const MIN_PASSWORD_LENGTH = 10;

	/** Ile wariantów sluga sprawdzamy, zanim uznamy nazwę za nie do użycia. */
	private const SLUG_ATTEMPTS = 50;

	public function __construct(
		private StudioStore $tenants,
		private UserDirectory $users,
		private Throttle $throttle,
	) {}

	public function handle( string $studioName, string $email, string $password, ?string $ipHash = null ): Result {
		$studioName = trim( $studioName );
		$email      = trim( $email );

		// Rejestracja jest publiczna, więc jest też darmowym generatorem kont
		// i sposobem na sprawdzanie, które adresy są zajęte. Limit na adres IP.
		if ( null !== $ipHash ) {
			$limit = RateLimit::loginByAddress( $ipHash );

			if ( ! $this->throttle->isAllowed( $limit ) ) {
				return Result::failure(
					'kadr_rate_limited',
					'Zbyt wiele prób rejestracji z tego adresu. Spróbuj ponownie za chwilę.',
					array( 'retry_after' => $this->throttle->retryAfter( $limit ) )
				);
			}

			$this->throttle->record( $limit );
		}

		$invalid = $this->validate( $studioName, $email, $password );

		if ( array() !== $invalid ) {
			return Result::failure(
				'kadr_invalid_input',
				'Popraw zaznaczone pola.',
				array( 'params' => $invalid )
			);
		}

		if ( $this->users->emailTaken( $email ) ) {
			// Tu WOLNO powiedzieć, że adres jest zajęty: bez tego fotograf,
			// który po prostu zapomniał, że ma konto, nie ma jak się dowiedzieć.
			// To inna sytuacja niż formularz magic linku, gdzie ujawnienie
			// zdradzałoby listę klientów cudzego studia (ADR-003).
			return Result::failure(
				'kadr_invalid_input',
				'Popraw zaznaczone pola.',
				array( 'params' => array( 'email' => 'Konto z tym adresem już istnieje. Zaloguj się.' ) )
			);
		}

		$slug = $this->availableSlug( $studioName );

		if ( null === $slug ) {
			return Result::failure(
				'kadr_invalid_input',
				'Popraw zaznaczone pola.',
				array( 'params' => array( 'studio' => 'Ta nazwa nie da się zamienić na adres. Dodaj literę lub cyfrę.' ) )
			);
		}

		$userId = $this->users->createOwner( $email, $password, $studioName );

		try {
			$studio = $this->tenants->createStudio( $studioName, (string) $slug, $email, $userId );
		} catch ( \Throwable $error ) {
			// Konto powstaje przed studiem, bo studio potrzebuje jego
			// identyfikatora. Gdy zapis studia padnie, konto MUSI zniknąć:
			// inaczej fotograf ma login bez studia, a ponowna rejestracja
			// odbija się o zajęty adres e-mail — czyli sytuację bez wyjścia.
			$this->users->deleteAccount( $userId );

			return Result::failure( 'kadr_registration_failed', 'Nie udało się założyć studia. Spróbuj ponownie.' );
		}

		$this->users->signIn( $userId );

		return Result::success(
			array(
				'tenant_id' => $studio['id'],
				'studio'    => $studioName,
				'slug'      => (string) $slug,
			)
		);
	}

	/**
	 * @return array<string, string> pole => komunikat
	 */
	private function validate( string $studioName, string $email, string $password ): array {
		$errors = array();

		if ( '' === $studioName ) {
			$errors['studio'] = 'Podaj nazwę studia — klient zobaczy ją w galerii i w wiadomościach.';
		} elseif ( mb_strlen( $studioName ) > 120 ) {
			$errors['studio'] = 'Nazwa studia może mieć najwyżej 120 znaków.';
		}

		if ( '' === $email ) {
			$errors['email'] = 'Podaj adres e-mail.';
		} elseif ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$errors['email'] = 'Podaj adres e-mail w formacie nazwa@domena.pl.';
		}

		// Długość zamiast wymuszania znaków specjalnych: dłuższe hasło jest
		// trudniejsze do złamania niż krótkie z wykrzyknikiem, a wymagania
		// typu „jedna wielka litera" produkują `Haslo1!` u wszystkich.
		if ( mb_strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			$errors['password'] = sprintf(
				'Hasło musi mieć co najmniej %d znaków. Najlepsze są długie i łatwe do zapamiętania.',
				self::MIN_PASSWORD_LENGTH
			);
		}

		return $errors;
	}

	private function availableSlug( string $studioName ): ?StudioSlug {
		$base = StudioSlug::fromName( $studioName );

		if ( null === $base ) {
			return null;
		}

		if ( ! $base->isReserved() && ! $this->tenants->slugTaken( $base->value ) ) {
			return $base;
		}

		for ( $number = 2; $number <= self::SLUG_ATTEMPTS; $number++ ) {
			$candidate = $base->withSuffix( $number );

			if ( ! $candidate->isReserved() && ! $this->tenants->slugTaken( $candidate->value ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
