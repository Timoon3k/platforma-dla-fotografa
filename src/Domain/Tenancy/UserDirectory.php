<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Katalog kont fotografów.
 *
 * Fotograf JEST użytkownikiem WordPressa (w odróżnieniu od klienta, ADR-003),
 * ale warstwa aplikacji nie ma prawa o tym wiedzieć. Ten interfejs jest
 * granicą: dzięki niemu rejestracja studia jest testowalna bez ładowania
 * WordPressa, a wymiana katalogu kont dotyka jednej klasy w Infrastructure.
 */
interface UserDirectory {

	public function emailTaken( string $email ): bool;

	/**
	 * @return int Identyfikator utworzonego konta.
	 * @throws \RuntimeException gdy konta nie da się utworzyć.
	 */
	public function createOwner( string $email, string $password, string $displayName ): int;

	/**
	 * Zalogowanie właśnie utworzonego konta.
	 *
	 * Rejestracja kończy się w panelu, a nie na ekranie logowania —
	 * proszenie o hasło pięć sekund po jego ustawieniu jest tarciem
	 * bez żadnej korzyści dla bezpieczeństwa.
	 */
	public function signIn( int $userId ): void;

	/**
	 * Usunięcie konta utworzonego w nieudanej rejestracji.
	 *
	 * Bez tego nieudany zapis studia zostawia konto WordPressa bez tenanta —
	 * użytkownik może się zalogować i nie ma czym zarządzać, a ponowna
	 * rejestracja odbija się o zajęty adres e-mail.
	 */
	public function deleteAccount( int $userId ): void;
}
