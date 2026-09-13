<?php
declare( strict_types=1 );

namespace Kadr\Domain\Notification;

/**
 * Wiadomość do wysłania.
 *
 * Treść jest zwykłym tekstem, nie HTML-em. Powód jest praktyczny, nie
 * purystyczny: wiadomość od fotografa ma wyglądać jak wiadomość od
 * człowieka, a nie jak newsletter. Mniej powodów, żeby wpaść do spamu,
 * i żadnego ryzyka, że dane klientki wyjdą niezescapowane.
 */
final readonly class Message {

	private function __construct(
		public string $to,
		public string $subject,
		public string $body,
		public string $replyTo = '',
	) {}

	public static function to( string $address, string $subject, string $body, string $replyTo = '' ): self {
		$address = trim( $address );
		$replyTo = trim( $replyTo );

		if ( ! self::isAddress( $address ) ) {
			throw new \InvalidArgumentException( 'Adres odbiorcy jest nieprawidłowy.' );
		}

		return new self(
			$address,
			// Nagłówek nie może przenieść nowej linii — to jest klasyczne
			// wstrzyknięcie nagłówka, które zamienia jedną wiadomość w wysyłkę
			// na cudze adresy.
			self::singleLine( $subject ),
			trim( $body ),
			self::isAddress( $replyTo ) ? $replyTo : ''
		);
	}

	public static function isAddress( string $value ): bool {
		return '' !== $value && false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	private static function singleLine( string $value ): string {
		$value = preg_replace( '/[\r\n\t]+/u', ' ', trim( $value ) ) ?? '';

		return trim( preg_replace( '/ {2,}/u', ' ', $value ) ?? '' );
	}
}
