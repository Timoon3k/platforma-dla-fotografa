<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Storage;

use Kadr\Domain\Storage\StorageFailure;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Storage\StorageProvider;

/**
 * Magazyn na dysku lokalnym.
 *
 * Katalog bazowy leży POZA `wp-content/uploads` i jest niedostępny przez
 * serwer WWW (docs/SECURITY.md §3). Zapis pliku `.htaccess` chroni instalacje
 * na Apache; na nginx ochroną jest brak mapowania katalogu, co opisuje
 * dokumentacja wdrożeniowa.
 *
 * Zapis jest atomowy: najpierw plik tymczasowy, potem `rename()`. Przerwane
 * wysyłanie nie zostawia uszkodzonego obiektu, który wyglądałby na poprawny.
 */
final class LocalStorage implements StorageProvider {

	public function __construct(
		private readonly string $basePath,
		private readonly string $downloadEndpoint = '',
	) {}

	public function put( StoragePath $path, mixed $contents ): void {
		$target    = $this->absolute( $path );
		$directory = dirname( $target );

		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0o755, true ) && ! is_dir( $directory ) ) {
			throw StorageFailure::writeFailed( $path, 'Nie udało się utworzyć katalogu.' );
		}

		$this->protectDirectory();

		// Zapis atomowy — plik pojawia się pod docelową nazwą dopiero kompletny.
		$temporary = $target . '.' . bin2hex( random_bytes( 8 ) ) . '.part';
		$handle    = fopen( $temporary, 'wb' );

		if ( false === $handle ) {
			throw StorageFailure::writeFailed( $path, 'Nie udało się otworzyć pliku tymczasowego.' );
		}

		try {
			if ( is_resource( $contents ) ) {
				if ( false === stream_copy_to_stream( $contents, $handle ) ) {
					throw StorageFailure::writeFailed( $path, 'Przerwane kopiowanie strumienia.' );
				}
			} else {
				if ( false === fwrite( $handle, (string) $contents ) ) {
					throw StorageFailure::writeFailed( $path, 'Przerwany zapis.' );
				}
			}
		} finally {
			fclose( $handle );
		}

		if ( ! rename( $temporary, $target ) ) {
			@unlink( $temporary );
			throw StorageFailure::writeFailed( $path, 'Nie udało się sfinalizować zapisu.' );
		}

		chmod( $target, 0o644 );
	}

	public function readStream( StoragePath $path ): mixed {
		$target = $this->absolute( $path );

		if ( ! is_file( $target ) ) {
			throw StorageFailure::notFound( $path );
		}

		$handle = fopen( $target, 'rb' );

		if ( false === $handle ) {
			throw StorageFailure::notFound( $path );
		}

		return $handle;
	}

	public function delete( StoragePath $path ): void {
		$target = $this->absolute( $path );

		if ( ! is_file( $target ) ) {
			return; // Usunięcie nieistniejącego obiektu nie jest błędem.
		}

		if ( ! unlink( $target ) ) {
			throw StorageFailure::deleteFailed( $path );
		}
	}

	public function exists( StoragePath $path ): bool {
		return is_file( $this->absolute( $path ) );
	}

	public function size( StoragePath $path ): int {
		$target = $this->absolute( $path );

		if ( ! is_file( $target ) ) {
			throw StorageFailure::notFound( $path );
		}

		return (int) filesize( $target );
	}

	/**
	 * Dysk lokalny nie ma podpisanych adresów, więc kierujemy na kontrolowany
	 * endpoint pobrania. Token i jego wygaśnięcie obsługuje warstwa wyżej —
	 * tutaj nie ma miejsca, w którym plik wyciekłby bezpośrednio.
	 */
	public function temporaryUrl( StoragePath $path, int $ttlSeconds ): string {
		if ( '' === $this->downloadEndpoint ) {
			throw StorageFailure::writeFailed( $path, 'Nie skonfigurowano endpointu pobrania.' );
		}

		return rtrim( $this->downloadEndpoint, '/' ) . '/' . ltrim( (string) $path, '/' );
	}

	public function deletePrefix( string $prefix ): int {
		$directory = $this->absolute( StoragePath::fromString( rtrim( $prefix, '/' ) . '/.keep' ) );
		$directory = dirname( $directory );

		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$removed = 0;
		$items   = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
				continue;
			}

			if ( unlink( $item->getPathname() ) ) {
				++$removed;
			}
		}

		@rmdir( $directory );

		return $removed;
	}

	public function localPath( StoragePath $path ): ?string {
		$target = $this->absolute( $path );

		return is_file( $target ) ? $target : null;
	}

	public function basePath(): string {
		return $this->basePath;
	}

	/**
	 * Ścieżka bezwzględna z ponowną kontrolą wyjścia poza katalog bazowy.
	 *
	 * `StoragePath` już to waliduje, ale to jest ostatnia bariera przed
	 * systemem plików i kosztuje jedno porównanie ciągów.
	 */
	private function absolute( StoragePath $path ): string {
		$base   = rtrim( $this->basePath, '/' );
		$target = $base . '/' . ltrim( (string) $path, '/' );

		if ( ! str_starts_with( $target, $base . '/' ) ) {
			throw StorageFailure::writeFailed( $path, 'Ścieżka wychodzi poza magazyn.' );
		}

		return $target;
	}

	/**
	 * Blokada listowania i serwowania przez Apache.
	 *
	 * Na nginx ochroną jest brak mapowania tego katalogu w konfiguracji —
	 * opisane w dokumentacji wdrożeniowej.
	 */
	private function protectDirectory(): void {
		$guard = rtrim( $this->basePath, '/' ) . '/.htaccess';

		if ( is_file( $guard ) ) {
			return;
		}

		if ( ! is_dir( $this->basePath ) ) {
			mkdir( $this->basePath, 0o755, true );
		}

		file_put_contents(
			$guard,
			"# Pliki Kadr nie są serwowane bezpośrednio.\n"
			. "Require all denied\n"
			. "<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n"
			. "Options -Indexes\n"
		);
	}
}
