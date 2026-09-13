<?php
declare( strict_types=1 );

namespace Kadr\Tests\Studio;

use Kadr\Application\Studio\RegisterStudio;
use Kadr\Domain\Tenancy\StudioSlug;
use Kadr\Domain\Tenancy\StudioStore;
use Kadr\Domain\Tenancy\UserDirectory;
use Kadr\Infrastructure\Database\Platform\TenantStore;
use Kadr\Infrastructure\Security\InMemoryThrottle;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Rejestracja fotografa: konto WordPressa + studio w jednej operacji.
 *
 * Testy jadą na prawdziwym SQL-u i na atrapie katalogu kont — dzięki
 * interfejsowi `UserDirectory` cała ścieżka jest sprawdzalna bez WordPressa.
 */
final class RegisterStudioTest extends TestCase {

	public function testCreatesStudioAndSignsTheOwnerIn(): void {
		$db      = TestDatabase::migrated();
		$users   = new FakeUserDirectory();
		$useCase = new RegisterStudio( new TenantStore( $db ), $users, new InMemoryThrottle() );

		$result = $useCase->handle( 'Studio Kadr', 'foto@example.test', 'dlugie-haslo-1' );

		$this->assertTrue( $result->ok );
		$this->assertSame( 'studio-kadr', $result->value['slug'] );

		// Konto jest zalogowane od razu — proszenie o hasło pięć sekund
		// po jego ustawieniu to tarcie bez korzyści.
		$this->assertSame( array( $users->lastId ), $users->signedIn );

		$row = $db->selectOne(
			'SELECT name, slug, plan, contact_email FROM `wp_kadr_tenants` WHERE id = ?',
			array( $result->value['tenant_id'] )
		);

		$this->assertSame( 'Studio Kadr', (string) $row['name'] );
		$this->assertSame( 'free', (string) $row['plan'] );
		$this->assertSame( 'foto@example.test', (string) $row['contact_email'] );
	}

	public function testOwnerIsLinkedToTheStudio(): void {
		$db      = TestDatabase::migrated();
		$users   = new FakeUserDirectory();
		$useCase = new RegisterStudio( new TenantStore( $db ), $users, new InMemoryThrottle() );

		$result = $useCase->handle( 'Studio Kadr', 'foto@example.test', 'dlugie-haslo-1' );

		$store = new TenantStore( $db );
		$link  = $store->forWpUser( $users->lastId );

		$this->assertSame( $result->value['tenant_id'], (int) $link['tenant_id'] );
		$this->assertSame( 'owner', (string) $link['role'] );
	}

	public function testStudioCreationIsRecordedInTheAuditLog(): void {
		$db      = TestDatabase::migrated();
		$useCase = new RegisterStudio( new TenantStore( $db ), new FakeUserDirectory(), new InMemoryThrottle() );

		$result = $useCase->handle( 'Studio Kadr', 'foto@example.test', 'dlugie-haslo-1' );

		$entry = $db->selectOne(
			'SELECT action, entity_type FROM `wp_kadr_audit_log` WHERE tenant_id = ?',
			array( $result->value['tenant_id'] )
		);

		$this->assertSame( 'studio.created', (string) $entry['action'] );
		$this->assertSame( 'tenant', (string) $entry['entity_type'] );
	}

	public function testSecondStudioWithTheSameNameGetsANumberedSlug(): void {
		$db      = TestDatabase::migrated();
		$store   = new TenantStore( $db );
		$useCase = new RegisterStudio( $store, new FakeUserDirectory(), new InMemoryThrottle() );

		$first  = $useCase->handle( 'Foto Studio', 'jeden@example.test', 'dlugie-haslo-1' );
		$second = $useCase->handle( 'Foto Studio', 'dwa@example.test', 'dlugie-haslo-2' );

		$this->assertSame( 'foto-studio', $first->value['slug'] );
		// Numer, nie losowy ciąg — fotograf ma rozpoznać własny adres.
		$this->assertSame( 'foto-studio-2', $second->value['slug'] );
	}

	/**
	 * Nieudany zapis studia NIE MOŻE zostawić konta bez studia.
	 *
	 * Taki użytkownik loguje się do panelu, nie ma czym zarządzać, a ponowna
	 * rejestracja odbija się o zajęty adres e-mail — sytuacja bez wyjścia,
	 * z której nie da się wyjść bez administratora.
	 */
	public function testAccountIsRemovedWhenTheStudioCannotBeSaved(): void {
		$users   = new FakeUserDirectory();
		$useCase = new RegisterStudio( new BrokenTenantStore(), $users, new InMemoryThrottle() );

		$result = $useCase->handle( 'Studio Kadr', 'foto@example.test', 'dlugie-haslo-1' );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_registration_failed', $result->code );
		$this->assertSame( array( $users->lastId ), $users->deleted );
		$this->assertSame( array(), $users->signedIn );
	}

	public function testValidationPointsAtTheFieldThatIsWrong(): void {
		$db      = TestDatabase::migrated();
		$useCase = new RegisterStudio( new TenantStore( $db ), new FakeUserDirectory(), new InMemoryThrottle() );

		$result = $useCase->handle( '', 'to-nie-adres', 'krotkie' );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_invalid_input', $result->code );

		$params = $result->details['params'];

		$this->assertTrue( isset( $params['studio'] ) );
		$this->assertTrue( isset( $params['email'] ) );
		$this->assertTrue( isset( $params['password'] ) );
	}

	public function testNothingIsCreatedWhenValidationFails(): void {
		$db      = TestDatabase::migrated();
		$users   = new FakeUserDirectory();
		$useCase = new RegisterStudio( new TenantStore( $db ), $users, new InMemoryThrottle() );

		$useCase->handle( '', 'to-nie-adres', 'krotkie' );

		$this->assertSame( 0, $users->created );
		$this->assertSame( 0, (int) $db->selectValue( 'SELECT COUNT(*) FROM `wp_kadr_tenants`' ) );
	}

	public function testExistingEmailIsReportedOnTheEmailField(): void {
		$db      = TestDatabase::migrated();
		$users   = new FakeUserDirectory();
		$users->taken[] = 'zajety@example.test';

		$useCase = new RegisterStudio( new TenantStore( $db ), $users, new InMemoryThrottle() );
		$result  = $useCase->handle( 'Studio Kadr', 'zajety@example.test', 'dlugie-haslo-1' );

		$this->assertTrue( $result->isFailure() );
		$this->assertTrue( isset( $result->details['params']['email'] ) );
		$this->assertSame( 0, $users->created );
	}

	/**
	 * Rejestracja bez limitu jest darmowym generatorem kont WordPressa.
	 */
	public function testRegistrationIsThrottledByAddress(): void {
		$db       = TestDatabase::migrated();
		$throttle = new InMemoryThrottle();
		$useCase  = new RegisterStudio( new TenantStore( $db ), new FakeUserDirectory(), $throttle );

		$blocked = false;

		for ( $i = 0; $i < 30; $i++ ) {
			$result = $useCase->handle( "Studio $i", "foto$i@example.test", 'dlugie-haslo-1', 'hash-adresu' );

			if ( $result->isFailure() && 'kadr_rate_limited' === $result->code ) {
				$blocked = true;
				break;
			}
		}

		$this->assertTrue( $blocked );
	}

	public function testSlugTransliteratesPolishCharacters(): void {
		// „Łąka” ma dać `laka`, a nie `ka` — wycięcie polskich znaków
		// zamiast zamiany produkuje adresy, których nikt nie rozpozna.
		$this->assertSame( 'fotografia-laka', (string) StudioSlug::fromName( 'Fotografia Łąka' ) );
		$this->assertSame( 'zdjecia-zorawskiej', (string) StudioSlug::fromName( 'Zdjęcia Żórawskiej' ) );
	}

	public function testSlugRejectsNamesThatProduceNothing(): void {
		$this->assertNull( StudioSlug::fromName( '!!! ???' ) );
		$this->assertNull( StudioSlug::fromName( '   ' ) );
	}

	public function testReservedSlugGetsANumberInstead(): void {
		$db      = TestDatabase::migrated();
		$useCase = new RegisterStudio( new TenantStore( $db ), new FakeUserDirectory(), new InMemoryThrottle() );

		// „app” koliduje z trasą panelu — studio nie może przejąć tego adresu.
		$result = $useCase->handle( 'App', 'foto@example.test', 'dlugie-haslo-1' );

		$this->assertTrue( $result->ok );
		$this->assertSame( 'app-2', $result->value['slug'] );
	}

	public function testSlugNeverExceedsTheColumnLength(): void {
		$slug = StudioSlug::fromName( str_repeat( 'nazwa studia ', 20 ) );

		$this->assertTrue( strlen( (string) $slug ) <= StudioSlug::MAX_LENGTH );
		$this->assertTrue( strlen( (string) $slug->withSuffix( 12 ) ) <= StudioSlug::MAX_LENGTH );
	}
}

/**
 * Katalog kont w pamięci — zastępuje WordPressa w testach.
 */
final class FakeUserDirectory implements UserDirectory {

	/** @var list<string> */
	public array $taken = array();

	/** @var list<int> */
	public array $signedIn = array();

	/** @var list<int> */
	public array $deleted = array();

	public int $created = 0;
	public int $lastId = 0;

	private int $nextId = 1000;

	public function emailTaken( string $email ): bool {
		return in_array( strtolower( $email ), array_map( 'strtolower', $this->taken ), true );
	}

	public function createOwner( string $email, string $password, string $displayName ): int {
		++$this->created;
		$this->taken[] = $email;
		$this->lastId  = ++$this->nextId;

		return $this->lastId;
	}

	public function signIn( int $userId ): void {
		$this->signedIn[] = $userId;
	}

	public function deleteAccount( int $userId ): void {
		$this->deleted[] = $userId;
	}
}

/**
 * Magazyn studiów, który zawsze pada przy zapisie — do sprawdzenia wycofania.
 */
final class BrokenTenantStore implements StudioStore {

	public function slugTaken( string $slug ): bool {
		return false;
	}

	public function createStudio( string $name, string $slug, string $contactEmail, int $ownerWpUserId ): array {
		throw new \RuntimeException( 'Zapis studia nie powiódł się.' );
	}
}
