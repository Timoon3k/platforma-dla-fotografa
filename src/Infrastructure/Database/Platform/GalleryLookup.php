<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Platform;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Odnalezienie galerii po tokenie z publicznego adresu `/g/{token}`.
 *
 * TO JEST ŚWIADOME WYJŚCIE POZA TENANTA i jedyne w całej ścieżce klienta.
 *
 * Powód jest nieusuwalny: klientka otwiera link, nie będąc nikim zalogowanym.
 * Nie ma sesji, nie ma konta, nie ma tenanta — jedyne, co przynosi, to token.
 * Ktoś musi zamienić ten token na tenanta, zanim reszta kodu ruszy.
 *
 * Dlatego klasa leży w `Platform\`, tak jak `TenantStore`, i robi dokładnie
 * jedną rzecz: **zamienia hash tokenu na identyfikator tenanta i galerii**.
 * Nie czyta ani jednego zdjęcia, ani jednego klienta, ani jednego ustawienia —
 * od tego momentu wszystko idzie przez zwykłe repozytoria z `TenantContext`
 * zbudowanym z tego, co tu znalazła.
 *
 * Wyszukiwanie jest po `token_hash`, który jest UNIQUE globalnie i ma
 * 256 bitów entropii. Nie da się go zgadnąć ani przeszukać.
 */
final readonly class GalleryLookup {

	public function __construct( private Database $db ) {}

	/**
	 * @return array{tenant_id: int, gallery_id: int, access_id: int}|null
	 */
	public function byTokenHash( string $tokenHash ): ?array {
		$row = $this->db->selectOne(
			sprintf(
				'SELECT id, tenant_id, gallery_id FROM `%s` WHERE token_hash = ? LIMIT 1',
				$this->db->table( Tables::GALLERY_ACCESS )
			),
			array( $tokenHash )
		);

		if ( null === $row ) {
			return null;
		}

		return array(
			'tenant_id'  => (int) $row['tenant_id'],
			'gallery_id' => (int) $row['gallery_id'],
			'access_id'  => (int) $row['id'],
		);
	}
}
