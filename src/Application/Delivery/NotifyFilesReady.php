<?php
declare( strict_types=1 );

namespace Kadr\Application\Delivery;

use Kadr\Domain\Notification\Mailer;
use Kadr\Domain\Notification\Message;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;

/**
 * „Twoje zdjęcia są gotowe".
 *
 * Bez tej wiadomości cały etap dostawy zatrzymuje się na fotografie:
 * paczka leży spakowana, a klientka nie ma skąd o tym wiedzieć. Dziś ten
 * krok robi się ręcznie i to jest jedno z tych dwudziestu przerwań, które
 * składają się na 2–4 godziny administracji przy sesji.
 *
 * Treść jest krótka i rzeczowa, bo ma wyglądać jak wiadomość od fotografa,
 * a nie jak mailing. Link prowadzi do GALERII, nie do paczki: token pobrania
 * żyje dobę, a klientka przeczyta maila w czwartek wieczorem albo za tydzień.
 * Z galerii wyda sobie świeży link jednym kliknięciem.
 */
final readonly class NotifyFilesReady {

	public function __construct(
		private GalleryRepository $galleries,
		private ClientRepository $clients,
		private ArchiveRepository $archives,
		private Mailer $mailer,
	) {}

	/**
	 * @param string $galleryUrl Adres galerii klientki (`/g/{token}`).
	 */
	public function send( Ulid $archiveId, string $galleryUrl, string $studioName, string $studioEmail = '' ): Result {
		$archive = $this->archives->findByPublicId( $archiveId );

		if ( null === $archive || 'ready' !== (string) $archive['status'] ) {
			return Result::failure( 'kadr_archive_not_ready', 'Paczka nie jest gotowa.' );
		}

		$gallery = $this->galleries->findById( (int) $archive['gallery_id'] );

		if ( null === $gallery ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono galerii.' );
		}

		$client = null === $gallery['client_id']
			? null
			: $this->clients->byIds( array( (int) $gallery['client_id'] ) )[ (int) $gallery['client_id'] ] ?? null;

		if ( null === $client || ! Message::isAddress( (string) ( $client['email'] ?? '' ) ) ) {
			// Galeria bez przypisanej klientki to normalny przypadek —
			// fotograf wyśle link sam. Brak adresu nie jest błędem.
			return Result::failure( 'kadr_no_recipient', 'Ta galeria nie ma przypisanej klientki z adresem e-mail.' );
		}

		$name = trim( (string) ( $client['first_name'] ?? '' ) );

		$sent = $this->mailer->send(
			Message::to(
				(string) $client['email'],
				sprintf( 'Zdjęcia z sesji „%s" są gotowe', (string) $gallery['title'] ),
				$this->body( $name, (string) $gallery['title'], $galleryUrl, $studioName ),
				$studioEmail
			)
		);

		return $sent
			? Result::success( array( 'to' => (string) $client['email'] ) )
			: Result::failure( 'kadr_mail_failed', 'Nie udało się wysłać wiadomości.' );
	}

	private function body( string $name, string $title, string $galleryUrl, string $studioName ): string {
		return implode(
			"\n",
			array(
				'' === $name ? 'Dzień dobry,' : sprintf( 'Cześć %s,', $name ),
				'',
				sprintf( 'zdjęcia z sesji „%s" są gotowe do pobrania.', $title ),
				'',
				$galleryUrl,
				'',
				// To zdanie ratuje połowę wiadomości zwrotnych: klientka
				// otwiera maila na telefonie i próbuje pobrać tam paczkę
				// (skill photography-workflow §3).
				'Paczkę najlepiej pobrać na komputerze. Na telefonie zdjęcia zapiszesz pojedynczo, przytrzymując kadr palcem.',
				'',
				$studioName,
			)
		);
	}
}
