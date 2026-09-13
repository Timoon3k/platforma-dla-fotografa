<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

use Kadr\Domain\Tenancy\UserDirectory;

defined( 'ABSPATH' ) || exit;

/**
 * Konta fotografów oparte o użytkowników WordPressa.
 *
 * Cała wiedza o `wp_users` kończy się w tej klasie (CLAUDE.md §4.1).
 * Hasła liczy WordPress — własnej kryptografii nie piszemy (ADR-003).
 */
final class WpUserDirectory implements UserDirectory {

	public function emailTaken( string $email ): bool {
		return false !== email_exists( $email );
	}

	public function createOwner( string $email, string $password, string $displayName ): int {
		// Login to adres e-mail: fotograf i tak go zna, a osobna „nazwa
		// użytkownika" jest kolejnym polem do wymyślenia i zapomnienia.
		$userId = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $displayName,
				'role'         => Capabilities::ROLE_OWNER,
			)
		);

		if ( is_wp_error( $userId ) ) {
			throw new \RuntimeException( $userId->get_error_message() );
		}

		return (int) $userId;
	}

	public function signIn( int $userId ): void {
		wp_set_current_user( $userId );
		wp_set_auth_cookie( $userId, false );
	}

	public function deleteAccount( int $userId ): void {
		// `wp_delete_user` żyje w pliku ładowanym tylko w panelu administratora,
		// a rejestracja dzieje się na froncie.
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( $userId );
	}
}
