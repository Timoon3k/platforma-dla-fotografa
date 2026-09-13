<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Delivery;

use Kadr\Domain\Delivery\ArchiveEntry;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Storage\StorageProvider;

/**
 * Dopisanie porcji zdjęć do paczki.
 *
 * TA KLASA ISTNIEJE, ŻEBY PAKOWANIE DAŁO SIĘ PRZERWAĆ I WZNOWIĆ.
 *
 * Wesele to 30–80 GB (skill photography-workflow §7). Spakowanie tego
 * w jednym przebiegu PHP nie ma prawa się udać: skończy się limitem czasu
 * wykonania, a fotograf zobaczy błąd po dwudziestu minutach czekania.
 * Dlatego zadanie dopisuje PORCJĘ, zamyka archiwum i wraca do kolejki —
 * a `ZipArchive` potrafi otworzyć istniejące archiwum i dopisać do niego.
 *
 * Dwie decyzje wydajnościowe:
 *
 *  1. **Bez kompresji.** JPEG i tak jest skompresowany; deflate wyciska
 *     z niego ułamek procenta, a kosztuje pełne przejście CPU po
 *     osiemdziesięciu gigabajtach. `CM_STORE` pakuje z prędkością dysku.
 *  2. **Plik z dysku, nie z pamięci.** `addFile()` czyta źródło dopiero przy
 *     zamknięciu archiwum, więc szczyt pamięci nie zależy od rozmiaru
 *     zdjęcia. Magazyn zdalny (S3) nie ma ścieżki lokalnej — wtedy
 *     schodzimy na strumień i plik tymczasowy, wolniej, ale poprawnie.
 */
final readonly class ZipPacker {

	public function __construct(
		private StorageProvider $storage,
	) {}

	public function isSupported(): bool {
		return class_exists( \ZipArchive::class );
	}

	/**
	 * Dopisanie porcji wpisów do archiwum.
	 *
	 * @param list<ArchiveEntry> $entries
	 * @return array{added: int, bytes: int} Ile wpisów doszło i ile waży archiwum.
	 * @throws \RuntimeException Gdy archiwum nie daje się otworzyć ani zapisać.
	 */
	public function append( StoragePath $archive, array $entries ): array {
		if ( ! $this->isSupported() ) {
			throw new \RuntimeException( 'Rozszerzenie PHP "zip" nie jest dostępne na tym serwerze.' );
		}

		$target = $this->archiveFile( $archive );
		$zip    = new \ZipArchive();
		$opened = $zip->open( $target, \ZipArchive::CREATE );

		if ( true !== $opened ) {
			throw new \RuntimeException( sprintf( 'Nie udało się otworzyć paczki (kod %d).', (int) $opened ) );
		}

		$temporary = array();
		$added     = 0;

		try {
			foreach ( $entries as $entry ) {
				$source = $this->sourceFor( $entry, $temporary );

				if ( null === $source ) {
					// Brakujący plik nie może wywrócić całej paczki: zdjęcie
					// mogło zostać usunięte między zleceniem a pakowaniem.
					// Reszta sesji jest dla klientki warta więcej niż błąd.
					continue;
				}

				if ( ! $zip->addFile( $source, $entry->nameInArchive ) ) {
					throw new \RuntimeException( sprintf( 'Nie udało się dopisać pliku %s.', $entry->nameInArchive ) );
				}

				$zip->setCompressionName( $entry->nameInArchive, \ZipArchive::CM_STORE );
				++$added;
			}

			// Zamknięcie jest tu operacją właściwą: dopiero teraz `ZipArchive`
			// czyta pliki źródłowe i zapisuje archiwum.
			if ( ! $zip->close() ) {
				throw new \RuntimeException( 'Nie udało się zapisać paczki.' );
			}
		} catch ( \Throwable $error ) {
			// `close()` po błędzie utrwaliłby połowę porcji. Wycofujemy całą,
			// żeby ponowienie zaczęło tę porcję od nowa i nie zdublowało wpisów.
			@$zip->unchangeAll();
			@$zip->close();

			throw $error;
		} finally {
			foreach ( $temporary as $file ) {
				@unlink( $file );
			}
		}

		clearstatcache( true, $target );

		return array(
			'added' => $added,
			'bytes' => (int) ( filesize( $target ) ?: 0 ),
		);
	}

	/**
	 * Liczba wpisów już leżących w archiwum.
	 *
	 * Zadanie wznawiane po przerwaniu musi wiedzieć, gdzie skończyło —
	 * a archiwum jest w tej sprawie wiarygodniejsze niż licznik w bazie,
	 * bo zapis pliku i zapis wiersza to dwie osobne operacje i między nimi
	 * serwer może paść.
	 */
	public function count( StoragePath $archive ): int {
		$target = $this->storage->localPath( $archive );

		if ( null === $target || ! $this->isSupported() ) {
			return 0;
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $target ) ) {
			return 0;
		}

		$count = $zip->numFiles;
		$zip->close();

		return $count;
	}

	/**
	 * Rozmiar gotowej paczki w bajtach.
	 */
	public function size( StoragePath $archive ): int {
		$target = $this->storage->localPath( $archive );

		if ( null === $target ) {
			return 0;
		}

		clearstatcache( true, $target );

		return (int) ( filesize( $target ) ?: 0 );
	}

	/**
	 * Usunięcie paczki.
	 *
	 * Ponowne zlecenie zaczyna od zera, więc stary plik musi zniknąć —
	 * inaczej dopisywalibyśmy do archiwum z poprzedniego wydania i klientka
	 * dostałaby zdjęcia, których fotograf już nie wybrał.
	 */
	public function reset( StoragePath $archive ): void {
		if ( $this->storage->exists( $archive ) ) {
			$this->storage->delete( $archive );
		}
	}

	/**
	 * Ścieżka archiwum w systemie plików — z utworzeniem katalogu.
	 */
	private function archiveFile( StoragePath $archive ): string {
		$existing = $this->storage->localPath( $archive );

		if ( null !== $existing ) {
			return $existing;
		}

		// Magazyn nie zna jeszcze tego pliku. Tworzymy go pustym przez
		// interfejs, żeby katalog i uprawnienia powstały tak samo jak dla
		// każdego innego obiektu.
		$this->storage->put( $archive, '' );

		$created = $this->storage->localPath( $archive );

		if ( null === $created ) {
			throw new \RuntimeException( 'Ten magazyn nie pozwala pakować plików na miejscu.' );
		}

		return $created;
	}

	/**
	 * @param list<string> $temporary Nazwy plików tymczasowych do sprzątnięcia.
	 */
	private function sourceFor( ArchiveEntry $entry, array &$temporary ): ?string {
		$path = StoragePath::fromString( $entry->storagePath );

		if ( ! $this->storage->exists( $path ) ) {
			return null;
		}

		$local = $this->storage->localPath( $path );

		if ( null !== $local ) {
			return $local;
		}

		// Magazyn zdalny: kopiujemy strumieniem, bez wciągania pliku do pamięci.
		$handle = $this->storage->readStream( $path );
		$file   = tempnam( sys_get_temp_dir(), 'kadr-zip-' );

		if ( false === $file ) {
			return null;
		}

		$out = fopen( $file, 'wb' );

		if ( false === $out ) {
			@unlink( $file );

			return null;
		}

		stream_copy_to_stream( $handle, $out );
		fclose( $out );

		if ( is_resource( $handle ) ) {
			fclose( $handle );
		}

		$temporary[] = $file;

		return $file;
	}
}
