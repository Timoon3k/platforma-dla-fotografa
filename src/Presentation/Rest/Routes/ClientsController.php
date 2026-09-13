<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Klienci fotografa.
 *
 * Klient nie jest użytkownikiem WordPressa (ADR-003) — to wiersz w tabeli
 * tenanta. Ta sama osoba może być klientką dwóch fotografów i żaden z nich
 * nie ma prawa o tym wiedzieć.
 */
final class ClientsController extends Controller {

	use TenantRequest;

	private const PAGE_SIZE = 25;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/clients',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => $this->requires( Capability::ManageClients ),
				'args'                => array(
					'q'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'cursor' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$clients = new ClientRepository( Connection::get(), $tenant );
		$search  = $request->get_param( 'q' );

		$rows = $clients->page(
			is_string( $search ) && '' !== $search ? $search : null,
			(string) $request->get_param( 'cursor' ) ?: null,
			self::PAGE_SIZE + 1
		);

		$hasMore = count( $rows ) > self::PAGE_SIZE;
		$rows    = array_slice( $rows, 0, self::PAGE_SIZE );

		$items = array_map(
			static fn ( array $row ): array => array(
				'id'         => (string) $row['public_id'],
				'first_name' => (string) $row['first_name'],
				'last_name'  => (string) ( $row['last_name'] ?? '' ),
				'email'      => (string) $row['email'],
				'phone'      => $row['phone'],
				'created_at' => $row['created_at'],
			),
			$rows
		);

		return $this->collection(
			$items,
			$hasMore && array() !== $rows ? (string) $rows[ count( $rows ) - 1 ]['public_id'] : null
		);
	}
}
