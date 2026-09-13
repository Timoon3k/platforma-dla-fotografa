<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest;

use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Infrastructure\WordPress\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Kontekst tenanta dla kontrolera REST.
 *
 * Uprawnienie (`permission_callback`) mówi, co użytkownik WOLNO robić.
 * Tenant mówi, NA CZYICH danych. To są dwie różne rzeczy i muszą być
 * sprawdzane osobno — capability bez tenanta pozwoliłaby zalogowanemu
 * użytkownikowi bez studia dobić się do zapytania bez `WHERE tenant_id`.
 *
 * Repozytoria i tak nie pozwalają na zapytanie bez tenanta (konstrukcyjnie),
 * więc to jest druga warstwa, nie jedyna.
 */
trait TenantRequest {

	private function tenant(): TenantContext|\WP_Error {
		$tenant = Container::instance()->tenantForCurrentUser();

		if ( null === $tenant ) {
			return new \WP_Error(
				'kadr_no_tenant',
				__( 'To konto nie należy do żadnego studia.', 'kadr' ),
				array( 'status' => 409 )
			);
		}

		return $tenant;
	}
}
