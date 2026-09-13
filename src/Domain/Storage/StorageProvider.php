<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

/**
 * Kontrakt magazynu plików (ADR-011).
 *
 * Celowo wąski. Aplikacja nigdy nie dotyka systemu plików ani klienta S3
 * bezpośrednio — dzięki temu przejście z dysku lokalnego na object storage
 * jest wymianą jednej implementacji, a nie przeszukiwaniem kodu.
 */
interface StorageProvider {

	/**
	 * @param resource|string $contents
	 *
	 * @throws StorageFailure
	 */
	public function put( StoragePath $path, mixed $contents ): void;

	/**
	 * @return resource
	 *
	 * @throws StorageFailure Gdy obiekt nie istnieje.
	 */
	public function readStream( StoragePath $path ): mixed;

	/**
	 * @throws StorageFailure
	 */
	public function delete( StoragePath $path ): void;

	public function exists( StoragePath $path ): bool;

	/**
	 * @throws StorageFailure Gdy obiekt nie istnieje.
	 */
	public function size( StoragePath $path ): int;

	/**
	 * Adres ważny przez ograniczony czas.
	 *
	 * Implementacja lokalna kieruje na kontrolowany endpoint pobrania,
	 * implementacja S3 zwraca podpisany URL. W obu wypadkach adres wygasa
	 * i nie da się go zgadnąć (docs/SECURITY.md §3).
	 */
	public function temporaryUrl( StoragePath $path, int $ttlSeconds ): string;

	/**
	 * Usuwa wszystkie obiekty pod prefiksem — np. przy kasowaniu galerii.
	 *
	 * @return int Liczba usuniętych obiektów.
	 */
	public function deletePrefix( string $prefix ): int;

	/**
	 * Ścieżka w systemie plików albo `null`, gdy magazyn nie jest lokalny.
	 *
	 * Jedyny przypadek, w którym ma to znaczenie, to pakowanie galerii:
	 * `ZipArchive` potrafi dopisać plik z dysku bez wciągania go do pamięci,
	 * a wesele to bywa osiemdziesiąt gigabajtów. Adapter zdalny (S3, ADR-017)
	 * zwróci `null`, a pakowanie przejdzie na strumień i plik tymczasowy —
	 * wolniej, ale poprawnie.
	 *
	 * Metoda NIE służy do obchodzenia interfejsu. Kod, który chce czytać
	 * zawartość, używa `readStream()`.
	 */
	public function localPath( StoragePath $path ): ?string;
}
