<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

/**
 * Awaria magazynu plików.
 *
 * Komunikat trafia do logu, nie do użytkownika — nie zdradzamy ścieżek
 * serwera ani nazw obiektów (docs/SECURITY.md §6).
 */
final class StorageFailure extends \RuntimeException {

	public static function notFound( StoragePath $path ): self {
		return new self( sprintf( 'Obiekt nie istnieje: %s', $path ) );
	}

	public static function writeFailed( StoragePath $path, string $reason = '' ): self {
		return new self( rtrim( sprintf( 'Nie udało się zapisać obiektu %s. %s', $path, $reason ) ) );
	}

	public static function deleteFailed( StoragePath $path ): self {
		return new self( sprintf( 'Nie udało się usunąć obiektu: %s', $path ) );
	}
}
