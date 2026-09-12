<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Rola w obrębie tenanta — nazwany zestaw uprawnień, nie reguła bezpieczeństwa.
 */
enum Role: string {

	case Owner  = 'owner';
	case Member = 'member';

	/**
	 * @return list<Capability>
	 */
	public function defaultCapabilities(): array {
		return match ( $this ) {
			self::Owner  => Capability::cases(),
			self::Member => array(
				Capability::AccessApp,
				Capability::ManageGalleries,
				Capability::UploadAssets,
				Capability::ManageClients,
				Capability::ViewOrders,
			),
		};
	}

	public function label(): string {
		return match ( $this ) {
			self::Owner  => 'Właściciel',
			self::Member => 'Członek zespołu',
		};
	}
}
