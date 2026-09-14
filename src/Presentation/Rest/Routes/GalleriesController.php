<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Gallery\ManageGalleries;
use Kadr\Application\Selection\SelectionRoom;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Galerie fotografa — lista dla panelu.
 *
 * Kontroler nie zna reguł biznesowych: pobiera stronę z repozytorium
 * (które samo filtruje po tenancie) i tłumaczy wiersze na zasób API.
 */
final class GalleriesController extends Controller {

	use TenantRequest;

	private const PAGE_SIZE = 25;

	public function register_routes(): void {
		// Jedna rejestracja na trasę, z listą metod. Dwa osobne wywołania
		// `register_rest_route` dla tego samego adresu WordPress scala
		// w sposób, którego nie da się przewidzieć z lektury kodu.
		register_rest_route(
			self::NAMESPACE,
			'/galleries',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => array(
						'status' => array(
							'type'              => 'string',
							'enum'              => array( 'draft', 'published', 'expired', 'archived' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'q'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'cursor' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => $this->settingsArgs(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => $this->settingsArgs(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'destroy' ),
					// Usunięcie ma własne uprawnienie: asystentka może
					// zarządzać galeriami, nie musi móc ich kasować.
					'permission_callback' => $this->requires( Capability::DeleteGallery ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/journey',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'journey' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/selection',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'selection' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'reopenSelection' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/publish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
			)
		);
	}

	/**
	 * Pola ustawień galerii przyjmowane z formularza.
	 *
	 * Biała lista: cokolwiek dopisze klient do żądania, do zapisu trafi
	 * wyłącznie to, co jest tutaj.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function settingsArgs(): array {
		return array(
			'title'             => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'client_id'         => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'intro'             => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
			'theme'             => array( 'type' => 'string', 'enum' => array( 'noir', 'paper', 'minimal' ) ),
			'package_limit'     => array( 'type' => array( 'integer', 'null' ) ),
			'extra_photo_price' => array( 'type' => array( 'integer', 'null' ) ),
			'allow_download'    => array( 'type' => 'boolean' ),
			'watermark'         => array( 'type' => 'boolean' ),
			'cover_asset_id'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'expires_at'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		);
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$result = $this->useCase( $tenant )->create( $this->submitted( $request ) );

		return $this->respond( $result, 201 );
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		return $this->respond( $this->useCase( $tenant )->update( $id, $this->submitted( $request ) ) );
	}

	public function publish( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
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

		$photos = ( new AssetRepository( $db, $tenant ) )->countForGallery( (int) $gallery['id'] );

		return $this->respond( $this->useCase( $tenant )->publish( $id, $photos ) );
	}

	/**
	 * Wybór klientki oczami fotografa.
	 *
	 * Ta sama arytmetyka, co w galerii — `PackageTally` z warstwy Domain.
	 * Gdyby panel liczył dopłatę po swojemu, prędzej czy później pokazałby
	 * inną kwotę niż ta, którą zobaczyła klientka.
	 */
	public function selection( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		return $this->respond( $this->room( $tenant )->state( $id ) );
	}

	/**
	 * Ponowne otwarcie wyboru.
	 *
	 * `DELETE` na zasobie wyboru, bo z punktu widzenia fotografa to jest
	 * cofnięcie zatwierdzenia, a nie utworzenie czegoś nowego.
	 *
	 * Klientka zawsze się rozmyśli — to normalny bieg sprawy, nie wyjątek.
	 */
	public function reopenSelection( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		return $this->respond( $this->room( $tenant )->reopen( $id ) );
	}

	/**
	 * Oś procesu dla jednej sesji.
	 *
	 * Nic tu nie jest przechowywane — każdy etap wynika z danych, które
	 * i tak istnieją (patrz `Domain\Journey\Timeline`). Dlatego to jest
	 * odczyt, a nie zasób z własnym stanem.
	 */
	public function journey( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
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

		$summary = ( new \Kadr\Application\Journey\GalleryJourney(
			new AssetRepository( $db, $tenant ),
			new \Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository( $db, $tenant ),
			new SelectionRepository( $db, $tenant ),
			new SelectionItemRepository( $db, $tenant ),
			new \Kadr\Infrastructure\Database\Repositories\ArchiveRepository( $db, $tenant ),
			new \Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository( $db, $tenant )
		) )->summary( $gallery );

		$steps = array();

		foreach ( $summary['steps'] as $step ) {
			$steps[] = array(
				'stage' => $step->stage->value,
				// Panel mówi językiem fotografa, nie klientki.
				'label' => $step->stage->label(),
				'state' => $step->state->value,
				'at'    => $step->at,
			);
		}

		return $this->ok(
			$steps,
			array(
				'current'  => $summary['current']->stage->value,
				'complete' => $summary['complete'],
			)
		);
	}

	private function room( \Kadr\Domain\Tenancy\TenantContext $tenant ): SelectionRoom {
		$db = Connection::get();

		return new SelectionRoom(
			new GalleryRepository( $db, $tenant ),
			new AssetRepository( $db, $tenant ),
			new SelectionRepository( $db, $tenant ),
			new SelectionItemRepository( $db, $tenant )
		);
	}

	public function destroy( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		// Usunięcie jest miękkie: galeria trafia do kosza i da się ją
		// przywrócić. Zdjęcia klienta to nie jest miejsce na operacje,
		// których nie da się cofnąć (CLAUDE.md §2).
		$removed = ( new GalleryRepository( Connection::get(), $tenant ) )->delete( $id );

		return 0 === $removed ? $this->notFound() : $this->ok( null, array(), 204 );
	}

	/**
	 * Identyfikator z adresu — `null`, gdy nie jest poprawnym ULID-em.
	 *
	 * Nieprawidłowy identyfikator daje 404, nie 400: nie potwierdzamy nawet
	 * tego, jak wyglądają nasze identyfikatory (docs/SECURITY.md §2).
	 */
	private function identifier( \WP_REST_Request $request ): ?Ulid {
		return Ulid::tryFrom( (string) $request->get_param( 'id' ) );
	}

	/**
	 * Wyłącznie te pola, które faktycznie przyszły w żądaniu.
	 *
	 * Różnica między „pole nieobecne" a „pole puste" jest tu istotna:
	 * pierwsze zostawia wartość w bazie, drugie ją kasuje.
	 *
	 * @return array<string, mixed>
	 */
	private function submitted( \WP_REST_Request $request ): array {
		$submitted = array();

		foreach ( array_keys( $this->settingsArgs() ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$submitted[ $field ] = $request->get_param( $field );
			}
		}

		return $submitted;
	}

	private function useCase( \Kadr\Domain\Tenancy\TenantContext $tenant ): ManageGalleries {
		$db = Connection::get();

		return new ManageGalleries(
			new GalleryRepository( $db, $tenant ),
			new ClientRepository( $db, $tenant ),
			new AssetRepository( $db, $tenant ),
			Container::instance()->entitlementsFor( $tenant->id() )
		);
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$db        = Connection::get();
		$galleries = new GalleryRepository( $db, $tenant );
		$assets    = new AssetRepository( $db, $tenant );
		$clients   = new ClientRepository( $db, $tenant );

		$status = $request->get_param( 'status' );
		$search = $request->get_param( 'q' );

		// O jeden wiersz więcej, niż oddamy: obecność nadmiarowego wiersza
		// mówi, że jest kolejna strona, bez osobnego zapytania COUNT.
		$rows = $galleries->page(
			is_string( $status ) && '' !== $status ? $status : null,
			is_string( $search ) && '' !== $search ? $search : null,
			(string) $request->get_param( 'cursor' ) ?: null,
			self::PAGE_SIZE + 1
		);

		$hasMore = count( $rows ) > self::PAGE_SIZE;
		$rows    = array_slice( $rows, 0, self::PAGE_SIZE );

		$photoCounts = $assets->countsByGallery();
		$clientNames = $this->clientNames( $clients, $rows );

		$items = array_map(
			fn ( array $row ): array => $this->resource( $row, $photoCounts, $clientNames ),
			$rows
		);

		return $this->collection(
			$items,
			$hasMore && array() !== $rows ? (string) $rows[ count( $rows ) - 1 ]['public_id'] : null
		);
	}

	/**
	 * Klienci przypisani do galerii z bieżącej strony.
	 *
	 * Pobieramy ich raz, dla wszystkich wierszy naraz — pytanie o klienta
	 * przy każdej galerii byłoby N+1.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @return array<int, array{name: string, id: string}>
	 */
	private function clientNames( ClientRepository $clients, array $rows ): array {
		$byId = array();

		foreach ( $clients->all( 500 ) as $client ) {
			$byId[ (int) $client['id'] ] = array(
				'name' => trim(
					sprintf( '%s %s', (string) $client['first_name'], (string) ( $client['last_name'] ?? '' ) )
				),
				'id'   => (string) $client['public_id'],
			);
		}

		return $byId;
	}

	/**
	 * Wiersz bazy → zasób API.
	 *
	 * Na zewnątrz wychodzi `public_id` (ULID), nigdy sekwencyjne `id`
	 * (docs/SECURITY.md §1). Kwoty w groszach — nigdy zmiennoprzecinkowe.
	 *
	 * @param array<string, mixed> $row
	 * @param array<int, int>                                $photoCounts
	 * @param array<int, array{name: string, id: string}>    $clientNames
	 * @return array<string, mixed>
	 */
	private function resource( array $row, array $photoCounts, array $clientNames ): array {
		$clientId = null === $row['client_id'] ? null : (int) $row['client_id'];

		$client = null === $clientId ? null : ( $clientNames[ $clientId ] ?? null );

		return array(
			'id'                => (string) $row['public_id'],
			'title'             => (string) $row['title'],
			'slug'              => (string) $row['slug'],
			'status'            => (string) $row['status'],
			'theme'             => (string) $row['theme'],
			'client'            => null === $client ? null : $client['name'],
			// Publiczny identyfikator klienta, żeby formularz mógł go
			// zaznaczyć — nigdy sekwencyjne `id` (docs/SECURITY.md §1).
			'client_id'         => null === $client ? null : $client['id'],
			'photos'            => $photoCounts[ (int) $row['id'] ] ?? 0,
			'package_limit'     => null === $row['package_limit'] ? null : (int) $row['package_limit'],
			'extra_photo_price' => null === $row['extra_photo_price'] ? null : (int) $row['extra_photo_price'],
			'allow_download'    => (bool) $row['allow_download'],
			'published_at'      => $row['published_at'],
			'expires_at'        => $row['expires_at'],
			'created_at'        => $row['created_at'],
		);
	}
}
