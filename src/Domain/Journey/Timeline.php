<?php
declare( strict_types=1 );

namespace Kadr\Domain\Journey;

/**
 * Oś procesu dla jednej sesji.
 *
 * TO JEST ODPOWIEDŹ NA PYTANIE „KIEDY BĘDĄ ZDJĘCIA?", ZANIM KTOŚ JE ZADA.
 *
 * Fotograf dostaje to pytanie kilka razy przy każdej sesji i za każdym razem
 * odpowiada ręcznie — to jedno z dwudziestu przerwań składających się na
 * 2–4 godziny administracji przy jednej sesji.
 *
 * **Oś jest WYLICZANA, nie przechowywana.** Nie ma tabeli `journey`, nie ma
 * kolumny `stage`, nie ma przejść statusów do utrzymania. Każdy etap wynika
 * wprost z danych, które i tak istnieją: status galerii, wydane linki, stan
 * wyboru, stan paczki, użycie tokenu pobrania.
 *
 * Powód jest jeden i wystarczający: **oś, która jest kopią stanu, prędzej
 * czy później skłamie.** Zadanie w tle padnie między zapisem paczki a zapisem
 * etapu, fotograf cofnie publikację, klientka otworzy wybór ponownie — i oś
 * pokazuje coś innego niż reszta produktu. Wyliczanie nie ma tej klasy błędu
 * w ogóle; kosztuje za to kilka zapytań, które i tak wykonujemy.
 *
 * Klasa należy do warstwy Domain: dostaje gotowe fakty, oddaje etapy.
 */
final readonly class Timeline {

	/**
	 * @param array{
	 *     photos: int,
	 *     published: bool,
	 *     published_at: ?string,
	 *     link_issued: bool,
	 *     link_opened: int,
	 *     selection_status: ?string,
	 *     selection_marks: int,
	 *     submitted_at: ?string,
	 *     archive_status: ?string,
	 *     archive_ready_at: ?string,
	 *     downloaded: bool
	 * } $facts
	 * @return list<Step>
	 */
	public static function project( array $facts ): array {
		$done = self::completed( $facts );

		$steps = array();
		$found = false;

		foreach ( Stage::cases() as $stage ) {
			// `array_key_exists`, nie `isset`: etap bez znacznika czasu ma
			// wartość `null`, a `isset( null )` to `false`. Na tym poległo
			// pięć etapów naraz — oś stała na „galeria gotowa" niezależnie
			// od tego, co się wydarzyło.
			if ( array_key_exists( $stage->value, $done ) ) {
				$steps[] = new Step( $stage, State::Done, $done[ $stage->value ] );

				continue;
			}

			// Pierwszy niezrobiony etap jest bieżący, reszta czeka. Dwa
			// „bieżące" etapy naraz nie odpowiadałyby na żadne pytanie.
			$steps[] = new Step( $stage, $found ? State::Waiting : State::Current );
			$found   = true;
		}

		return $steps;
	}

	/**
	 * Etap, na którym sesja stoi w tej chwili.
	 *
	 * @param list<Step> $steps
	 */
	public static function current( array $steps ): Step {
		foreach ( $steps as $step ) {
			if ( $step->isCurrent() ) {
				return $step;
			}
		}

		// Wszystko zrobione — stoimy na ostatnim etapie.
		return $steps[ count( $steps ) - 1 ];
	}

	/**
	 * @param list<Step> $steps
	 */
	public static function isComplete( array $steps ): bool {
		foreach ( $steps as $step ) {
			if ( ! $step->isDone() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Które etapy już się wydarzyły i kiedy.
	 *
	 * @param array<string, mixed> $facts
	 * @return array<string, ?string> wartość etapu => znacznik czasu
	 */
	private static function completed( array $facts ): array {
		$done = array();

		// Galeria bez zdjęć nie jest jeszcze gotowa — to sesja, którą
		// fotograf dopiero zakłada.
		if ( (int) $facts['photos'] > 0 ) {
			$done[ Stage::Prepared->value ] = null;
		}

		// Sam status „opublikowana" nie wystarcza: publikacja bez wydanego
		// linku znaczy, że klientka nadal nie ma jak wejść.
		if ( (bool) $facts['published'] && (bool) $facts['link_issued'] ) {
			$done[ Stage::Shared->value ] = $facts['published_at'] ?? null;
		}

		if ( (int) $facts['link_opened'] > 0 ) {
			$done[ Stage::Opened->value ] = null;
		}

		$status = $facts['selection_status'];

		// „Wybór w toku" jest zrobiony, gdy klientka cokolwiek zaznaczyła —
		// albo gdy już zatwierdziła, bo wtedy tym bardziej.
		if ( (int) $facts['selection_marks'] > 0 || 'submitted' === $status ) {
			$done[ Stage::Choosing->value ] = null;
		}

		if ( 'submitted' === $status ) {
			$done[ Stage::Chosen->value ] = $facts['submitted_at'] ?? null;
		}

		if ( 'ready' === $facts['archive_status'] ) {
			$done[ Stage::Packed->value ] = $facts['archive_ready_at'] ?? null;
		}

		if ( (bool) $facts['downloaded'] ) {
			$done[ Stage::Delivered->value ] = null;
		}

		return $done;
	}
}
