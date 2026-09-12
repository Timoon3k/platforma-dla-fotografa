<?php
declare( strict_types=1 );

namespace Kadr\Domain\Queue;

/**
 * Zadanie do wykonania w tle.
 *
 * Ładunek jest serializowany do JSON, więc może zawierać wyłącznie dane
 * skalarne i tablice — nigdy obiektów. Zadanie ma odtworzyć potrzebny stan
 * z bazy po identyfikatorach, a nie wozić go ze sobą: między zakolejkowaniem
 * a wykonaniem mogą minąć godziny i dane zdążą się zmienić.
 */
final readonly class Job {

	/**
	 * @param array<string, scalar|array<mixed>|null> $payload
	 */
	public function __construct(
		public string $name,
		public array $payload = array(),
		public int $priority = 0,
		public int $maxAttempts = 5,
	) {
		if ( '' === trim( $name ) ) {
			throw new \InvalidArgumentException( 'Zadanie musi mieć nazwę.' );
		}
	}

	public function encodedPayload(): string {
		$encoded = json_encode( $this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $encoded ) {
			throw new \InvalidArgumentException( 'Ładunek zadania nie daje się zserializować do JSON.' );
		}

		return $encoded;
	}

	/**
	 * Opóźnienie przed kolejną próbą — rosnące wykładniczo.
	 *
	 * 1 min → 2 min → 4 min → 8 min → 16 min. Nagłe ponowienie po sekundzie
	 * zwykle trafia w tę samą awarię i tylko obciąża system.
	 */
	public static function backoffSeconds( int $attempt ): int {
		return (int) min( 3600, 60 * ( 2 ** max( 0, $attempt - 1 ) ) );
	}
}
