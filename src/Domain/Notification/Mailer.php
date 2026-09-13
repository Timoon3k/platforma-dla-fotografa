<?php
declare( strict_types=1 );

namespace Kadr\Domain\Notification;

/**
 * Wysyłka wiadomości.
 *
 * Interfejs leży w warstwie Domain, żeby reguły produktu („klientka dowiaduje
 * się, że pliki są gotowe") dało się przetestować bez WordPressa i bez
 * serwera pocztowego.
 */
interface Mailer {

	/**
	 * @return bool Czy wiadomość została przyjęta do wysyłki.
	 */
	public function send( Message $message ): bool;
}
