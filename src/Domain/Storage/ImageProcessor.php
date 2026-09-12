<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

/**
 * Kontrakt przetwarzania obrazów.
 *
 * Dwie implementacje: Imagick (preferowana, lepsza jakość i szybkość)
 * oraz GD (degradowany fallback). Kod aplikacyjny nie wie, która działa.
 */
interface ImageProcessor {

	public function isAvailable(): bool;

	public function name(): string;

	/**
	 * @throws ImageFailure
	 */
	public function readMetadata( string $sourcePath ): ImageMetadata;

	/**
	 * Tworzy wariant: skalowanie, korekta obrotu, USUNIĘCIE METADANYCH.
	 *
	 * Wariant NIGDY nie zachowuje EXIF ani GPS — to jest wymóg, nie opcja.
	 *
	 * @return int Liczba zapisanych bajtów.
	 *
	 * @throws ImageFailure
	 */
	public function createVariant(
		string $sourcePath,
		string $targetPath,
		VariantSpec $spec,
		ImageFormat $format,
		ImageMetadata $metadata
	): int;

	/**
	 * @return list<ImageFormat> Formaty, które ta implementacja potrafi zapisać.
	 */
	public function supportedFormats(): array;
}
