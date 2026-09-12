<?php
declare( strict_types=1 );

namespace Kadr\Domain\Queue;

/**
 * Zadanie pobrane z kolejki wraz z kontekstem wykonania.
 */
final readonly class ClaimedJob {

	/**
	 * @param array<string, mixed> $payload
	 */
	public function __construct(
		public int $id,
		public int $tenantId,
		public string $name,
		public array $payload,
		public int $attempts,
		public int $maxAttempts,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$payload = json_decode( (string) ( $row['payload'] ?? '{}' ), true );

		return new self(
			id:          (int) $row['id'],
			tenantId:    (int) ( $row['tenant_id'] ?? 0 ),
			name:        (string) ( $row['job_name'] ?? '' ),
			payload:     is_array( $payload ) ? $payload : array(),
			attempts:    (int) ( $row['attempts'] ?? 0 ),
			maxAttempts: (int) ( $row['max_attempts'] ?? 5 ),
		);
	}

	public function hasAttemptsLeft(): bool {
		return $this->attempts < $this->maxAttempts;
	}

	public function get( string $key, mixed $default = null ): mixed {
		return $this->payload[ $key ] ?? $default;
	}
}
