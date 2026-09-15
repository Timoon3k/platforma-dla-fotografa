<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Domain\Printing\PrintFormat;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\ProductRepository;
use Kadr\Infrastructure\Database\Repositories\ProductVariantRepository;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Katalog produktów fotografa.
 *
 * Formaty są danymi, nie kodem (skill photography-workflow §5), więc ten
 * kontroler jest jedynym miejscem, w którym cennik odbitek w ogóle powstaje.
 */
final class CatalogueController extends Controller {

	use TenantRequest;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/catalogue',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'createProduct' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => array(
						'type' => array(
							'type'              => 'string',
							'enum'              => array( 'print', 'enlargement', 'album', 'canvas', 'custom' ),
							'default'           => 'print',
							'sanitize_callback' => 'sanitize_key',
						),
						'name' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/catalogue/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/variants',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'createVariant' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
				'args'                => $this->variantArgs(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/catalogue/variants/(?P<variant>[0-9A-HJKMNP-TV-Z]{26})',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'updateVariant' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => $this->variantArgs( false ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'deleteVariant' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/catalogue/seed',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'seed' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
			)
		);
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$db       = Connection::get();
		$products = new ProductRepository( $db, $tenant );
		$variants = new ProductVariantRepository( $db, $tenant );

		$rows = $products->all();

		if ( array() === $rows ) {
			return $this->ok( array(), array( 'empty' => true ) );
		}

		$grouped = $variants->forProducts(
			array_map( static fn( array $p ): int => (int) $p['id'], $rows ),
			false
		);

		$out = array();

		foreach ( $rows as $product ) {
			$out[] = array(
				'id'          => (string) $product['public_id'],
				'type'        => (string) $product['type'],
				'name'        => (string) $product['name'],
				'description' => (string) ( $product['description'] ?? '' ),
				'active'      => 1 === (int) $product['active'],
				'variants'    => array_map(
					static fn( array $v ): array => array(
						'id'        => (string) $v['public_id'],
						'label'     => (string) $v['label'],
						'width_mm'  => null === $v['width_mm'] ? null : (int) $v['width_mm'],
						'height_mm' => null === $v['height_mm'] ? null : (int) $v['height_mm'],
						'paper'     => $v['paper'],
						'price'     => (int) $v['price'],
						'active'    => 1 === (int) $v['active'],
					),
					$grouped[ (int) $product['id'] ] ?? array()
				),
			);
		}

		return $this->ok( $out );
	}

	public function createProduct( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$name = trim( (string) $request->get_param( 'name' ) );

		if ( '' === $name ) {
			return $this->invalid( array( 'name' => __( 'Podaj nazwę produktu.', 'kadr' ) ) );
		}

		$products = new ProductRepository( Connection::get(), $tenant );

		$id = $products->create(
			(string) $request->get_param( 'type' ),
			$name,
			(string) ( $request->get_param( 'description' ) ?? '' ),
			count( $products->all() )
		);

		return $this->ok( array( 'id' => (string) $id ), array(), 201 );
	}

	public function createVariant( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = $this->identifier( $request );

		if ( null === $id ) {
			return $this->notFound();
		}

		$db      = Connection::get();
		$product = ( new ProductRepository( $db, $tenant ) )->findByPublicId( $id );

		if ( null === $product ) {
			return $this->notFound();
		}

		$errors = $this->validateVariant( $request );

		if ( array() !== $errors ) {
			return $this->invalid( $errors );
		}

		$variants = new ProductVariantRepository( $db, $tenant );

		$variantId = $variants->create(
			(int) $product['id'],
			$this->labelFor( $request ),
			(int) $request->get_param( 'price' ),
			$this->dimension( $request, 'width_mm' ),
			$this->dimension( $request, 'height_mm' ),
			$this->paper( $request ),
			$variants->countForProduct( (int) $product['id'] )
		);

		return $this->ok( array( 'id' => (string) $variantId ), array(), 201 );
	}

	public function updateVariant( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = Ulid::tryFrom( (string) $request->get_param( 'variant' ) );

		if ( null === $id ) {
			return $this->notFound();
		}

		$variants = new ProductVariantRepository( Connection::get(), $tenant );

		if ( null === $variants->findByPublicId( $id ) ) {
			return $this->notFound();
		}

		$data = array();

		foreach ( array( 'price', 'width_mm', 'height_mm' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = (int) $request->get_param( $field );
			}
		}

		if ( null !== $request->get_param( 'label' ) ) {
			$data['label'] = sanitize_text_field( (string) $request->get_param( 'label' ) );
		}

		if ( null !== $request->get_param( 'paper' ) ) {
			$data['paper'] = $this->paper( $request );
		}

		if ( null !== $request->get_param( 'active' ) ) {
			$data['active'] = $request->get_param( 'active' ) ? 1 : 0;
		}

		if ( array() === $data ) {
			return $this->invalid( array( 'price' => __( 'Nie podano nic do zmiany.', 'kadr' ) ) );
		}

		$variants->update( $id, $data );

		return $this->ok( array( 'id' => (string) $id ) );
	}

	public function deleteVariant( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = Ulid::tryFrom( (string) $request->get_param( 'variant' ) );

		if ( null === $id ) {
			return $this->notFound();
		}

		$variants = new ProductVariantRepository( Connection::get(), $tenant );

		if ( null === $variants->findByPublicId( $id ) ) {
			return $this->notFound();
		}

		// Usunięcie jest miękkie: wariant może już być w czyimś zamówieniu,
		// a historia zamówień nie może stracić nazwy ani ceny.
		$variants->delete( $id );

		return $this->ok( array( 'id' => (string) $id ) );
	}

	/**
	 * Wstawienie typowego cennika z polskiego rynku.
	 *
	 * NIE SĄ TO DANE UDAWANE (CLAUDE.md §9), tylko punkt wyjścia, który
	 * fotograf edytuje: sześć formatów, które oferuje każde polskie
	 * laboratorium (skill photography-workflow §5). Ceny są celowo
	 * na zero — fotograf musi je wpisać sam, bo to jego marża, a nie nasza
	 * sugestia.
	 *
	 * Działanie jest jawne (przycisk „Wstaw typowy cennik"), nie wykonuje
	 * się przy aktywacji za plecami użytkownika.
	 */
	public function seed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$db       = Connection::get();
		$products = new ProductRepository( $db, $tenant );

		if ( array() !== $products->all() ) {
			return $this->respond(
				\Kadr\Domain\Shared\Result::failure(
					'kadr_catalogue_exists',
					__( 'Katalog nie jest pusty — dopisz formaty ręcznie, żeby nie nadpisać swojego cennika.', 'kadr' )
				)
			);
		}

		$productId = $products->create( 'print', __( 'Odbitki', 'kadr' ) );
		$row       = $products->findByPublicId( $productId );
		$variants  = new ProductVariantRepository( $db, $tenant );

		$formats = array(
			array( 100, 150 ),
			array( 130, 180 ),
			array( 150, 210 ),
			array( 200, 300 ),
			array( 300, 400 ),
			array( 500, 700 ),
		);

		foreach ( $formats as $index => [$width, $height] ) {
			$variants->create(
				(int) $row['id'],
				PrintFormat::ofMillimetres( $width, $height )->label(),
				0,
				$width,
				$height,
				null,
				$index
			);
		}

		return $this->ok( array( 'id' => (string) $productId, 'variants' => count( $formats ) ), array(), 201 );
	}

	/**
	 * Błędy pól w kształcie, który panel wyświetla pod polami formularza.
	 *
	 * @param array<string, string> $errors
	 */
	private function invalid( array $errors ): \WP_REST_Response|\WP_Error {
		return $this->respond(
			\Kadr\Domain\Shared\Result::failure(
				'kadr_invalid_input',
				__( 'Popraw zaznaczone pola.', 'kadr' ),
				array( 'params' => $errors )
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function validateVariant( \WP_REST_Request $request ): array {
		$errors = array();
		$price  = $request->get_param( 'price' );

		if ( null === $price || (int) $price < 0 ) {
			$errors['price'] = __( 'Podaj cenę — zero znaczy „w cenie pakietu".', 'kadr' );
		}

		$width  = $this->dimension( $request, 'width_mm' );
		$height = $this->dimension( $request, 'height_mm' );

		// Albo oba wymiary, albo żaden: jeden wymiar nie pozwala policzyć
		// kadrowania, a to jedyny powód, dla którego je zbieramy.
		if ( ( null === $width ) !== ( null === $height ) ) {
			$errors['width_mm'] = __( 'Podaj oba wymiary formatu albo żadnego.', 'kadr' );
		}

		if ( '' === $this->labelFor( $request ) ) {
			$errors['label'] = __( 'Podaj nazwę wariantu albo wymiary formatu.', 'kadr' );
		}

		return $errors;
	}

	/**
	 * Nazwa wariantu: podana wprost albo zbudowana z wymiarów.
	 *
	 * Fotograf wpisujący 100 i 150 nie powinien musieć wpisywać jeszcze
	 * „10×15" — to ta sama informacja dwa razy, a druga kopia rozjedzie się
	 * z pierwszą przy pierwszej poprawce.
	 */
	private function labelFor( \WP_REST_Request $request ): string {
		$label = trim( (string) ( $request->get_param( 'label' ) ?? '' ) );

		if ( '' !== $label ) {
			return sanitize_text_field( $label );
		}

		$width  = $this->dimension( $request, 'width_mm' );
		$height = $this->dimension( $request, 'height_mm' );

		if ( null === $width || null === $height ) {
			return '';
		}

		return PrintFormat::ofMillimetres( $width, $height )->label();
	}

	private function dimension( \WP_REST_Request $request, string $field ): ?int {
		$value = $request->get_param( $field );

		if ( null === $value || '' === $value ) {
			return null;
		}

		return (int) $value > 0 ? (int) $value : null;
	}

	private function paper( \WP_REST_Request $request ): ?string {
		$paper = trim( (string) ( $request->get_param( 'paper' ) ?? '' ) );

		return '' === $paper ? null : sanitize_text_field( $paper );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function variantArgs( bool $required = true ): array {
		return array(
			'label'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'width_mm'  => array( 'type' => 'integer', 'minimum' => 0 ),
			'height_mm' => array( 'type' => 'integer', 'minimum' => 0 ),
			'paper'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'price'     => array( 'type' => 'integer', 'minimum' => 0, 'required' => $required ),
			'active'    => array( 'type' => 'boolean' ),
		);
	}
}
