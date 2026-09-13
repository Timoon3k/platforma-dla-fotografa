<?php
declare( strict_types=1 );

namespace Kadr\Tests\Listing;

use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Paginacja kursorowa, wyszukiwanie i zliczanie z grupowaniem.
 *
 * Te trzy rzeczy zasilają listy w panelu. Każda wykonuje SQL sklejany
 * z nazw kolumn, więc każda musi przejść przez prawdziwą bazę, a nie atrapę.
 */
final class CursorPaginationTest extends TestCase {

	public function testPageReturnsNewestFirstAndCursorContinuesWithoutOverlap(): void {
		$db         = TestDatabase::migrated();
		$galleries  = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$identifiers = array();

		for ( $i = 1; $i <= 7; $i++ ) {
			$identifiers[] = (string) $galleries->create( "Galeria $i", "galeria-$i" );
			// ULID koduje czas z dokładnością do milisekundy — bez odstępu
			// kolejność wewnątrz jednej milisekundy zależałaby od losowej części.
			usleep( 2000 );
		}

		$first = $galleries->page( null, null, null, 3 );
		$this->assertSame( 3, count( $first ) );

		// Najnowsze na górze: ostatnio utworzona galeria jest pierwsza.
		$this->assertSame( $identifiers[6], (string) $first[0]['public_id'] );

		$cursor = (string) $first[2]['public_id'];
		$second = $galleries->page( null, null, $cursor, 3 );

		$this->assertSame( 3, count( $second ) );

		$firstIds  = array_map( static fn ( array $row ): string => (string) $row['public_id'], $first );
		$secondIds = array_map( static fn ( array $row ): string => (string) $row['public_id'], $second );

		// Druga strona nie powtarza ani jednego wiersza z pierwszej.
		$this->assertSame( array(), array_intersect( $firstIds, $secondIds ) );
	}

	/**
	 * Wstawienie nowego wiersza w trakcie przeglądania nie przesuwa strony.
	 *
	 * To jest cała różnica względem OFFSET-u: tam nowy wiersz na górze
	 * powoduje, że ostatni wiersz strony pierwszej pojawia się ponownie
	 * na stronie drugiej.
	 */
	public function testInsertDuringPagingDoesNotDuplicateRows(): void {
		$db        = TestDatabase::migrated();
		$galleries = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );

		for ( $i = 1; $i <= 4; $i++ ) {
			$galleries->create( "Galeria $i", "galeria-$i" );
			usleep( 2000 );
		}

		$first  = $galleries->page( null, null, null, 2 );
		$cursor = (string) $first[1]['public_id'];

		// Ktoś dodaje galerię, kiedy fotograf patrzy na pierwszą stronę.
		$galleries->create( 'Wstawiona w trakcie', 'wstawiona' );

		$second = $galleries->page( null, null, $cursor, 2 );

		$firstIds  = array_map( static fn ( array $row ): string => (string) $row['public_id'], $first );
		$secondIds = array_map( static fn ( array $row ): string => (string) $row['public_id'], $second );

		$this->assertSame( array(), array_intersect( $firstIds, $secondIds ) );
	}

	public function testCursorNeverLeavesTheTenant(): void {
		$db = TestDatabase::migrated();
		$a  = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$b  = new GalleryRepository( $db, TestDatabase::tenant( 2 ) );

		$a->create( 'Galeria fotografa A', 'a-1' );
		usleep( 2000 );
		$idOfA = (string) $a->create( 'Druga galeria A', 'a-2' );

		$b->create( 'Galeria fotografa B', 'b-1' );

		// Tenant B używa kursora tenanta A — dostaje wyłącznie swoje wiersze.
		$page = $b->page( null, null, $idOfA, 10 );

		foreach ( $page as $row ) {
			$this->assertSame( 2, (int) $row['tenant_id'] );
		}
	}

	public function testSearchMatchesPartOfTheTitle(): void {
		$db        = TestDatabase::migrated();
		$galleries = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );

		$galleries->create( 'Ślub Marty i Piotra', 'slub-marty' );
		$galleries->create( 'Chrzciny Antka', 'chrzciny' );

		$found = $galleries->page( null, 'Marty', null, 10 );

		$this->assertSame( 1, count( $found ) );
		$this->assertSame( 'Ślub Marty i Piotra', (string) $found[0]['title'] );
	}

	/**
	 * Procent wpisany w wyszukiwarkę jest szukany dosłownie.
	 *
	 * Bez ucieczki `%` dopasowuje wszystko — użytkownik wpisuje jeden znak
	 * i dostaje całą tabelę, co przy dużym zbiorze potrafi wyłożyć zapytanie.
	 */
	public function testWildcardTypedByUserIsSearchedLiterally(): void {
		$db        = TestDatabase::migrated();
		$galleries = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );

		$galleries->create( 'Sesja 100% naturalna', 'sesja-100' );
		$galleries->create( 'Plener jesienny', 'plener' );

		$this->assertSame( 1, count( $galleries->page( null, '100%', null, 10 ) ) );
		$this->assertSame( 0, count( $galleries->page( null, '%%%', null, 10 ) ) );
	}

	public function testUnderscoreIsSearchedLiterallyToo(): void {
		$db        = TestDatabase::migrated();
		$galleries = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );

		$galleries->create( 'Sesja_robocza', 'sesja-robocza' );
		$galleries->create( 'Sesja wieczorna', 'sesja-wieczorna' );

		// `_` w LIKE dopasowuje dowolny znak, więc bez ucieczki
		// „Sesja_” trafiłoby również w „Sesja ”.
		$this->assertSame( 1, count( $galleries->page( null, 'Sesja_', null, 10 ) ) );
	}

	public function testStatusFilterAndSearchWorkTogether(): void {
		$db        = TestDatabase::migrated();
		$galleries = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );

		$published = $galleries->create( 'Ślub Marty', 'slub-marty' );
		$galleries->publish( $published );
		$galleries->create( 'Ślub Anny', 'slub-anny' );

		$this->assertSame( 2, count( $galleries->page( null, 'Ślub', null, 10 ) ) );
		$this->assertSame( 1, count( $galleries->page( 'published', 'Ślub', null, 10 ) ) );
		$this->assertSame( 0, count( $galleries->page( 'published', 'Anny', null, 10 ) ) );
	}

	public function testGroupedCountAvoidsQueryPerRowAndStaysInTenant(): void {
		$db = TestDatabase::migrated();

		$galleriesA = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$assetsA    = new AssetRepository( $db, TestDatabase::tenant( 1 ) );
		$galleriesB = new GalleryRepository( $db, TestDatabase::tenant( 2 ) );
		$assetsB    = new AssetRepository( $db, TestDatabase::tenant( 2 ) );

		$first  = $galleriesA->findByPublicId( $galleriesA->create( 'Pierwsza', 'pierwsza' ) );
		$second = $galleriesA->findByPublicId( $galleriesA->create( 'Druga', 'druga' ) );
		$other  = $galleriesB->findByPublicId( $galleriesB->create( 'Obca', 'obca' ) );

		$this->addAssets( $assetsA, (int) $first['id'], 3 );
		$this->addAssets( $assetsA, (int) $second['id'], 1 );
		$this->addAssets( $assetsB, (int) $other['id'], 5 );

		$counts = $assetsA->countsByGallery();

		$this->assertSame( 3, $counts[ (int) $first['id'] ] ?? 0 );
		$this->assertSame( 1, $counts[ (int) $second['id'] ] ?? 0 );

		// Galeria obcego tenanta nie pojawia się w wyniku — nawet jako zero.
		$this->assertFalse( array_key_exists( (int) $other['id'], $counts ) );
	}

	public function testClientSearchMatchesLastName(): void {
		$db      = TestDatabase::migrated();
		$clients = new ClientRepository( $db, TestDatabase::tenant( 1 ) );

		$clients->create( 'Anna', 'anna@example.test', 'Kowalska' );
		$clients->create( 'Marta', 'marta@example.test', 'Nowak' );

		$this->assertSame( 1, count( $clients->page( 'Kowal', null, 10 ) ) );
		$this->assertSame( 2, count( $clients->page( null, null, 10 ) ) );
	}

	private function addAssets( AssetRepository $assets, int $galleryId, int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$assets->create(
				$galleryId,
				array(
					'original_name' => "zdjecie-$i.jpg",
					'storage_path'  => "private/$galleryId/zdjecie-$i.jpg",
					'content_hash'  => str_repeat( (string) ( $i % 10 ), 64 ),
					'bytes'         => 1024,
					'width'         => 100,
					'height'        => 100,
				)
			);
		}
	}
}
