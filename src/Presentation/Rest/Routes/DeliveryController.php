<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Delivery\IssueDownload;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Shared\SystemClock;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AuditLogRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Dostawa plików — przygotowanie paczki i wydanie linku.
 *
 * Trzy czasowniki, bo tak wygląda ta operacja z punktu widzenia fotografa:
 * zleć (POST), sprawdź, czy gotowe (GET), daj mi link (POST na `/link`).
 * Drugi krok istnieje dlatego, że pakowanie wesela trwa kilkanaście minut
 * i fotograf w tym czasie zamknie kartę.
 */
final class DeliveryController extends Controller {

	use TenantRequest;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/archive',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'status' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => $this->scopeArg(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'prepare' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => $this->scopeArg(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/archive/notify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'notify' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
				'args'                => $this->scopeArg(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/archive/link',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'link' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
				'args'                => $this->scopeArg(),
			)
		);
	}

	public function prepare( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		$pack = Container::instance()->packArchiveFor( $tenant->tenantId->toInt() );

		return $this->respond( $pack->request( $id, $this->scope( $request ) ), 202 );
	}

	public function status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		$db      = Connection::get();
		$gallery = ( new GalleryRepository( $db, $tenant ) )->findByPublicId( $id );

		if ( null === $gallery ) {
			return $this->notFound();
		}

		$archive = ( new ArchiveRepository( $db, $tenant ) )
			->forGallery( (int) $gallery['id'], $this->scope( $request ) );

		if ( null === $archive ) {
			// Brak paczki nie jest błędem — fotograf jeszcze jej nie zlecił.
			return $this->ok( array( 'status' => 'none' ) );
		}

		return $this->ok(
			array(
				'status'   => (string) $archive['status'],
				'packed'   => (int) $archive['packed_items'],
				'total'    => (int) $archive['total_items'],
				'bytes'    => (int) $archive['bytes'],
				'ready_at' => $archive['ready_at'],
				// Komunikat błędu jest dla fotografa; ślad stosu nigdy nie
				// wychodzi przez API (docs/SECURITY.md §8).
				'error'    => $archive['last_error'],
			)
		);
	}

	/**
	 * Wydanie linku do pobrania.
	 *
	 * Jawna wartość tokenu istnieje wyłącznie w tej odpowiedzi — w bazie
	 * leży sam hash i nie da się jej odtworzyć.
	 */
	public function link( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		$db    = Connection::get();
		$issue = new IssueDownload(
			new GalleryRepository( $db, $tenant ),
			new ArchiveRepository( $db, $tenant ),
			new DownloadTokenRepository( $db, $tenant ),
			new AuditLogRepository( $db, $tenant ),
			new SystemClock()
		);

		$result = $issue->forArchive( $id, $this->scope( $request ) );

		if ( $result->isFailure() ) {
			return $this->respond( $result );
		}

		$value = $result->value;

		return $this->ok(
			array(
				'url'        => home_url( '/d/' . rawurlencode( (string) $value['token'] ) ),
				'expires_at' => $value['expires_at'],
				'bytes'      => $value['bytes'],
				'filename'   => $value['filename'],
			),
			array(),
			201
		);
	}

	/**
	 * Powiadomienie klientki, że pliki są gotowe.
	 *
	 * Wydajemy przy okazji ŚWIEŻY link do galerii. Powód jest prozaiczny:
	 * jawnej wartości wcześniejszego tokenu nie da się odtworzyć z bazy
	 * (leży tam sam hash), więc nie mamy czego wkleić w wiadomość. Nowy link
	 * jest przy okazji uczciwszy — stary mógł już wygasnąć.
	 *
	 * Wysyłka jest JAWNĄ decyzją fotografa, nie efektem ubocznym spakowania:
	 * to on decyduje, kiedy klientka ma dostać wiadomość, i to jego nazwisko
	 * jest pod nią podpisane.
	 */
	public function notify( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		$db      = Connection::get();
		$clock   = new SystemClock();
		$share   = new \Kadr\Application\Gallery\ShareGallery(
			new GalleryRepository( $db, $tenant ),
			new \Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository( $db, $tenant ),
			$clock
		);

		$link = $share->issue( $id );

		if ( $link->isFailure() ) {
			return $this->respond( $link );
		}

		$archive = ( new ArchiveRepository( $db, $tenant ) )->forGallery(
			(int) ( new GalleryRepository( $db, $tenant ) )->findByPublicId( $id )['id'],
			$this->scope( $request )
		);

		if ( null === $archive ) {
			return $this->respond(
				\Kadr\Domain\Shared\Result::failure( 'kadr_archive_missing', __( 'Najpierw przygotuj pliki.', 'kadr' ) )
			);
		}

		$notify = new \Kadr\Application\Delivery\NotifyFilesReady(
			new GalleryRepository( $db, $tenant ),
			new \Kadr\Infrastructure\Database\Repositories\ClientRepository( $db, $tenant ),
			new ArchiveRepository( $db, $tenant ),
			Container::instance()->mailer()
		);

		// Podpis pod wiadomością: nazwa studia, a jeśli jej nie ma — nazwa
		// witryny. Klientka ma zobaczyć w skrzynce fotografa, u którego była.
		$studio = Container::instance()->studioName( $tenant->tenantId->toInt() );

		if ( '' === $studio ) {
			$studio = (string) get_bloginfo( 'name' );
		}

		$result = $notify->send(
			\Kadr\Domain\Shared\Ulid::fromString( (string) $archive['public_id'] ),
			home_url( '/g/' . rawurlencode( (string) $link->value['token'] ) ),
			$studio
		);

		if ( $result->isFailure() ) {
			return $this->respond( $result );
		}

		return $this->ok( array( 'to' => $result->value['to'] ) );
	}

	private function scope( \WP_REST_Request $request ): ArchiveScope {
		return ArchiveScope::tryFrom( (string) $request->get_param( 'scope' ) ) ?? ArchiveScope::Selected;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function scopeArg(): array {
		return array(
			'scope' => array(
				'type'              => 'string',
				'enum'              => array( 'selected', 'everything' ),
				'default'           => 'selected',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}
}
