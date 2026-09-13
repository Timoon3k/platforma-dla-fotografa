<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Zdjęcia w galeriach.
 *
 * Najliczniejsza tabela w systemie — ~18 mln wierszy przy tysiącu fotografów.
 * Każde zapytanie musi trafiać w indeks (tenant_id, gallery_id, sort_order).
 */
final class AssetRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::galleries()[1];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByPublicId( Ulid $id ): ?array {
		return $this->findOneBy( array( 'public_id' => (string) $id ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function forGallery( int $galleryId, int $limit = 100, int $offset = 0 ): array {
		return $this->findAllBy( array( 'gallery_id' => $galleryId ), 'sort_order', 'ASC', $limit, $offset );
	}

	/**
	 * Liczba zdjęć w każdej galerii tenanta — jedno zapytanie.
	 *
	 * Lista galerii pokazuje licznik przy każdej pozycji; pytanie o każdą
	 * z osobna byłoby N+1.
	 *
	 * @return array<int, int> gallery_id => liczba zdjęć
	 */
	public function countsByGallery(): array {
		$counts = array();

		foreach ( $this->countGroupedBy( 'gallery_id' ) as $galleryId => $count ) {
			$counts[ (int) $galleryId ] = $count;
		}

		return $counts;
	}

	/**
	 * Zdjęcie po wewnętrznym identyfikatorze — w obrębie tenanta.
	 *
	 * Używane tam, gdzie identyfikator przyszedł z innego wiersza tego samego
	 * tenanta (np. okładka galerii), a nie z żądania użytkownika.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array {
		return $this->findOneBy( array( 'id' => $id ) );
	}

	/**
	 * Publiczne identyfikatory dla listy wewnętrznych — jedno zapytanie.
	 *
	 * Wybór klientki potrafi objąć dwieście kadrów; pytanie o każdy z osobna
	 * to dwieście zapytań przy każdym kliknięciu serduszka
	 * (docs/PERFORMANCE.md §5).
	 *
	 * @param list<int> $ids
	 * @return array<int, string>
	 */
	public function publicIdsFor( array $ids ): array {
		$map = array();

		foreach ( $this->findAllIn( 'id', $ids ) as $row ) {
			$map[ (int) $row['id'] ] = (string) $row['public_id'];
		}

		return $map;
	}

	public function countForGallery( int $galleryId ): int {
		return $this->countBy( array( 'gallery_id' => $galleryId ) );
	}

	/**
	 * Wykrywanie duplikatu przed wysłaniem pliku (docs/PERFORMANCE.md §8).
	 *
	 * @return array<string, mixed>|null
	 */
	public function findDuplicate( string $contentHash ): ?array {
		return $this->findOneBy( array( 'content_hash' => $contentHash ) );
	}

	/**
	 * Utworzenie zdjęcia.
	 *
	 * Identyfikator można podać z zewnątrz, bo ścieżka pliku w magazynie jest
	 * budowana z ULID-a ZANIM powstanie wiersz. Gdyby repozytorium generowało
	 * własny, wiersz i plik wskazywałyby na różne identyfikatory, a plik
	 * stałby się nieosiągalny.
	 *
	 * @param array<string, scalar|null> $data
	 */
	public function create( int $galleryId, array $data, ?Ulid $id = null ): Ulid {
		$id ??= Ulid::generate();

		$this->insertRow(
			array_merge(
				$data,
				array(
					'public_id'  => (string) $id,
					'gallery_id' => $galleryId,
				)
			)
		);

		return $id;
	}

	/**
	 * @param array<string, scalar|null> $data
	 */
	public function update( Ulid $id, array $data ): int {
		return $this->updateBy( array( 'public_id' => (string) $id ), $data );
	}

	public function markReady( Ulid $id ): int {
		return $this->updateBy( array( 'public_id' => (string) $id ), array( 'status' => 'ready' ) );
	}

	public function markFailed( Ulid $id ): int {
		return $this->updateBy( array( 'public_id' => (string) $id ), array( 'status' => 'failed' ) );
	}

	/**
	 * Kolejność zdjęć w galerii — same identyfikatory publiczne.
	 *
	 * Osobno od `forGallery()`, bo do przestawiania kolejności nie są
	 * potrzebne warianty, wymiary ani skróty plików. Wesele to bywa tysiąc
	 * kadrów; wciąganie całych wierszy po to, żeby przesunąć jeden, byłoby
	 * marnotrawstwem pamięci przy każdym przeciągnięciu.
	 *
	 * @return list<string>
	 */
	public function orderedIdsFor( int $galleryId ): array {
		$ids    = array();
		$offset = 0;
		$page   = 1000;

		do {
			$rows = $this->findAllBy( array( 'gallery_id' => $galleryId ), 'sort_order', 'ASC', $page, $offset );

			foreach ( $rows as $row ) {
				$ids[] = (string) $row['public_id'];
			}

			$offset += $page;
		} while ( count( $rows ) === $page );

		return $ids;
	}

	/**
	 * Zmiana kolejności zdjęć.
	 *
	 * Zapisujemy WYŁĄCZNIE wiersze, których pozycja faktycznie się zmieniła.
	 * Przesunięcie jednego kadru o dwa miejsca w tysiącu zdjęć to trzy zapisy,
	 * nie tysiąc — a to jest operacja, którą fotograf wykonuje dziesiątki razy
	 * pod rząd, układając galerię.
	 *
	 * @param list<string> $orderedPublicIds
	 * @param array<string, int> $current Obecne pozycje, jeśli są już znane.
	 */
	public function reorder( array $orderedPublicIds, array $current = array() ): int {
		$changed = 0;

		foreach ( array_values( $orderedPublicIds ) as $position => $publicId ) {
			if ( array_key_exists( $publicId, $current ) && $current[ $publicId ] === $position ) {
				continue;
			}

			$changed += $this->updateBy(
				array( 'public_id' => $publicId ),
				array( 'sort_order' => $position )
			);
		}

		return $changed;
	}

	public function delete( Ulid $id ): int {
		return $this->softDeleteBy( array( 'public_id' => (string) $id ) );
	}

	/**
	 * Suma bajtów zdjęć tenanta.
	 *
	 * Używane WYŁĄCZNIE przez nocne przeliczanie liczników. Widok dashboardu
	 * czyta licznik przyrostowy z `usage_counters`, nigdy to zapytanie —
	 * SUM po 18 mln wierszy przy każdym wejściu to koniec wydajności
	 * (docs/PERFORMANCE.md §5). Stronicujemy, żeby nie wciągać całej tabeli
	 * do pamięci.
	 */
	public function totalBytes(): int {
		$total  = 0;
		$offset = 0;
		$page   = 1000;

		do {
			$rows = $this->findAllBy( array(), 'id', 'ASC', $page, $offset );

			foreach ( $rows as $row ) {
				$total += (int) ( $row['bytes'] ?? 0 );
			}

			$offset += $page;
		} while ( count( $rows ) === $page );

		return $total;
	}
}
