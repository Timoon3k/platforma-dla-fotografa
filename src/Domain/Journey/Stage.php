<?php
declare( strict_types=1 );

namespace Kadr\Domain\Journey;

/**
 * Etapy współpracy widoczne na osi procesu.
 *
 * Są tu WYŁĄCZNIE etapy, które produkt naprawdę potrafi rozpoznać z danych.
 * Kuszące byłoby rozpisać całą drogę od zapytania do odbitek i wyszarzyć
 * to, czego jeszcze nie ma — ale sześć trwale szarych kropek to nie jest
 * mapa, tylko obietnica na kredyt (CLAUDE.md §9: zero placeholderów).
 *
 * Oś rośnie razem z produktem: zamówienie wejdzie między „wybór zatwierdzony"
 * a „pliki gotowe", gdy powstaną płatności.
 */
enum Stage: string {

	case Prepared  = 'prepared';
	case Shared    = 'shared';
	case Opened    = 'opened';
	case Choosing  = 'choosing';
	case Chosen    = 'chosen';
	case Packed    = 'packed';
	case Delivered = 'delivered';

	/**
	 * Nazwa dla fotografa — jego język (skill photography-workflow §9).
	 */
	public function label(): string {
		return match ( $this ) {
			self::Prepared  => 'Galeria gotowa',
			self::Shared    => 'Link wysłany',
			self::Opened    => 'Klientka obejrzała',
			self::Choosing  => 'Wybór w toku',
			self::Chosen    => 'Wybór zatwierdzony',
			self::Packed    => 'Pliki przygotowane',
			self::Delivered => 'Pliki pobrane',
		};
	}

	/**
	 * Nazwa dla klientki — ta sama rzecz z jej strony.
	 *
	 * „Klientka obejrzała" powiedziane klientce brzmi jak podglądanie.
	 * To jest ten sam etap, ale opowiedziany jej, a nie o niej.
	 */
	public function clientLabel(): string {
		return match ( $this ) {
			self::Prepared  => 'Zdjęcia przygotowane',
			self::Shared    => 'Galeria otwarta dla Ciebie',
			self::Opened    => 'Oglądasz zdjęcia',
			self::Choosing  => 'Wybierasz zdjęcia',
			self::Chosen    => 'Wybór wysłany',
			self::Packed    => 'Pliki gotowe',
			self::Delivered => 'Pliki pobrane',
		};
	}

	/**
	 * Co się stanie dalej — zdanie, które odpowiada na „kiedy będą zdjęcia?"
	 * ZANIM ktokolwiek zdąży zapytać.
	 */
	public function clientNext(): string {
		return match ( $this ) {
			self::Prepared  => 'Fotograf kończy przygotowania.',
			self::Shared    => 'Możesz obejrzeć zdjęcia i zaznaczyć te, które mamy obrobić.',
			self::Opened    => 'Zaznacz zdjęcia, które mamy obrobić — licznik na dole pokazuje, ile obejmuje pakiet.',
			self::Choosing  => 'Gdy skończysz, wyślij wybór. Do tego czasu możesz go dowolnie zmieniać.',
			self::Chosen    => 'Fotograf przygotowuje pliki. Damy znać, gdy będą gotowe.',
			self::Packed    => 'Pliki czekają na Ciebie — pobierz je z tej galerii.',
			self::Delivered => 'Gotowe. Pliki masz u siebie.',
		};
	}
}
