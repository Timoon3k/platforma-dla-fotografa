<?php
declare( strict_types=1 );

namespace Kadr\Tests\TenantIsolation;

use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\ProductRepository;
use Kadr\Infrastructure\Database\Repositories\ProductVariantRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Izolacja tenantów — ryzyko R2, skutek katastrofalny.
 *
 * Testy wykonują prawdziwe zapytania SQL na schemacie produkcyjnym.
 * Scenariusz jest zawsze ten sam: tenant B próbuje sięgnąć po zasób
 * tenanta A, znając jego identyfikator.
 */
final class TenantIsolationTest extends TestCase {

	public function testForeignClientIsInvisibleEvenWithItsIdentifier(): void {
		$db = TestDatabase::migrated();

		$a = new ClientRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new ClientRepository( $db, TestDatabase::tenant( 2 ) );

		$clientOfA = $a->create( 'Kasia', 'kasia@example.test' );

		// Tenant A widzi swojego klienta.
		$this->assertTrue( null !== $a->findByPublicId( $clientOfA ) );

		// Tenant B, znając dokładny identyfikator, nie widzi nic.
		$this->assertNull( $b->findByPublicId( $clientOfA ) );
	}

	public function testForeignClientIsInvisibleByEmail(): void {
		$db = TestDatabase::migrated();

		$a = new ClientRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new ClientRepository( $db, TestDatabase::tenant( 2 ) );

		$a->create( 'Kasia', 'kasia@example.test' );

		$this->assertNull( $b->findByEmail( 'kasia@example.test' ) );
	}

	/**
	 * Ta sama osoba jako klientka dwóch fotografów.
	 *
	 * To jest argument rozstrzygający z ADR-003 — w modelu opartym o wp_users
	 * ten scenariusz kończy się albo współdzielonym kontem, albo konfliktem.
	 */
	public function testSamePersonCanBeAClientOfTwoPhotographers(): void {
		$db = TestDatabase::migrated();

		$a = new ClientRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new ClientRepository( $db, TestDatabase::tenant( 2 ) );

		$a->create( 'Kasia', 'kasia@example.test' );
		$b->create( 'Kasia', 'kasia@example.test' );

		$this->assertSame( 1, $a->count() );
		$this->assertSame( 1, $b->count() );
	}

	public function testListingNeverLeaksAcrossTenants(): void {
		$db = TestDatabase::migrated();

		$a = new ClientRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new ClientRepository( $db, TestDatabase::tenant( 2 ) );

		$a->create( 'Ola', 'ola@example.test' );
		$a->create( 'Marek', 'marek@example.test' );
		$b->create( 'Ewa', 'ewa@example.test' );

		$this->assertSame( 2, count( $a->all() ) );
		$this->assertSame( 1, count( $b->all() ) );
		$this->assertSame( 2, $a->count() );
		$this->assertSame( 1, $b->count() );
	}

	public function testForeignGalleryCannotBeUpdated(): void {
		$db = TestDatabase::migrated();

		$a = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new GalleryRepository( $db, TestDatabase::tenant( 2 ) );

		$gallery = $a->create( 'Sesja rodzinna', 'sesja-rodzinna' );

		// Tenant B próbuje podmienić tytuł cudzej galerii.
		$changed = $b->update( $gallery, array( 'title' => 'Przejęte' ) );

		$this->assertSame( 0, $changed );
		$this->assertSame( 'Sesja rodzinna', (string) $a->findByPublicId( $gallery )['title'] );
	}

	public function testForeignGalleryCannotBeDeleted(): void {
		$db = TestDatabase::migrated();

		$a = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new GalleryRepository( $db, TestDatabase::tenant( 2 ) );

		$gallery = $a->create( 'Wesele', 'wesele' );

		$this->assertSame( 0, $b->delete( $gallery ) );
		$this->assertTrue( null !== $a->findByPublicId( $gallery ) );
	}

	public function testForeignGalleryCannotBePublished(): void {
		$db = TestDatabase::migrated();

		$a = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new GalleryRepository( $db, TestDatabase::tenant( 2 ) );

		$gallery = $a->create( 'Chrzciny', 'chrzciny' );

		$this->assertSame( 0, $b->publish( $gallery ) );
		$this->assertSame( 'draft', (string) $a->findByPublicId( $gallery )['status'] );
	}

	/**
	 * Wstawienie wiersza z cudzym tenant_id musi być niewykonalne.
	 */
	public function testInsertAlwaysBelongsToTheActingTenant(): void {
		$db = TestDatabase::migrated();

		$b     = new GalleryRepository( $db, TestDatabase::tenant( 2 ) );
		$a     = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$asset = $b->create( 'Próba', 'proba' );

		// Wiersz powstał u tenanta 2, mimo że tenanta 1 też pytamy o niego.
		$this->assertNull( $a->findByPublicId( $asset ) );
		$this->assertTrue( null !== $b->findByPublicId( $asset ) );
	}

	public function testAssetsAndSelectionsAreIsolatedToo(): void {
		$db = TestDatabase::migrated();

		$galleriesA = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$assetsA    = new AssetRepository( $db, TestDatabase::tenant( 1 ) );
		$assetsB    = new AssetRepository( $db, TestDatabase::tenant( 2 ) );

		$galleriesA->create( 'Plener', 'plener' );
		$galleryRow = $galleriesA->all()[0];
		$galleryId  = (int) $galleryRow['id'];

		$assetsA->create(
			$galleryId,
			array(
				'original_name' => 'IMG_0001.jpg',
				'storage_path'  => 'originals/1/1/IMG_0001.jpg',
				'content_hash'  => str_repeat( 'a', 64 ),
				'bytes'         => 5_000_000,
				'width'         => 6000,
				'height'        => 4000,
				'sort_order'    => 0,
			)
		);

		$this->assertSame( 1, $assetsA->countForGallery( $galleryId ) );
		// Tenant B pyta o tę samą galerię po jej wewnętrznym identyfikatorze.
		$this->assertSame( 0, $assetsB->countForGallery( $galleryId ) );
		$this->assertNull( $assetsB->findDuplicate( str_repeat( 'a', 64 ) ) );
	}

	public function testSelectionItemsAreIsolated(): void {
		$db = TestDatabase::migrated();

		$itemsA = new SelectionItemRepository( $db, TestDatabase::tenant( 1 ) );
		$itemsB = new SelectionItemRepository( $db, TestDatabase::tenant( 2 ) );

		$itemsA->setState( 10, 20, SelectionState::Selected );

		$this->assertSame( 1, $itemsA->countInState( 10, SelectionState::Selected ) );
		$this->assertSame( 0, $itemsB->countInState( 10, SelectionState::Selected ) );

		// Tenant B próbuje zmienić cudzy wybór — tworzy własny wpis, nie rusza cudzego.
		$itemsB->setState( 10, 20, SelectionState::Rejected );

		$this->assertSame( 1, $itemsA->countInState( 10, SelectionState::Selected ) );
		$this->assertSame( 0, $itemsA->countInState( 10, SelectionState::Rejected ) );
	}

	/**
	 * Nazwy kolumn trafiają do SQL-a bez parametryzacji, więc jedyną ochroną
	 * jest lista dozwolonych nazw z deklaracji tabeli.
	 */
	/**
	 * Paczka plików drugiego fotografa nie istnieje.
	 *
	 * To jest wiersz, który wskazuje na plik z prywatnymi zdjęciami cudzej
	 * rodziny — przeciek tutaj znaczy wydanie linku do nich.
	 */
	public function testForeignArchiveIsInvisible(): void {
		$db = TestDatabase::migrated();

		$galleriesA = new GalleryRepository( $db, TestDatabase::tenant( 1 ) );
		$archivesA  = new ArchiveRepository( $db, TestDatabase::tenant( 1 ) );
		$archivesB  = new ArchiveRepository( $db, TestDatabase::tenant( 2 ) );

		$gallery = $galleriesA->create( 'Wesele', 'wesele' );
		$row     = $galleriesA->findByPublicId( $gallery );

		$archiveId = $archivesA->request( (int) $row['id'], ArchiveScope::Everything, 10, 'finals/1/x/everything.zip' );

		$this->assertNotNull( $archivesA->findByPublicId( $archiveId ) );
		$this->assertNull( $archivesB->findByPublicId( $archiveId ) );
		// Nawet znając wewnętrzny identyfikator galerii.
		$this->assertNull( $archivesB->forGallery( (int) $row['id'], ArchiveScope::Everything ) );
	}

	/**
	 * Token pobrania drugiego fotografa nie istnieje.
	 */
	public function testForeignDownloadTokenIsInvisible(): void {
		$db = TestDatabase::migrated();

		$tokensA = new DownloadTokenRepository( $db, TestDatabase::tenant( 1 ) );
		$tokensB = new DownloadTokenRepository( $db, TestDatabase::tenant( 2 ) );

		$hash = hash( 'sha256', 'token-fotografa-a' );

		$tokensA->create( $hash, 'zip', gmdate( 'Y-m-d H:i:s', time() + 3600 ), 1, null, 'finals/1/x/everything.zip' );

		$this->assertNotNull( $tokensA->findByTokenHash( $hash ) );
		$this->assertNull( $tokensB->findByTokenHash( $hash ) );
	}

	/**
	 * Unieważnianie nie może sięgać poza własnego tenanta.
	 *
	 * Gdyby sięgało, jeden fotograf zamykałby dostęp klientkom drugiego —
	 * po identyfikatorze galerii, który łatwo zgadnąć, bo jest sekwencyjny.
	 */
	public function testRevokingCannotReachAnotherPhotographersTokens(): void {
		$db = TestDatabase::migrated();

		$tokensA = new DownloadTokenRepository( $db, TestDatabase::tenant( 1 ) );
		$tokensB = new DownloadTokenRepository( $db, TestDatabase::tenant( 2 ) );

		$hash = hash( 'sha256', 'token-fotografa-a' );
		$tokensA->create( $hash, 'zip', gmdate( 'Y-m-d H:i:s', time() + 3600 ), 7, null, 'finals/1/x/everything.zip' );

		$tokensB->revokeForGallery( 7 );

		$this->assertNull( $tokensA->findByTokenHash( $hash )['revoked_at'] );
	}

	/**
	 * Dziennik zdarzeń jednego fotografa jest niewidoczny dla drugiego.
	 */
	public function testAuditLogIsIsolated(): void {
		$db = TestDatabase::migrated();

		$auditA = new AuditLogRepository( $db, TestDatabase::tenant( 1 ) );
		$auditB = new AuditLogRepository( $db, TestDatabase::tenant( 2 ) );

		$auditA->record( \Kadr\Domain\Audit\AuditEvent::DownloadTokenIssued, 'user', null, 'gallery', 'ABC' );

		$this->assertSame( 1, count( $auditA->latest() ) );
		$this->assertSame( 0, count( $auditB->latest() ) );
		$this->assertSame( 0, count( $auditB->forEntity( 'gallery', 'ABC' ) ) );
	}

	/**
	 * Cennik drugiego fotografa nie istnieje.
	 *
	 * To jest jego marża — przeciek tutaj pokazuje konkurencji, po ile
	 * sprzedaje odbitki.
	 */
	public function testForeignCatalogueIsInvisible(): void {
		$db = TestDatabase::migrated();

		$a = new ProductRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new ProductRepository( $db, TestDatabase::tenant( 2 ) );

		$product = $a->create( 'print', 'Odbitki' );

		$this->assertNotNull( $a->findByPublicId( $product ) );
		$this->assertNull( $b->findByPublicId( $product ) );
		$this->assertSame( 0, count( $b->all() ) );
	}

	/**
	 * Warianty również — i to nawet wtedy, gdy ktoś zna wewnętrzny
	 * identyfikator produktu.
	 */
	public function testForeignVariantsAreInvisibleEvenWithTheProductId(): void {
		$db = TestDatabase::migrated();

		$products = new ProductRepository( $db, TestDatabase::tenant( 1 ) );
		$a        = new ProductVariantRepository( $db, TestDatabase::tenant( 1 ) );
		$b        = new ProductVariantRepository( $db, TestDatabase::tenant( 2 ) );

		$product   = $products->findByPublicId( $products->create( 'print', 'Odbitki' ) );
		$productId = (int) $product['id'];

		$variant = $a->create( $productId, '10×15', 200, 100, 150 );

		$this->assertNotNull( $a->findByPublicId( $variant ) );
		$this->assertNull( $b->findByPublicId( $variant ) );
		$this->assertSame( 0, count( $b->forProduct( $productId ) ) );
		$this->assertSame( array(), $b->forProducts( array( $productId ) ) );
	}

	/**
	 * Zmiana ceny nie może sięgnąć poza własnego tenanta — inaczej jeden
	 * fotograf przecenia odbitki drugiemu.
	 */
	public function testPriceChangesCannotReachAnotherPhotographer(): void {
		$db = TestDatabase::migrated();

		$products = new ProductRepository( $db, TestDatabase::tenant( 1 ) );
		$a        = new ProductVariantRepository( $db, TestDatabase::tenant( 1 ) );
		$b        = new ProductVariantRepository( $db, TestDatabase::tenant( 2 ) );

		$product = $products->findByPublicId( $products->create( 'print', 'Odbitki' ) );
		$variant = $a->create( (int) $product['id'], '10×15', 200, 100, 150 );

		$b->update( $variant, array( 'price' => 1 ) );

		$this->assertSame( 200, (int) $a->findByPublicId( $variant )['price'] );
	}

	public function testUnknownColumnIsRejectedInsteadOfReachingSql(): void {
		$db = TestDatabase::migrated();
		$a  = new ClientRepository( $db, TestDatabase::tenant( 1 ) );

		$this->assertThrows(
			\InvalidArgumentException::class,
			static fn() => $a->update( Ulid::generate(), array( 'email = ? OR 1=1 --' => 'x' ) )
		);
	}

	/**
	 * Próba nadpisania tenanta przez dane wejściowe musi być bezskuteczna.
	 */
	public function testTenantIdCannotBeOverriddenThroughUpdate(): void {
		$db = TestDatabase::migrated();

		$a = new ClientRepository( $db, TestDatabase::tenant( 1 ) );
		$b = new ClientRepository( $db, TestDatabase::tenant( 2 ) );

		$client = $a->create( 'Ola', 'ola@example.test' );

		// Właściciel próbuje „przenieść” klienta do innego tenanta.
		$a->update( $client, array( 'tenant_id' => 2, 'first_name' => 'Ola K.' ) );

		$this->assertTrue( null !== $a->findByPublicId( $client ) );
		$this->assertNull( $b->findByPublicId( $client ) );
		$this->assertSame( 'Ola K.', (string) $a->findByPublicId( $client )['first_name'] );
	}

	public function testSoftDeletedRowsDisappearFromNormalQueries(): void {
		$db = TestDatabase::migrated();
		$a  = new ClientRepository( $db, TestDatabase::tenant( 1 ) );

		$client = $a->create( 'Ola', 'ola@example.test' );

		$this->assertSame( 1, $a->count() );
		$a->delete( $client );
		$this->assertSame( 0, $a->count() );
		$this->assertNull( $a->findByPublicId( $client ) );

		// Kosz działa — dane nie znikają, tylko przestają być widoczne.
		$a->restore( $client );
		$this->assertSame( 1, $a->count() );
	}
}
