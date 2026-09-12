<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Wewnętrzny identyfikator tenanta.
 *
 * Typ własny, a nie `int`, celowo: dzięki temu nie da się przez pomyłkę
 * przekazać do repozytorium identyfikatora klienta, galerii albo użytkownika
 * WordPressa w miejsce tenanta. Kompilator łapie to, czego code review nie musi.
 */
final readonly class TenantId {

	private function __construct( public int $value ) {}

	public static function fromInt( int $value ): self {
		if ( $value <= 0 ) {
			throw new \InvalidArgumentException( 'Identyfikator tenanta musi być liczbą dodatnią.' );
		}

		return new self( $value );
	}

	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}
}
