<?php
declare( strict_types=1 );

namespace Kadr\Application\Selection;

use Kadr\Domain\Selection\PackageTally;
use Kadr\Domain\Shared\Money;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;

/**
 * Skrzynka wyborów — jeden ekran z odpowiedzią na pytanie „co mam zrobić".
 *
 * Fotograf nie otwiera panelu po to, żeby przeglądać galerie. Otwiera go,
 * żeby dowiedzieć się, która klientka już wybrała zdjęcia (czyli gdzie
 * zaczyna się praca i gdzie czeka dopłata), a która jeszcze się zastanawia
 * (czyli kogo warto dziś przypomnieć). Dziś ta wiedza siedzi w skrzynce
 * mailowej i w pamięci — dlatego regularnie wyparowuje.
 *
 * Dwie decyzje, które warto rozumieć czytając tę klasę:
 *
 *  1. **Dla wyborów zatwierdzonych bierzemy liczby ZAPISANE przy
 *     zatwierdzeniu**, a nie liczone ponownie. Klientka widziała konkretną
 *     kwotę, klikając „wysyłam". Gdyby fotograf zobaczył inną — bo w
 *     międzyczasie zmienił cenę zdjęcia ponad pakiet — to jego faktura
 *     rozjechałaby się z tym, na co klientka się zgodziła.
 *  2. **Dla wyborów w toku nie liczymy nic.** Kwota, która jeszcze się
 *     zmieni, nie jest informacją — jest szumem. Liczba z licznika pojawia
 *     się dopiero wtedy, gdy coś znaczy.
 */
final readonly class SelectionInbox {

	public function __construct(
		private SelectionRepository $selections,
		private GalleryRepository $galleries,
		private ClientRepository $clients,
	) {}

	/**
	 * @return array{items: list<array<string, mixed>>, summary: array<string, int>}
	 */
	public function list( int $limit = 50 ): array {
		$rows = $this->selections->latest( $limit );

		if ( array() === $rows ) {
			return array(
				'items'   => array(),
				'summary' => array(
					'submitted' => 0,
					'in_progress' => 0,
					'due' => 0,
				),
			);
		}

		// Galerie i klientów pobieramy po jednym zapytaniu na zbiór,
		// nie po jednym na wiersz.
		$galleries = $this->galleries->byIds(
			array_values( array_unique( array_map( static fn( array $row ): int => (int) $row['gallery_id'], $rows ) ) )
		);

		$clientIds = array_values(
			array_unique(
				array_filter(
					array_map( static fn( array $row ): ?int => null === $row['client_id'] ? null : (int) $row['client_id'], $rows )
				)
			)
		);

		$clients = $this->clients->byIds( $clientIds );

		$items      = array();
		$submitted  = 0;
		$inProgress = 0;
		$due        = 0;

		foreach ( $rows as $row ) {
			$gallery = $galleries[ (int) $row['gallery_id'] ] ?? null;

			// Galeria usunięta zabiera ze sobą wybór — pokazywanie sieroty
			// dałoby wiersz, którego nie da się otworzyć.
			if ( null === $gallery ) {
				continue;
			}

			$status = (string) $row['status'];
			$item   = array(
				'id'         => (string) $row['public_id'],
				'status'     => $status,
				'gallery_id' => (string) $gallery['public_id'],
				'gallery'    => (string) $gallery['title'],
				'client'     => $this->clientName( $clients[ (int) ( $row['client_id'] ?? 0 ) ] ?? null ),
				'submitted_at' => null === $row['submitted_at'] ? null : (string) $row['submitted_at'],
				'tally'      => null,
			);

			if ( 'submitted' === $status ) {
				++$submitted;

				$tally = PackageTally::calculate(
					(int) $row['included_count'] + (int) $row['extra_count'],
					null === $gallery['package_limit'] ? null : (int) $gallery['package_limit'],
					Money::fromMinor( (int) ( $gallery['extra_photo_price'] ?? 0 ) )
				);

				$due += $tally->total->minor;

				$item['tally'] = array(
					'selected'      => $tally->selected,
					'package_limit' => $tally->packageLimit,
					'included'      => $tally->included,
					'extra'         => $tally->extra,
					'total'         => $tally->total->minor,
					'needs_payment' => $tally->requiresPayment(),
				);
			} else {
				++$inProgress;
			}

			$items[] = $item;
		}

		// Zatwierdzone na górze: to one czekają na ruch fotografa.
		usort(
			$items,
			static function ( array $a, array $b ): int {
				$rank = static fn( array $item ): int => 'submitted' === $item['status'] ? 0 : 1;

				return $rank( $a ) <=> $rank( $b );
			}
		);

		return array(
			'items'   => $items,
			'summary' => array(
				'submitted'   => $submitted,
				'in_progress' => $inProgress,
				'due'         => $due,
			),
		);
	}

	/**
	 * @param array<string, mixed>|null $client
	 */
	private function clientName( ?array $client ): ?string {
		if ( null === $client ) {
			return null;
		}

		return trim( (string) $client['first_name'] . ' ' . (string) ( $client['last_name'] ?? '' ) );
	}
}
