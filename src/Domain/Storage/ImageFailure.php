<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

final class ImageFailure extends \RuntimeException {

	public static function unreadable( string $reason = '' ): self {
		return new self( rtrim( 'Nie udało się odczytać pliku obrazu. ' . $reason ) );
	}

	public static function unsupportedFormat( ImageFormat $format, string $processor ): self {
		return new self( sprintf( 'Format %s nie jest obsługiwany przez %s.', $format->value, $processor ) );
	}

	public static function processingFailed( string $reason = '' ): self {
		return new self( rtrim( 'Przetwarzanie obrazu nie powiodło się. ' . $reason ) );
	}
}
