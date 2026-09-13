<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Client;

use Kadr\Application\Delivery\IssueDownload;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Shared\SystemClock;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Domain\Tenancy\Role;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Tenancy\TenantId;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Platform\GalleryLookup;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\WordPress\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Pobranie paczki: `/d/{token}`.
 *
 * Prywatne zdjęcia nigdy nie leżą pod przewidywalnym publicznym adresem
 * (CLAUDE.md §5). Plik jest poza `wp-content/uploads`, serwer WWW go nie
 * widzi, a jedyną drogą jest to żądanie — które najpierw sprawdza token,
 * jego ważność i unieważnienie.
 *
 * Wysyłamy z obsługą `Range`, bo to jest różnica między „pobrało się"
 * a „zaczynam od nowa": osiemdziesiąt gigabajtów przez domowe łącze rwie się
 * regularnie, a przeglądarka umie wznowić TYLKO wtedy, gdy serwer to potrafi.
 */
final class DownloadPage {

	/** Porcja czytana z dysku. 1 MB to kompromis między liczbą operacji a pamięcią. */
	private const CHUNK = 1048576;

	public function register_hooks(): void {
		add_action( 'kadr_render_route', array( $this, 'render' ), 10, 2 );
	}

	public function render( string $route, string $param ): void {
		if ( 'download' !== $route ) {
			return;
		}

		$token = trim( (string) $param, '/' );

		// Token niesie tenanta, bo pobierający nie jest nikim zalogowanym.
		// To samo jedno wyjście poza tenanta, co przy galerii (ADR-024).
		$db    = Connection::get();
		$found = ( new GalleryLookup( $db ) )->tenantForDownloadToken( SecureToken::hash( $token ) );

		if ( null === $found ) {
			$this->refuse();
		}

		// Rola `Member` z PUSTĄ listą uprawnień: pobierająca nie jest
		// członkiem zespołu i nie ma prawa do niczego poza tym jednym plikiem.
		$tenant = TenantContext::for( TenantId::fromInt( $found ), 0, Role::Member, array() );

		$issue = new IssueDownload(
			new GalleryRepository( $db, $tenant ),
			new ArchiveRepository( $db, $tenant ),
			new DownloadTokenRepository( $db, $tenant ),
			new AuditLogRepository( $db, $tenant ),
			new SystemClock()
		);

		$result = $issue->redeem( $token, $this->ipHash() );

		if ( $result->isFailure() ) {
			$this->refuse();
		}

		$this->send(
			StoragePath::fromString( (string) $result->value['storage_path'] ),
			$this->filenameFor( (string) $result->value['storage_path'] )
		);
	}

	/**
	 * Odmowa wygląda tak samo niezależnie od powodu.
	 *
	 * Zły token, wygasły, unieważniony, cudzy — jeden ekran i jeden status.
	 * Rozróżnianie ich mówiłoby zgadującemu, że trafił w istniejący link
	 * (docs/SECURITY.md §1.9).
	 */
	private function refuse(): never {
		status_header( 410 );
		nocache_headers();

		echo wp_kses_post(
			( new GalleryMarkup() )->unavailable(
				__( 'Ten link do pobrania już nie działa', 'kadr' ),
				__( 'Linki do plików wygasają po dobie. Napisz do fotografa — wyda nowy w kilka sekund.', 'kadr' )
			)
		);

		exit;
	}

	private function send( StoragePath $path, string $filename ): never {
		$storage = Container::instance()->storage();

		if ( ! $storage->exists( $path ) ) {
			$this->refuse();
		}

		$size  = $storage->size( $path );
		$range = $this->rangeFor( $size );

		nocache_headers();

		header( 'Content-Type: application/zip' );
		// Nazwa w cudzysłowie i po `rawurlencode` — nie może przenieść
		// łamania nagłówka ani polskich znaków w postaci, której przeglądarka
		// nie zrozumie.
		header(
			sprintf(
				"Content-Disposition: attachment; filename=\"%s\"; filename*=UTF-8''%s",
				$filename,
				rawurlencode( $filename )
			)
		);
		header( 'Accept-Ranges: bytes' );
		header( 'X-Content-Type-Options: nosniff' );

		if ( null === $range ) {
			header( 'Content-Length: ' . $size );
			$this->pump( $storage->readStream( $path ), 0, $size );
		}

		status_header( 206 );
		header( sprintf( 'Content-Range: bytes %d-%d/%d', $range['from'], $range['to'], $size ) );
		header( 'Content-Length: ' . ( $range['to'] - $range['from'] + 1 ) );

		$this->pump( $storage->readStream( $path ), $range['from'], $range['to'] - $range['from'] + 1 );
	}

	/**
	 * Żądany zakres bajtów albo `null`, gdy klient chce całość.
	 *
	 * @return array{from: int, to: int}|null
	 */
	private function rangeFor( int $size ): ?array {
		$header = (string) ( $_SERVER['HTTP_RANGE'] ?? '' );

		if ( '' === $header || 1 !== preg_match( '/^bytes=(\d*)-(\d*)$/', $header, $matches ) ) {
			return null;
		}

		$from = '' === $matches[1] ? null : (int) $matches[1];
		$to   = '' === $matches[2] ? null : (int) $matches[2];

		if ( null === $from && null === $to ) {
			return null;
		}

		// `bytes=-500` znaczy „ostatnie 500 bajtów", nie „od zera do 500".
		if ( null === $from ) {
			$from = max( 0, $size - (int) $to );
			$to   = $size - 1;
		}

		$to = null === $to ? $size - 1 : min( $to, $size - 1 );

		if ( $from > $to || $from >= $size ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}

		return array( 'from' => $from, 'to' => $to );
	}

	/**
	 * @param resource|mixed $handle
	 */
	private function pump( mixed $handle, int $offset, int $length ): never {
		if ( ! is_resource( $handle ) ) {
			$this->refuse();
		}

		// Bufory PHP-a trzymałyby w pamięci cały plik — przy paczce wesela
		// to koniec procesu.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		}

		$remaining = $length;

		while ( $remaining > 0 && ! feof( $handle ) ) {
			$chunk = fread( $handle, (int) min( self::CHUNK, $remaining ) );

			if ( false === $chunk || '' === $chunk ) {
				break;
			}

			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput -- zawartość binarna pliku ZIP, escaping zniszczyłby plik.
			flush();

			$remaining -= strlen( $chunk );

			// Klientka zamknęła kartę w połowie sześćdziesięciu gigabajtów.
			// Bez tego PHP mieli dalej do końca pliku.
			if ( connection_aborted() ) {
				break;
			}
		}

		fclose( $handle );
		exit;
	}

	private function filenameFor( string $storagePath ): string {
		$name = basename( $storagePath );

		return '' === $name ? 'zdjecia.zip' : $name;
	}

	/**
	 * Adres wyłącznie jako hash z solą instalacji (docs/SECURITY.md §5).
	 */
	private function ipHash(): ?string {
		$address = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

		if ( '' === $address ) {
			return null;
		}

		return hash_hmac( 'sha256', $address, wp_salt( 'auth' ) );
	}
}
