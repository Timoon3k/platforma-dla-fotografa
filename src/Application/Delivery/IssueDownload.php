<?php
declare( strict_types=1 );

namespace Kadr\Application\Delivery;

use Kadr\Domain\Audit\AuditEvent;
use Kadr\Domain\Delivery\ArchiveEntry;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Shared\Clock;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * Wydanie i zrealizowanie linku do pobrania paczki.
 *
 * Link istnieje w jawnej postaci dokładnie raz — w chwili wydania. W bazie
 * leży sam hash (docs/SECURITY.md §3), więc wyciek bazy nie daje działających
 * linków do cudzych zdjęć rodzinnych.
 *
 * Token pobrania żyje 24 godziny, nie 90 dni jak link do galerii. Link do
 * galerii ma służyć wracaniu i oglądaniu; ten ma wystarczyć na jedno
 * ściągnięcie plików i zgasnąć.
 */
final readonly class IssueDownload {

	public function __construct(
		private GalleryRepository $galleries,
		private ArchiveRepository $archives,
		private DownloadTokenRepository $tokens,
		private AuditLogRepository $audit,
		private Clock $clock,
	) {}

	/**
	 * Wydanie linku do gotowej paczki.
	 *
	 * @return Result Wartość zawiera `token` — jedyny moment, w którym
	 *                jawna wartość w ogóle istnieje.
	 */
	public function forArchive( Ulid $galleryId, ArchiveScope $scope, string $actorType = 'user' ): Result {
		$gallery = $this->galleries->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono.' );
		}

		$archive = $this->archives->forGallery( (int) $gallery['id'], $scope );

		if ( null === $archive ) {
			return Result::failure( 'kadr_archive_missing', 'Ta paczka nie została jeszcze przygotowana.' );
		}

		if ( 'ready' !== (string) $archive['status'] ) {
			return Result::failure(
				'kadr_archive_not_ready',
				'packing' === (string) $archive['status']
					? 'Pliki jeszcze się pakują. Damy znać, gdy będą gotowe.'
					: 'Pliki nie są jeszcze gotowe.'
			);
		}

		$token   = SecureToken::generate();
		$expires = $this->clock->now()->modify(
			sprintf( '+%d hours', AccessGrant::downloadLifetimeHours() )
		);

		$this->tokens->create(
			$token->hash,
			'zip',
			$expires->format( 'Y-m-d H:i:s' ),
			(int) $gallery['id'],
			null,
			(string) $archive['storage_path'],
			// Bez limitu użyć: pobieranie sześćdziesięciu gigabajtów przez 4G
			// rwie się i klientka zaczyna od nowa. Limit na jedno użycie
			// zamieniłby zwykłą niedogodność w utratę dostępu.
			null
		);

		$this->audit->record(
			AuditEvent::DownloadTokenIssued,
			$actorType,
			null,
			'gallery',
			(string) $galleryId,
			array( 'scope' => $scope->value )
		);

		return Result::success(
			array(
				'token'      => $token->plain,
				'expires_at' => $expires->format( 'Y-m-d H:i:s' ),
				'bytes'      => (int) $archive['bytes'],
				'filename'   => ArchiveEntry::archiveName( (string) $gallery['title'], $scope ),
			)
		);
	}

	/**
	 * Sprawdzenie linku przedstawionego przy pobraniu.
	 *
	 * Każdy powód odmowy — zły token, wygaśnięcie, unieważnienie, wyczerpany
	 * limit — daje TEN SAM wynik. Rozróżnianie ich mówiłoby zgadującemu,
	 * że trafił w istniejący link (docs/SECURITY.md §1.9).
	 */
	public function redeem( string $plainToken, ?string $ipHash = null ): Result {
		if ( ! SecureToken::looksValid( $plainToken ) ) {
			return Result::failure( 'kadr_not_found', 'Ten link już nie działa.' );
		}

		$row = $this->tokens->findByTokenHash( SecureToken::hash( $plainToken ) );

		if ( null === $row ) {
			return Result::failure( 'kadr_not_found', 'Ten link już nie działa.' );
		}

		$grant = AccessGrant::fromRow( $row );

		if ( ! $grant->isUsableAt( $this->clock ) ) {
			return Result::failure( 'kadr_not_found', 'Ten link już nie działa.' );
		}

		$path = (string) ( $row['storage_path'] ?? '' );

		if ( '' === $path ) {
			return Result::failure( 'kadr_not_found', 'Ten link już nie działa.' );
		}

		$this->tokens->recordUse( (int) $row['id'] );

		$this->audit->record(
			AuditEvent::DownloadTokenUsed,
			'client',
			null,
			'gallery',
			null === $row['gallery_id'] ? null : (string) $row['gallery_id'],
			array( 'scope' => (string) $row['scope'] ),
			// Adres wyłącznie jako hash: wystarczy, żeby zobaczyć „to samo
			// źródło pobrało sto razy", nie wystarczy, żeby wskazać osobę.
			$ipHash
		);

		return Result::success(
			array(
				'storage_path' => $path,
				'scope'        => (string) $row['scope'],
				'gallery_id'   => $row['gallery_id'],
			)
		);
	}
}
