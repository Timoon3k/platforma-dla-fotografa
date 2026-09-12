<?php
declare( strict_types=1 );

namespace Kadr\Domain\Security;

use Kadr\Domain\Shared\Clock;

/**
 * Uprawnienie czasowe: token wraz z regułami jego ważności.
 *
 * Trzy niezależne powody odmowy — wygaśnięcie, unieważnienie i wyczerpanie
 * limitu użyć — rozstrzygane w jednym miejscu, żeby żaden kontroler nie
 * sprawdził tylko dwóch z nich.
 */
final readonly class AccessGrant {

	public function __construct(
		public string $tokenHash,
		public ?\DateTimeImmutable $expiresAt,
		public ?\DateTimeImmutable $revokedAt = null,
		public ?int $maxUses = null,
		public int $usedCount = 0,
	) {}

	/**
	 * @param array<string, mixed> $row Wiersz z bazy.
	 */
	public static function fromRow( array $row ): self {
		return new self(
			tokenHash:  (string) ( $row['token_hash'] ?? '' ),
			expiresAt:  self::date( $row['expires_at'] ?? null ),
			revokedAt:  self::date( $row['revoked_at'] ?? null ),
			maxUses:    isset( $row['max_uses'] ) && null !== $row['max_uses'] ? (int) $row['max_uses'] : null,
			usedCount:  (int) ( $row['used_count'] ?? 0 ),
		);
	}

	public function isUsableAt( Clock $clock ): bool {
		return null === $this->denialReason( $clock );
	}

	/**
	 * Powód odmowy albo `null`, gdy dostęp jest ważny.
	 *
	 * Powód służy do logu i do decyzji, jaki komunikat pokazać — nigdy nie
	 * trafia do odpowiedzi HTTP w surowej postaci.
	 */
	public function denialReason( Clock $clock ): ?string {
		if ( null !== $this->revokedAt ) {
			return 'revoked';
		}

		if ( null !== $this->expiresAt && $this->expiresAt <= $clock->now() ) {
			return 'expired';
		}

		if ( null !== $this->maxUses && $this->usedCount >= $this->maxUses ) {
			return 'exhausted';
		}

		return null;
	}

	public function remainingUses(): ?int {
		return null === $this->maxUses ? null : max( 0, $this->maxUses - $this->usedCount );
	}

	/**
	 * Domyślne czasy życia (docs/SECURITY.md §3).
	 */
	public static function galleryLifetimeDays(): int {
		return 90;
	}

	public static function downloadLifetimeHours(): int {
		return 24;
	}

	public static function magicLinkLifetimeMinutes(): int {
		return 15;
	}

	private static function date( mixed $value ): ?\DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		try {
			return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception ) {
			return null;
		}
	}
}
