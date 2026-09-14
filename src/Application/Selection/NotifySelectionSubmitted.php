<?php
declare( strict_types=1 );

namespace Kadr\Application\Selection;

use Kadr\Domain\Notification\Mailer;
use Kadr\Domain\Notification\Message;
use Kadr\Domain\Shared\Money;
use Kadr\Domain\Shared\Result;

/**
 * „Klientka wysłała wybór".
 *
 * Bez tej wiadomości fotograf dowiaduje się o zatwierdzonym wyborze dopiero
 * wtedy, gdy sam zajrzy do panelu — czyli zwykle kilka dni później.
 * A po drugiej stronie klientka właśnie zdecydowała się na dopłatę i czeka.
 * To jest dokładnie ten przestój, który produkt ma usuwać.
 *
 * Kwota jest w temacie wiadomości celowo. Fotograf czyta pocztę na telefonie
 * między sesjami; „Marta wybrała 28 zdjęć — dopłata 480 zł" mówi mu wszystko,
 * czego potrzebuje, bez otwierania czegokolwiek.
 */
final readonly class NotifySelectionSubmitted {

	public function __construct(
		private Mailer $mailer,
	) {}

	/**
	 * @param array<string, mixed> $tally Rozliczenie z `SelectionRoom`.
	 */
	public function send(
		string $photographerEmail,
		string $galleryTitle,
		?string $clientName,
		array $tally,
		string $panelUrl
	): Result {
		if ( ! Message::isAddress( $photographerEmail ) ) {
			return Result::failure( 'kadr_no_recipient', 'Brak adresu fotografa.' );
		}

		$who      = null === $clientName || '' === trim( $clientName ) ? 'Klientka' : trim( $clientName );
		$selected = (int) ( $tally['selected'] ?? 0 );
		$extra    = (int) ( $tally['extra'] ?? 0 );
		$total    = Money::fromMinor( (int) ( $tally['total'] ?? 0 ) );

		$subject = $extra > 0
			? sprintf( '%s wybrała %d zdjęć — dopłata %s', $who, $selected, $this->money( $total ) )
			: sprintf( '%s wybrała %d zdjęć', $who, $selected );

		$sent = $this->mailer->send(
			Message::to(
				$photographerEmail,
				$subject,
				$this->body( $who, $galleryTitle, $selected, $extra, $total, $panelUrl )
			)
		);

		return $sent
			? Result::success( array( 'to' => $photographerEmail ) )
			: Result::failure( 'kadr_mail_failed', 'Nie udało się wysłać wiadomości.' );
	}

	private function body(
		string $who,
		string $galleryTitle,
		int $selected,
		int $extra,
		Money $total,
		string $panelUrl
	): string {
		$lines = array(
			sprintf( '%s zatwierdziła wybór zdjęć z sesji „%s".', $who, $galleryTitle ),
			'',
			sprintf( 'Wybranych zdjęć: %d', $selected ),
		);

		if ( $extra > 0 ) {
			$lines[] = sprintf( 'Ponad pakiet: %d — do dopłaty %s', $extra, $this->money( $total ) );
		} else {
			$lines[] = 'Wybór mieści się w pakiecie.';
		}

		$lines[] = '';
		$lines[] = $panelUrl;
		$lines[] = '';
		$lines[] = 'Kadr';

		return implode( "\n", $lines );
	}

	/**
	 * Kwota po polsku, bez zależności od ustawień lokalnych serwera.
	 */
	private function money( Money $amount ): string {
		$złote = intdiv( $amount->minor, 100 );
		$grosze = $amount->minor % 100;

		return 0 === $grosze
			? sprintf( '%d zł', $złote )
			: sprintf( '%d,%02d zł', $złote, $grosze );
	}
}
