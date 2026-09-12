<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Schema;

/**
 * Definicje tabel wprowadzonych w Session 3 (rdzeń SaaS).
 *
 * ⚠️ REGUŁA KLUCZY UNIKALNYCH W TABELACH TENANTA
 *
 * Klucz unikalny na kolumnach będących identyfikatorami LOKALNYMI dla tenanta
 * (np. `gallery_id`, `asset_id`, `email`) MUSI zaczynać się od `tenant_id`.
 * Bez tego wiersz jednego fotografa blokuje zapis drugiemu, co jest
 * jednocześnie awarią i wyciekiem informacji o istnieniu cudzych danych.
 *
 * Wyjątek: wartości unikalne GLOBALNIE z założenia — `public_id` (ULID)
 * i `token_hash` — zostają bez `tenant_id`, bo ich globalna unikalność
 * jest funkcją bezpieczeństwa, nie przypadkiem.
 *
 * Reguły pilnuje `tests/Domain/SchemaTest.php`.
 *
 * Każda tabela należąca do tenanta jest oznaczona `tenantScoped()`, co
 * dokłada `tenant_id` i indeks zaczynający się od tenanta. Tabele commerce
 * i booking dochodzą w Session 4 i 5 — patrz docs/DATABASE.md.
 */
final class Tables {

	public const TENANTS         = 'kadr_tenants';
	public const TENANT_USERS    = 'kadr_tenant_users';
	public const TENANT_SETTINGS = 'kadr_tenant_settings';
	public const CLIENTS         = 'kadr_clients';
	public const CLIENT_SESSIONS = 'kadr_client_sessions';
	public const GALLERIES       = 'kadr_galleries';
	public const GALLERY_ASSETS  = 'kadr_gallery_assets';
	public const ASSET_VARIANTS  = 'kadr_asset_variants';
	public const GALLERY_ACCESS  = 'kadr_gallery_access';
	public const SELECTIONS      = 'kadr_selections';
	public const SELECTION_ITEMS = 'kadr_selection_items';
	public const ASSET_COMMENTS  = 'kadr_asset_comments';
	public const USAGE_COUNTERS  = 'kadr_usage_counters';
	public const AUDIT_LOG       = 'kadr_audit_log';

	/**
	 * @return list<Table>
	 */
	public static function tenancy(): array {
		return array(
			// Tenant jest jedyną tabelą BEZ tenant_id — to on nim jest.
			Table::named( self::TENANTS )
				->ulid()
				->string( 'name' )
				->string( 'slug' )
				->string( 'status', 32, false, 'active' )   // active | readonly | paused | suspended
				->string( 'plan', 32, false, 'free' )
				->string( 'timezone', 64, false, 'Europe/Warsaw' )
				->string( 'currency', 3, false, 'PLN' )
				->string( 'contact_email' )
				->timestamps()
				->unique( 'slug' )
				->index( 'status' ),

			Table::named( self::TENANT_USERS )
				->tenantScoped()
				->ulid()
				->reference( 'wp_user_id' )
				->string( 'role', 32, false, 'member' )
				->json( 'capabilities' )
				->datetime( 'invited_at' )
				->datetime( 'accepted_at' )
				->timestamps()
				->unique( 'tenant_id', 'wp_user_id' )
				->index( 'wp_user_id' ),

			Table::named( self::TENANT_SETTINGS )
				->tenantScoped()
				->string( 'setting_key' )
				->text( 'setting_value' )
				->bool( 'is_encrypted' )
				->timestamps( false )
				->unique( 'tenant_id', 'setting_key' ),

			Table::named( self::USAGE_COUNTERS )
				->tenantScoped()
				->string( 'metric', 64 )
				->bigint( 'value_current' )
				->datetime( 'recalculated_at' )
				->timestamps( false )
				->unique( 'tenant_id', 'metric' ),
		);
	}

	/**
	 * @return list<Table>
	 */
	public static function clients(): array {
		return array(
			Table::named( self::CLIENTS )
				->tenantScoped()
				->ulid()
				->string( 'first_name', 120 )
				->string( 'last_name', 120, true )
				->string( 'email' )
				->string( 'phone', 32, true )
				->string( 'source', 64, true )
				->string( 'password_hash', 255, true )
				->text( 'note' )
				->timestamps()
				// Ta sama osoba może być klientką dwóch fotografów — unikalność
				// jest w obrębie tenanta, nie globalna (ADR-003).
				->unique( 'tenant_id', 'email' )
				->index( 'tenant_id', 'last_name' ),

			Table::named( self::CLIENT_SESSIONS )
				->tenantScoped()
				->reference( 'client_id' )
				// Trzymamy HASH tokenu, nigdy samego tokenu (docs/SECURITY.md §3).
				->string( 'token_hash', 64 )
				->string( 'purpose', 32, false, 'session' )   // session | magic_link
				->string( 'ip_hash', 64, true )
				->datetime( 'expires_at', false )
				->datetime( 'used_at' )
				->datetime( 'revoked_at' )
				->timestamps( false )
				->unique( 'token_hash' )
				->index( 'tenant_id', 'client_id' )
				->index( 'expires_at' ),
		);
	}

	/**
	 * @return list<Table>
	 */
	public static function galleries(): array {
		return array(
			Table::named( self::GALLERIES )
				->tenantScoped()
				->ulid()
				->reference( 'client_id', true )
				->string( 'title' )
				->string( 'slug' )
				->text( 'intro' )
				->reference( 'cover_asset_id', true )
				->string( 'theme', 32, false, 'paper' )        // paper | noir | minimal
				->string( 'status', 32, false, 'draft' )        // draft | published | expired | archived
				->string( 'lifecycle_state', 32, false, 'active' )
				->int( 'package_limit', true, null )
				->money( 'extra_photo_price', true )
				->bool( 'allow_download' )
				->bool( 'watermark', true )
				->datetime( 'published_at' )
				->datetime( 'expires_at' )
				->timestamps()
				->index( 'tenant_id', 'status' )
				->index( 'tenant_id', 'client_id' )
				->index( 'expires_at' ),

			Table::named( self::GALLERY_ASSETS )
				->tenantScoped()
				->ulid()
				->reference( 'gallery_id' )
				->string( 'original_name', 255 )
				->string( 'storage_path', 255 )
				->string( 'content_hash', 64 )
				->bigint( 'bytes' )
				->int( 'width' )
				->int( 'height' )
				->datetime( 'taken_at' )
				->int( 'sort_order' )
				->string( 'status', 32, false, 'pending' )      // pending | processing | ready | failed
				->timestamps()
				// Najczęstsze zapytanie galerii: zdjęcia jednej galerii po kolejności.
				->index( 'tenant_id', 'gallery_id', 'sort_order' )
				// Wykrywanie duplikatów przy wysyłaniu.
				->index( 'tenant_id', 'content_hash' ),

			Table::named( self::ASSET_VARIANTS )
				->tenantScoped()
				->reference( 'asset_id' )
				->string( 'variant', 16 )                        // thumb | grid | view | wm
				->string( 'format', 8 )                          // avif | webp | jpeg
				->string( 'storage_path', 255 )
				->bigint( 'bytes' )
				->int( 'width' )
				->timestamps( false )
				->unique( 'tenant_id', 'asset_id', 'variant', 'format' ),

			Table::named( self::GALLERY_ACCESS )
				->tenantScoped()
				->reference( 'gallery_id' )
				->string( 'access_type', 32, false, 'link' )     // link | pin | password | invite
				->string( 'token_hash', 64 )
				->string( 'pin_hash', 255, true )
				->int( 'max_uses', true, null )
				->int( 'used_count' )
				->datetime( 'expires_at' )
				->datetime( 'revoked_at' )
				->timestamps( false )
				->unique( 'token_hash' )
				->index( 'tenant_id', 'gallery_id' ),
		);
	}

	/**
	 * @return list<Table>
	 */
	public static function selections(): array {
		return array(
			Table::named( self::SELECTIONS )
				->tenantScoped()
				->ulid()
				->reference( 'gallery_id' )
				->reference( 'client_id', true )
				->string( 'status', 32, false, 'open' )          // open | submitted | reopened
				->int( 'included_count' )
				->int( 'extra_count' )
				->datetime( 'submitted_at' )
				->timestamps( false )
				->unique( 'tenant_id', 'gallery_id' )
				->index( 'tenant_id', 'status' ),

			Table::named( self::SELECTION_ITEMS )
				->tenantScoped()
				->reference( 'selection_id' )
				->reference( 'asset_id' )
				->string( 'state', 16, false, 'favorite' )       // favorite | selected | rejected
				->int( 'position' )
				->timestamps( false )
				// Brak podwójnego wpisu dla tego samego kadru.
				// tenant_id MUSI być w kluczu — bez niego wpis jednego fotografa
				// blokowałby zapis drugiemu (wykryte testem izolacji).
				->unique( 'tenant_id', 'selection_id', 'asset_id' )
				->index( 'tenant_id', 'selection_id', 'state' ),

			Table::named( self::ASSET_COMMENTS )
				->tenantScoped()
				->reference( 'asset_id' )
				->string( 'author_type', 16, false, 'client' )   // client | photographer
				->reference( 'author_id', true )
				->text( 'body', false )
				->timestamps( false )
				->index( 'tenant_id', 'asset_id' ),
		);
	}

	/**
	 * @return list<Table>
	 */
	public static function system(): array {
		return array(
			Table::named( self::AUDIT_LOG )
				->tenantScoped()
				->string( 'action', 64 )
				->string( 'actor_type', 32, false, 'user' )      // user | client | system | platform_admin
				->reference( 'actor_id', true )
				->string( 'entity_type', 64, true )
				->string( 'entity_id', 26, true )
				->json( 'changes' )
				->string( 'ip_hash', 64, true )
				->timestamps( false )
				->index( 'tenant_id', 'created_at' )
				// Historia encji jest zawsze czytana w kontekście tenanta.
				->index( 'tenant_id', 'entity_type', 'entity_id' ),
		);
	}

	/**
	 * Wszystkie tabele bieżącego schematu.
	 *
	 * @return list<Table>
	 */
	public static function all(): array {
		return array_merge(
			self::tenancy(),
			self::clients(),
			self::galleries(),
			self::selections(),
			self::system()
		);
	}
}
