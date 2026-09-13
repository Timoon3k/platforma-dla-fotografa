<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Linki dostępowe do galerii.
 *
 * W bazie leży wyłącznie HASH tokenu (docs/SECURITY.md §3). Wyciek bazy nie
 * daje działających linków — a fotograf, który zgubił link, generuje nowy,
 * zamiast odczytywać stary.
 */
final class GalleryAccessRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::byName( Tables::GALLERY_ACCESS );
	}

	/**
	 * Linki jednej galerii, od najnowszego.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function forGallery( int $galleryId ): array {
		return $this->findAllBy( array( 'gallery_id' => $galleryId ), 'id', 'DESC', 20 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByTokenHash( string $tokenHash ): ?array {
		return $this->findOneBy( array( 'token_hash' => $tokenHash ) );
	}

	public function create(
		int $galleryId,
		string $tokenHash,
		?string $pinHash,
		?string $expiresAt,
		?int $maxUses = null
	): int {
		return $this->insertRow(
			array(
				'gallery_id'  => $galleryId,
				'access_type' => null === $pinHash ? 'link' : 'pin',
				'token_hash'  => $tokenHash,
				'pin_hash'    => $pinHash,
				'max_uses'    => $maxUses,
				'used_count'  => 0,
				'expires_at'  => $expiresAt,
			)
		);
	}

	/**
	 * Odnotowanie użycia linku.
	 *
	 * Liczba użyć rośnie po KAŻDYM otwarciu galerii, także przez tę samą
	 * osobę — limit użyć chroni przed rozesłaniem linku dalej, a nie przed
	 * powrotem klientki do własnych zdjęć. Dlatego domyślnie limitu nie ma.
	 */
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
}
