<?php
declare( strict_types=1 );

namespace Kadr\Domain\Journey;

/**
 * Jeden etap na osi wraz z tym, czy już się wydarzył.
 */
final readonly class Step {

	public function __construct(
		public Stage $stage,
		public State $state,
		public ?string $at = null,
		public string $detail = '',
	) {}

	public function isDone(): bool {
		return State::Done === $this->state;
	}

	public function isCurrent(): bool {
		return State::Current === $this->state;
	}
}
