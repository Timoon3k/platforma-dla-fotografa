<?php
declare( strict_types=1 );

namespace Kadr\Tests\Gallery;

use Kadr\Application\Gallery\ManageGalleries;
use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Tworzenie i ustawienia galerii.
 *
 * Reguły, które tu stoją, są regułami PRODUKTU, nie walidacją formularza:
 * limit planu i to, że pakiet bez ceny za nadmiar oznacza utracony przychód.
 */
final class ManageGalleriesTest extends TestCase {

	public function testCreatesGalleryWithSlugFromTitle(): void {
		$useCase = $this->useCase( 'studio' );

		$result = $useCase->handle->create( array( 'title' => 'Ślub Marty i Piotra' ) );

		$this->assertTrue( $result->ok );

		$row = $useCase->galleries->findByPublicId( Ulid::fromString( $result->value['id'] ) );

		$this->assertSame( 'slub-marty-i-piotra', (string) $row['slug'] );
		$this->assertSame( 'draft', (string) $row['status'] );
	}

	/**
	 * Dwie sesje ślubne o tej samej nazwie zdarzają się co tydzień.
	 */
	public function testSecondGalleryWithTheSameTitleGetsItsOwnSlug(): void {
		$useCase = $this->useCase( 'studio' );

		$first  = $useCase->handle->create( array( 'title' => 'Sesja rodzinna' ) );
		$second = $useCase->handle->create( array( 'title' => 'Sesja rodzinna' ) );

		$firstRow  = $useCase->galleries->findByPublicId( Ulid::fromString( $first->value['id'] ) );
		$secondRow = $useCase->galleries->findByPublicId( Ulid::fromString( $second->value['id'] ) );

		$this->assertSame( 'sesja-rodzinna', (string) $firstRow['slug'] );
		$this->assertSame( 'sesja-rodzinna-2', (string) $secondRow['slug'] );
	}

	/**
	 * Pakiet bez ceny za nadmiar to dokładnie ta strata, którą produkt
	 * ma likwidować: klient wybiera więcej zdjęć i nikt za nie nie płaci.
	 */
	public function testPackageWithoutSurchargePriceIsRejected(): void {
		$useCase = $this->useCase( 'studio' );

		$result = $useCase->handle->create(
			array(
				'title'         => 'Sesja rodzinna',
				'package_limit' => 20,
			)
		);

		$this->assertTrue( $result->isFailure() );
		$this->assertTrue( isset( $result->details['params']['extra_photo_price'] ) );
	}

	public function testPackageWithSurchargePriceIsAccepted(): void {
		$useCase = $this->useCase( 'studio' );

		$result = $useCase->handle->create(
			array(
				'title'             => 'Sesja rodzinna',
				'package_limit'     => 20,
				'extra_photo_price' => 5000,
			)
		);

		$this->assertTrue( $result->ok );

		$row = $useCase->galleries->findByPublicId( Ulid::fromString( $result->value['id'] ) );

		$this->assertSame( 20, (int) $row['package_limit'] );
		// Kwoty w groszach — nigdy zmiennoprzecinkowe.
		$this->assertSame( 5000, (int) $row['extra_photo_price'] );
	}

	public function testPlanLimitStopsCreationWithAMessageThatSaysWhatToDo(): void {
		// Plan darmowy: pięć galerii.
		$useCase = $this->useCase( 'free' );

		for ( $i = 1; $i <= 5; $i++ ) {
			$this->assertTrue( $useCase->handle->create( array( 'title' => "Galeria $i" ) )->ok );
		}

		$blocked = $useCase->handle->create( array( 'title' => 'Szósta' ) );

		$this->assertTrue( $blocked->isFailure() );
		$this->assertSame( 'kadr_limit_reached', $blocked->code );
		$this->assertSame( 5, (int) $blocked->details['limit'] );
	}

	public function testUnlimitedPlanIsNotBlocked(): void {
		// Pro nie ma limitu galerii — `null` musi znaczyć „bez limitu",
		// a nie „zero" (regresja z sesji 2).
		$useCase = $this->useCase( 'pro' );

		for ( $i = 1; $i <= 12; $i++ ) {
			$this->assertTrue( $useCase->handle->create( array( 'title' => "Galeria $i" ) )->ok );
		}
	}

	public function testExpiryCoversTheWholeDayTheUserTyped(): void {
		$useCase = $this->useCase( 'studio' );
		$created = $useCase->handle->create( array( 'title' => 'Sesja' ) );
		$id      = Ulid::fromString( $created->value['id'] );

		$useCase->handle->update( $id, array( 'expires_at' => '2026-09-30' ) );

		$row = $useCase->galleries->findByPublicId( $id );

		// Fotograf wpisuje „do 30 września" i ma na myśli cały ten dzień.
		$this->assertSame( '2026-09-30 23:59:59', (string) $row['expires_at'] );
	}

	public function testEmptyExpiryClearsTheDeadline(): void {
		$useCase = $this->useCase( 'studio' );
		$created = $useCase->handle->create( array( 'title' => 'Sesja' ) );
		$id      = Ulid::fromString( $created->value['id'] );

		$useCase->handle->update( $id, array( 'expires_at' => '2026-09-30' ) );
		$useCase->handle->update( $id, array( 'expires_at' => '' ) );

		$this->assertNull( $useCase->galleries->findByPublicId( $id )['expires_at'] );
	}

	public function testUnknownFieldsNeverReachTheDatabase(): void {
		$useCase = $this->useCase( 'studio' );

		$result = $useCase->handle->create(
			array(
				'title'     => 'Sesja',
				'status'    => 'published',
				'tenant_id' => 999,
			)
		);

		$row = $useCase->galleries->findByPublicId( Ulid::fromString( $result->value['id'] ) );

		// Status zmienia się wyłącznie publikacją, a tenant nigdy.
		$this->assertSame( 'draft', (string) $row['status'] );
		$this->assertSame( 1, (int) $row['tenant_id'] );
	}

	public function testGalleryWithoutPhotosCannotBePublished(): void {
		$useCase = $this->useCase( 'studio' );
		$created = $useCase->handle->create( array( 'title' => 'Sesja' ) );
		$id      = Ulid::fromString( $created->value['id'] );

		$result = $useCase->handle->publish( $id, 0 );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_gallery_empty', $result->code );
		$this->assertSame( 'draft', (string) $useCase->galleries->findByPublicId( $id )['status'] );
	}

	public function testGalleryWithPhotosIsPublished(): void {
		$useCase = $this->useCase( 'studio' );
		$created = $useCase->handle->create( array( 'title' => 'Sesja' ) );
		$id      = Ulid::fromString( $created->value['id'] );

		$this->assertTrue( $useCase->handle->publish( $id, 42 )->ok );

		$row = $useCase->galleries->findByPublicId( $id );

		$this->assertSame( 'published', (string) $row['status'] );
		$this->assertTrue( null !== $row['published_at'] );
	}

	public function testForeignGalleryIsNotFound(): void {
		$db = TestDatabase::migrated();

		$mine   = $this->useCaseOn( $db, 1, 'studio' );
		$theirs = $this->useCaseOn( $db, 2, 'studio' );

		$created = $theirs->handle->create( array( 'title' => 'Cudza galeria' ) );
		$id      = Ulid::fromString( $created->value['id'] );

		// Znając dokładny identyfikator, obcy tenant dostaje 404, nie 403 —
		// nie potwierdzamy istnienia cudzych danych.
		$this->assertSame( 'kadr_not_found', $mine->handle->update( $id, array( 'title' => 'Przejęte' ) )->code );
		$this->assertSame( 'kadr_not_found', $mine->handle->publish( $id, 10 )->code );
	}

	public function testTitleIsRequiredOnCreateButOptionalOnUpdate(): void {
		$useCase = $this->useCase( 'studio' );

		$this->assertTrue( $useCase->handle->create( array() )->isFailure() );

		$created = $useCase->handle->create( array( 'title' => 'Sesja' ) );
		$id      = Ulid::fromString( $created->value['id'] );

		// Zmiana samego motywu nie wymaga przesyłania tytułu z powrotem.
		$this->assertTrue( $useCase->handle->update( $id, array( 'theme' => 'noir' ) )->ok );
		$this->assertSame( 'noir', (string) $useCase->galleries->findByPublicId( $id )['theme'] );
	}

	public function testUnknownThemeIsRejected(): void {
		$useCase = $this->useCase( 'studio' );
		$created = $useCase->handle->create( array( 'title' => 'Sesja' ) );

		$result = $useCase->handle->update(
			Ulid::fromString( $created->value['id'] ),
			array( 'theme' => 'neon' )
		);

		$this->assertTrue( $result->isFailure() );
		$this->assertTrue( isset( $result->details['params']['theme'] ) );
	}

	private function useCase( string $plan ): object {
		return $this->useCaseOn( TestDatabase::migrated(), 1, $plan );
	}

	private function useCaseOn( object $db, int $tenantId, string $plan ): object {
		$tenant    = TestDatabase::tenant( $tenantId );
		$galleries = new GalleryRepository( $db, $tenant );

		return new class( $galleries, new ManageGalleries(
			$galleries,
			new ClientRepository( $db, $tenant ),
			new AssetRepository( $db, $tenant ),
			new Entitlements( PlanRegistry::get( $plan ) )
		) ) {
			public function __construct(
				public GalleryRepository $galleries,
				public ManageGalleries $handle,
			) {}
		};
	}
}
