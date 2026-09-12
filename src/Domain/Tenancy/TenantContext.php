<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Kontekst tenanta obowiązujący w bieżącym żądaniu.
 *
 * To jest warstwa, na której stoi izolacja danych (ryzyko R2 — skutek
 * katastrofalny). Każde repozytorium przyjmuje ten obiekt w konstruktorze
 * i samo dokłada `WHERE tenant_id = ?`.
 *
 * ZASADA: nie może istnieć sposób na wykonanie zapytania bez tenanta.
 * Nie ma metody `findAny()`, nie ma flagi `$ignoreTenant`. Jedyne wyjście
 * poza tenanta to osobne repozytoria administratora platformy
 * w `Infrastructure\Database\Platform\`, objęte audit logiem.
 */
final readonly class TenantContext {

	/**
	 * @param list<Capability> $capabilities
	 */
	private function __construct(
		public TenantId $tenantId,
		public int $userId,
		public Role $role,
		private array $capabilities,
	) {}

	/**
	 * @param list<Capability> $capabilities
	 */
	public static function for( TenantId $tenantId, int $userId, Role $role, ?array $capabilities = null ): self {
		return new self(
			$tenantId,
			$userId,
			$role,
			$capabilities ?? $role->defaultCapabilities()
		);
	}

	public function id(): int {
		return $this->tenantId->value;
	}

	public function can( Capability $capability ): bool {
		// Właściciel ma pełnię praw w swoim tenancie — ale wyłącznie w swoim.
		if ( Role::Owner === $this->role ) {
			return true;
		}

		return in_array( $capability, $this->capabilities, true );
	}

	/**
	 * @throws AccessDenied
	 */
	public function require( Capability $capability ): void {
		if ( ! $this->can( $capability ) ) {
			throw new AccessDenied( $capability );
		}
	}

	public function owns( TenantId $tenantId ): bool {
		return $this->tenantId->equals( $tenantId );
	}
}
