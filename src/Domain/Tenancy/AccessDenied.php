<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Brak uprawnienia do wykonania operacji.
 *
 * Komunikat NIE zawiera informacji o istnieniu zasobu — próba sięgnięcia
 * po cudzy zasób kończy się odpowiedzią 404, nie 403, żeby nie potwierdzać,
 * że coś takiego w ogóle istnieje (docs/SECURITY.md §2).
 */
final class AccessDenied extends \RuntimeException {

	public function __construct( public readonly Capability $capability ) {
		parent::__construct( 'Brak uprawnienia do wykonania tej operacji.' );
	}
}
