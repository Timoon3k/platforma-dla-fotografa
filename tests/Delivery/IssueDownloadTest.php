<?php
declare( strict_types=1 );

namespace Kadr\Tests\Delivery;

use Kadr\Application\Delivery\IssueDownload;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Domain\Shared\FrozenClock;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Linki do pobrania plików.
 *
 * Te testy pilnują obietnicy, którą składamy klientkom fotografa: prywatne
 * zdjęcia ich rodzin nie leżą pod adresem, który da się zgadnąć, a link,
 * który gdzieś wyciekł, da się unieważnić i tak czy owak zgaśnie sam.
 */
final class IssueDownloadTest extends TestCase {

	public function testIssuingGivesThePlainTokenExactlyOnce(): void {
		$world = $this->world();
		$world->readyArchive();

		$result = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything );

		$this->assertTrue( $result->ok );
		$this->assertTrue( strlen( $result->value['token'] ) > 20 );

		// W bazie leży wyłącznie hash — jawnej wartości nie da się odtworzyć.
		$rows = $world->tokens->forGallery( $world->galleryRowId );

		$this->assertSame( 1, count( $rows ) );
		$this->assertSame( SecureToken::hash( $result->value['token'] ), $rows[0]['token_hash'] );
		$this->assertTrue( ! str_contains( json_encode( $rows[0] ), $result->value['token'] ) );
	}

	public function testAValidTokenOpensTheArchive(): void {
		$world = $this->world();
		$world->readyArchive();

		$token = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];

		$redeemed = $world->issue->redeem( $token );

		$this->assertTrue( $redeemed->ok );
		$this->assertTrue( str_contains( $redeemed->value['storage_path'], '.zip' ) );
	}

	/**
	 * Token pobrania żyje 24 godziny, nie 90 dni jak link do galerii.
	 */
	public function testATokenStopsWorkingAfterTwentyFourHours(): void {
		$world = $this->world();
		$world->readyArchive();

		$token = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];

		$world->clock->advance( '+25 hours' );

		$this->assertFalse( $world->issue->redeem( $token )->ok );
	}

	public function testARevokedTokenStopsWorkingImmediately(): void {
		$world = $this->world();
		$world->readyArchive();

		$token = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];

		$world->tokens->revoke( (int) $world->tokens->forGallery( $world->galleryRowId )[0]['id'] );

		$this->assertFalse( $world->issue->redeem( $token )->ok );
	}

	/**
	 * Wycofanie publikacji ma zamykać wszystkie linki jednym ruchem —
	 * fotograf ma jeden przełącznik, nie listę do odhaczenia.
	 */
	public function testRevokingTheGalleryClosesEveryDownloadLink(): void {
		$world = $this->world();
		$world->readyArchive();

		$first  = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];
		$second = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];

		$world->tokens->revokeForGallery( $world->galleryRowId );

		$this->assertFalse( $world->issue->redeem( $first )->ok );
		$this->assertFalse( $world->issue->redeem( $second )->ok );
	}

	/**
	 * Każdy powód odmowy daje TEN SAM komunikat. Rozróżnianie ich mówiłoby
	 * zgadującemu, że trafił w istniejący link.
	 */
	public function testEveryRefusalLooksTheSameFromOutside(): void {
		$world = $this->world();
		$world->readyArchive();

		$token = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];
		$world->tokens->revoke( (int) $world->tokens->forGallery( $world->galleryRowId )[0]['id'] );

		$messages = array(
			$world->issue->redeem( $token )->message,
			$world->issue->redeem( 'zupelnie-wymyslony-token-ktory-nie-istnieje' )->message,
			$world->issue->redeem( '' )->message,
		);

		$this->assertSame( 1, count( array_unique( $messages ) ) );
	}

	/**
	 * Pobieranie 60 GB przez 4G się rwie. Limit na jedno użycie zamieniłby
	 * zwykłą niedogodność w utratę dostępu do własnych zdjęć.
	 */
	public function testDownloadingTwiceIsAllowed(): void {
		$world = $this->world();
		$world->readyArchive();

		$token = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];

		$this->assertTrue( $world->issue->redeem( $token )->ok );
		$this->assertTrue( $world->issue->redeem( $token )->ok );
	}

	public function testAnArchiveThatIsNotReadyCannotBeHandedOut(): void {
		$world = $this->world();
		$world->pendingArchive();

		$result = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_archive_not_ready', $result->code );
	}

	public function testWithoutAnArchiveThereIsNothingToHandOut(): void {
		$world = $this->world();

		$result = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_archive_missing', $result->code );
	}

	/**
	 * Wydanie i użycie linku trafiają do dziennika (docs/SECURITY.md §6) —
	 * ale adres IP wyłącznie jako hash.
	 */
	public function testIssuingAndUsingAreAuditedWithoutStoringTheAddress(): void {
		$world = $this->world();
		$world->readyArchive();

		$token = $world->issue->forArchive( $world->gallery, ArchiveScope::Everything )->value['token'];
		$world->issue->redeem( $token, hash( 'sha256', '198.51.100.7' ) );

		$rows = $world->audit->latest();
		$actions = array_map( static fn( array $r ): string => (string) $r['action'], $rows );

		$this->assertTrue( in_array( 'download_token.issued', $actions, true ) );
		$this->assertTrue( in_array( 'download_token.used', $actions, true ) );

		$dump = json_encode( $rows );

		$this->assertTrue( ! str_contains( $dump, '198.51.100.7' ) );
		$this->assertTrue( ! str_contains( $dump, $token ) );
	}

	/**
	 * Izolacja tenantów (ryzyko R2): token drugiego fotografa nie istnieje.
	 */
	public function testATokenFromAnotherPhotographerIsNotFound(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 1, 'moja' );
		$other = $this->worldOn( $db, 2, 'cudza' );

		$other->readyArchive();
		$token = $other->issue->forArchive( $other->gallery, ArchiveScope::Everything )->value['token'];

		$this->assertFalse( $mine->issue->redeem( $token )->ok );
	}

	private function world(): object {
		return $this->worldOn( TestDatabase::migrated(), 1 );
	}

	private function worldOn( mixed $db, int $tenantId, string $slug = 'wesele' ): object {
		$tenant    = TestDatabase::tenant( $tenantId );
		$galleries = new GalleryRepository( $db, $tenant );
		$archives  = new ArchiveRepository( $db, $tenant );
		$tokens    = new DownloadTokenRepository( $db, $tenant );
		$audit     = new AuditLogRepository( $db, $tenant );
		$clock     = new FrozenClock( '2026-04-01 10:00:00' );

		$gallery = $galleries->create( 'Ślub Marty', $slug );
		$row     = $galleries->findByPublicId( $gallery );

		return new class(
			$gallery,
			(int) $row['id'],
			$archives,
			$tokens,
			$audit,
			$clock,
			new IssueDownload( $galleries, $archives, $tokens, $audit, $clock )
		) {
			public function __construct(
				public Ulid $gallery,
				public int $galleryRowId,
				public ArchiveRepository $archives,
				public DownloadTokenRepository $tokens,
				public AuditLogRepository $audit,
				public FrozenClock $clock,
				public IssueDownload $issue,
			) {}

			public function readyArchive(): void {
				$id = $this->archives->request(
					$this->galleryRowId,
					ArchiveScope::Everything,
					10,
					'finals/1/' . $this->gallery . '/everything.zip'
				);

				$this->archives->markReady( $id, 1024, gmdate( 'Y-m-d H:i:s', time() + 86400 ) );
			}

			public function pendingArchive(): void {
				$this->archives->request(
					$this->galleryRowId,
					ArchiveScope::Everything,
					10,
					'finals/1/' . $this->gallery . '/everything.zip'
				);
			}
		};
	}
}
