<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Client;

use Kadr\Application\Gallery\OpenSharedGallery;
use Kadr\Domain\Shared\SystemClock;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Platform\GalleryLookup;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AssetVariantRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Security\WpCacheThrottle;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Infrastructure\WordPress\Paths;

defined( 'ABSPATH' ) || exit;

/**
 * Galeria klienta pod adresem `/g/{token}`.
 *
 * Kontekst użycia, który decyduje o każdej decyzji w tym pliku:
 * **telefon, 22:30, jedną ręką, czasem słaby zasięg.** Klientka nie zakłada
 * konta, nie instaluje aplikacji i nie czyta instrukcji — klika link z SMS-a.
 *
 * Dlatego pierwszy ekran jest renderowany po stronie serwera razem
 * z pierwszymi kadrami, a JavaScript dokłada wyłącznie to, czego HTML nie
 * potrafi: lightbox i doczytywanie kolejnych zdjęć.
 *
 * Pod tym samym adresem leżą też same zdjęcia
 * (`/g/{token}/i/{zdjęcie}/{wariant}`), bo token z adresu jest jedynym
 * uprawnieniem, jakie klientka posiada. Prywatny plik nigdy nie leży pod
 * przewidywalnym adresem — ten endpoint sprawdza token i PIN, zanim cokolwiek
 * wyśle (CLAUDE.md §5).
 */
final class GalleryPage {

	/** Ile zdjęć dokłada jedno żądanie doczytania. */
	private const PAGE = 48;

	public function register_hooks(): void {
		add_action( 'kadr_render_route', array( $this, 'render' ), 10, 2 );
	}

	public function render( string $route, string $param ): void {
		if ( 'gallery' !== $route ) {
			return;
		}

		$segments = explode( '/', trim( $param, '/' ) );
		$token    = (string) ( $segments[0] ?? '' );

		$opened = $this->useCase()->open( $token );

		if ( $opened->isFailure() ) {
			$this->document( ( new GalleryMarkup() )->unavailable(), __( 'Galeria — Kadr', 'kadr' ), 'noir', 410 );
		}

		$context = $opened->value;

		// Pobranie pliku: `/g/{token}/d/{zdjęcie}`.
		if ( 'd' === ( $segments[1] ?? '' ) ) {
			$this->serveDownload( $context, (string) ( $segments[2] ?? '' ) );
		}

		// Ścieżka obrazka: `/g/{token}/i/{zdjęcie}/{wariant}`.
		if ( 'i' === ( $segments[1] ?? '' ) ) {
			$this->serveImage( $token, $context, (string) ( $segments[2] ?? '' ), (string) ( $segments[3] ?? '' ) );
		}

		// Doczytywanie kolejnych kadrów: `/g/{token}/dalej/{strona}`.
		if ( 'dalej' === ( $segments[1] ?? '' ) ) {
			$this->serveFragment( $token, $context, max( 2, (int) ( $segments[2] ?? 2 ) ) );
		}

		$this->serveFirstScreen( $token, $context );
	}

	/**
	 * Pierwszy ekran albo bramka PIN-u.
	 *
	 * @param array<string, mixed> $context
	 */
	private function serveFirstScreen( string $token, array $context ): never {
		$markup = new GalleryMarkup();
		$theme  = (string) $context['gallery']['theme'];

		if ( $context['needs_pin'] && ! $this->pinAccepted( $context ) ) {
			$error = $this->handlePinSubmission( $token, $context );

			$this->document(
				$markup->gate( $this->studio( $context ), $error ),
				(string) $context['gallery']['title'],
				$theme,
				200,
				false
			);
		}

		$photos = $this->photos( $token, $context, 1 );

		( new GalleryAccessRepository( Connection::get(), $context['tenant'] ) )
			->recordUse( (int) $context['access']['id'] );

		$this->document(
			$markup->page(
				$this->galleryData( $token, $context, $photos['total'] ),
				$photos['items'],
				$this->studio( $context ),
				$photos['has_more'],
				(bool) $context['gallery']['allow_download']
			),
			(string) $context['gallery']['title'],
			$theme
		);
	}

	/**
	 * Kolejna porcja kadrów.
	 *
	 * Odpowiedź to sam fragment HTML-a — skrypt wkleja go na koniec siatki.
	 * Nagłówek `X-Kadr-More` mówi, czy jest jeszcze co doczytywać, żeby
	 * przeglądarka nie musiała zgadywać po długości odpowiedzi.
	 *
	 * @param array<string, mixed> $context
	 */
	private function serveFragment( string $token, array $context, int $page ): never {
		if ( $context['needs_pin'] && ! $this->pinAccepted( $context ) ) {
			$this->abort( 403 );
		}

		$photos = $this->photos( $token, $context, $page );

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: private, no-store' );
		header( 'X-Kadr-More: ' . ( $photos['has_more'] ? '1' : '0' ) );

		// phpcs:ignore WordPress.Security.EscapingOutput -- markup z GalleryMarkup, każda wartość escapowana u źródła.
		echo ( new GalleryMarkup() )->items( $photos['items'], $photos['offset'] );
		exit;
	}

	/**
	 * Zdjęcia jednej strony wraz z adresami wariantów.
	 *
	 * @param array<string, mixed> $context
	 * @return array{items: list<array<string, mixed>>, total: int, offset: int, has_more: bool}
	 */
	private function photos( string $token, array $context, int $page ): array {
		$db     = Connection::get();
		$tenant = $context['tenant'];
		$assets = new AssetRepository( $db, $tenant );

		$galleryId = (int) $context['gallery']['id'];
		$size      = 1 === $page ? GalleryMarkup::EAGER : self::PAGE;
		$offset    = 1 === $page ? 0 : GalleryMarkup::EAGER + ( $page - 2 ) * self::PAGE;

		$rows  = $assets->forGallery( $galleryId, $size, $offset );
		$total = $assets->countForGallery( $galleryId );

		$ready = array_values(
			array_filter( $rows, static fn ( array $row ): bool => 'ready' === (string) $row['status'] )
		);

		$gallery  = $this->galleryUrl( $token );
		$download = (bool) $context['gallery']['allow_download'];

		$items = array_map(
			static fn ( array $row ): array => array(
				'id'       => (string) $row['public_id'],
				'src'      => $gallery . '/i/' . $row['public_id'] . '/grid',
				'full'     => $gallery . '/i/' . $row['public_id'] . '/view',
				'download' => $download ? $gallery . '/d/' . $row['public_id'] : '',
				'lqip'     => (string) ( $row['lqip'] ?? '' ),
				'width'    => (int) $row['width'],
				'height'   => (int) $row['height'],
				'alt'      => '',
			),
			$ready
		);

		return array(
			'items'    => $items,
			'total'    => $total,
			'offset'   => $offset,
			'has_more' => $offset + count( $rows ) < $total,
		);
	}

	/**
	 * Wysłanie wariantu zdjęcia.
	 *
	 * @param array<string, mixed> $context
	 */
	private function serveImage( string $token, array $context, string $assetId, string $variant ): never {
		if ( $context['needs_pin'] && ! $this->pinAccepted( $context ) ) {
			// Galeria z PIN-em nie wydaje zdjęć bez PIN-u. Inaczej link
			// do pliku byłby obejściem całej bramki.
			$this->abort( 403 );
		}

		if ( ! in_array( $variant, array( 'thumb', 'grid', 'view' ), true ) ) {
			$this->abort( 404 );
		}

		$id = Ulid::tryFrom( $assetId );

		if ( null === $id ) {
			$this->abort( 404 );
		}

		$db    = Connection::get();
		$asset = ( new AssetRepository( $db, $context['tenant'] ) )->findByPublicId( $id );

		// Zdjęcie musi należeć DO TEJ galerii, nie tylko do tego tenanta —
		// inaczej jeden link otwierałby cały dorobek fotografa.
		if ( null === $asset || (int) $asset['gallery_id'] !== (int) $context['gallery']['id'] ) {
			$this->abort( 404 );
		}

		$variants = new AssetVariantRepository( $db, $context['tenant'] );
		$storage  = Container::instance()->storage();

		// Watermark zamiast podglądu, gdy fotograf go włączył: proofing bez
		// znaku wodnego to zaproszenie do zrzutu ekranu zamiast dopłaty.
		$wanted = ( (bool) $context['gallery']['watermark'] && 'view' === $variant ) ? 'wm' : $variant;

		foreach ( array( 'avif', 'webp', 'jpeg' ) as $format ) {
			$row = $variants->find( (int) $asset['id'], $wanted, $format );

			if ( null === $row ) {
				continue;
			}

			$path = StoragePath::fromString( (string) $row['storage_path'] );

			if ( ! $storage->exists( $path ) ) {
				continue;
			}

			$this->stream( $storage->readStream( $path ), $format, (int) $row['bytes'] );
		}

		$this->abort( 404 );
	}

	/**
	 * Pobranie pliku przez klientkę.
	 *
	 * Wychodzi wariant `view`, nie oryginał: oryginał to plik RAW albo JPEG
	 * z aparatu, który na telefonie jest bezużyteczny, a fotografowi zabiera
	 * transfer. Pełne pliki są przedmiotem dostawy, nie proofingu (sesja 10).
	 *
	 * @param array<string, mixed> $context
	 */
	private function serveDownload( array $context, string $assetId ): never {
		if ( ! (bool) $context['gallery']['allow_download'] ) {
			// Pobieranie wyłączone przy proofingu nie jest kaprysem: zdjęcie
			// pobrane przed wyborem to zdjęcie, za które nikt nie dopłaci.
			$this->abort( 403 );
		}

		if ( $context['needs_pin'] && ! $this->pinAccepted( $context ) ) {
			$this->abort( 403 );
		}

		$id = Ulid::tryFrom( $assetId );

		if ( null === $id ) {
			$this->abort( 404 );
		}

		$db    = Connection::get();
		$asset = ( new AssetRepository( $db, $context['tenant'] ) )->findByPublicId( $id );

		if ( null === $asset || (int) $asset['gallery_id'] !== (int) $context['gallery']['id'] ) {
			$this->abort( 404 );
		}

		$variants = new AssetVariantRepository( $db, $context['tenant'] );
		$storage  = Container::instance()->storage();

		foreach ( array( 'jpeg', 'webp', 'avif' ) as $format ) {
			$row = $variants->find( (int) $asset['id'], 'view', $format );

			if ( null === $row ) {
				continue;
			}

			$path = StoragePath::fromString( (string) $row['storage_path'] );

			if ( ! $storage->exists( $path ) ) {
				continue;
			}

			// Nazwa pliku z oryginału, ale rozszerzenie z wariantu — inaczej
			// `DSC_1234.NEF` pobrałoby się jako plik, którego nic nie otworzy.
			$name = pathinfo( (string) $asset['original_name'], PATHINFO_FILENAME ) . '.' . $format;

			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );

			$this->stream( $storage->readStream( $path ), $format, (int) $row['bytes'] );
		}

		$this->abort( 404 );
	}

	/**
	 * @param resource|mixed $stream
	 */
	private function stream( mixed $stream, string $format, int $bytes ): never {
		$types = array(
			'avif' => 'image/avif',
			'webp' => 'image/webp',
			'jpeg' => 'image/jpeg',
		);

		header( 'Content-Type: ' . $types[ $format ] );
		header( 'Content-Length: ' . $bytes );
		// Wariant jest niezmienny — jego treść zmienia się tylko razem
		// z identyfikatorem. Prywatny, więc `private`.
		header( 'Cache-Control: private, max-age=604800, immutable' );
		header( 'X-Content-Type-Options: nosniff' );

		if ( is_resource( $stream ) ) {
			fpassthru( $stream );
			fclose( $stream );
		}

		exit;
	}

	/**
	 * Obsługa formularza PIN-u.
	 *
	 * @param array<string, mixed> $context
	 * @return string Komunikat błędu albo pusty łańcuch.
	 */
	private function handlePinSubmission( string $token, array $context ): string {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- formularz publiczny bez sesji; ochroną jest limit prób po tokenie.
		$pin = isset( $_POST['pin'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['pin'] ) ) : '';

		$result = $this->useCase()->verifyPin( $token, $pin );

		if ( $result->isFailure() ) {
			return $result->message;
		}

		$this->rememberPin( $context );

		// Przekierowanie po udanym POST: odświeżenie strony nie może wysyłać
		// PIN-u drugi raz i zużywać limitu prób.
		wp_safe_redirect( $this->galleryUrl( $token ) );
		exit;
	}

	/**
	 * Czy PIN został już podany w tej przeglądarce.
	 *
	 * Ciasteczko jest podpisane kluczem instalacji, więc nie da się go
	 * podrobić, i nie zawiera ani PIN-u, ani tokenu — wyłącznie dowód,
	 * że bramka została przejdzona.
	 *
	 * @param array<string, mixed> $context
	 */
	private function pinAccepted( array $context ): bool {
		$name = $this->cookieName( $context );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt własnego, podpisanego ciasteczka.
		$given = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $name ] ) ) : '';

		return '' !== $given && hash_equals( $this->cookieValue( $context ), $given );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function rememberPin( array $context ): void {
		setcookie(
			$this->cookieName( $context ),
			$this->cookieValue( $context ),
			array(
				'expires'  => time() + ( 12 * HOUR_IN_SECONDS ),
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function cookieName( array $context ): string {
		return 'kadr_g_' . substr( hash( 'sha256', (string) $context['access']['token_hash'] ), 0, 12 );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function cookieValue( array $context ): string {
		return hash_hmac( 'sha256', (string) $context['access']['token_hash'], wp_salt( 'auth' ) );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array{title: string, intro: string, theme: string, count: int, cover: ?array<string, mixed>}
	 */
	private function galleryData( string $token, array $context, int $count ): array {
		$gallery = $context['gallery'];
		$cover   = null;

		if ( null !== $gallery['cover_asset_id'] ) {
			$asset = ( new AssetRepository( Connection::get(), $context['tenant'] ) )
				->findById( (int) $gallery['cover_asset_id'] );

			if ( null !== $asset && 'ready' === (string) $asset['status'] ) {
				$cover = array(
					'src'    => $this->galleryUrl( $token ) . '/i/' . $asset['public_id'] . '/view',
					'width'  => (int) $asset['width'],
					'height' => (int) $asset['height'],
				);
			}
		}

		return array(
			'title' => (string) $gallery['title'],
			'intro' => (string) ( $gallery['intro'] ?? '' ),
			'theme' => (string) $gallery['theme'],
			'count' => $count,
			'cover' => $cover,
		);
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array{name: string, logo: ?string, footer: string}
	 */
	private function studio( array $context ): array {
		return array(
			'name'   => Container::instance()->studioName( $context['tenant']->id() ),
			'logo'   => null,
			'footer' => '',
		);
	}

	private function galleryUrl( string $token ): string {
		return home_url( '/g/' . rawurlencode( $token ) );
	}

	private function useCase(): OpenSharedGallery {
		$db = Connection::get();

		return new OpenSharedGallery( $db, new GalleryLookup( $db ), new WpCacheThrottle(), new SystemClock() );
	}

	private function abort( int $status ): never {
		status_header( $status );
		nocache_headers();
		exit;
	}

	private function document( string $body, string $title, string $theme, int $status = 200, bool $withScript = true ): never {
		status_header( $status );
		nocache_headers();
		add_filter( 'show_admin_bar', '__return_false' );

		$allowed = in_array( $theme, array( 'noir', 'paper', 'minimal' ), true ) ? $theme : 'noir';

		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="<?php echo 'noir' === $allowed ? 'dark' : 'light'; ?>">
<title><?php echo esc_html( $title ); ?></title>
<?php
		// Galeria klienta nie jest treścią do zaindeksowania. To są czyjeś
		// prywatne zdjęcia (docs/SECURITY.md §4).
		wp_robots_no_robots();

		// Arkusze są wpisane wprost, a nie przez kolejkę WordPressa: ten
		// dokument nie jest podstroną motywu i nie ma ładować niczego,
		// czego nie zamówiliśmy.
		$this->inlineStyles();
?>
</head>
<body class="kadr-gallery" data-theme="<?php echo esc_attr( $allowed ); ?>">
<?php
		echo $body; // phpcs:ignore WordPress.Security.EscapingOutput -- markup z GalleryMarkup, każda wartość escapowana u źródła.

		if ( $withScript ) {
			printf(
				'<script type="module" src="%s"></script>',
				esc_url( Paths::url( 'assets/js/gallery/gallery.js' ) . '?v=' . Paths::asset_version( 'assets/js/gallery/gallery.js' ) )
			);
		}
?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Style galerii w jednym żądaniu.
	 *
	 * Trzy arkusze to trzy żądania blokujące render. Na 4G każde z nich
	 * kosztuje tyle, co pierwsze zdjęcie — a mieszczą się razem w kilku
	 * kilobajtach (docs/PERFORMANCE.md §2).
	 */
	private function inlineStyles(): void {
		$css = '';

		foreach ( array( 'tokens', 'gallery' ) as $sheet ) {
			$path = Paths::dir( "assets/css/$sheet.css" );

			if ( is_readable( $path ) ) {
				$css .= (string) file_get_contents( $path );
			}
		}

		printf( '<style>%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapingOutput -- własny arkusz z dysku wtyczki.
	}
}
