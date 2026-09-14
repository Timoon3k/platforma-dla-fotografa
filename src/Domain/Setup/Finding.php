<?php
declare( strict_types=1 );

namespace Kadr\Domain\Setup;

/**
 * Jedno ustalenie z przeglądu instalacji.
 *
 * Każde niesie TRZY rzeczy: co jest nie tak, dlaczego to boli i co zrobić.
 * Komunikat bez ostatniego elementu zostawia administratora z problemem,
 * którego nie umie rozwiązać — a to jest dokładnie ten moment, w którym
 * ludzie odinstalowują wtyczkę.
 */
final readonly class Finding {

	private function __construct(
		public string $id,
		public Severity $severity,
		public string $label,
		public string $consequence,
		public string $fix,
	) {}

	public static function blocking( string $id, string $label, string $consequence, string $fix ): self {
		return new self( $id, Severity::Blocking, $label, $consequence, $fix );
	}

	public static function warning( string $id, string $label, string $consequence, string $fix ): self {
		return new self( $id, Severity::Warning, $label, $consequence, $fix );
	}

	public static function ok( string $id, string $label ): self {
		return new self( $id, Severity::Ok, $label, '', '' );
	}

	public function blocks(): bool {
		return Severity::Blocking === $this->severity;
	}
}
