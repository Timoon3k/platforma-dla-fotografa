<?php
declare( strict_types=1 );

namespace Kadr\Domain\Printing;

enum Grade: string {

	case Good       = 'good';
	case Acceptable = 'acceptable';
	case Poor       = 'poor';
}
