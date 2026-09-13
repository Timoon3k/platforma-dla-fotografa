<?php
declare( strict_types=1 );

namespace Kadr\Tests\Selection;

use Kadr\Application\Selection\SelectionInbox;
use Kadr\Application\Selection\SelectionRoom;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Skrzynka wyborów — ekran, na którym fotograf widzi, gdzie czeka praca
 * i gdzie czeka pieniądz.
 *
 * Testy opisują reguły produktu: co trafia na górę listy, skąd biorą się
 * kwoty i czego fotograf NIE ma prawa zobaczyć.
 */
final class SelectionInboxTest extends TestCase {

	public function testSubmittedSelectionShowsTheAmountTheClientAgreedTo(): void {
		$world = $this->world();
		$world->submitGallery( 'Wesele Marty', 20, 6000, 28 );

		$list = $world->inbox->list();

		$this->assertSame( 1, count( $list['items'] ) );

		$item = $list['items'][0];

		$this->assertSame( 'submitted', $item['status'] );
		$this->assertSame( 'Wesele Marty', $item['gallery'] );
		$this->assertSame( 28, $item['tally']['selected'] );
		$this->assertSame( 8, $item['tally']['extra'] );
		// 8 × 60 zł = 480 zł, w groszach — ta sama liczba, którą klientka
		// widziała, klikając „wysyłam".
		$this->assertSame( 48000, $item['tally']['total'] );
		$this->assertTrue( $item['tally']['needs_payment'] );
	}

	/**
	 * Kwoty pochodzą z chwili zatwierdzenia, nie z dzisiejszego cennika.
	 *
	 * Fotograf podniesie cenę zdjęcia ponad pakiet — to normalne. Gdyby
	 * skrzynka przeliczała wybory wstecz, jego faktura rozjechałaby się
	 * z tym, na co klientka się zgodziła.
	 */
	public function testRaisingThePriceDoesNotRewriteWhatTheClientAlreadyAgreedTo(): void {
		$world = $this->world();
		$gallery = $world->submitGallery( 'Chrzciny', 10, 5000, 14 );

		$before = $world->inbox->list()['items'][0]['tally']['total'];
		$this->assertSame( 20000, $before );

		$world->galleries->update( $gallery, array( 'extra_photo_price' => 9900 ) );

		$after = $world->inbox->list()['items'][0];

		// Liczba zdjęć ponad pakiet się nie zmienia — jest zamrożona.
		$this->assertSame( 4, $after['tally']['extra'] );
		$this->assertSame( 14, $after['tally']['selected'] );
	}

	/**
	 * Wybór w toku nie pokazuje kwoty. Kwota, która jeszcze się zmieni,
	 * nie jest informacją.
	 */
	public function testSelectionInProgressCarriesNoAmount(): void {
		$world = $this->world();
		$world->openGallery( 'Sesja rodzinna', 20, 6000, 5 );

		$item = $world->inbox->list()['items'][0];

		$this->assertSame( 'open', $item['status'] );
		$this->assertNull( $item['tally'] );
	}

	/**
	 * Zatwierdzone na górze — to one czekają na ruch fotografa.
	 */
	public function testSubmittedSelectionsComeFirst(): void {
		$world = $this->world();
		$world->openGallery( 'W toku', 20, 6000, 5, 'w-toku' );
		$world->submitGallery( 'Gotowe', 20, 6000, 25, 'gotowe' );

		$items = $world->inbox->list()['items'];

		$this->assertSame( 'Gotowe', $items[0]['gallery'] );
		$this->assertSame( 'W toku', $items[1]['gallery'] );
	}

	/**
	 * Podsumowanie odpowiada na pytanie „ile dziś czeka".
	 */
	public function testSummaryAddsUpWhatIsWaiting(): void {
		$world = $this->world();
		$world->submitGallery( 'Pierwsza', 20, 6000, 28, 'pierwsza' );
		$world->submitGallery( 'Druga', 10, 5000, 14, 'druga' );
		$world->openGallery( 'Trzecia', 20, 6000, 3, 'trzecia' );

		$summary = $world->inbox->list()['summary'];

		$this->assertSame( 2, $summary['submitted'] );
		$this->assertSame( 1, $summary['in_progress'] );
		// 480 zł + 200 zł.
		$this->assertSame( 68000, $summary['due'] );
	}

	public function testEmptyInboxIsNotAnError(): void {
		$world = $this->world();

		$list = $world->inbox->list();

		$this->assertSame( array(), $list['items'] );
		$this->assertSame( 0, $list['summary']['submitted'] );
		$this->assertSame( 0, $list['summary']['due'] );
	}

	/**
	 * Izolacja tenantów (ryzyko R2). Skrzynka czyta wybory, galerie I klientów
	 * — trzy tabele, z których każda mogłaby przeciec.
	 */
	public function testOnePhotographerNeverSeesAnotherPhotographersSelections(): void {
		$db = TestDatabase::migrated();

		$first  = $this->worldOn( $db, 1 );
		$second = $this->worldOn( $db, 2 );

		$first->submitGallery( 'Moje wesele', 20, 6000, 28, 'moje' );
		$second->submitGallery( 'Cudze wesele', 20, 6000, 30, 'cudze' );

		$mine = $first->inbox->list();

		$this->assertSame( 1, count( $mine['items'] ) );
		$this->assertSame( 'Moje wesele', $mine['items'][0]['gallery'] );
		$this->assertSame( 48000, $mine['summary']['due'] );
	}

	/**
	 * Nazwisko klientki przy wyborze — bez niego fotograf musi otworzyć
	 * galerię tylko po to, żeby sprawdzić, czyj to wybór.
	 */
	public function testSelectionCarriesTheClientName(): void {
		$world  = $this->world();
		$client = $world->clients->create( 'Marta', 'marta@example.test', 'Nowak' );
		$row    = $world->clients->findByPublicId( $client );

		$world->submitGallery( 'Wesele', 20, 6000, 25, 'wesele', (int) $row['id'] );

		$this->assertSame( 'Marta Nowak', $world->inbox->list()['items'][0]['client'] );
	}

	public function testSelectionWithoutAClientIsStillListed(): void {
		$world = $this->world();
		$world->submitGallery( 'Bez klienta', 20, 6000, 25 );

		$this->assertNull( $world->inbox->list()['items'][0]['client'] );
	}

	private function world(): object {
		return $this->worldOn( TestDatabase::migrated(), 1 );
	}

	private function worldOn( object $db, int $tenantId ): object {
		$tenant = TestDatabase::tenant( $tenantId );

		$galleries  = new GalleryRepository( $db, $tenant );
		$assets     = new AssetRepository( $db, $tenant );
		$selections = new SelectionRepository( $db, $tenant );
		$items      = new SelectionItemRepository( $db, $tenant );
		$clients    = new ClientRepository( $db, $tenant );

		return new class(
			$galleries,
			$assets,
			$clients,
			new SelectionRoom( $galleries, $assets, $selections, $items ),
			new SelectionInbox( $selections, $galleries, $clients )
		) {
			public function __construct(
				public GalleryRepository $galleries,
				public AssetRepository $assets,
				public ClientRepository $clients,
				public SelectionRoom $room,
				public SelectionInbox $inbox,
			) {}

			public function openGallery(
				string $title,
				?int $limit,
				int $price,
				int $selected,
				string $slug = 'galeria',
				?int $clientId = null
			): \Kadr\Domain\Shared\Ulid {
				$gallery = $this->galleries->create( $title, $slug, $clientId );
				$this->galleries->update(
					$gallery,
					array(
						'package_limit'     => $limit,
						'extra_photo_price' => $price,
					)
				);

				$row = $this->galleries->findByPublicId( $gallery );

				for ( $i = 0; $i < $selected; $i++ ) {
					$asset = $this->assets->create(
						(int) $row['id'],
						array(
							'original_name' => "kadr-$i.jpg",
							'storage_path'  => "private/$slug/kadr-$i.jpg",
							'content_hash'  => hash( 'sha256', "$slug-$i" ),
							'bytes'         => 2048,
							'width'         => 3000,
							'height'        => 2000,
							'sort_order'    => $i,
						)
					);

					$this->room->mark( $gallery, $asset, SelectionState::Selected, $clientId );
				}

				return $gallery;
			}

			public function submitGallery(
				string $title,
				?int $limit,
				int $price,
				int $selected,
				string $slug = 'galeria',
				?int $clientId = null
			): \Kadr\Domain\Shared\Ulid {
				$gallery = $this->openGallery( $title, $limit, $price, $selected, $slug, $clientId );
				$this->room->submit( $gallery );

				return $gallery;
			}
		};
	}
}
