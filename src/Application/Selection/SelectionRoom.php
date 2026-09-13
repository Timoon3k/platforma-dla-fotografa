<?php
declare( strict_types=1 );

namespace Kadr\Application\Selection;

use Kadr\Domain\Selection\PackageTally;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Money;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;

/**
 * Wybór zdjęć przez klientkę.
 *
 * TO JEST ETAP, NA KTÓRYM PRODUKT ZARABIA.
 *
 * Dziś wygląda on tak: klientka opisuje wybór w wiadomościach („te z drugiego
 * rzędu i to, gdzie Zosia się śmieje"), fotograf ręcznie odszukuje pliki,
 * a gdy wyborów wyjdzie więcej niż obejmuje pakiet — zwykle dorzuca gratis,
 * bo prosić o dopłatę jest niezręcznie. Tu przychód wyparowuje.
 *
 * Dlatego reguły tej klasy są regułami produktu, nie walidacją formularza:
 *
 *  - **licznik liczy się przy każdej zmianie**, nie dopiero przy zatwierdzeniu.
 *    Klientka ma wiedzieć, ile kosztuje dwudzieste pierwsze zdjęcie, ZANIM
 *    je kliknie — wtedy dopłata jest jej decyzją, a nie niespodzianką;
 *  - **ulubione to nie to samo, co wybrane**. Klientka najpierw przechodzi
 *    galerię i serduszkuje, a dopiero potem zawęża. Sklejenie tych stanów
 *    zmuszałoby ją do decyzji zakupowej przy pierwszym przejrzeniu;
 *  - **wybór da się otworzyć ponownie**. Klientka się rozmyśli — to normalny
 *    bieg sprawy, nie przypadek brzegowy.
 */
final readonly class SelectionRoom {

	public function __construct(
		private GalleryRepository $galleries,
		private AssetRepository $assets,
		private SelectionRepository $selections,
		private SelectionItemRepository $items,
	) {}

	/**
	 * Bieżący stan wyboru dla galerii.
	 *
	 * Zakłada wybór, jeśli jeszcze nie istnieje — pierwsze kliknięcie serduszka
	 * nie powinno wymagać osobnego „rozpocznij wybór".
	 */
	public function state( Ulid $galleryId, ?int $clientId = null ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$selection = $this->selections->forGallery( (int) $gallery['id'] );

		if ( null === $selection ) {
			$this->selections->open( (int) $gallery['id'], $clientId );
			$selection = $this->selections->forGallery( (int) $gallery['id'] );
		}

		return Result::success( $this->snapshot( $gallery, $selection ) );
	}

	/**
	 * Ustawienie albo zdjęcie stanu z kadru.
	 *
	 * @param SelectionState|null $state `null` czyści stan — kliknięcie
	 *                                   w zaznaczone zdjęcie ma je odznaczać.
	 */
	public function mark( Ulid $galleryId, Ulid $assetId, ?SelectionState $state, ?int $clientId = null ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$asset = $this->assets->findByPublicId( $assetId );

		// Kadr musi należeć DO TEJ galerii. Inaczej jeden link pozwalałby
		// zaznaczać zdjęcia z innych sesji tego samego fotografa.
		if ( null === $asset || (int) $asset['gallery_id'] !== (int) $gallery['id'] ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$selection = $this->selections->forGallery( (int) $gallery['id'] );

		if ( null === $selection ) {
			$this->selections->open( (int) $gallery['id'], $clientId );
			$selection = $this->selections->forGallery( (int) $gallery['id'] );
		}

		if ( 'submitted' === (string) $selection['status'] ) {
			// Wybór zatwierdzony jest zamknięty do czasu, aż fotograf otworzy
			// go ponownie. Bez tego klientka zmienia wybór po tym, jak
			// fotograf zaczął obróbkę.
			return Result::failure(
				'kadr_selection_closed',
				'Wybór został już wysłany. Napisz do fotografa, jeśli chcesz go zmienić.'
			);
		}

		if ( null === $state ) {
			$this->items->clear( (int) $selection['id'], (int) $asset['id'] );
		} else {
			$this->items->setState(
				(int) $selection['id'],
				(int) $asset['id'],
				$state,
				(int) $asset['sort_order']
			);
		}

		return Result::success( $this->snapshot( $gallery, $selection ) );
	}

	/**
	 * Zatwierdzenie wyboru.
	 *
	 * Zapisujemy liczby POLICZONE W TEJ CHWILI, a nie przysłane przez
	 * przeglądarkę: kwota dopłaty nie może zależeć od tego, co klient wyśle
	 * w żądaniu.
	 */
	public function submit( Ulid $galleryId ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$selection = $this->selections->forGallery( (int) $gallery['id'] );

		if ( null === $selection ) {
			return Result::failure( 'kadr_selection_empty', 'Nie wybrałaś jeszcze żadnego zdjęcia.' );
		}

		if ( 'submitted' === (string) $selection['status'] ) {
			return Result::failure( 'kadr_selection_closed', 'Ten wybór został już wysłany.' );
		}

		$snapshot = $this->snapshot( $gallery, $selection );

		if ( 0 === $snapshot['tally']['selected'] ) {
			return Result::failure(
				'kadr_selection_empty',
				'Zaznacz zdjęcia, które mamy obrobić — bez tego nie ma czego wysłać.'
			);
		}

		$this->selections->submit(
			Ulid::fromString( (string) $selection['public_id'] ),
			$snapshot['tally']['included'],
			$snapshot['tally']['extra']
		);

		$snapshot['status'] = 'submitted';

		return Result::success( $snapshot );
	}

	/**
	 * Ponowne otwarcie wyboru przez fotografa.
	 *
	 * Klientka zawsze się rozmyśli. Bez tej operacji fotograf musiałby
	 * kasować wybór i prosić o powtórzenie całej pracy.
	 */
	public function reopen( Ulid $galleryId ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$selection = $this->selections->forGallery( (int) $gallery['id'] );

		if ( null === $selection ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$this->selections->reopen( Ulid::fromString( (string) $selection['public_id'] ) );

		return Result::success( $this->snapshot( $gallery, $this->selections->forGallery( (int) $gallery['id'] ) ) );
	}

	/**
	 * Pełny stan wyboru wraz z rozliczeniem pakietu.
	 *
	 * Rozliczenie liczy `PackageTally` z warstwy Domain — ta sama arytmetyka
	 * obsługuje panel, galerię i przyszłe zamówienie, więc nie ma szans
	 * rozjechać się między nimi.
	 *
	 * @param array<string, mixed> $gallery
	 * @param array<string, mixed> $selection
	 * @return array<string, mixed>
	 */
	private function snapshot( array $gallery, array $selection ): array {
		$selectionId = (int) $selection['id'];

		$states = array();

		foreach ( SelectionState::cases() as $state ) {
			foreach ( $this->items->forSelection( $selectionId, $state ) as $item ) {
				$states[ (int) $item['asset_id'] ] = $state->value;
			}
		}

		$selectedCount = $this->items->countInState( $selectionId, SelectionState::Selected );
		$price         = Money::fromMinor( (int) ( $gallery['extra_photo_price'] ?? 0 ) );
		$limit         = null === $gallery['package_limit'] ? null : (int) $gallery['package_limit'];

		$tally = PackageTally::calculate( $selectedCount, $limit, $price );

		// Mapa stanów jest po PUBLICZNYCH identyfikatorach — sekwencyjne `id`
		// nigdy nie wychodzi na zewnątrz (docs/SECURITY.md §1).
		// Tłumaczenie idzie JEDNYM zapytaniem: wybór potrafi objąć dwieście
		// kadrów, a ten stan liczy się przy każdym kliknięciu serduszka.
		$byPublicId = array();
		$publicIds  = $this->assets->publicIdsFor( array_map( 'intval', array_keys( $states ) ) );

		foreach ( $states as $assetId => $state ) {
			if ( isset( $publicIds[ (int) $assetId ] ) ) {
				$byPublicId[ $publicIds[ (int) $assetId ] ] = $state;
			}
		}

		return array(
			'status' => (string) $selection['status'],
			'states' => $byPublicId,
			'tally'  => array(
				'selected'      => $tally->selected,
				'package_limit' => $tally->packageLimit,
				'included'      => $tally->included,
				'extra'         => $tally->extra,
				'unit_price'    => $tally->extraUnitPrice->minor,
				'total'         => $tally->total->minor,
				'remaining'     => $tally->remainingInPackage(),
				'at_limit'      => $tally->isAtPackageLimit(),
				'needs_payment' => $tally->requiresPayment(),
			),
			'favorites' => $this->items->countInState( $selectionId, SelectionState::Favorite ),
			'rejected'  => $this->items->countInState( $selectionId, SelectionState::Rejected ),
		);
	}
}
