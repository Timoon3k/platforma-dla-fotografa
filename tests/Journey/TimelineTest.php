<?php
declare( strict_types=1 );

namespace Kadr\Tests\Journey;

use Kadr\Domain\Journey\Stage;
use Kadr\Domain\Journey\State;
use Kadr\Domain\Journey\Step;
use Kadr\Domain\Journey\Timeline;
use Kadr\Tests\TestCase;

/**
 * Oś procesu.
 *
 * Odpowiada na pytanie „kiedy będą zdjęcia?", zanim ktokolwiek je zada.
 * Fotograf dostaje je kilka razy przy każdej sesji — to jedno z dwudziestu
 * przerwań składających się na 2–4 godziny administracji.
 *
 * Testy opisują REGUŁY, nie kształt danych: kiedy etap uznajemy za przebyty
 * i dlaczego akurat wtedy.
 */
final class TimelineTest extends TestCase {

	public function testAFreshGalleryWithoutPhotosStandsAtTheVeryBeginning(): void {
		$steps = Timeline::project( $this->facts() );

		$this->assertSame( Stage::Prepared, Timeline::current( $steps )->stage );
		$this->assertFalse( $steps[0]->isDone() );
	}

	public function testAGalleryWithPhotosIsPrepared(): void {
		$steps = Timeline::project( $this->facts( array( 'photos' => 120 ) ) );

		$this->assertTrue( $this->step( $steps, Stage::Prepared )->isDone() );
		$this->assertSame( Stage::Shared, Timeline::current( $steps )->stage );
	}

	/**
	 * Publikacja bez wydanego linku nie znaczy, że klientka ma jak wejść.
	 *
	 * To jest różnica, którą łatwo przeoczyć: fotograf klika „opublikuj",
	 * oś skacze do „wysłane", a klientka nadal nie dostała adresu.
	 */
	public function testPublishingWithoutIssuingALinkIsNotSharing(): void {
		$steps = Timeline::project(
			$this->facts( array( 'photos' => 10, 'published' => true, 'link_issued' => false ) )
		);

		$this->assertFalse( $this->step( $steps, Stage::Shared )->isDone() );
		$this->assertSame( Stage::Shared, Timeline::current( $steps )->stage );
	}

	public function testAnIssuedLinkOnAPublishedGalleryCountsAsShared(): void {
		$steps = Timeline::project( $this->shared() );

		$this->assertTrue( $this->step( $steps, Stage::Shared )->isDone() );
		$this->assertSame( Stage::Opened, Timeline::current( $steps )->stage );
	}

	public function testOpeningTheLinkMovesTheSessionForward(): void {
		$steps = Timeline::project( $this->shared( array( 'link_opened' => 3 ) ) );

		$this->assertTrue( $this->step( $steps, Stage::Opened )->isDone() );
		$this->assertSame( Stage::Choosing, Timeline::current( $steps )->stage );
	}

	/**
	 * Otwarty wybór bez ani jednego zaznaczenia to jeszcze nie „wybór w toku".
	 * Sam fakt, że rekord powstał, nic nie mówi — powstaje przy pierwszym
	 * wejściu do galerii.
	 */
	public function testAnEmptySelectionDoesNotCountAsChoosing(): void {
		$steps = Timeline::project(
			$this->shared( array( 'link_opened' => 1, 'selection_status' => 'open', 'selection_marks' => 0 ) )
		);

		$this->assertFalse( $this->step( $steps, Stage::Choosing )->isDone() );
	}

	public function testTheFirstMarkStartsTheChoosingStage(): void {
		$steps = Timeline::project(
			$this->shared( array( 'link_opened' => 1, 'selection_status' => 'open', 'selection_marks' => 1 ) )
		);

		$this->assertTrue( $this->step( $steps, Stage::Choosing )->isDone() );
		$this->assertSame( Stage::Chosen, Timeline::current( $steps )->stage );
	}

	public function testASubmittedSelectionCarriesItsTimestamp(): void {
		$steps = Timeline::project( $this->submitted() );

		$step = $this->step( $steps, Stage::Chosen );

		$this->assertTrue( $step->isDone() );
		$this->assertSame( '2026-09-12 18:40:00', $step->at );
		$this->assertSame( Stage::Packed, Timeline::current( $steps )->stage );
	}

	/**
	 * Paczka w trakcie pakowania to jeszcze nie paczka gotowa.
	 */
	public function testAnArchiveStillPackingIsNotDone(): void {
		$steps = Timeline::project( $this->submitted( array( 'archive_status' => 'packing' ) ) );

		$this->assertFalse( $this->step( $steps, Stage::Packed )->isDone() );
		$this->assertSame( Stage::Packed, Timeline::current( $steps )->stage );
	}

	public function testTheWholeJourneyCompletesWhenTheFilesAreDownloaded(): void {
		$steps = Timeline::project(
			$this->submitted( array( 'archive_status' => 'ready', 'downloaded' => true ) )
		);

		$this->assertTrue( Timeline::isComplete( $steps ) );
		$this->assertSame( Stage::Delivered, Timeline::current( $steps )->stage );
	}

	/**
	 * Dokładnie JEDEN etap jest bieżący. Dwa naraz nie odpowiadałyby
	 * na żadne pytanie.
	 */
	public function testExactlyOneStageIsCurrentAtATime(): void {
		foreach ( array( $this->facts(), $this->shared(), $this->submitted() ) as $facts ) {
			$current = array_filter(
				Timeline::project( $facts ),
				static fn( Step $s ): bool => $s->isCurrent()
			);

			$this->assertSame( 1, count( $current ) );
		}
	}

	/**
	 * Etapy przed bieżącym są zrobione, po nim — czekają. Oś, po której
	 * trzeba wodzić palcem, nie jest osią.
	 */
	public function testStagesAfterTheCurrentOneAreWaiting(): void {
		$steps = Timeline::project( $this->shared() );
		$seen  = false;

		foreach ( $steps as $step ) {
			if ( $step->isCurrent() ) {
				$seen = true;

				continue;
			}

			$this->assertSame(
				$seen ? State::Waiting : State::Done,
				$step->state,
				$step->stage->value
			);
		}
	}

	/**
	 * Klientka i fotograf widzą ten sam etap opowiedziany inaczej.
	 * „Klientka obejrzała" powiedziane klientce brzmi jak podglądanie.
	 */
	public function testEveryStageSpeaksToBothSides(): void {
		foreach ( Stage::cases() as $stage ) {
			$this->assertTrue( '' !== $stage->label(), $stage->value );
			$this->assertTrue( '' !== $stage->clientLabel(), $stage->value );
			// Zdanie „co dalej" jest po to, żeby klientka nie musiała pytać.
			$this->assertTrue( '' !== $stage->clientNext(), $stage->value );
		}
	}

	public function testThePhotographerAndClientWordingDiffer(): void {
		$this->assertTrue( Stage::Opened->label() !== Stage::Opened->clientLabel() );
	}

	/**
	 * @param list<Step> $steps
	 */
	private function step( array $steps, Stage $stage ): Step {
		foreach ( $steps as $step ) {
			if ( $step->stage === $stage ) {
				return $step;
			}
		}

		throw new \RuntimeException( 'Brak etapu ' . $stage->value );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'photos'           => 0,
				'published'        => false,
				'published_at'     => null,
				'link_issued'      => false,
				'link_opened'      => 0,
				'selection_status' => null,
				'selection_marks'  => 0,
				'submitted_at'     => null,
				'archive_status'   => null,
				'archive_ready_at' => null,
				'downloaded'       => false,
			),
			$overrides
		);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function shared( array $overrides = array() ): array {
		return $this->facts(
			array_merge(
				array(
					'photos'       => 120,
					'published'    => true,
					'published_at' => '2026-09-10 12:00:00',
					'link_issued'  => true,
				),
				$overrides
			)
		);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function submitted( array $overrides = array() ): array {
		return $this->shared(
			array_merge(
				array(
					'link_opened'      => 5,
					'selection_status' => 'submitted',
					'selection_marks'  => 28,
					'submitted_at'     => '2026-09-12 18:40:00',
				),
				$overrides
			)
		);
	}
}
