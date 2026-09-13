<?php
declare( strict_types=1 );

namespace Kadr\Application\Gallery;

use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Tworzenie i zmiana ustawień galerii.
 *
 * Limit planu jest sprawdzany TUTAJ, w jednym miejscu, a nie w kontrolerze.
 * Gdyby siedział w kontrolerze, każda kolejna ścieżka tworzenia galerii
 * (import, duplikowanie, szablon) musiałaby go powtórzyć — i któraś by
 * zapomniała.
 */
final readonly class ManageGalleries {

	/** Motywy galerii klienta (ADR-015). */
	private const THEMES = array( 'noir', 'paper', 'minimal' );

	public function __construct(
		private GalleryRepository $galleries,
		private ClientRepository $clients,
		private AssetRepository $assets,
		private Entitlements $entitlements,
	) {}

	/**
	 * @param array<string, mixed> $input
	 */
	public function create( array $input ): Result {
		$invalid = $this->validate( $input, true );

		if ( array() !== $invalid ) {
			return Result::failure( 'kadr_invalid_input', 'Popraw zaznaczone pola.', array( 'params' => $invalid ) );
		}

		if ( ! $this->entitlements->hasRoomFor( 'gallery_limit', $this->galleries->countActive() ) ) {
			$limit = $this->entitlements->limit( 'gallery_limit' )->value;

			// Reguła biznesowa, nie błąd żądania: dane są poprawne, tylko plan
			// na to nie pozwala. Komunikat mówi, ile jest i co z tym zrobić.
			return Result::failure(
				'kadr_limit_reached',
				sprintf(
					'Plan %s obejmuje %d galerii. Zarchiwizuj zakończoną albo zmień plan.',
					$this->entitlements->plan()->name,
					(int) $limit
				),
				array( 'limit' => $limit )
			);
		}

		$title = trim( (string) $input['title'] );
		$id    = $this->galleries->create( $title, $this->uniqueSlug( $title ), $this->clientId( $input ) );

		$settings = $this->settings( $input );

		if ( array() !== $settings ) {
			$this->galleries->update( $id, $settings );
		}

		return Result::success( array( 'id' => (string) $id ) );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function update( Ulid $id, array $input ): Result {
		if ( null === $this->galleries->findByPublicId( $id ) ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$invalid = $this->validate( $input, false );

		if ( array() !== $invalid ) {
			return Result::failure( 'kadr_invalid_input', 'Popraw zaznaczone pola.', array( 'params' => $invalid ) );
		}

		$changes = $this->settings( $input );

		if ( array_key_exists( 'title', $input ) ) {
			$changes['title'] = trim( (string) $input['title'] );
		}

		if ( array_key_exists( 'client_id', $input ) ) {
			$changes['client_id'] = $this->clientId( $input );
		}

		if ( array() !== $changes ) {
			$this->galleries->update( $id, $changes );
		}

		return Result::success( array( 'id' => (string) $id ) );
	}

	/**
	 * Publikacja galerii.
	 *
	 * Galeria bez zdjęć nie ma po co być opublikowana — klient dostałby
	 * wiadomość i pusty ekran. To najtańszy moment, żeby to zatrzymać.
	 */
	public function publish( Ulid $id, int $photoCount ): Result {
		$gallery = $this->galleries->findByPublicId( $id );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		if ( 0 === $photoCount ) {
			return Result::failure(
				'kadr_gallery_empty',
				'Galeria nie ma jeszcze zdjęć. Wyślij je, zanim wyślesz ją klientowi.'
			);
		}

		$this->galleries->publish( $id );

		return Result::success( array( 'id' => (string) $id ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, string> pole => komunikat
	 */
	private function validate( array $input, bool $requireTitle ): array {
		$errors = array();

		if ( $requireTitle || array_key_exists( 'title', $input ) ) {
			$title = trim( (string) ( $input['title'] ?? '' ) );

			if ( '' === $title ) {
				$errors['title'] = 'Podaj nazwę galerii — klient zobaczy ją w wiadomości.';
			} elseif ( mb_strlen( $title ) > 200 ) {
				$errors['title'] = 'Nazwa galerii może mieć najwyżej 200 znaków.';
			}
		}

		if ( array_key_exists( 'theme', $input ) && ! in_array( (string) $input['theme'], self::THEMES, true ) ) {
			$errors['theme'] = 'Wybierz jeden z dostępnych motywów.';
		}

		if ( array_key_exists( 'package_limit', $input ) && null !== $input['package_limit'] ) {
			$limit = (int) $input['package_limit'];

			if ( $limit < 1 ) {
				$errors['package_limit'] = 'Pakiet musi obejmować co najmniej jedno zdjęcie. Zostaw puste, jeśli nie ma limitu.';
			}
		}

		if ( array_key_exists( 'extra_photo_price', $input ) && null !== $input['extra_photo_price'] ) {
			$price = (int) $input['extra_photo_price'];

			if ( $price < 0 ) {
				$errors['extra_photo_price'] = 'Cena nie może być ujemna.';
			}
		}

		// Pakiet bez ceny za nadmiar znaczy, że klient wybierze więcej zdjęć
		// i nikt za nie nie zapłaci — czyli dokładnie ta strata, którą ten
		// produkt ma zlikwidować (CLAUDE.md §1).
		$hasPackage = ! empty( $input['package_limit'] );
		$hasPrice   = array_key_exists( 'extra_photo_price', $input ) && null !== $input['extra_photo_price'];

		if ( $hasPackage && ! $hasPrice ) {
			$errors['extra_photo_price'] = 'Ustaw cenę zdjęcia ponad pakiet — inaczej nadmiarowe wybory będą darmowe.';
		}

		return $errors;
	}

	/**
	 * Ustawienia, które wolno zmieniać z formularza.
	 *
	 * Biała lista, nie czarna: pole spoza niej nie trafi do zapisu, nawet
	 * jeśli ktoś je dopisze do żądania.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, scalar|null>
	 */
	private function settings( array $input ): array {
		$settings = array();

		if ( array_key_exists( 'intro', $input ) ) {
			$settings['intro'] = trim( (string) $input['intro'] );
		}

		if ( array_key_exists( 'theme', $input ) ) {
			$settings['theme'] = (string) $input['theme'];
		}

		if ( array_key_exists( 'package_limit', $input ) ) {
			$settings['package_limit'] = null === $input['package_limit'] || '' === $input['package_limit']
				? null
				: (int) $input['package_limit'];
		}

		if ( array_key_exists( 'extra_photo_price', $input ) ) {
			$settings['extra_photo_price'] = null === $input['extra_photo_price'] || '' === $input['extra_photo_price']
				? null
				: (int) $input['extra_photo_price'];
		}

		if ( array_key_exists( 'allow_download', $input ) ) {
			$settings['allow_download'] = (int) (bool) $input['allow_download'];
		}

		if ( array_key_exists( 'watermark', $input ) ) {
			$settings['watermark'] = (int) (bool) $input['watermark'];
		}

		if ( array_key_exists( 'cover_asset_id', $input ) ) {
			$settings['cover_asset_id'] = $this->coverId( $input );
		}

		if ( array_key_exists( 'expires_at', $input ) ) {
			$settings['expires_at'] = $this->expiry( (string) $input['expires_at'] );
		}

		return $settings;
	}

	/**
	 * Termin ważności podany jako data → koniec tego dnia w UTC.
	 *
	 * Fotograf wpisuje „do 30 września" i ma na myśli cały ten dzień,
	 * a nie północ, po której klient traci dostęp.
	 */
	private function expiry( string $date ): ?string {
		if ( '' === trim( $date ) ) {
			return null;
		}

		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );

		return false === $parsed ? null : $parsed->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Okładka galerii.
	 *
	 * Przyjmujemy publiczny identyfikator zdjęcia i sprawdzamy, czy należy
	 * do TEJ galerii — inaczej okładką jednej sesji dałoby się ustawić kadr
	 * z innej, a klient zobaczyłby cudze zdjęcie.
	 *
	 * @param array<string, mixed> $input
	 */
	private function coverId( array $input ): ?int {
		$publicId = trim( (string) ( $input['cover_asset_id'] ?? '' ) );

		if ( '' === $publicId ) {
			return null;
		}

		$id = Ulid::tryFrom( $publicId );

		if ( null === $id ) {
			return null;
		}

		$asset = $this->assets->findByPublicId( $id );

		if ( null === $asset || 'ready' !== (string) $asset['status'] ) {
			return null;
		}

		return (int) $asset['id'];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function clientId( array $input ): ?int {
		$publicId = trim( (string) ( $input['client_id'] ?? '' ) );

		if ( '' === $publicId ) {
			return null;
		}

		$client = $this->clients->findByPublicId( Ulid::fromString( $publicId ) );

		return null === $client ? null : (int) $client['id'];
	}

	/**
	 * Slug galerii unikalny w obrębie tenanta.
	 *
	 * Trafia do adresu, który klient dostaje mailem, więc ma być czytelny —
	 * i musi być jednoznaczny, bo dwie sesje ślubne u tego samego fotografa
	 * zdarzają się co tydzień.
	 */
	private function uniqueSlug( string $title ): string {
		$base = $this->slugify( $title );

		if ( null === $this->galleries->findBySlug( $base ) ) {
			return $base;
		}

		for ( $number = 2; $number <= 200; $number++ ) {
			$candidate = substr( $base, 0, 180 ) . '-' . $number;

			if ( null === $this->galleries->findBySlug( $candidate ) ) {
				return $candidate;
			}
		}

		// Ostateczność: ULID zawsze jest wolny. Brzydki adres jest lepszy
		// niż odmowa utworzenia galerii.
		return substr( $base, 0, 150 ) . '-' . strtolower( (string) Ulid::generate() );
	}

	private function slugify( string $title ): string {
		$ascii = strtr(
			$title,
			array(
				'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
				'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
				'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
				'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
			)
		);

		$slug = preg_replace( '~[^a-z0-9]+~', '-', mb_strtolower( $ascii, 'UTF-8' ) ) ?? '';
		$slug = trim( $slug, '-' );

		return '' === $slug ? 'galeria' : substr( $slug, 0, 190 );
	}
}
