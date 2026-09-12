<?php
declare( strict_types=1 );

namespace Kadr\Domain\Shared;

/**
 * Wynik operacji aplikacyjnej.
 *
 * Use case zwraca Result zamiast rzucać wyjątkiem na regułę biznesową —
 * przekroczenie limitu planu nie jest błędem programu, tylko normalną
 * odpowiedzią, którą kontroler ma zamienić na 422 z czytelnym komunikatem.
 */
final readonly class Result {

	/**
	 * @param array<string, mixed> $details
	 */
	private function __construct(
		public bool $ok,
		public mixed $value = null,
		public string $code = '',
		public string $message = '',
		public array $details = array(),
	) {}

	public static function success( mixed $value = null ): self {
		return new self( true, $value );
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function failure( string $code, string $message = '', array $details = array() ): self {
		return new self( false, null, $code, $message, $details );
	}

	public function isFailure(): bool {
		return ! $this->ok;
	}
}
