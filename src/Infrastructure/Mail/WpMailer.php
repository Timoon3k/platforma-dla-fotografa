<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Mail;

use Kadr\Domain\Notification\Mailer;
use Kadr\Domain\Notification\Message;

/**
 * Wysyłka przez `wp_mail`.
 *
 * Świadomie bez własnego SMTP: fotograf i tak skonfiguruje wtyczkę pocztową
 * (dostarczalność z VPS-a bez tego jest fatalna), a `wp_mail` jest punktem,
 * pod który każda z nich się podpina. Własna implementacja SMTP dawałaby
 * drugą konfigurację do pomylenia i zero korzyści.
 */
final readonly class WpMailer implements Mailer {

	public function __construct(
		private string $fromName = '',
		private string $fromAddress = '',
	) {}

	public function send( Message $message ): bool {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( '' !== $this->fromAddress && Message::isAddress( $this->fromAddress ) ) {
			$headers[] = sprintf(
				'From: %s <%s>',
				// Nazwa nadawcy trafia do nagłówka, więc nie może przenieść
				// nowej linii ani cudzysłowu.
				preg_replace( '/["\r\n<>]+/u', '', $this->fromName ) ?? 'Kadr',
				$this->fromAddress
			);
		}

		if ( '' !== $message->replyTo ) {
			$headers[] = 'Reply-To: ' . $message->replyTo;
		}

		return (bool) wp_mail( $message->to, $message->subject, $message->body, $headers );
	}
}
