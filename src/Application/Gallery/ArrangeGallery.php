<?php
declare( strict_types=1 );

namespace Kadr\Application\Gallery;

use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Układanie kolejności zdjęć w galerii.
 *
 * Kolejność nie jest kosmetyką: klientka ogląda galerię od góry i pierwsze
 * dwadzieścia kadrów decyduje o tym, czy przewinie dalej. Fotograf układa
 * je ręcznie — i robi to na samym początku, zanim wyśle link.
 *
 * Żądanie opisuje ZAMIAR („przenieś te kadry przed ten"), a nie gotową
 * listę. Dwa powody:
 *
 *  1. Lista tysiąca identyfikatorów w każdym przeciągnięciu to trzydzieści
 *     kilobajtów na ruch myszy.
 *  2. Przeglądarka fotografa ma wczytaną tylko część galerii (siatka jest
 *     wirtualizowana). Gdyby przysyłała „całą" kolejność, kadry, których
 *     jeszcze nie doczytała, wypadłyby na koniec galerii.
 *
 * Kolejność wyliczamy więc po stronie serwera, z pełnej listy.
 */
final readonly class ArrangeGallery {

	public function __construct(
		private GalleryRepository $galleries,
		private AssetRepository $assets,
	) {}

	/**
	 * Przeniesienie zdjęć przed wskazany kadr.
	 *
	 * @param list<Ulid> $moved   Kadry do przeniesienia, w kolejności docelowej.
	 * @param Ulid|null  $before  Kadr, PRZED który wstawiamy. `null` oznacza koniec galerii.
	 */
	public function move( Ulid $galleryId, array $moved, ?Ulid $before ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono galerii.' );
		}

		if ( array() === $moved ) {
			return Result::failure( 'kadr_nothing_to_move', 'Nie wskazano zdjęć do przeniesienia.' );
		}

		$order = $this->assets->orderedIdsFor( (int) $gallery['id'] );

		if ( array() === $order ) {
			return Result::failure( 'kadr_not_found', 'Ta galeria nie ma jeszcze zdjęć.' );
		}

		$current  = array_flip( $order );
		$movedIds = array();

		foreach ( $moved as $id ) {
			$value = (string) $id;

			// Kadr musi należeć DO TEJ galerii. Inaczej jedno żądanie
			// pozwalałoby przestawiać zdjęcia z innych sesji fotografa —
			// a przy okazji zerować im `sort_order`.
			if ( ! isset( $current[ $value ] ) ) {
				return Result::failure( 'kadr_not_found', 'To zdjęcie nie należy do tej galerii.' );
			}

			// Powtórzony identyfikator zdublowałby kadr w wyniku.
			$movedIds[ $value ] = true;
		}

		$target = null === $before ? null : (string) $before;

		if ( null !== $target && ! isset( $current[ $target ] ) ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono zdjęcia, przed które mamy wstawić.' );
		}

		// Wstawienie zaznaczenia przed kadr, który sam jest zaznaczony,
		// nie znaczy nic — zaznaczenie już tam jest.
		if ( null !== $target && isset( $movedIds[ $target ] ) ) {
			return Result::success( array( 'moved' => 0 ) );
		}

		$rest   = array_values( array_filter( $order, static fn( string $id ): bool => ! isset( $movedIds[ $id ] ) ) );
		$picked = array_keys( $movedIds );
		$result = array();

		foreach ( $rest as $id ) {
			if ( $id === $target ) {
				array_push( $result, ...$picked );
			}

			$result[] = $id;
		}

		if ( null === $target ) {
			array_push( $result, ...$picked );
		}

		return Result::success(
			array( 'moved' => $this->assets->reorder( $result, $current ) )
		);
	}
}
