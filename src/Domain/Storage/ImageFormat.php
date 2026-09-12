<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

/**
 * Format wariantu podglądu.
 */
enum ImageFormat: string {

	case Avif = 'avif';
	case Webp = 'webp';
	case Jpeg = 'jpeg';

	public function mimeType(): string {
		return match ( $this ) {
			self::Avif => 'image/avif',
			self::Webp => 'image/webp',
			self::Jpeg => 'image/jpeg',
		};
	}

	/**
	 * Formaty generowane od razu przy wysyłaniu zdjęcia.
	 *
	 * JPEG powstaje leniwie, przy pierwszym żądaniu z przeglądarki bez obsługi
	 * AVIF/WebP — dziś to margines, a storage jest kosztem SaaS (ADR-011).
	 *
	 * @return list<self>
	 */
	public static function eager(): array {
		return array( self::Avif, self::Webp );
	}

	/**
	 * Jakość kompresji dobrana per format.
	 *
	 * AVIF przy tej samej jakości wizualnej znosi niższą wartość niż JPEG,
	 * dlatego liczby się różnią — to nie przeoczenie.
	 */
	public function quality(): int {
		return match ( $this ) {
			self::Avif => 50,
			self::Webp => 78,
			self::Jpeg => 82,
		};
	}
}
