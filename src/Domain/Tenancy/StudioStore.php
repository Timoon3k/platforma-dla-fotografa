<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Zapis studia w chwili rejestracji.
 *
 * Interfejs istnieje, żeby warstwa aplikacji nie zależała od konkretnego
 * magazynu (CLAUDE.md §4) — `RegisterStudio` ma być testowalny bez bazy
 * i bez WordPressa. Celowo wąski: rejestracja nie potrzebuje niczego poza
 * sprawdzeniem sluga i utworzeniem studia.
 */
interface StudioStore {

	/**
	 * Czy slug studia jest już zajęty.
	 *
	 * Slug jest globalny, bo trafia do publicznych adresów rezerwacji.
	 */
	public function slugTaken( string $slug ): bool;

	/**
	 * @return array{id: int, public_id: string}
	 * @throws \RuntimeException gdy studia nie da się zapisać.
	 */
	public function createStudio( string $name, string $slug, string $contactEmail, int $ownerWpUserId ): array;
}
