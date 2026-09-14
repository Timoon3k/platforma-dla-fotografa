<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Selection\SelectionRoom;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Presentation\Client\GalleryGate;
use Kadr\Presentation\Rest\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Wybór zdjęć przez klientkę.
 *
 * Uprawnieniem jest **token z adresu galerii plus przejdziony PIN** — klientka
 * nie ma konta ani sesji WordPressa (ADR-003). `permission_callback` NIE jest
 * tu `__return_true` (CLAUDE.md §5): każde żądanie przechodzi przez tę samą
 * bramkę, co strona galerii, i nieudane otwarcie tokenu kończy się odmową
 * przed dotknięciem czegokolwiek.
 *
 * Kontekst tenanta bierze się z tokenu, więc dalej wszystko idzie przez
 * zwykłe repozytoria z `TenantContext` (ADR-024).
 */
final class SelectionController extends Controller {

	private const TOKEN = '(?P<token>[A-Za-z0-9_-]{20,120})';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/shared/' . self::TOKEN . '/selection',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'allowed' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit' ),
					'permission_callback' => array( $this, 'allowed' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/shared/' . self::TOKEN . '/selection/(?P<asset>[0-9A-HJKMNP-TV-Z]{26})',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'mark' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'state' => array(
						'type' => array( 'string', 'null' ),
						'enum' => array( 'favorite', 'selected', 'rejected', null ),
					),
				),
			)
		);
	}

	/**
	 * Bramka: poprawny token i przejdziony PIN.
	 *
	 * Kontekst zapamiętujemy, żeby nie rozwiązywać tokenu dwa razy w jednym
	 * żądaniu — raz w sprawdzeniu uprawnień, raz w obsłudze.
	 */
	public function allowed( \WP_REST_Request $request ): bool|\WP_Error {
		$context = GalleryGate::context( (string) $request->get_param( 'token' ) );

		if ( null === $context ) {
			return new \WP_Error(
				'kadr_invalid_link',
				__( 'Ten link nie działa. Poproś fotografa o nowy.', 'kadr' ),
				array( 'status' => 403 )
			);
		}

		$this->context = $context;

		return true;
	}

	/** @var array<string, mixed>|null */
	private ?array $context = null;

	public function show(): \WP_REST_Response|\WP_Error {
		return $this->respond( $this->room()->state( $this->galleryId() ) );
	}

	public function mark( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$asset = Ulid::tryFrom( (string) $request->get_param( 'asset' ) );

		if ( null === $asset ) {
			return $this->notFound();
		}

		$raw   = $request->get_param( 'state' );
		$state = is_string( $raw ) && '' !== $raw ? SelectionState::tryFrom( $raw ) : null;

		if ( is_string( $raw ) && '' !== $raw && null === $state ) {
			return new \WP_Error(
				'kadr_invalid_input',
				__( 'Nieznany stan zdjęcia.', 'kadr' ),
				array( 'status' => 422 )
			);
		}

		return $this->respond( $this->room()->mark( $this->galleryId(), $asset, $state ) );
	}

	public function submit(): \WP_REST_Response|\WP_Error {
		$result = $this->room()->submit( $this->galleryId() );

		if ( $result->ok ) {
			$this->notifyPhotographer( $result->value['tally'] ?? array() );
		}

		return $this->respond( $result );
	}

	/**
	 * Powiadomienie fotografa, że klientka zatwierdziła wybór.
	 *
	 * Idzie przez KOLEJKĘ, nie wprost: gdyby wysyłka poszła w tym żądaniu,
	 * padnięty serwer pocztowy zwróciłby klientce błąd przy zatwierdzaniu
	 * wyboru — mimo że wybór zapisał się poprawnie. Klientka kliknęłaby
	 * jeszcze raz i dostała „ten wybór został już wysłany".
	 *
	 * @param array<string, mixed> $tally
	 */
	private function notifyPhotographer( array $tally ): void {
		$tenant = $this->context['tenant'];

		if ( ! $tenant instanceof TenantContext ) {
			return;
		}

		Container::instance()->queueFor( $tenant->tenantId->toInt() )->dispatch(
			new \Kadr\Domain\Queue\Job(
				'NotifySelection',
				array(
					'gallery_id' => (string) $this->galleryId(),
					'selected'   => (int) ( $tally['selected'] ?? 0 ),
					'extra'      => (int) ( $tally['extra'] ?? 0 ),
					'total'      => (int) ( $tally['total'] ?? 0 ),
				)
			)
		);
	}

	private function galleryId(): Ulid {
		return Ulid::fromString( (string) $this->context['gallery']['public_id'] );
	}

	private function room(): SelectionRoom {
		$db     = Connection::get();
		$tenant = $this->context['tenant'];

		if ( ! $tenant instanceof TenantContext ) {
			throw new \RuntimeException( 'Brak kontekstu tenanta.' );
		}

		return new SelectionRoom(
			new GalleryRepository( $db, $tenant ),
			new AssetRepository( $db, $tenant ),
			new SelectionRepository( $db, $tenant ),
			new SelectionItemRepository( $db, $tenant )
		);
	}
}
