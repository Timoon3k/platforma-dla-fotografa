<?php
declare( strict_types=1 );

namespace Kadr\Domain\Billing;

use Kadr\Domain\Shared\Money;

/**
 * Jedyne źródło prawdy o planach i cenach (ADR-008, docs/BILLING.md).
 *
 * Strona cennika, silnik limitów i moduł rozliczeń czytają TĘ SAMĄ definicję.
 * Cena nie może pojawić się w drugim miejscu — inaczej po pierwszej zmianie
 * cennika marketing rozjedzie się z produktem.
 */
final class PlanRegistry {

	public const GB = 1073741824;   // 1024^3
	public const TB = 1099511627776;

	/** @var array<string, Plan>|null */
	private static ?array $plans = null;

	/**
	 * @return array<string, Plan>
	 */
	public static function all(): array {
		return self::$plans ??= array(
			'free'    => new Plan(
				key:          'free',
				name:         'Start',
				monthly:      Money::zero(),
				yearly:       Money::zero(),
				entitlements: array(
					// Dla planu darmowego gallery_limit odpowiada liczbie darmowych
					// projektów — egzekwowanie idzie jednolicie przez gallery_limit.
					'projects_free'          => 5,
					'gallery_limit'          => 5,
					'storage_limit_bytes'    => 5 * self::GB,
					'client_limit'           => 25,
					'team_seats'             => 1,
					'sell_extra_photos'      => true,
					'products_prints'        => false,
					'bookings'               => true,
					'automations'            => 'basic',
					'gallery_themes'         => 1,
					'hide_platform_branding' => false,
					'custom_domain'          => false,
					'custom_email_sender'    => false,
					'analytics_level'        => 'basic',
					'api_access'             => false,
					'sms_credits'            => 0,
					'support_level'          => 'email',
				),
			),
			'starter' => new Plan(
				key:          'starter',
				name:         'Starter',
				monthly:      Money::fromMajor( 69 ),
				yearly:       Money::fromMajor( 690 ),
				entitlements: array(
					'gallery_limit'          => 30,
					'storage_limit_bytes'    => 50 * self::GB,
					'client_limit'           => 300,
					'team_seats'             => 1,
					'sell_extra_photos'      => true,
					'products_prints'        => false,
					'bookings'               => true,
					'automations'            => 'basic',
					'gallery_themes'         => 2,
					'hide_platform_branding' => false,
					'custom_domain'          => false,
					'custom_email_sender'    => false,
					'analytics_level'        => 'basic',
					'api_access'             => false,
					'sms_credits'            => 0,
					'support_level'          => 'email',
				),
			),
			'studio'  => new Plan(
				key:          'studio',
				name:         'Studio',
				monthly:      Money::fromMajor( 149 ),
				yearly:       Money::fromMajor( 1490 ),
				recommended:  true,
				entitlements: array(
					'gallery_limit'          => 150,
					'storage_limit_bytes'    => 250 * self::GB,
					'client_limit'           => null,
					'team_seats'             => 3,
					'sell_extra_photos'      => true,
					'products_prints'        => true,
					'bookings'               => true,
					'automations'            => 'full',
					'gallery_themes'         => 5,
					'hide_platform_branding' => true,
					'custom_domain'          => false,
					'custom_email_sender'    => false,
					'analytics_level'        => 'sales',
					'api_access'             => false,
					'sms_credits'            => 0,
					'support_level'          => 'priority',
				),
			),
			'pro'     => new Plan(
				key:          'pro',
				name:         'Pro',
				monthly:      Money::fromMajor( 299 ),
				yearly:       Money::fromMajor( 2990 ),
				entitlements: array(
					'gallery_limit'          => null,
					'storage_limit_bytes'    => self::TB,
					'client_limit'           => null,
					'team_seats'             => 10,
					'sell_extra_photos'      => true,
					'products_prints'        => true,
					'bookings'               => true,
					'automations'            => 'advanced',
					'gallery_themes'         => null,
					'hide_platform_branding' => true,
					'custom_domain'          => true,
					'custom_email_sender'    => true,
					'analytics_level'        => 'full',
					'api_access'             => true,
					'sms_credits'            => 0,
					'support_level'          => 'priority_onboarding',
				),
			),
		);
	}

	public static function get( string $key ): ?Plan {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Plany płatne, w kolejności prezentacji na stronie cennika.
	 *
	 * @return array<string, Plan>
	 */
	public static function paid(): array {
		return array_filter( self::all(), static fn( Plan $plan ): bool => ! $plan->isFree() );
	}

	/**
	 * Dodatki rozliczane cyklicznie (docs/BILLING.md §4).
	 *
	 * @return list<array{key: string, name: string, price: Money, recurring: bool}>
	 */
	public static function addons(): array {
		return array(
			array(
				'key'       => 'storage_100gb',
				'name'      => '+100 GB miejsca',
				'price'     => Money::fromMajor( 25 ),
				'recurring' => true,
			),
			array(
				'key'       => 'team_seat',
				'name'      => 'Dodatkowy użytkownik',
				'price'     => Money::fromMajor( 29 ),
				'recurring' => true,
			),
			array(
				'key'       => 'custom_domain',
				'name'      => 'Własna domena',
				'price'     => Money::fromMajor( 19 ),
				'recurring' => true,
			),
			array(
				'key'       => 'sms_250',
				'name'      => 'Pakiet 250 SMS',
				'price'     => Money::fromMajor( 39 ),
				'recurring' => true,
			),
			array(
				'key'       => 'premium_themes',
				'name'      => 'Motywy premium galerii',
				'price'     => Money::fromMajor( 29 ),
				'recurring' => true,
			),
			array(
				'key'       => 'gallery_reactivation',
				'name'      => 'Reaktywacja zarchiwizowanej galerii',
				'price'     => Money::fromMajor( 9 ),
				'recurring' => false,
			),
		);
	}
}
