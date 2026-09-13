<?php
declare( strict_types=1 );

namespace Kadr\Tests\Delivery;

use Kadr\Domain\Delivery\ArchiveEntry;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Tests\TestCase;

/**
 * Nazwy plików w paczce.
 *
 * Nazwa pliku przychodzi od klienta przy wysyłce, a przy pakowaniu trafia
 * do archiwum, które ktoś rozpakuje na swoim dysku. To jest dokładnie ta
 * droga, którą chodzi „zip slip" — więc testy opisują, czego nazwa NIE może
 * przenieść (docs/SECURITY.md §3.4).
 */
final class ArchiveEntryTest extends TestCase {

	public function testNumberGoesFirstSoTheOrderSurvivesUnpacking(): void {
		$entry = ArchiveEntry::of( 'private/wesele/x.jpg', 'DSC_4210.jpg', 7 );

		$this->assertSame( '007-DSC_4210.jpg', $entry->nameInArchive );
	}

	/**
	 * Rozpakowana paczka sortuje się alfabetycznie. Bez numeru klientka
	 * dostaje kolejność nazw z aparatu, czyli przypadkową względem tej,
	 * którą fotograf ułożył.
	 */
	public function testNumberingKeepsGalleryOrderAlphabetically(): void {
		$names = array();

		foreach ( array( 'b.jpg', 'a.jpg', 'c.jpg' ) as $index => $name ) {
			$names[] = ArchiveEntry::of( 'p', $name, $index + 1 )->nameInArchive;
		}

		$sorted = $names;
		sort( $sorted );

		$this->assertSame( $names, $sorted );
	}

	public function testPathTraversalCannotEscapeTheArchive(): void {
		$entry = ArchiveEntry::of( 'p', '../../../etc/passwd', 1 );

		$this->assertSame( '001-passwd', $entry->nameInArchive );
	}

	public function testWindowsPathSeparatorsAreStrippedToo(): void {
		$entry = ArchiveEntry::of( 'p', '..\\..\\Windows\\System32\\evil.dll', 1 );

		$this->assertSame( '001-evil.dll', $entry->nameInArchive );
	}

	public function testAbsolutePathBecomesJustAName(): void {
		$entry = ArchiveEntry::of( 'p', '/home/ofiara/.ssh/id_rsa', 1 );

		$this->assertSame( '001-id_rsa', $entry->nameInArchive );
	}

	/**
	 * Nazwa złożona z samych kropek nie może dać pustej ani ukrytej.
	 */
	public function testDotsOnlyNameGetsAFallback(): void {
		$entry = ArchiveEntry::of( 'p', '...', 3 );

		$this->assertSame( '003-kadr.jpg', $entry->nameInArchive );
	}

	public function testControlCharactersAreRemoved(): void {
		$entry = ArchiveEntry::of( 'p', "zdj\x00ecie\x1f.jpg", 1 );

		$this->assertSame( '001-zdjecie.jpg', $entry->nameInArchive );
	}

	/**
	 * Znaki zakazane w nazwach plików na Windowsie psują rozpakowanie
	 * u połowy klientek.
	 */
	public function testCharactersWindowsForbidsAreRemoved(): void {
		$entry = ArchiveEntry::of( 'p', 'a<b>c:d"e|f?g*h.jpg', 1 );

		$this->assertSame( '001-abcdefgh.jpg', $entry->nameInArchive );
	}

	public function testVeryLongNameIsShortenedButKeepsItsExtension(): void {
		$entry = ArchiveEntry::of( 'p', str_repeat( 'a', 300 ) . '.jpg', 1 );

		$this->assertTrue( mb_strlen( $entry->nameInArchive ) <= 124 );
		$this->assertTrue( str_ends_with( $entry->nameInArchive, '.jpg' ) );
	}

	public function testPolishTitleBecomesAReadableArchiveName(): void {
		$this->assertSame(
			'slub-marty-i-piotra-wybrane.zip',
			ArchiveEntry::archiveName( 'Ślub Marty i Piotra', ArchiveScope::Selected )
		);
	}

	public function testEverythingScopeSaysSoInTheFileName(): void {
		$this->assertSame(
			'chrzciny-zosi-cala-galeria.zip',
			ArchiveEntry::archiveName( 'Chrzciny Zosi', ArchiveScope::Everything )
		);
	}

	/**
	 * Nazwa paczki trafia do nagłówka `Content-Disposition`, więc nie może
	 * przenieść cudzysłowu ani nowej linii.
	 */
	public function testArchiveNameSurvivesAHostileGalleryTitle(): void {
		$name = ArchiveEntry::archiveName( "x\"\r\nContent-Type: text/html", ArchiveScope::Everything );

		$this->assertSame( 1, preg_match( '/^[a-z0-9-]+\.zip$/', $name ) );
	}

	public function testGalleryWithoutALetterInItsTitleStillGetsAName(): void {
		$this->assertSame( 'galeria-wybrane.zip', ArchiveEntry::archiveName( '???', ArchiveScope::Selected ) );
	}
}
