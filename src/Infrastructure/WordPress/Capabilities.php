<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

use Kadr\Domain\Tenancy\Capability;
use Kadr\Domain\Tenancy\Role;

defined( 'ABSPATH' ) || exit;

/**
 * Role i uprawnienia fotografa w WordPressie.
 *
 * Fotograf jest użytkownikiem WordPressa (w odróżnieniu od klienta, ADR-003),
 * ale NIGDY nie widzi panelu administracyjnego. Uprawnienia `kadr_*` decydują
 * o dostępie do aplikacji, a nie o dostępie do WP Admina.
 */
final class Capabilities {

	public const ROLE_OWNER  = 'kadr_owner';
	public const ROLE_MEMBER = 'kadr_member';

	public function register_hooks(): void {
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_action( 'admin_init', array( $this, 'block_admin_access' ) );
	}

	/**
	 * Tworzenie ról przy aktywacji wtyczki.
	 */
	public static function install(): void {
		foreach ( array( self::ROLE_OWNER => Role::Owner, self::ROLE_MEMBER => Role::Member ) as $slug => $role ) {
			remove_role( $slug );

			$capabilities = array( 'read' => true );

			foreach ( $role->defaultCapabilities() as $capability ) {
				$capabilities[ $capability->value ] = true;
			}

			add_role( $slug, $role->label(), $capabilities );
		}

		// Administrator platformy widzi wszystko — ale w swoim panelu,
		// nie w panelu fotografa.
		$administrator = get_role( 'administrator' );

		if ( null !== $administrator ) {
			foreach ( Capability::cases() as $capability ) {
				$administrator->add_cap( $capability->value );
			}
		}
	}

	public static function uninstall(): void {
		remove_role( self::ROLE_OWNER );
		remove_role( self::ROLE_MEMBER );
	}

	public static function isPhotographer( ?int $userId = null ): bool {
		return user_can( $userId ?? get_current_user_id(), Capability::AccessApp->value );
	}

	public function hide_admin_bar( bool $show ): bool {
		// Pasek WordPressa nie ma się pojawiać nad aplikacją fotografa.
		return self::isPhotographer() && ! current_user_can( 'manage_options' ) ? false : $show;
	}

	/**
	 * Fotograf wchodzący na `/wp-admin` trafia do swojej aplikacji.
	 *
	 * Wyjątek dla `admin-ajax.php`, bo rdzeń WordPressa i inne wtyczki
	 * używają go do rzeczy niezwiązanych z naszym panelem.
	 */
	public function block_admin_access(): void {
		if ( wp_doing_ajax() || ! self::isPhotographer() || current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( home_url( '/app/' ) );
		exit;
	}
}
