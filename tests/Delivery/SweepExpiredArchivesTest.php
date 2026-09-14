<?php
declare( strict_types=1 );

namespace Kadr\Tests\Delivery;

use Kadr\Application\Delivery\SweepExpiredArchives;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Storage\LocalStorage;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Sprzątanie wygasłych paczek.
 *
 * To jest pozycja na rachunku, nie higiena: paczka wesela waży 30–80 GB,
 * a fotograf ślubny robi trzydzieści wesel w sezonie.
 */
final class SweepExpiredArchivesTest extends TestCase {

	public function testAnExpiredArchiveLosesItsFileAndItsRow(): void {
		$w = $this->world();
		$id = $w->archive( '-1 hour' );

		$this->assertTrue( $w->fileExists() );

		$result = $w->sweep->run();

		$this->assertSame( 1, $result['removed'] );
		$this->assertFalse( $w->fileExists() );
		$this->assertNull( $w->archives->findByPublicId( $id ) );
	}

	/**
	 * Paczka, która jeszcze żyje, zostaje. Skasowanie jej w trakcie
	 * pobierania to najgorsza możliwa niespodzianka.
	 */
	public function testAnArchiveThatIsStillValidIsLeftAlone(): void {
		$w = $this->world();
		$id = $w->archive( '+12 hours' );

		$this->assertSame( 0, $w->sweep->run()['removed'] );
		$this->assertNotNull( $w->archives->findByPublicId( $id ) );
		$this->assertTrue( $w->fileExists() );
	}

	/**
	 * Kolejność: najpierw odebranie dostępu, potem kasowanie pliku.
	 * Odwrotnie powstałoby okno, w którym czynny token wskazuje na
	 * nieistniejący plik — klientka dostałaby błąd zamiast „link wygasł".
	 */
	public function testTokensAreRevokedSoNoLinkOutlivesItsFile(): void {
		$w = $this->world();
		$w->archive( '-1 hour' );
		$w->issueToken();

		$w->sweep->run();

		$token = $w->tokens->forGallery( $w->galleryRowId )[0];

		$this->assertNotNull( $token['revoked_at'] );
	}

	/**
	 * Plik zniknął spod nas — wiersz i tak musi odejść. Zostawienie go
	 * dałoby paczkę, która wygląda na gotową, a nie ma czego pobrać.
	 */
	public function testAMissingFileStillRemovesTheRow(): void {
		$w = $this->world();
		$id = $w->archive( '-1 hour' );
		$w->deleteFile();

		$this->assertSame( 1, $w->sweep->run()['removed'] );
		$this->assertNull( $w->archives->findByPublicId( $id ) );
	}

	/**
	 * Izolacja tenantów: sprzątaczka jednego fotografa nie dotyka
	 * paczek drugiego.
	 */
	public function testSweepingDoesNotTouchAnotherPhotographersArchives(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 1, 'moja' );
		$other = $this->worldOn( $db, 2, 'cudza' );

		$mine->archive( '-1 hour' );
		$otherId = $other->archive( '-1 hour' );

		$this->assertSame( 1, $mine->sweep->run()['removed'] );
		$this->assertNotNull( $other->archives->findByPublicId( $otherId ) );
	}

	private function world(): object {
		return $this->worldOn( TestDatabase::migrated(), 1 );
	}

	private function worldOn( mixed $db, int $tenantId, string $slug = 'wesele' ): object {
		$base      = sys_get_temp_dir() . '/kadr-sweep-' . bin2hex( random_bytes( 6 ) );
		$storage   = new LocalStorage( $base, 'https://example.test/d' );
		$tenant    = TestDatabase::tenant( $tenantId );
		$galleries = new GalleryRepository( $db, $tenant );
		$archives  = new ArchiveRepository( $db, $tenant );
		$tokens    = new DownloadTokenRepository( $db, $tenant );

		$gallery = $galleries->create( 'Wesele', $slug );
		$row     = $galleries->findByPublicId( $gallery );
		$path    = StoragePath::archive( $tenantId, $gallery, 'selected' );

		return new class(
			(int) $row['id'],
			$path,
			$storage,
			$archives,
			$tokens,
			new SweepExpiredArchives( $archives, $tokens, $storage )
		) {
			public function __construct(
				public int $galleryRowId,
				public StoragePath $path,
				public LocalStorage $storage,
				public ArchiveRepository $archives,
				public DownloadTokenRepository $tokens,
				public SweepExpiredArchives $sweep,
			) {}

			public function archive( string $when ): Ulid {
				$this->storage->put( $this->path, 'udawana-paczka' );

				$id = $this->archives->request(
					$this->galleryRowId,
					ArchiveScope::Selected,
					3,
					(string) $this->path
				);

				$this->archives->markReady( $id, 14, gmdate( 'Y-m-d H:i:s', strtotime( $when ) ) );

				return $id;
			}

			public function issueToken(): void {
				$this->tokens->create(
					hash( 'sha256', 'token-' . $this->galleryRowId ),
					'zip',
					gmdate( 'Y-m-d H:i:s', time() + 3600 ),
					$this->galleryRowId,
					null,
					(string) $this->path
				);
			}

			public function fileExists(): bool {
				return $this->storage->exists( $this->path );
			}

			public function deleteFile(): void {
				$this->storage->delete( $this->path );
			}
		};
	}
}
