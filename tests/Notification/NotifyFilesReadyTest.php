<?php
declare( strict_types=1 );

namespace Kadr\Tests\Notification;

use Kadr\Application\Delivery\NotifyFilesReady;
use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Notification\Mailer;
use Kadr\Domain\Notification\Message;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Wiadomość „Twoje zdjęcia są gotowe".
 *
 * Bez niej cały etap dostawy zatrzymuje się na fotografie: paczka leży
 * spakowana, a klientka nie ma skąd o tym wiedzieć.
 */
final class NotifyFilesReadyTest extends TestCase {

	public function testTheClientGetsAMessageWithTheGalleryLink(): void {
		$world = $this->world( 'Marta', 'marta@example.test' );
		$id    = $world->readyArchive();

		$result = $world->notify->send( $id, 'https://studio.example/g/abc', 'Studio Przykładowe' );

		$this->assertTrue( $result->ok );
		$this->assertSame( 'marta@example.test', $world->mailer->last->to );
		$this->assertTrue( str_contains( $world->mailer->last->body, 'https://studio.example/g/abc' ) );
		$this->assertTrue( str_contains( $world->mailer->last->body, 'Cześć Marta' ) );
		$this->assertTrue( str_contains( $world->mailer->last->subject, 'Ślub Marty' ) );
	}

	/**
	 * Link prowadzi do GALERII, nie do paczki.
	 *
	 * Token pobrania żyje dobę, a klientka przeczyta maila w czwartek
	 * wieczorem albo za tydzień. Link do paczki byłby martwy, zanim
	 * ktokolwiek go kliknie.
	 */
	public function testTheLinkPointsToTheGalleryNotToAOneDayToken(): void {
		$world = $this->world( 'Marta', 'marta@example.test' );
		$id    = $world->readyArchive();

		$world->notify->send( $id, 'https://studio.example/g/abc', 'Studio' );

		$this->assertTrue( ! str_contains( $world->mailer->last->body, '/d/' ) );
	}

	/**
	 * Klientka otworzy maila na telefonie i spróbuje pobrać tam paczkę.
	 * To zdanie ratuje połowę wiadomości zwrotnych.
	 */
	public function testTheMessageWarnsAboutOpeningTheArchiveOnAPhone(): void {
		$world = $this->world( 'Marta', 'marta@example.test' );
		$id    = $world->readyArchive();

		$world->notify->send( $id, 'https://studio.example/g/abc', 'Studio' );

		$this->assertTrue( str_contains( $world->mailer->last->body, 'telefonie' ) );
	}

	public function testNothingIsSentBeforeTheArchiveIsReady(): void {
		$world = $this->world( 'Marta', 'marta@example.test' );
		$id    = $world->pendingArchive();

		$result = $world->notify->send( $id, 'https://studio.example/g/abc', 'Studio' );

		$this->assertFalse( $result->ok );
		$this->assertNull( $world->mailer->last );
	}

	/**
	 * Galeria bez przypisanej klientki to normalny przypadek — fotograf
	 * wyśle link sam. Brak adresu nie jest awarią.
	 */
	public function testAGalleryWithoutAClientIsNotAnError(): void {
		$world = $this->world( null, null );
		$id    = $world->readyArchive();

		$result = $world->notify->send( $id, 'https://studio.example/g/abc', 'Studio' );

		$this->assertSame( 'kadr_no_recipient', $result->code );
		$this->assertNull( $world->mailer->last );
	}

	/**
	 * Temat trafia do nagłówka wiadomości, więc nie może przenieść nowej
	 * linii — to jest klasyczne wstrzyknięcie nagłówka, które zamienia jedną
	 * wiadomość w wysyłkę na cudze adresy.
	 */
	public function testAHostileGalleryTitleCannotInjectAHeader(): void {
		$world = $this->world( 'Marta', 'marta@example.test', "Sesja\r\nBcc: ofiara@example.test" );
		$id    = $world->readyArchive();

		$world->notify->send( $id, 'https://studio.example/g/abc', 'Studio' );

		$this->assertTrue( ! str_contains( $world->mailer->last->subject, "\r" ) );
		$this->assertTrue( ! str_contains( $world->mailer->last->subject, "\n" ) );
	}

	public function testAnInvalidRecipientIsRefusedBeforeSending(): void {
		$this->assertThrows(
			\InvalidArgumentException::class,
			static fn () => Message::to( 'to nie jest adres', 'Temat', 'Treść' )
		);
	}

	private function world( ?string $firstName, ?string $email, string $title = 'Ślub Marty' ): object {
		$db        = TestDatabase::migrated();
		$tenant    = TestDatabase::tenant( 1 );
		$galleries = new GalleryRepository( $db, $tenant );
		$clients   = new ClientRepository( $db, $tenant );
		$archives  = new ArchiveRepository( $db, $tenant );

		$clientId = null;

		if ( null !== $firstName && null !== $email ) {
			$clientRow = $clients->findByPublicId( $clients->create( $firstName, $email ) );
			$clientId  = (int) $clientRow['id'];
		}

		$gallery = $galleries->create( $title, 'wesele', $clientId );
		$row     = $galleries->findByPublicId( $gallery );

		$mailer = new class() implements Mailer {
			public ?Message $last = null;

			public function send( Message $message ): bool {
				$this->last = $message;

				return true;
			}
		};

		return new class(
			(int) $row['id'],
			$archives,
			$mailer,
			new NotifyFilesReady( $galleries, $clients, $archives, $mailer )
		) {
			public function __construct(
				public int $galleryRowId,
				public ArchiveRepository $archives,
				public object $mailer,
				public NotifyFilesReady $notify,
			) {}

			public function readyArchive(): Ulid {
				$id = $this->pendingArchive();
				$this->archives->markReady( $id, 2048, gmdate( 'Y-m-d H:i:s', time() + 86400 ) );

				return $id;
			}

			public function pendingArchive(): Ulid {
				return $this->archives->request(
					$this->galleryRowId,
					ArchiveScope::Selected,
					5,
					'finals/1/x/selected.zip'
				);
			}
		};
	}
}
