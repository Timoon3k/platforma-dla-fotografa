<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest;

use Kadr\Domain\Shared\Result;
use Kadr\Domain\Tenancy\Capability;

defined( 'ABSPATH' ) || exit;

/**
 * Baza kontrolerów REST v1.
 *
 * Kontroler tłumaczy HTTP na use case i z powrotem. Zero logiki biznesowej
 * (CLAUDE.md §4) — dzięki temu reguły są testowane bez WordPressa, a tu
 * zostaje kształt odpowiedzi i uprawnienia.
 */
abstract class Controller {

	public const NAMESPACE = 'kadr/v1';

	/**
	 * Odpowiedź sukcesu w jednolitym kształcie `{ data, meta }`.
	 *
	 * @param mixed                $data
	 * @param array<string, mixed> $meta
	 */
	protected function ok( mixed $data, array $meta = array(), int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'data' => $data,
				'meta' => $meta,
			),
			$status
		);
	}

	/**
	 * Odpowiedź listy z kursorem.
	 *
	 * Paginacja kursorowa, nie offsetowa: `OFFSET 50000` skanuje pięćdziesiąt
	 * tysięcy wierszy, żeby oddać dwadzieścia (docs/PERFORMANCE.md §5).
	 *
	 * @param list<mixed> $items
	 */
	protected function collection( array $items, ?string $nextCursor = null ): \WP_REST_Response {
		return $this->ok(
			$items,
			array(
				'count'       => count( $items ),
				'next_cursor' => $nextCursor,
				'has_more'    => null !== $nextCursor,
			)
		);
	}

	/**
	 * Zamiana wyniku use case'u na odpowiedź HTTP.
	 *
	 * Mapowanie kodu błędu na status jest tutaj jedno — żeby nie rozjechało
	 * się między endpointami.
	 */
	protected function respond( Result $result, int $successStatus = 200 ): \WP_REST_Response|\WP_Error {
		if ( $result->ok ) {
			return $this->ok( $result->value, array(), $successStatus );
		}

		return new \WP_Error(
			$result->code,
			$result->message,
			array_merge(
				$result->details,
				array( 'status' => $this->statusFor( $result->code ) )
			)
		);
	}

	/**
	 * Cudzy albo nieistniejący zasób daje 404, nigdy 403 — nie potwierdzamy
	 * istnienia danych innego fotografa (docs/SECURITY.md §2).
	 */
	protected function notFound(): \WP_Error {
		return new \WP_Error(
			'kadr_not_found',
			__( 'Nie znaleziono.', 'kadr' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Sprawdzenie uprawnienia fotografa. Używane jako `permission_callback` —
	 * `__return_true` jest w tym projekcie zakazane dla danych prywatnych.
	 */
	protected function requires( Capability $capability ): callable {
		return static function () use ( $capability ): bool|\WP_Error {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error(
					'kadr_unauthenticated',
					__( 'Zaloguj się, aby kontynuować.', 'kadr' ),
					array( 'status' => 401 )
				);
			}

			if ( ! current_user_can( $capability->value ) ) {
				return new \WP_Error(
					'kadr_forbidden',
					__( 'Brak uprawnienia do wykonania tej operacji.', 'kadr' ),
					array( 'status' => 403 )
				);
			}

			return true;
		};
	}

	private function statusFor( string $code ): int {
		return match ( $code ) {
			'kadr_not_found'         => 404,
			'kadr_unauthenticated'   => 401,
			'kadr_forbidden'         => 403,
			'kadr_rate_limited'      => 429,
			'kadr_invalid_link'      => 400,
			// Dane nie przeszły walidacji — `details.params` wskazuje pola,
			// a formularz pokazuje błąd pod każdym z nich.
			'kadr_invalid_input'     => 422,
			'kadr_registration_failed' => 500,
			'kadr_no_tenant'         => 409,
			'kadr_invalid_chunk'     => 400,
			'kadr_upload_rejected'   => 400,
			'kadr_upload_corrupted'  => 400,
			'kadr_upload_incomplete' => 409,
			// Reguła biznesowa, nie błąd żądania: dane są poprawne,
			// ale plan na to nie pozwala.
			'kadr_storage_exceeded'  => 422,
			'kadr_limit_reached'     => 422,
			default                  => 500,
		};
	}

	abstract public function register_routes(): void;
}
