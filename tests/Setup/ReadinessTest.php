<?php
declare( strict_types=1 );

namespace Kadr\Tests\Setup;

use Kadr\Domain\Setup\Finding;
use Kadr\Domain\Setup\Readiness;
use Kadr\Domain\Setup\Severity;
use Kadr\Tests\TestCase;

/**
 * Przegląd instalacji.
 *
 * Te testy pilnują jednej obietnicy: po wgraniu wtyczki administrator ma
 * w trzy sekundy wiedzieć, CO jest zepsute i CO z tym zrobić. Nie „coś
 * poszło nie tak", tylko konkretny krok.
 */
final class ReadinessTest extends TestCase {

	/**
	 * Pułapka numer jeden. Przy odnośnikach „zwykłych" cała platforma
	 * zwraca 404 — bez jednego słowa wyjaśnienia.
	 */
	public function testPlainPermalinksBlockTheWholePlatform(): void {
		$finding = $this->find( $this->facts( array( 'permalinks' => '' ) ), 'permalinks' );

		$this->assertSame( Severity::Blocking, $finding->severity );
		$this->assertTrue( str_contains( $finding->consequence, '404' ) );
		// Komunikat MUSI mówić, co kliknąć.
		$this->assertTrue( str_contains( $finding->fix, 'Bezpośrednie odnośniki' ) );
	}

	public function testPrettyPermalinksPass(): void {
		$this->assertSame(
			Severity::Ok,
			$this->find( $this->facts( array( 'permalinks' => '/%postname%/' ) ), 'permalinks' )->severity
		);
	}

	/**
	 * Każde ustalenie niesie TRZY rzeczy: co, dlaczego boli i co zrobić.
	 * Komunikat bez ostatniego zostawia administratora z problemem,
	 * którego nie umie rozwiązać.
	 */
	public function testEveryProblemSaysWhatToDoAboutIt(): void {
		$findings = Readiness::inspect( $this->facts( array(
			'permalinks'       => '',
			'schema_current'   => false,
			'has_zip'          => false,
			'has_imagick'      => false,
			'storage_writable' => false,
			'has_landing_page' => false,
			'is_https'         => false,
		) ) );

		foreach ( Readiness::problems( $findings ) as $problem ) {
			$this->assertTrue( '' !== $problem->label, $problem->id . ': brak nazwy' );
			$this->assertTrue( '' !== $problem->consequence, $problem->id . ': brak skutku' );
			$this->assertTrue( '' !== $problem->fix, $problem->id . ': brak instrukcji naprawy' );
		}
	}

	/**
	 * Brak `zip` odbiera cały etap dostawy plików — jeden z czterech etapów,
	 * na których produkt zarabia. To nie jest ostrzeżenie.
	 */
	public function testMissingZipExtensionBlocks(): void {
		$this->assertSame(
			Severity::Blocking,
			$this->find( $this->facts( array( 'has_zip' => false ) ), 'zip' )->severity
		);
	}

	/**
	 * Imagick ma zapas w postaci GD, więc jego brak tylko pogarsza jakość.
	 */
	public function testMissingImagickOnlyWarns(): void {
		$this->assertSame(
			Severity::Warning,
			$this->find( $this->facts( array( 'has_imagick' => false ) ), 'imagick' )->severity
		);
	}

	public function testUnwritableStorageBlocks(): void {
		$this->assertSame(
			Severity::Blocking,
			$this->find( $this->facts( array( 'storage_writable' => false ) ), 'storage' )->severity
		);
	}

	public function testPendingMigrationBlocks(): void {
		$this->assertSame(
			Severity::Blocking,
			$this->find( $this->facts( array( 'schema_current' => false ) ), 'schema' )->severity
		);
	}

	/**
	 * Brak strony głównej nie psuje platformy — psuje pierwsze wrażenie.
	 * Ale administrator musi o tym kroku usłyszeć, bo nie zgadnie.
	 */
	public function testMissingLandingPageWarnsButDoesNotBlock(): void {
		$finding = $this->find( $this->facts( array( 'has_landing_page' => false ) ), 'landing' );

		$this->assertSame( Severity::Warning, $finding->severity );
		$this->assertTrue( str_contains( $finding->fix, 'Utwórz stronę główną' ) );
	}

	public function testHttpOnlyInstallationWarnsAboutTokens(): void {
		$finding = $this->find( $this->facts( array( 'is_https' => false ) ), 'https' );

		$this->assertSame( Severity::Warning, $finding->severity );
		$this->assertTrue( str_contains( $finding->consequence, 'tokeny' ) );
	}

	public function testAHealthyInstallationReportsNoProblems(): void {
		$findings = Readiness::inspect( $this->facts() );

		$this->assertSame( array(), Readiness::problems( $findings ) );
		$this->assertTrue( Readiness::isUsable( $findings ) );
	}

	/**
	 * Ostrzeżenie nie może blokować — inaczej brak Imagicka zatrzymałby
	 * instalację, która działa.
	 */
	public function testWarningsAloneDoNotMakeTheInstallationUnusable(): void {
		$findings = Readiness::inspect( $this->facts( array(
			'has_imagick'      => false,
			'has_landing_page' => false,
			'is_https'         => false,
		) ) );

		$this->assertTrue( Readiness::isUsable( $findings ) );
		$this->assertSame( 3, count( Readiness::problems( $findings ) ) );
	}

	public function testOneBlockingProblemMakesTheInstallationUnusable(): void {
		$this->assertFalse(
			Readiness::isUsable( Readiness::inspect( $this->facts( array( 'permalinks' => '' ) ) ) )
		);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'permalinks'       => '/%postname%/',
				'schema_current'   => true,
				'has_zip'          => true,
				'has_imagick'      => true,
				'storage_writable' => true,
				'has_landing_page' => true,
				'is_https'         => true,
			),
			$overrides
		);
	}

	/**
	 * @param array<string, mixed> $facts
	 */
	private function find( array $facts, string $id ): Finding {
		foreach ( Readiness::inspect( $facts ) as $finding ) {
			if ( $finding->id === $id ) {
				return $finding;
			}
		}

		throw new \RuntimeException( sprintf( 'Brak ustalenia „%s" w przeglądzie.', $id ) );
	}
}
