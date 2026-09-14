<?php
declare( strict_types=1 );

namespace Kadr\Tests\Notification;

use Kadr\Application\Selection\NotifySelectionSubmitted;
use Kadr\Domain\Notification\Mailer;
use Kadr\Domain\Notification\Message;
use Kadr\Tests\TestCase;

/**
 * Wiadomość „klientka wysłała wybór".
 *
 * Bez niej fotograf dowiaduje się o zatwierdzonym wyborze dopiero wtedy,
 * gdy sam zajrzy do panelu — a po drugiej stronie klientka właśnie
 * zdecydowała się na dopłatę i czeka.
 */
final class NotifySelectionSubmittedTest extends TestCase {

	/**
	 * Kwota w TEMACIE, nie w treści.
	 *
	 * Fotograf czyta pocztę na telefonie między sesjami. Temat ma powiedzieć
	 * wszystko, czego potrzebuje, bez otwierania czegokolwiek.
	 */
	public function testTheAmountDueIsInTheSubjectLine(): void {
		$mailer = $this->mailer();

		( new NotifySelectionSubmitted( $mailer ) )->send(
			'fotograf@example.test',
			'Ślub Marty i Piotra',
			'Marta',
			array( 'selected' => 28, 'extra' => 8, 'total' => 48000 ),
			'https://studio.example/app/galerie/01J'
		);

		$this->assertSame( 'Marta wybrała 28 zdjęć — dopłata 480 zł', $mailer->last->subject );
	}

	public function testAmountsWithGroszeAreFormattedCorrectly(): void {
		$mailer = $this->mailer();

		( new NotifySelectionSubmitted( $mailer ) )->send(
			'fotograf@example.test',
			'Sesja',
			'Ania',
			array( 'selected' => 3, 'extra' => 1, 'total' => 4950 ),
			'https://studio.example/app'
		);

		$this->assertTrue( str_contains( $mailer->last->subject, '49,50 zł' ) );
	}

	/**
	 * Wybór mieszczący się w pakiecie też jest wiadomością wartą wysłania —
	 * fotograf może zaczynać obróbkę. Ale bez kwoty, bo jej nie ma.
	 */
	public function testASelectionWithinThePackageSaysSoWithoutAnAmount(): void {
		$mailer = $this->mailer();

		( new NotifySelectionSubmitted( $mailer ) )->send(
			'fotograf@example.test',
			'Chrzciny',
			'Anna',
			array( 'selected' => 15, 'extra' => 0, 'total' => 0 ),
			'https://studio.example/app'
		);

		$this->assertSame( 'Anna wybrała 15 zdjęć', $mailer->last->subject );
		$this->assertTrue( str_contains( $mailer->last->body, 'mieści się w pakiecie' ) );
	}

	public function testTheMessageLinksToThePanel(): void {
		$mailer = $this->mailer();

		( new NotifySelectionSubmitted( $mailer ) )->send(
			'fotograf@example.test',
			'Sesja',
			'Marta',
			array( 'selected' => 5, 'extra' => 0, 'total' => 0 ),
			'https://studio.example/app/galerie/01J'
		);

		$this->assertTrue( str_contains( $mailer->last->body, 'https://studio.example/app/galerie/01J' ) );
	}

	/**
	 * Galeria bez przypisanej klientki nadal generuje sensowną wiadomość.
	 */
	public function testAGalleryWithoutAClientNameStillReadsWell(): void {
		$mailer = $this->mailer();

		( new NotifySelectionSubmitted( $mailer ) )->send(
			'fotograf@example.test',
			'Plener',
			null,
			array( 'selected' => 9, 'extra' => 0, 'total' => 0 ),
			'https://studio.example/app'
		);

		$this->assertSame( 'Klientka wybrała 9 zdjęć', $mailer->last->subject );
	}

	/**
	 * Tytuł galerii to dane od użytkownika, a temat trafia do nagłówka
	 * wiadomości. Nowa linia zamieniłaby jedną wiadomość w wysyłkę
	 * na cudze adresy.
	 */
	public function testAHostileGalleryTitleCannotInjectAHeader(): void {
		$mailer = $this->mailer();

		( new NotifySelectionSubmitted( $mailer ) )->send(
			'fotograf@example.test',
			"Sesja\r\nBcc: ofiara@example.test",
			"Marta\r\nX",
			array( 'selected' => 1, 'extra' => 0, 'total' => 0 ),
			'https://studio.example/app'
		);

		$this->assertTrue( ! str_contains( $mailer->last->subject, "\n" ) );
		$this->assertTrue( ! str_contains( $mailer->last->subject, "\r" ) );
	}

	public function testWithoutAPhotographerAddressNothingIsSent(): void {
		$mailer = $this->mailer();

		$result = ( new NotifySelectionSubmitted( $mailer ) )->send(
			'',
			'Sesja',
			'Marta',
			array( 'selected' => 1, 'extra' => 0, 'total' => 0 ),
			'https://studio.example/app'
		);

		$this->assertFalse( $result->ok );
		$this->assertNull( $mailer->last );
	}

	private function mailer(): object {
		return new class() implements Mailer {
			public ?Message $last = null;

			public function send( Message $message ): bool {
				$this->last = $message;

				return true;
			}
		};
	}
}
