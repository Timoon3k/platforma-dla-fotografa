<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Tokeny pobrania plików.
 *
 * Prywatne zdjęcie nigdy nie leży pod przewidywalnym publicznym adresem
 * (CLAUDE.md §5). Plik jest poza `wp-content/uploads`, serwer WWW go nie widzi,
 * a jedyną drogą jest `/d/{token}` — który najpierw sprawdza ten wiersz.
 *
 * W bazie leży wyłącznie HASH tokenu. Jawna wartość istnieje raz, w chwili
 * wydania linku; z bazy nie da się jej odtworzyć. To jest cecha, nie
 * niedogodność: wyciek bazy nie daje działających linków do cudzych zdjęć.
 *
 * Token pobrania ma KRÓTSZY czas życia niż link do galerii. Link do galerii
 * żyje tygodniami, bo klientka wraca oglądać. Token pobrania ma wystarczyć
 * na jedno ściągnięcie plików.
 */
final class DownloadTokenRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::byName( Tables::DOWNLOAD_TOKENS );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByTokenHash( string $tokenHash ): ?array {
		return $this->findOneBy( array( 'token_hash' => $tokenHash ) );
	}

	public function create(
		string $tokenHash,
		string $scope,
		string $expiresAt,
		?int $galleryId = null,
		?int $assetId = null,
		?string $storagePath = null,
		?int $maxUses = null
	): int {
		return $this->insertRow(
			array(
				'gallery_id'   => $galleryId,
				'asset_id'     => $assetId,
				'token_hash'   => $tokenHash,
				'scope'        => $scope,
				'storage_path' => $storagePath,
				'max_uses'     => $maxUses,
				'used_count'   => 0,
				'expires_at'   => $expiresAt,
			)
		);
	}

	public function recordUse( int $id ): int {
		$current = $this->findOneBy( array( 'id' => $id ) );

		if ( null === $current ) {
			return 0;
		}

		return $this->updateBy(
			array( 'id' => $id ),
			array( 'used_count' => (int) $current['used_count'] + 1 )
		);
	}

	public function revoke( int $id ): int {
		return $this->updateBy(
			array( 'id' => $id ),
			array( 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) )
		);
	}

	/**
	 * Unieważnienie wszystkich tokenów galerii naraz.
	 *
	 * Wycofanie publikacji ma zamykać dostęp jednym ruchem — fotograf ma
	 * jeden przełącznik, nie listę linków do odhaczenia.
	 */
	public function revokeForGallery( int $galleryId ): int {
		$revoked = 0;

		foreach ( $this->findAllBy( array( 'gallery_id' => $galleryId ) ) as $row ) {
			if ( null === $row['revoked_at'] ) {
				$revoked += $this->revoke( (int) $row['id'] );
			}
		}

		return $revoked;
	}

	/**
	 * Tokeny galerii, od najnowszego.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function forGallery( int $galleryId, int $limit = 20 ): array {
		return $this->findAllBy( array( 'gallery_id' => $galleryId ), 'id', 'DESC', $limit );
	}
}
