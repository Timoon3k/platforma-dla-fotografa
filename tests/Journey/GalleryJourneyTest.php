<?php
declare( strict_types=1 );

namespace Kadr\Tests\Journey;

use Kadr\Application\Journey\GalleryJourney;
use Kadr\Application\Selection\SelectionRoom;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Journey\Stage;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Oś procesu na prawdziwych danych.
 *
 * `TimelineTest` sprawdza reguły na faktach podanych z ręki. Ten test
 * przeprowadza SESJĘ przez wszystkie etapy na prawdziwym SQL-u i pilnuje,
 * że oś czyta to, co naprawdę się stało — bo właśnie tam kryją się pomyłki
 * w rodzaju „nie ta kolumna" albo „nie ta paczka".
 */
final class GalleryJourneyTest extends TestCase {

	public function testASessionWalksThroughEveryStage(): void {
		$w = $this->world();

		// Pusta galeria: stoimy na samym początku.
		$this->assertSame( Stage::Prepared, $w->current() );

		$w->addPhotos( 6 );
		$this->assertSame( Stage::Shared, $w->current() );

		// Publikacja bez linku nie przesuwa osi — klientka nadal nie ma adresu.
		$w->publish();
		$this->assertSame( Stage::Shared, $w->current() );

		$w->issueLink();
		$this->assertSame( Stage::Opened, $w->current() );

		$w->clientOpensGallery();
		$this->assertSame( Stage::Choosing, $w->current() );

		$w->clientMarks( 3 );
		$this->assertSame( Stage::Chosen, $w->current() );

		$w->clientSubmits();
		$this->assertSame( Stage::Packed, $w->current() );

		$w->archiveReady();
		$this->assertSame( Stage::Delivered, $w->current() );

		$w->clientDownloads();
		$this->assertTrue( $w->summary()['complete'] );
	}

	/**
	 * Unieważnienie linku odbiera dostęp, ale nie wymazuje tego, że klientka
	 * już galerię otworzyła. Oś opowiada historię, a nie bieżące uprawnienia.
	 */
	public function testRevokingALinkDoesNotErasePastEvents(): void {
		$w = $this->world();
		$w->addPhotos( 3 );
		$w->publish();
		$w->issueLink();
		$w->clientOpensGallery();

		$w->revokeLinks();

		$steps = $w->summary()['steps'];

		$this->assertTrue( $this->done( $steps, Stage::Opened ) );
		// Ale „link wysłany" już nie jest prawdą — nie ma czynnego linku.
		$this->assertFalse( $this->done( $steps, Stage::Shared ) );
	}

	/**
	 * Paczka „cała galeria" to zwykle kopia dla fotografa. Na klientkę czeka
	 * paczka z WYBRANYMI zdjęciami i to ona przesuwa oś.
	 */
	public function testOnlyTheSelectedScopeArchiveMovesTheJourney(): void {
		$w = $this->world();
		$w->addPhotos( 4 );
		$w->publish();
		$w->issueLink();
		$w->clientOpensGallery();
		$w->clientMarks( 2 );
		$w->clientSubmits();

		$w->archiveReady( ArchiveScope::Everything );
		$this->assertSame( Stage::Packed, $w->current() );

		$w->archiveReady( ArchiveScope::Selected );
		$this->assertSame( Stage::Delivered, $w->current() );
	}

	/**
	 * Serduszko też liczy się jako „wybór w toku" — klientka zaczęła
	 * podejmować decyzje, nawet jeśli jeszcze niczego nie wybrała na stałe.
	 */
	public function testAFavouriteCountsAsChoosingToo(): void {
		$w = $this->world();
		$w->addPhotos( 3 );
		$w->publish();
		$w->issueLink();
		$w->clientOpensGallery();

		$w->clientFavourites( 1 );

		$this->assertTrue( $this->done( $w->summary()['steps'], Stage::Choosing ) );
	}

	/**
	 * Izolacja tenantów: oś cudzej galerii nie istnieje.
	 */
	public function testAnotherPhotographersGalleryHasNoJourneyHere(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 1, 'moja' );
		$other = $this->worldOn( $db, 2, 'cudza' );

		$other->addPhotos( 5 );
		$other->publish();
		$other->issueLink();

		// Fotograf 1 pyta o oś galerii fotografa 2, znając jej wiersz.
		$steps = $mine->journey->forGallery( $other->galleryRow() );

		// Nic nie wyciekło: z perspektywy tenanta 1 ta galeria jest pusta.
		$this->assertFalse( $this->done( $steps, Stage::Prepared ) );
		$this->assertFalse( $this->done( $steps, Stage::Shared ) );
	}

	/**
	 * @param list<\Kadr\Domain\Journey\Step> $steps
	 */
	private function done( array $steps, Stage $stage ): bool {
		foreach ( $steps as $step ) {
			if ( $step->stage === $stage ) {
				return $step->isDone();
			}
		}

		return false;
	}

	private function world(): object {
		return $this->worldOn( TestDatabase::migrated(), 1 );
	}

	private function worldOn( mixed $db, int $tenantId, string $slug = 'wesele' ): object {
		$tenant     = TestDatabase::tenant( $tenantId );
		$galleries  = new GalleryRepository( $db, $tenant );
		$assets     = new AssetRepository( $db, $tenant );
		$access     = new GalleryAccessRepository( $db, $tenant );
		$selections = new SelectionRepository( $db, $tenant );
		$items      = new SelectionItemRepository( $db, $tenant );
		$archives   = new ArchiveRepository( $db, $tenant );
		$tokens     = new DownloadTokenRepository( $db, $tenant );

		$gallery = $galleries->create( 'Ślub Marty', $slug );

		return new class(
			$gallery,
			$galleries,
			$assets,
			$access,
			$archives,
			$tokens,
			new SelectionRoom( $galleries, $assets, $selections, $items ),
			new GalleryJourney( $assets, $access, $selections, $items, $archives, $tokens )
		) {
			/** @var list<Ulid> */
			public array $photos = array();

			public function __construct(
				public Ulid $gallery,
				public GalleryRepository $galleries,
				public AssetRepository $assets,
				public GalleryAccessRepository $access,
				public ArchiveRepository $archives,
				public DownloadTokenRepository $tokens,
				public SelectionRoom $room,
				public GalleryJourney $journey,
			) {}

			/**
			 * @return array<string, mixed>
			 */
			public function galleryRow(): array {
				return $this->galleries->findByPublicId( $this->gallery );
			}

			/**
			 * @return array{steps: list<\Kadr\Domain\Journey\Step>, current: \Kadr\Domain\Journey\Step, complete: bool}
			 */
			public function summary(): array {
				return $this->journey->summary( $this->galleryRow() );
			}

			public function current(): Stage {
				return $this->summary()['current']->stage;
			}

			public function addPhotos( int $count ): void {
				$row = $this->galleryRow();

				for ( $i = 0; $i < $count; $i++ ) {
					$this->photos[] = $this->assets->create(
						(int) $row['id'],
						array(
							'original_name' => "kadr-$i.jpg",
							'storage_path'  => "private/x/kadr-$i.jpg",
							'content_hash'  => hash( 'sha256', $this->gallery . "-$i" ),
							'bytes'         => 2048,
							'width'         => 3000,
							'height'        => 2000,
							'sort_order'    => $i,
							'status'        => 'ready',
						)
					);
				}
			}

			public function publish(): void {
				$this->galleries->publish( $this->gallery );
			}

			public function issueLink(): void {
				$this->access->create(
					(int) $this->galleryRow()['id'],
					SecureToken::generate()->hash,
					null,
					gmdate( 'Y-m-d H:i:s', time() + 86400 )
				);
			}

			public function revokeLinks(): void {
				foreach ( $this->access->forGallery( (int) $this->galleryRow()['id'] ) as $link ) {
					$this->access->revoke( (int) $link['id'] );
				}
			}

			public function clientOpensGallery(): void {
				$links = $this->access->forGallery( (int) $this->galleryRow()['id'] );
				$this->access->recordUse( (int) $links[0]['id'] );
			}

			public function clientMarks( int $count ): void {
				foreach ( array_slice( $this->photos, 0, $count ) as $photo ) {
					$this->room->mark( $this->gallery, $photo, SelectionState::Selected );
				}
			}

			public function clientFavourites( int $count ): void {
				foreach ( array_slice( $this->photos, 0, $count ) as $photo ) {
					$this->room->mark( $this->gallery, $photo, SelectionState::Favorite );
				}
			}

			public function clientSubmits(): void {
				$this->room->submit( $this->gallery );
			}

			public function archiveReady( ArchiveScope $scope = ArchiveScope::Selected ): void {
				$id = $this->archives->request( (int) $this->galleryRow()['id'], $scope, 3, 'finals/1/x/a.zip' );
				$this->archives->markReady( $id, 1024, gmdate( 'Y-m-d H:i:s', time() + 86400 ) );
			}

			public function clientDownloads(): void {
				$id = $this->tokens->create(
					hash( 'sha256', 'token-' . $this->gallery ),
					'zip',
					gmdate( 'Y-m-d H:i:s', time() + 3600 ),
					(int) $this->galleryRow()['id'],
					null,
					'finals/1/x/a.zip'
				);

				$this->tokens->recordUse( $id );
			}
		};
	}
}
