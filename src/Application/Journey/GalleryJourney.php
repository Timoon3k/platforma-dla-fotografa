<?php
declare( strict_types=1 );

namespace Kadr\Application\Journey;

use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Journey\Stage;
use Kadr\Domain\Journey\Step;
use Kadr\Domain\Journey\Timeline;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;

/**
 * Oś procesu dla jednej galerii — zebranie faktów i rzut na etapy.
 *
 * Wszystkie fakty pochodzą z danych, które i tak istnieją. Nie ma tabeli
 * `journey`, nie ma kolumny ze statusem etapu, nie ma przejść do utrzymania
 * — powody w `Domain\Journey\Timeline`.
 */
final readonly class GalleryJourney {

	public function __construct(
		private AssetRepository $assets,
		private GalleryAccessRepository $access,
		private SelectionRepository $selections,
		private SelectionItemRepository $items,
		private ArchiveRepository $archives,
		private DownloadTokenRepository $tokens,
	) {}

	/**
	 * @param array<string, mixed> $gallery Wiersz galerii.
	 * @return list<Step>
	 */
	public function forGallery( array $gallery ): array {
		return Timeline::project( $this->facts( $gallery ) );
	}

	/**
	 * Oś wraz z etapem bieżącym — to, czego potrzebuje widok.
	 *
	 * @param array<string, mixed> $gallery
	 * @return array{steps: list<Step>, current: Step, complete: bool}
	 */
	public function summary( array $gallery ): array {
		$steps = $this->forGallery( $gallery );

		return array(
			'steps'    => $steps,
			'current'  => Timeline::current( $steps ),
			'complete' => Timeline::isComplete( $steps ),
		);
	}

	/**
	 * @param array<string, mixed> $gallery
	 * @return array<string, mixed>
	 */
	private function facts( array $gallery ): array {
		$galleryId = (int) $gallery['id'];

		$links  = $this->access->forGallery( $galleryId );
		$opened = 0;
		$issued = false;

		foreach ( $links as $link ) {
			// Unieważniony link nie daje dostępu, więc nie liczy się jako
			// „wysłany" — ale jeśli klientka zdążyła go otworzyć, to się
			// wydarzyło i z osi nie znika.
			if ( null === $link['revoked_at'] ) {
				$issued = true;
			}

			$opened += (int) $link['used_count'];
		}

		$selection = $this->selections->forGallery( $galleryId );
		$marks     = 0;

		if ( null !== $selection ) {
			$marks = $this->items->countInState( (int) $selection['id'], SelectionState::Selected )
				+ $this->items->countInState( (int) $selection['id'], SelectionState::Favorite );
		}

		// Paczka z wybranymi zdjęciami jest tą, na którą czeka klientka;
		// „cała galeria" to zwykle kopia dla fotografa.
		$archive = $this->archives->forGallery( $galleryId, ArchiveScope::Selected );

		return array(
			'photos'           => $this->assets->countForGallery( $galleryId ),
			'published'        => 'published' === (string) $gallery['status'],
			'published_at'     => $gallery['published_at'] ?? null,
			'link_issued'      => $issued,
			'link_opened'      => $opened,
			'selection_status' => null === $selection ? null : (string) $selection['status'],
			'selection_marks'  => $marks,
			'submitted_at'     => $selection['submitted_at'] ?? null,
			'archive_status'   => null === $archive ? null : (string) $archive['status'],
			'archive_ready_at' => $archive['ready_at'] ?? null,
			'downloaded'       => $this->wasDownloaded( $galleryId ),
		);
	}

	private function wasDownloaded( int $galleryId ): bool {
		foreach ( $this->tokens->forGallery( $galleryId ) as $token ) {
			if ( (int) $token['used_count'] > 0 ) {
				return true;
			}
		}

		return false;
	}
}
