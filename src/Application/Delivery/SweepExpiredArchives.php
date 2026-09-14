<?php
declare( strict_types=1 );

namespace Kadr\Application\Delivery;

use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Storage\StorageProvider;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;

/**
 * Sprzątanie wygasłych paczek.
 *
 * TO JEST POZYCJA NA RACHUNKU, NIE HIGIENA.
 *
 * Paczka wesela waży 30–80 GB. Bez sprzątania każda przygotowana zostaje
 * na dysku na zawsze — a fotograf ślubny robi trzydzieści wesel w sezonie.
 * Dwa terabajty za coś, czego nikt już nie pobierze, płaci właściciel
 * platformy.
 *
 * Kolejność ma znaczenie: najpierw unieważniamy tokeny, potem kasujemy plik.
 * Odwrotnie powstałoby okno, w którym czynny token wskazuje na nieistniejący
 * plik, a klientka dostaje błąd zamiast uczciwego „ten link wygasł".
 */
final readonly class SweepExpiredArchives {

	public function __construct(
		private ArchiveRepository $archives,
		private DownloadTokenRepository $tokens,
		private StorageProvider $storage,
	) {}

	/**
	 * @return array{removed: int, bytes: int}
	 */
	public function run( int $limit = 50 ): array {
		$removed = 0;
		$bytes   = 0;

		foreach ( $this->archives->expired( gmdate( 'Y-m-d H:i:s' ), $limit ) as $archive ) {
			// Najpierw odbieramy dostęp…
			$this->tokens->revokeForGallery( (int) $archive['gallery_id'] );

			// …dopiero potem kasujemy plik.
			$path = (string) ( $archive['storage_path'] ?? '' );

			if ( '' !== $path ) {
				try {
					$object = StoragePath::fromString( $path );

					if ( $this->storage->exists( $object ) ) {
						$bytes += $this->storage->size( $object );
						$this->storage->delete( $object );
					}
				} catch ( \Throwable ) {
					// Plik już zniknął albo ścieżka jest uszkodzona. Wiersz
					// i tak usuwamy — zostawienie go sprawiłoby, że paczka
					// wygląda na gotową, a nie ma czego pobrać.
					$bytes += 0;
				}
			}

			$this->archives->delete( \Kadr\Domain\Shared\Ulid::fromString( (string) $archive['public_id'] ) );
			++$removed;
		}

		return array( 'removed' => $removed, 'bytes' => $bytes );
	}
}
