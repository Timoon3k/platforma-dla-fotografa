<?php
declare( strict_types=1 );

namespace Kadr\Domain\Audit;

/**
 * Zdarzenia, które trafiają do dziennika.
 *
 * Lista jest zamknięta celowo. Dziennik, do którego wolno dopisać dowolny
 * napis, po pół roku jest workiem na śmieci i nikt go nie czyta — a to jest
 * rejestr, do którego sięga się wtedy, gdy klientka pyta „kto pobrał moje
 * zdjęcia" albo gdy trzeba odtworzyć, co się stało z galerią.
 *
 * Zakres obowiązkowy wyznacza `docs/SECURITY.md` §6.
 */
enum AuditEvent: string {

	case GalleryPublished  = 'gallery.published';
	case GalleryUnpublished = 'gallery.unpublished';
	case GalleryDeleted    = 'gallery.deleted';

	case ShareLinkIssued   = 'share_link.issued';
	case ShareLinkRevoked  = 'share_link.revoked';

	case SelectionSubmitted = 'selection.submitted';
	case SelectionReopened  = 'selection.reopened';

	case DownloadTokenIssued = 'download_token.issued';
	case DownloadTokenUsed   = 'download_token.used';
	case DownloadTokenRevoked = 'download_token.revoked';

	case ArchiveRequested = 'archive.requested';
	case ArchiveReady     = 'archive.ready';
	case ArchiveFailed    = 'archive.failed';

	case ClientInvited = 'client.invited';
	case ClientDeleted = 'client.deleted';

	/**
	 * Opis po polsku, dla fotografa — dziennik ma być czytelny bez
	 * tłumaczenia nazw technicznych (skill photography-workflow §9).
	 */
	public function label(): string {
		return match ( $this ) {
			self::GalleryPublished    => 'Galeria opublikowana',
			self::GalleryUnpublished  => 'Publikacja wycofana',
			self::GalleryDeleted      => 'Galeria usunięta',
			self::ShareLinkIssued     => 'Wydano link dla klientki',
			self::ShareLinkRevoked    => 'Link unieważniony',
			self::SelectionSubmitted  => 'Klientka wysłała wybór',
			self::SelectionReopened   => 'Wybór otwarty ponownie',
			self::DownloadTokenIssued => 'Wydano link do pobrania',
			self::DownloadTokenUsed   => 'Pobrano pliki',
			self::DownloadTokenRevoked => 'Link do pobrania unieważniony',
			self::ArchiveRequested    => 'Zlecono przygotowanie plików',
			self::ArchiveReady        => 'Pliki gotowe do pobrania',
			self::ArchiveFailed       => 'Przygotowanie plików nie powiodło się',
			self::ClientInvited       => 'Zaproszono klientkę',
			self::ClientDeleted       => 'Klientka usunięta',
		};
	}
}
