<?php
declare( strict_types=1 );

namespace Kadr\Domain\Delivery;

/**
 * Jeden plik w paczce: skąd go wziąć i pod jaką nazwą zapisać.
 *
 * Nazwa w archiwum jest budowana przez nas, nigdy brana wprost od klienta
 * (docs/SECURITY.md §3.4). Nazwa pliku przysłana przy wysyłce jest kolumną
 * opisową — i trafia tutaj dopiero po przepuszczeniu przez `safeName()`.
 */
final readonly class ArchiveEntry {

	private function __construct(
		public string $storagePath,
		public string $nameInArchive,
	) {}

	public static function of( string $storagePath, string $originalName, int $position ): self {
		return new self( $storagePath, self::safeName( $originalName, $position ) );
	}

	/**
	 * Nazwa pliku w archiwum.
	 *
	 * Numer z przodu, bo kolejność w galerii jest decyzją fotografa
	 * (ADR-027), a rozpakowana paczka sortuje się alfabetycznie — bez numeru
	 * klientka dostaje zdjęcia w kolejności nazw z aparatu, czyli przypadkowej
	 * względem tego, co widziała.
	 *
	 * Wycinamy wszystko, co mogłoby wyjść poza katalog albo zepsuć
	 * rozpakowanie: separatory ścieżek, `..`, znaki sterujące i te zakazane
	 * w nazwach plików na Windowsie.
	 */
	public static function safeName( string $originalName, int $position ): string {
		$name = basename( str_replace( '\\', '/', trim( $originalName ) ) );

		// Znaki sterujące i zakazane na Windowsie: < > : " | ? *
		$name = preg_replace( '/[\x00-\x1F\x7F<>:"|?*\/\\\\]/u', '', $name ) ?? '';

		// Kropki na początku dają pliki ukryte i otwierają drogę do `..`.
		$name = ltrim( $name, '. ' );
		$name = trim( $name );

		if ( '' === $name ) {
			$name = 'kadr.jpg';
		}

		// Nazwy zbyt długie psują rozpakowanie na części systemów plików.
		if ( mb_strlen( $name ) > 120 ) {
			$extension = pathinfo( $name, PATHINFO_EXTENSION );
			$stem      = mb_substr( pathinfo( $name, PATHINFO_FILENAME ), 0, 110 );
			$name      = '' === $extension ? $stem : "$stem.$extension";
		}

		return sprintf( '%03d-%s', max( 1, $position ), $name );
	}

	/**
	 * Nazwa całej paczki.
	 *
	 * Klientka zobaczy ją w folderze Pobrane obok czterdziestu innych plików,
	 * więc musi mówić, co to jest — „archiwum.zip" nie mówi nic.
	 */
	public static function archiveName( string $galleryTitle, ArchiveScope $scope ): string {
		$title = self::slug( $galleryTitle );
		$part  = ArchiveScope::Selected === $scope ? 'wybrane' : 'cala-galeria';

		return sprintf( '%s-%s.zip', '' === $title ? 'galeria' : $title, $part );
	}

	private static function slug( string $value ): string {
		$map = array(
			'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
			'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
			'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
			'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
		);

		$value = strtr( mb_strtolower( trim( $value ) ), $map );
		$value = preg_replace( '/[^a-z0-9]+/u', '-', $value ) ?? '';

		return trim( mb_substr( $value, 0, 60 ), '-' );
	}
}
