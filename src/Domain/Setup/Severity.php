<?php
declare( strict_types=1 );

namespace Kadr\Domain\Setup;

/**
 * Waga ustalenia z przeglądu instalacji.
 *
 * Trzy poziomy, nie pięć. Administrator ma w trzy sekundy wiedzieć, co musi
 * naprawić TERAZ, a co może zostawić — lista, na której wszystko jest
 * „ważne", nie niesie tej informacji.
 */
enum Severity: string {

	/** Platforma nie działa, dopóki tego nie naprawisz. */
	case Blocking = 'blocking';

	/** Działa, ale gorzej albo nie w całości. */
	case Warning = 'warning';

	case Ok = 'ok';
}
