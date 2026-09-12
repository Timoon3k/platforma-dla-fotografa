<?php
declare( strict_types=1 );

namespace Kadr\Tests\FileAccess;

use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\StorageFailure;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Infrastructure\Storage\LocalStorage;
use Kadr\Tests\TestCase;

/**
 * Magazyn lokalny — testy na PRAWDZIWYM systemie plików.
 */
final class LocalStorageTest extends TestCase {

	private function storage(): LocalStorage {
		$base = sys_get_temp_dir() . '/kadr-test-' . bin2hex( random_bytes( 6 ) );

		return new LocalStorage( $base, 'https://example.test/d' );
	}

	private function somePath(): StoragePath {
		return StoragePath::original( 7, Ulid::generate(), Ulid::generate(), 'jpg' );
	}

	public function testWritesAndReadsBack(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$storage->put( $path, 'zawartość zdjęcia' );

		$this->assertTrue( $storage->exists( $path ) );

		$stream = $storage->readStream( $path );
		$this->assertSame( 'zawartość zdjęcia', stream_get_contents( $stream ) );
		fclose( $stream );

		$this->cleanup( $storage );
	}

	public function testReportsSize(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$storage->put( $path, str_repeat( 'x', 2048 ) );

		$this->assertSame( 2048, $storage->size( $path ) );
		$this->cleanup( $storage );
	}

	public function testAcceptsAStreamAsInput(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$source = fopen( 'php://temp', 'r+b' );
		fwrite( $source, 'dane ze strumienia' );
		rewind( $source );

		$storage->put( $path, $source );
		fclose( $source );

		$stream = $storage->readStream( $path );
		$this->assertSame( 'dane ze strumienia', stream_get_contents( $stream ) );
		fclose( $stream );

		$this->cleanup( $storage );
	}

	/**
	 * Zapis jest atomowy — przerwane wysyłanie nie zostawia pliku, który
	 * wyglądałby na kompletny.
	 */
	public function testLeavesNoPartialFilesBehind(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$storage->put( $path, 'kompletne dane' );

		$found = glob( $storage->basePath() . '/**/*.part' ) ?: array();
		$this->assertSame( 0, count( $found ) );

		$this->cleanup( $storage );
	}

	public function testMissingObjectRaisesFailureInsteadOfReturningEmpty(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$this->assertFalse( $storage->exists( $path ) );
		$this->assertThrows( StorageFailure::class, static fn() => $storage->readStream( $path ) );
		$this->assertThrows( StorageFailure::class, static fn() => $storage->size( $path ) );
	}

	/**
	 * Usunięcie nieistniejącego obiektu nie jest błędem — zadanie w tle
	 * ponawiane po awarii nie może się wywalić na tym, że już posprzątało.
	 */
	public function testDeletingMissingObjectIsNotAnError(): void {
		$storage = $this->storage();

		$storage->delete( $this->somePath() );
		$this->assertTrue( true );
	}

	public function testDeleteRemovesTheObject(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$storage->put( $path, 'x' );
		$storage->delete( $path );

		$this->assertFalse( $storage->exists( $path ) );
		$this->cleanup( $storage );
	}

	public function testDeletePrefixRemovesWholeGallery(): void {
		$storage = $this->storage();
		$gallery = Ulid::generate();

		for ( $i = 0; $i < 5; $i++ ) {
			$storage->put( StoragePath::original( 7, $gallery, Ulid::generate(), 'jpg' ), 'x' );
		}

		$removed = $storage->deletePrefix( "originals/7/$gallery" );

		$this->assertSame( 5, $removed );
		$this->cleanup( $storage );
	}

	/**
	 * Katalog magazynu nie może być serwowany przez Apache.
	 */
	public function testWritesAnApacheGuardFile(): void {
		$storage = $this->storage();
		$storage->put( $this->somePath(), 'x' );

		$guard = $storage->basePath() . '/.htaccess';

		$this->assertTrue( is_file( $guard ) );
		$this->assertTrue( str_contains( (string) file_get_contents( $guard ), 'Require all denied' ) );

		$this->cleanup( $storage );
	}

	public function testTemporaryUrlPointsAtTheControlledEndpoint(): void {
		$storage = $this->storage();
		$path    = $this->somePath();

		$url = $storage->temporaryUrl( $path, 300 );

		$this->assertTrue( str_starts_with( $url, 'https://example.test/d/' ) );
		// Adres prowadzi do endpointu, nie do pliku na dysku.
		$this->assertFalse( str_contains( $url, $storage->basePath() ) );
	}

	public function testWithoutAnEndpointTemporaryUrlFailsLoudly(): void {
		$storage = new LocalStorage( sys_get_temp_dir() . '/kadr-test-noendpoint' );

		$this->assertThrows(
			StorageFailure::class,
			fn() => $storage->temporaryUrl( $this->somePath(), 300 )
		);
	}

	private function cleanup( LocalStorage $storage ): void {
		$base = $storage->basePath();

		if ( ! is_dir( $base ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}

		@rmdir( $base );
	}
}
