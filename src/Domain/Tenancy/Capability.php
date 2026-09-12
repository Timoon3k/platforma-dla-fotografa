<?php
declare( strict_types=1 );

namespace Kadr\Domain\Tenancy;

/**
 * Uprawnienia w obrębie tenanta.
 *
 * Bezpieczeństwo NIE opiera się na nazwie roli (docs/ARCHITECTURE.md §4).
 * Rola jest wyłącznie nazwanym zestawem tych uprawnień.
 */
enum Capability: string {

	case AccessApp        = 'kadr_access_app';
	case ManageGalleries  = 'kadr_manage_galleries';
	case DeleteGallery    = 'kadr_delete_gallery';
	case UploadAssets     = 'kadr_upload_assets';
	case ManageClients    = 'kadr_manage_clients';
	case ViewOrders       = 'kadr_view_orders';
	case ManageOrders     = 'kadr_manage_orders';
	case ManageProducts   = 'kadr_manage_products';
	case ManageCalendar   = 'kadr_manage_calendar';
	case ViewAnalytics    = 'kadr_view_analytics';
	case ManageBilling    = 'kadr_manage_billing';
	case ManageTeam       = 'kadr_manage_team';
	case ManageSettings   = 'kadr_manage_settings';
	case ExportData       = 'kadr_export_data';

	/**
	 * Uprawnienia, które wolno nadać członkowi zespołu.
	 *
	 * Rozliczenia i zarządzanie zespołem zostają przy właścicielu —
	 * asystentka biura ma odpowiadać klientom, a nie widzieć przychodów
	 * (persona P3, docs/SESSION-LOG.md).
	 *
	 * @return list<self>
	 */
	public static function delegatable(): array {
		return array_values(
			array_filter(
				self::cases(),
				static fn( self $c ): bool => ! in_array( $c, array( self::ManageBilling, self::ManageTeam ), true )
			)
		);
	}

	public function isDelegatable(): bool {
		return in_array( $this, self::delegatable(), true );
	}
}
