<?php
declare( strict_types=1 );

namespace Kadr\Tests\Upload;

use Kadr\Application\Gallery\ChunkedUpload;
use Kadr\Application\Gallery\ProcessAsset;
use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Upload\UploadPolicy;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AssetVariantRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Image\GdProcessor;
use Kadr\Infrastructure\Queue\DatabaseQueue;
use Kadr\Infrastructure\Storage\LocalStorage;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Pełna ścieżka wysyłania zdjęcia: fragmenty → scalenie → kolejka → warianty.
 *
 * Test wykonuje prawdziwe operacje na wszystkich warstwach: SQLite, system
 * plików i GD. Nie ma tu ani jednej atrapy.
 */
final class UploadPipelineTest extends TestCase {

	private mixed $db = null;
	private ?LocalStorage $storage = null;
	private ?DatabaseQueue $queue = null;
	private ?GalleryRepository $galleries = null;
	private ?AssetRepository $assets = null;
	private ?AssetVariantRepository $variants = null;
	private string $base = '';

	/**
	 * @param array<string, scalar|null> $overrides
	 */
	private function boot( string $plan = 'studio', array $overrides = array() ): ChunkedUpload {
		$this->db      = TestDatabase::migrated();
		$this->base    = sys_get_temp_dir() . '/kadr-pipeline-' . bin2hex( random_bytes( 6 ) );
		$this->storage = new LocalStorage( $this->base, 'https://example.test/d' );
		$this->queue   = new DatabaseQueue( $this->db, 1 );
		$tenant        = TestDatabase::tenant( 1 );

		$this->galleries = new GalleryRepository( $this->db, $tenant );
		$this->assets    = new AssetRepository( $this->db, $tenant );
		$this->variants  = new AssetVariantRepository( $this->db, $tenant );

		return new ChunkedUpload(
			$this->galleries,
			$this->assets,
			$this->storage,
			$this->queue,
			new Entitlements( PlanRegistry::get( $plan ), $overrides ),
			1
		);
	}

	/**
	 * Prawdziwy JPEG o zadanych wymiarach.
	 */
	private function makeJpeg( int $width = 2400, int $height = 1600 ): string {
		$image = imagecreatetruecolor( $width, $height );

		for ( $x = 0; $x < $width; $x += 6 ) {
			$colour = imagecolorallocate( $image, ( $x * 5 ) % 256, ( $x * 3 ) % 256, ( $x * 9 ) % 256 );
			imagefilledrectangle( $image, $x, 0, $x + 6, $height, (int) $colour );
		}

		$path = tempnam( sys_get_temp_dir(), 'kadr-src' ) . '.jpg';
		imagejpeg( $image, $path, 90 );
		imagedestroy( $image );

		return $path;
	}

	/**
	 * Wysyła plik fragmentami, tak jak zrobiłaby to przeglądarka.
	 *
	 * @return array<string, mixed>
	 */
	private function upload( ChunkedUpload $upload, Ulid $galleryId, string $sourcePath ): array {
		$contents = (string) file_get_contents( $sourcePath );
		$hash     = hash( 'sha256', $contents );
		$bytes    = strlen( $contents );

		$begun = $upload->begin( $galleryId, 'IMG_1234.jpg', $bytes, $hash );

		if ( ! $begun->ok || null === $begun->value['upload_id'] ) {
			return array( 'begin' => $begun );
		}

		$uploadId = Ulid::fromString( $begun->value['upload_id'] );
		$chunks   = $begun->value['chunk_count'];

		for ( $index = 0; $index < $chunks; $index++ ) {
			$upload->appendChunk(
				$uploadId,
				$index,
				substr( $contents, $index * UploadPolicy::CHUNK_BYTES, UploadPolicy::CHUNK_BYTES )
			);
		}

		return array(
			'begin'    => $begun,
			'complete' => $upload->complete( $uploadId, $galleryId, 'IMG_1234.jpg', $chunks, $hash ),
		);
	}

	public function testUploadCreatesAPendingAssetAndQueuesProcessing(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Sesja rodzinna', 'sesja-rodzinna' );

		$result = $this->upload( $upload, $gallery, $this->makeJpeg() );

		$this->assertTrue( $result['complete']->ok );
		$this->assertSame( 'pending', $result['complete']->value['status'] );
		$this->assertTrue( $result['complete']->value['bytes'] > 0 );

		// Przetwarzanie trafiło do kolejki, a nie wykonało się w żądaniu.
		$this->assertSame( 1, $this->queue->stats()['pending'] );

		$this->cleanup();
	}

	/**
	 * BRAMKA SESJI: od fragmentów do gotowych wariantów.
	 */
	public function testEndToEndFromChunksToReadyVariants(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Wesele', 'wesele' );

		$result  = $this->upload( $upload, $gallery, $this->makeJpeg( 3000, 2000 ) );
		$assetId = Ulid::fromString( $result['complete']->value['asset_id'] );

		// Worker pobiera zadanie z kolejki.
		$claimed = $this->queue->claim( 1, 'worker-1' );
		$this->assertSame( 'ProcessAsset', $claimed[0]->name );

		$process = new ProcessAsset(
			$this->galleries,
			$this->assets,
			$this->variants,
			$this->storage,
			new GdProcessor(),
			1
		);

		$processed = $process(
			Ulid::fromString( $claimed[0]->get( 'asset_id' ) ),
			Ulid::fromString( $claimed[0]->get( 'gallery_id' ) )
		);

		$this->queue->complete( $claimed[0]->id );

		$this->assertTrue( $processed->ok );
		$this->assertSame( 3000, $processed->value['width'] );
		$this->assertSame( 2000, $processed->value['height'] );

		// Zdjęcie jest gotowe, a warianty zapisane i fizycznie istnieją.
		$asset = $this->assets->findByPublicId( $assetId );
		$this->assertSame( 'ready', (string) $asset['status'] );

		$variants = $this->variants->forAsset( (int) $asset['id'] );
		$this->assertTrue( count( $variants ) >= 3 );

		foreach ( $variants as $variant ) {
			$path = $this->base . '/' . $variant['storage_path'];
			$this->assertTrue( is_file( $path ), sprintf( 'Brak pliku wariantu: %s', $variant['storage_path'] ) );
			$this->assertTrue( (int) $variant['bytes'] > 0 );
		}

		$this->cleanup();
	}

	/**
	 * Ponowione zadanie nie może zduplikować wariantów.
	 */
	public function testProcessingIsIdempotent(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Plener', 'plener' );
		$result  = $this->upload( $upload, $gallery, $this->makeJpeg() );

		$process = new ProcessAsset(
			$this->galleries,
			$this->assets,
			$this->variants,
			$this->storage,
			new GdProcessor(),
			1
		);

		$assetId = Ulid::fromString( $result['complete']->value['asset_id'] );

		$process( $assetId, $gallery );
		$first = $this->variants->countForAsset( (int) $this->assets->findByPublicId( $assetId )['id'] );

		$process( $assetId, $gallery );
		$second = $this->variants->countForAsset( (int) $this->assets->findByPublicId( $assetId )['id'] );

		$this->assertSame( $first, $second );

		$this->cleanup();
	}

	/**
	 * Ten sam plik wysłany drugi raz nie jest przesyłany ponownie.
	 */
	public function testDuplicateIsDetectedBeforeTransfer(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Chrzciny', 'chrzciny' );
		$source  = $this->makeJpeg();

		$this->upload( $upload, $gallery, $source );

		$contents = (string) file_get_contents( $source );
		$again    = $upload->begin( $gallery, 'IMG_1234.jpg', strlen( $contents ), hash( 'sha256', $contents ) );

		$this->assertTrue( $again->ok );
		$this->assertNull( $again->value['upload_id'] );
		$this->assertTrue( null !== $again->value['duplicate_of'] );

		$this->cleanup();
	}

	/**
	 * O odrzuceniu klient dowiaduje się PRZED wysłaniem pliku.
	 */
	public function testUnsupportedFormatIsRejectedUpFront(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Test', 'test' );

		$rejected = $upload->begin( $gallery, 'zlosliwy.php', 1024, str_repeat( 'a', 64 ) );

		$this->assertFalse( $rejected->ok );
		$this->assertSame( 'kadr_upload_rejected', $rejected->code );
		$this->assertSame( 'extension', $rejected->details['reason'] );

		$this->cleanup();
	}

	public function testDoubleExtensionDoesNotSlipThrough(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Test', 'test' );

		// „zdjecie.jpg.php” ma rozszerzenie php, nie jpg.
		$rejected = $upload->begin( $gallery, 'zdjecie.jpg.php', 1024, str_repeat( 'a', 64 ) );

		$this->assertFalse( $rejected->ok );
		$this->cleanup();
	}

	public function testOversizedFileIsRejectedUpFront(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Test', 'test' );

		$rejected = $upload->begin( $gallery, 'ogromne.jpg', UploadPolicy::MAX_BYTES + 1, str_repeat( 'a', 64 ) );

		$this->assertFalse( $rejected->ok );
		$this->assertSame( 'size', $rejected->details['reason'] );

		$this->cleanup();
	}

	/**
	 * Limit planu sprawdzany PRZED transferem, nie po.
	 *
	 * Limit obniżony odstępstwem, bo plik przekraczający 5 GB odbiłby się
	 * najpierw o maksymalny rozmiar pojedynczego pliku — i tak ma być,
	 * bo tańsze sprawdzenie idzie pierwsze.
	 */
	public function testStorageLimitBlocksBeforeTransfer(): void {
		$upload  = $this->boot( 'free', array( 'storage_limit_bytes' => 50_000 ) );
		$gallery = $this->galleries->create( 'Test', 'test' );

		$tooBig = $upload->begin( $gallery, 'wesele.jpg', 120_000, str_repeat( 'b', 64 ) );

		$this->assertFalse( $tooBig->ok );
		$this->assertSame( 'kadr_storage_exceeded', $tooBig->code );
		$this->assertSame( 50_000, $tooBig->details['limit'] );

		$this->cleanup();
	}

	/**
	 * Kolejność sprawdzeń: rozmiar pliku przed limitem planu.
	 * Tańsze sprawdzenie idzie pierwsze, bo nie wymaga liczenia zużycia.
	 */
	public function testFileSizeIsCheckedBeforeThePlanLimit(): void {
		$upload  = $this->boot( 'free', array( 'storage_limit_bytes' => 1 ) );
		$gallery = $this->galleries->create( 'Test', 'test' );

		$result = $upload->begin( $gallery, 'ogromne.jpg', UploadPolicy::MAX_BYTES + 1, str_repeat( 'b', 64 ) );

		$this->assertSame( 'kadr_upload_rejected', $result->code );

		$this->cleanup();
	}

	/**
	 * Uszkodzony transfer nie może zostać zapisany jako poprawne zdjęcie.
	 */
	public function testCorruptedTransferIsRefused(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Test', 'test' );

		$contents = (string) file_get_contents( $this->makeJpeg() );
		$begun    = $upload->begin( $gallery, 'IMG.jpg', strlen( $contents ), hash( 'sha256', $contents ) );
		$uploadId = Ulid::fromString( $begun->value['upload_id'] );

		// Wysyłamy inną treść niż zadeklarowana.
		$upload->appendChunk( $uploadId, 0, 'to nie jest ten plik' );

		$completed = $upload->complete( $uploadId, $gallery, 'IMG.jpg', 1, hash( 'sha256', $contents ) );

		$this->assertFalse( $completed->ok );
		$this->assertSame( 'kadr_upload_corrupted', $completed->code );
		$this->assertSame( 0, $this->assets->countForGallery( 1 ) );

		$this->cleanup();
	}

	public function testMissingChunkIsReportedWithItsNumber(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Test', 'test' );

		$uploadId = Ulid::generate();
		$upload->appendChunk( $uploadId, 0, 'fragment' );

		$completed = $upload->complete( $uploadId, $gallery, 'IMG.jpg', 3, '' );

		$this->assertFalse( $completed->ok );
		$this->assertSame( 'kadr_upload_incomplete', $completed->code );
		$this->assertSame( 1, $completed->details['missing_chunk'] );

		$this->cleanup();
	}

	/**
	 * Wysłanie do cudzej galerii kończy się „nie istnieje”, nie „brak dostępu”.
	 */
	public function testUploadingToAForeignGalleryLooksLikeItDoesNotExist(): void {
		$upload = $this->boot();

		$otherTenant = new GalleryRepository( $this->db, TestDatabase::tenant( 2 ) );
		$foreign     = $otherTenant->create( 'Cudza', 'cudza' );

		$result = $upload->begin( $foreign, 'IMG.jpg', 1024, str_repeat( 'c', 64 ) );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );

		$this->cleanup();
	}

	/**
	 * Fragmenty są sprzątane po scaleniu — porzucone wysyłki nie mogą
	 * zapełnić dysku.
	 */
	public function testChunksAreCleanedUpAfterCompletion(): void {
		$upload  = $this->boot();
		$gallery = $this->galleries->create( 'Test', 'test' );

		$this->upload( $upload, $gallery, $this->makeJpeg() );

		$leftovers = glob( $this->base . '/tmp/1/*/*.part' ) ?: array();
		$this->assertSame( 0, count( $leftovers ) );

		$this->cleanup();
	}

	public function testChunkCountMatchesTheDeclaredSize(): void {
		$this->assertSame( 1, UploadPolicy::chunkCountFor( 1024 ) );
		$this->assertSame( 1, UploadPolicy::chunkCountFor( UploadPolicy::CHUNK_BYTES ) );
		$this->assertSame( 2, UploadPolicy::chunkCountFor( UploadPolicy::CHUNK_BYTES + 1 ) );
		$this->assertSame( 20, UploadPolicy::chunkCountFor( UploadPolicy::CHUNK_BYTES * 20 ) );
	}

	private function cleanup(): void {
		if ( '' === $this->base || ! is_dir( $this->base ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->base, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}

		@rmdir( $this->base );
	}
}
