<?php
declare( strict_types=1 );

namespace Kadr\Tests\Sharing;

use Kadr\Application\Gallery\OpenSharedGallery;
use Kadr\Application\Gallery\ShareGallery;
use Kadr\Domain\Shared\FrozenClock;
use Kadr\Infrastructure\Database\Platform\GalleryLookup;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Security\InMemoryThrottle;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Dostęp klientki do galerii przez publiczny link.
 *
 * To jest jedyne miejsce w produkcie, w którym ktoś niezalogowany czyta dane
 * tenanta. Testy pilnują dwóch rzeczy naraz: że działa dla właściwego linku
 * i że nie działa dla żadnego innego.
 */
final class GalleryAccessTest extends TestCase {

	public function testIssuedLinkOpensThePublishedGallery(): void {
		$world = $this->world();
		$token = $world->share->issue( $world->gallery )->value['token'];

		$opened = $world->open->open( $token );

		$this->assertTrue( $opened->ok );
		$this->assertSame( 'Wesele', (string) $opened->value['gallery']['title'] );
		$this->assertSame( 1, $opened->value['tenant']->id() );
		$this->assertFalse( $opened->value['needs_pin'] );
	}

	/**
	 * Jawny token istnieje tylko raz — w odpowiedzi na wydanie linku.
	 */
	public function testOnlyTheHashIsStored(): void {
		$world = $this->world();
		$token = $world->share->issue( $world->gallery )->value['token'];

		$rows = $world->access->forGallery( $world->galleryRow['id'] );

		$this->assertSame( 1, count( $rows ) );
		$this->assertSame( 64, strlen( (string) $rows[0]['token_hash'] ) );

		// Nigdzie w wierszu nie ma jawnego tokenu.
		foreach ( $rows[0] as $value ) {
			$this->assertFalse( is_string( $value ) && str_contains( $value, $token ) );
		}
	}

	public function testWrongTokenIsRefused(): void {
		$world = $this->world();
		$world->share->issue( $world->gallery );

		$result = $world->open->open( str_repeat( 'A', 43 ) );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_invalid_link', $result->code );
	}

	/**
	 * Wszystkie powody odmowy dają ten sam komunikat.
	 *
	 * Inaczej zgadujący dowiaduje się, że trafił w istniejący link — i wie,
	 * że warto próbować dalej.
	 */
	public function testEveryRefusalLooksTheSame(): void {
		$world = $this->world();
		$issued = $world->share->issue( $world->gallery )->value;

		$world->share->revoke( $world->gallery, $issued['id'] );

		$revoked = $world->open->open( $issued['token'] );
		$unknown = $world->open->open( str_repeat( 'B', 43 ) );

		$this->assertSame( $revoked->code, $unknown->code );
		$this->assertSame( $revoked->message, $unknown->message );
	}

	public function testExpiredLinkStopsWorking(): void {
		$clock = new FrozenClock( '2026-01-01 10:00:00' );
		$world = $this->world( $clock );
		$token = $world->share->issue( $world->gallery, null, 7 )->value['token'];

		$this->assertTrue( $world->open->open( $token )->ok );

		$clock->advance( '+8 days' );

		$this->assertSame( 'kadr_invalid_link', $world->open->open( $token )->code );
	}

	/**
	 * Wycofanie galerii z publikacji odcina dostęp natychmiast.
	 *
	 * Fotograf ma jeden przełącznik, nie dwa — nie musi pamiętać, żeby
	 * osobno unieważnić wszystkie wydane linki.
	 */
	public function testUnpublishingClosesEveryLink(): void {
		$world = $this->world();
		$token = $world->share->issue( $world->gallery )->value['token'];

		$this->assertTrue( $world->open->open( $token )->ok );

		$world->galleries->update( $world->gallery, array( 'status' => 'archived' ) );

		$this->assertSame( 'kadr_invalid_link', $world->open->open( $token )->code );
	}

	public function testDraftGalleryIsNeverReachable(): void {
		$world = $this->world( null, false );
		$token = $world->share->issue( $world->gallery )->value['token'];

		// Link wydany dla szkicu istnieje, ale nie otwiera niczego —
		// klient nie zobaczy galerii, zanim fotograf ją opublikuje.
		$this->assertSame( 'kadr_invalid_link', $world->open->open( $token )->code );
	}

	public function testPinProtectedGallerySaysSoWithoutRevealingPhotos(): void {
		$world = $this->world();
		$token = $world->share->issue( $world->gallery, '4821' )->value['token'];

		$opened = $world->open->open( $token );

		$this->assertTrue( $opened->ok );
		$this->assertTrue( $opened->value['needs_pin'] );
	}

	public function testCorrectPinOpensTheGallery(): void {
		$world = $this->world();
		$token = $world->share->issue( $world->gallery, '4821' )->value['token'];

		$this->assertTrue( $world->open->verifyPin( $token, '4821' )->ok );
		$this->assertSame( 'kadr_invalid_pin', $world->open->verifyPin( $token, '1111' )->code );
	}

	/**
	 * Cztery cyfry bez limitu prób są do zgadnięcia w kilkanaście minut.
	 */
	public function testPinGuessingIsThrottled(): void {
		$world = $this->world();
		$token = $world->share->issue( $world->gallery, '4821' )->value['token'];

		$blocked = false;

		for ( $attempt = 0; $attempt < 40; $attempt++ ) {
			if ( 'kadr_rate_limited' === $world->open->verifyPin( $token, '0000' )->code ) {
				$blocked = true;
				break;
			}
		}

		$this->assertTrue( $blocked );
	}

	/**
	 * Limit chroni LINK, nie adres IP — dwa różne linki nie blokują się
	 * nawzajem, bo klientka i zgadujący mogą siedzieć za tym samym adresem.
	 */
	public function testThrottleIsPerLinkNotGlobal(): void {
		$world  = $this->world();
		$first  = $world->share->issue( $world->gallery, '1234' )->value['token'];
		$second = $world->share->issue( $world->gallery, '5678' )->value['token'];

		for ( $attempt = 0; $attempt < 15; $attempt++ ) {
			$world->open->verifyPin( $first, '0000' );
		}

		$this->assertTrue( $world->open->verifyPin( $second, '5678' )->ok );
	}

	public function testPinMustBeFourDigits(): void {
		$world = $this->world();

		$this->assertSame( 'kadr_invalid_input', $world->share->issue( $world->gallery, '12' )->code );
		$this->assertSame( 'kadr_invalid_input', $world->share->issue( $world->gallery, 'abcd' )->code );
		$this->assertTrue( $world->share->issue( $world->gallery, '0000' )->ok );
	}

	/**
	 * Token jednego fotografa nie otwiera galerii drugiego — ani przez
	 * pomyłkę, ani przez podstawienie.
	 */
	public function testTokenNeverCrossesTenants(): void {
		$db     = TestDatabase::migrated();
		$first  = $this->worldOn( $db, 1 );
		$second = $this->worldOn( $db, 2 );

		$tokenOfFirst = $first->share->issue( $first->gallery )->value['token'];

		$opened = $second->open->open( $tokenOfFirst );

		// Ten sam obiekt `OpenSharedGallery` obsługuje oba tenanty, bo tenant
		// wynika z tokenu — więc otwarcie SIĘ UDA, ale zwróci tenanta 1,
		// nie 2. To jest właściwe zachowanie: link należy do galerii.
		$this->assertTrue( $opened->ok );
		$this->assertSame( 1, $opened->value['tenant']->id() );
		$this->assertSame( 'Wesele', (string) $opened->value['gallery']['title'] );
	}

	private function world( ?FrozenClock $clock = null, bool $publish = true ): object {
		return $this->worldOn( TestDatabase::migrated(), 1, $clock, $publish );
	}

	private function worldOn( object $db, int $tenantId, ?FrozenClock $clock = null, bool $publish = true ): object {
		$clock     = $clock ?? new FrozenClock( '2026-01-01 10:00:00' );
		$tenant    = TestDatabase::tenant( $tenantId );
		$galleries = new GalleryRepository( $db, $tenant );
		$access    = new GalleryAccessRepository( $db, $tenant );
		$throttle  = new InMemoryThrottle();

		$gallery = $galleries->create( 'Wesele', 'wesele-' . $tenantId );

		if ( $publish ) {
			$galleries->publish( $gallery );
		}

		return new class(
			$galleries,
			$access,
			$gallery,
			$galleries->findByPublicId( $gallery ),
			new ShareGallery( $galleries, $access, $clock ),
			new OpenSharedGallery( $db, new GalleryLookup( $db ), $throttle, $clock )
		) {
			public function __construct(
				public GalleryRepository $galleries,
				public GalleryAccessRepository $access,
				public \Kadr\Domain\Shared\Ulid $gallery,
				public array $galleryRow,
				public ShareGallery $share,
				public OpenSharedGallery $open,
			) {}
		};
	}
}
