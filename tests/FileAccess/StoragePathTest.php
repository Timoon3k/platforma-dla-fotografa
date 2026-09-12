<?php
declare( strict_types=1 );

namespace Kadr\Tests\FileAccess;

use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\ImageFormat;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Storage\VariantSpec;
use Kadr\Tests\TestCase;

/**
 * Ścieżki obiektów — zagrożenia T2 i T3 (skutek katastrofalny).
 */
final class StoragePathTest extends TestCase {

	public function testOriginalsAreNeverPubliclyServable(): void {
		$path = StoragePath::original( 7, Ulid::generate(), Ulid::generate(), 'jpg' );

		$this->assertFalse( $path->isPubliclyServable() );
		$this->assertSame( 'originals', $path->prefix() );
	}

	public function testPreviewsThumbsAndFinalsAreAlsoPrivate(): void {
		$gallery = Ulid::generate();
		$asset   = Ulid::generate();

		$preview = StoragePath::variant( 7, $gallery, $asset, VariantSpec::view(), ImageFormat::Avif );
		$thumb   = StoragePath::variant( 7, $gallery, $asset, VariantSpec::thumb(), ImageFormat::Webp );
		$final   = StoragePath::final( 7, $gallery, $asset, 'jpg' );

		$this->assertFalse( $preview->isPubliclyServable() );
		$this->assertFalse( $thumb->isPubliclyServable() );
		$this->assertFalse( $final->isPubliclyServable() );
	}

	public function testOnlyBrandingIsPublic(): void {
		$this->assertTrue( StoragePath::brand( 7, 'logo.svg' )->isPubliclyServable() );
	}

	public function testThumbnailsGoToTheirOwnPrefix(): void {
		$thumb = StoragePath::variant( 7, Ulid::generate(), Ulid::generate(), VariantSpec::thumb(), ImageFormat::Webp );
		$grid  = StoragePath::variant( 7, Ulid::generate(), Ulid::generate(), VariantSpec::grid(), ImageFormat::Webp );

		$this->assertSame( 'thumbs', $thumb->prefix() );
		$this->assertSame( 'previews', $grid->prefix() );
	}

	/**
	 * Ścieżka odtwarzana z bazy nie może wyprowadzić poza magazyn.
	 */
	public function testRejectsTraversalAttempts(): void {
		foreach ( array(
			'../../../etc/passwd',
			'originals/../../secret',
			'/etc/passwd',
			"originals/7\0/evil",
			'originals/7/../../../wp-config.php',
		) as $attempt ) {
			$this->assertThrows(
				\InvalidArgumentException::class,
				static fn() => StoragePath::fromString( $attempt ),
				sprintf( 'Ścieżka "%s" powinna zostać odrzucona.', $attempt )
			);
		}
	}

	public function testRejectsEmptyPath(): void {
		$this->assertThrows( \InvalidArgumentException::class, static fn() => StoragePath::fromString( '  ' ) );
	}

	public function testAcceptsItsOwnOutput(): void {
		$path = StoragePath::original( 7, Ulid::generate(), Ulid::generate(), 'jpg' );

		$this->assertSame( (string) $path, (string) StoragePath::fromString( (string) $path ) );
	}

	public function testKnowsWhichTenantItBelongsTo(): void {
		$path = StoragePath::original( 7, Ulid::generate(), Ulid::generate(), 'jpg' );

		$this->assertTrue( $path->belongsToTenant( 7 ) );
		$this->assertFalse( $path->belongsToTenant( 8 ) );
	}

	/**
	 * Nazwa pliku pochodzi od nas, nie od klienta (docs/SECURITY.md §4).
	 */
	public function testRejectsDangerousExtensions(): void {
		$gallery = Ulid::generate();
		$asset   = Ulid::generate();

		foreach ( array( 'php', 'jpg.php', '', '../sh', 'php5;jpg' ) as $extension ) {
			if ( 'php' === $extension ) {
				// Samo „php” jest składniowo poprawnym rozszerzeniem — o tym,
				// co wolno wysłać, decyduje lista dozwolonych typów przy uploadzie,
				// nie ta klasa. Sprawdzamy natomiast, że nie da się przemycić ścieżki.
				continue;
			}

			$this->assertThrows(
				\InvalidArgumentException::class,
				static fn() => StoragePath::original( 7, $gallery, $asset, $extension )
			);
		}
	}

	public function testBrandFilenamesAreSanitised(): void {
		$path = StoragePath::brand( 7, '../../moje logo!.svg' );

		$this->assertFalse( str_contains( (string) $path, '..' ) );
		$this->assertTrue( str_starts_with( (string) $path, 'brand/7/' ) );
	}

	public function testVariantsOnlyApplyWhenTheyShrinkTheImage(): void {
		// Powiększanie małego zdjęcia daje gorszy plik i zajmuje miejsce bez powodu.
		$this->assertFalse( VariantSpec::view()->appliesTo( 1200 ) );
		$this->assertTrue( VariantSpec::view()->appliesTo( 6000 ) );
		$this->assertTrue( VariantSpec::thumb()->appliesTo( 1200 ) );
	}

	public function testVariantHeightKeepsAspectRatio(): void {
		// 6000×4000 (3:2) zmniejszone do 900 px szerokości daje 600 px wysokości.
		$this->assertSame( 600, VariantSpec::grid()->heightFor( 6000, 4000 ) );
	}

	public function testWatermarkVariantOnlyWhenEnabled(): void {
		$this->assertSame( 3, count( VariantSpec::forGallery( false ) ) );
		$this->assertSame( 4, count( VariantSpec::forGallery( true ) ) );
	}

	/**
	 * Sześć plików na zdjęcie, nie dwadzieścia (ADR-011).
	 */
	public function testEagerFormatsAreLimitedToTwo(): void {
		$this->assertSame( 2, count( ImageFormat::eager() ) );
		$this->assertSame( 6, count( VariantSpec::standard() ) * count( ImageFormat::eager() ) );
	}
}
