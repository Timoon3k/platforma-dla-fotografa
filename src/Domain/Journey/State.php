<?php
declare( strict_types=1 );

namespace Kadr\Domain\Journey;

enum State: string {

	case Done    = 'done';
	case Current = 'current';
	case Waiting = 'waiting';
}
