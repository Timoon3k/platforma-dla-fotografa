/**
 * Widok jednej galerii: wysyłanie zdjęć i siatka kadrów.
 *
 * Siatka jest wirtualizowana — rysujemy tylko te kadry, które są w oknie,
 * plus zapas nad i pod. Osiemset zdjęć z wesela to osiemset elementów DOM
 * z obrazkami; bez wirtualizacji przeglądarka zaczyna się zacinać przy
 * przewijaniu, a na słabszym laptopie po prostu staje (docs/PERFORMANCE.md §5).
 */
import { html, useState, useEffect, useRef, useCallback, formatBytes, formatNumber, __ } from '../runtime.js';
import { api } from '../api.js';
import { createUploader } from '../upload.js';
import { toast } from '../toast.js';
import { confirmDestructive } from '../dialog.js';
import { LoadFailure } from './today.js';
import { EmptyPanel } from '../table.js';
import { openDrawer } from '../drawer.js';
import { ShareGallery } from './share.js';
import { SelectionPanel } from './selection.js';
import { useArrange, ArrangeBar } from './arrange.js';
import { DeliveryPanel } from './delivery.js';

/** Wysokość wiersza siatki w pikselach — musi zgadzać się z CSS-em. */
const ROW_HEIGHT = 180;
const GAP = 12;

export function GalleryView( { galleryId } ) {
	const [ gallery, setGallery ] = useState( null );
	const [ state, setState ] = useState( {
		status: 'loading',
		assets: [],
		total: 0,
		page: 1,
		hasMore: false,
		error: null,
	} );

	const loading = useRef( false );

	const reload = useCallback( async () => {
		loading.current = true;

		try {
			const { data, meta } = await api.get( `galleries/${ galleryId }/assets` );

			setState( {
				status: 'ready',
				assets: data,
				total: meta.total,
				page: 1,
				hasMore: Boolean( meta.has_more ),
				error: null,
			} );
		} catch ( error ) {
			setState( { status: 'error', assets: [], total: 0, page: 1, hasMore: false, error } );
		} finally {
			loading.current = false;
		}
	}, [ galleryId ] );

	/**
	 * Doczytanie kolejnej strony, gdy przewijanie dochodzi do końca
	 * wczytanych kadrów.
	 *
	 * Galeria ślubna to osiemset zdjęć. Ściągnięcie wszystkich metadanych
	 * na wejściu opóźniłoby pierwszy kadr o kilka sekund, a fotograf zwykle
	 * chce zobaczyć początek i tyle.
	 */
	const loadMore = useCallback( async () => {
		if ( loading.current || ! state.hasMore ) {
			return;
		}

		loading.current = true;

		try {
			const next = state.page + 1;
			const { data, meta } = await api.get( `galleries/${ galleryId }/assets`, { params: { page: next } } );

			setState( ( previous ) => ( {
				...previous,
				assets: [ ...previous.assets, ...data ],
				page: next,
				hasMore: Boolean( meta.has_more ),
			} ) );
		} catch ( error ) {
			toast.error( error, loadMore );
		} finally {
			loading.current = false;
		}
	}, [ galleryId, state.hasMore, state.page ] );

	const [ uploader ] = useState( () =>
		createUploader( galleryId, { onFinished: () => reload() } )
	);

	/**
	 * Dane samej galerii — potrzebne do udostępniania i okładki.
	 *
	 * Lista zwraca komplet pól, więc wyciągamy tę jedną pozycję zamiast
	 * dokładać osobny endpoint dla pojedynczej galerii.
	 */
	const loadGallery = useCallback( async () => {
		try {
			const { data } = await api.get( 'galleries' );

			setGallery( data.find( ( row ) => row.id === galleryId ) || null );
		} catch ( error ) {
			// Brak metadanych nie może zablokować widoku zdjęć.
		}
	}, [ galleryId ] );

	useEffect( () => {
		reload();
		loadGallery();

		return () => uploader.stop();
	}, [ reload, loadGallery, uploader ] );

	const share = () => {
		if ( ! gallery ) {
			return;
		}

		openDrawer( {
			title: __( 'Wyślij klientowi' ),
			content: () => html`<${ShareGallery} gallery=${ gallery } />`,
		} );
	};

	const setCover = async ( asset ) => {
		try {
			await api.patch( `galleries/${ galleryId }`, { cover_asset_id: asset.id } );
			toast.success( __( 'Okładka ustawiona.' ) );
			loadGallery();
		} catch ( error ) {
			toast.error( error );
		}
	};

	if ( 'error' === state.status ) {
		return html`<${LoadFailure} error=${ state.error } onRetry=${ reload } />`;
	}

	return html`
		<div class="kadr-view__head">
			<div>
				<h1 class="kadr-view__title">${ gallery ? gallery.title : __( 'Zdjęcia' ) }</h1>
				<p class="kadr-view__subtitle">
					${ formatNumber( state.total ) } ${ __( 'zdjęć w tej galerii' ) }
				</p>
			</div>
			${ gallery
				? html`<div class="kadr-view__actions">
						<button type="button" class="kadr-btn kadr-btn--primary" onClick=${ share }>
							${ __( 'Wyślij klientowi' ) }
						</button>
				  </div>`
				: null }
		</div>

		${ gallery
			? html`<${SelectionPanel} galleryId=${ galleryId } packageLimit=${ gallery.package_limit } />`
			: null }

		${ gallery ? html`<${DeliveryPanel} galleryId=${ galleryId } />` : null }

		<${DropZone} onFiles=${ uploader.add } />
		<${UploadQueue} uploader=${ uploader } />

		${ 0 === state.assets.length && 'ready' === state.status
			? html`<${EmptyPanel}
					title=${ __( 'Galeria jest pusta' ) }
					text=${ __( 'Przeciągnij zdjęcia albo wybierz je z dysku. Podgląd i warianty powstaną w tle.' ) }
			  />`
			: html`<${PhotoGrid}
					assets=${ state.assets }
					total=${ state.total }
					galleryId=${ galleryId }
					coverId=${ gallery?.cover_asset_id || null }
					onRemoved=${ reload }
					onNeedMore=${ loadMore }
					onCover=${ setCover }
			  />` }
	`;
}

/**
 * Pole zrzutu plików.
 *
 * Przeciąganie i klikanie prowadzą do tego samego — fotograf, który
 * przyszedł z Lightrooma, przeciągnie; ten, który pracuje na laptopie
 * bez myszy, kliknie.
 */
function DropZone( { onFiles } ) {
	const [ over, setOver ] = useState( false );
	const input = useRef( null );

	const drop = ( event ) => {
		event.preventDefault();
		setOver( false );

		if ( event.dataTransfer?.files?.length ) {
			onFiles( event.dataTransfer.files );
		}
	};

	return html`
		<div
			class="kadr-drop ${ over ? 'kadr-drop--over' : '' }"
			onDragOver=${ ( event ) => {
				event.preventDefault();
				setOver( true );
			} }
			onDragLeave=${ () => setOver( false ) }
			onDrop=${ drop }
		>
			<p class="kadr-drop__title">${ __( 'Przeciągnij zdjęcia tutaj' ) }</p>
			<p class="kadr-drop__hint">${ __( 'JPEG, PNG, WebP, AVIF, HEIC albo TIFF. Do 200 MB na plik.' ) }</p>
			<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ () => input.current?.click() }>
				${ __( 'Wybierz pliki' ) }
			</button>
			<input
				ref=${ input }
				class="kadr-sr-only"
				type="file"
				multiple
				accept="image/*"
				aria-label=${ __( 'Wybierz zdjęcia do wysłania' ) }
				onChange=${ ( event ) => {
					if ( event.target.files?.length ) {
						onFiles( event.target.files );
					}

					// Wyczyszczenie pozwala wybrać ten sam plik ponownie —
					// bez tego powtórny wybór nie wywołuje zdarzenia.
					event.target.value = '';
				} }
			/>
		</div>
	`;
}

const STATE_LABEL = {
	waiting: 'W kolejce',
	hashing: 'Sprawdzam plik',
	sending: 'Wysyłam',
	finishing: 'Kończę',
	done: 'Gotowe',
	duplicate: 'Już jest',
	failed: 'Błąd',
	cancelled: 'Anulowano',
};

function UploadQueue( { uploader } ) {
	const items = uploader.items.value;

	if ( 0 === items.length ) {
		return null;
	}

	const active = items.filter( ( item ) => [ 'waiting', 'hashing', 'sending', 'finishing' ].includes( item.state ) );
	const sent = items.reduce( ( sum, item ) => sum + item.sent, 0 );
	const total = items.reduce( ( sum, item ) => sum + item.bytes, 0 );

	return html`
		<section class="kadr-panel" aria-labelledby="kadr-upload-queue">
			<div class="kadr-upload__head">
				<h2 class="kadr-panel__title" id="kadr-upload-queue">
					${ active.length > 0
						? `${ __( 'Wysyłanie' ) } — ${ formatNumber( active.length ) } ${ __( 'w toku' ) }`
						: __( 'Wysyłanie zakończone' ) }
				</h2>
				<button type="button" class="kadr-btn kadr-btn--ghost" onClick=${ uploader.clearFinished }>
					${ __( 'Wyczyść ukończone' ) }
				</button>
			</div>

			${ total > 0
				? html`<div class="kadr-meter kadr-meter--tall">
						<div class="kadr-meter__fill" style=${ `width:${ Math.round( ( sent / total ) * 100 ) }%` }></div>
				  </div>
				  <p class="kadr-upload__total">
						${ formatBytes( sent ) } ${ __( 'z' ) } ${ formatBytes( total ) }
				  </p>`
				: null }

			<ul class="kadr-upload__list">
				${ items.map(
					( item ) => html`
						<li key=${ item.id } class="kadr-upload__row kadr-upload__row--${ item.state }">
							<span class="kadr-upload__name">${ item.name }</span>
							<span class="kadr-upload__state">
								${ __( STATE_LABEL[ item.state ] || item.state ) }
								${ item.message ? html`<span class="kadr-upload__message">${ item.message }</span>` : null }
							</span>
							<span class="kadr-upload__size">${ formatBytes( item.bytes ) }</span>
							${ 'failed' === item.state || 'cancelled' === item.state
								? html`<button type="button" class="kadr-btn kadr-btn--ghost" onClick=${ () => uploader.retry( item.id ) }>
										${ __( 'Ponów' ) }
								  </button>`
								: null }
							${ [ 'waiting', 'hashing', 'sending', 'finishing' ].includes( item.state )
								? html`<button type="button" class="kadr-btn kadr-btn--ghost" onClick=${ () => uploader.cancel( item.id ) }>
										${ __( 'Anuluj' ) }
								  </button>`
								: null }
						</li>
					`
				) }
			</ul>
		</section>
	`;
}

/**
 * Siatka kadrów z wirtualizacją.
 *
 * Liczymy, ile wierszy mieści się w oknie, i rysujemy tylko je. Reszta
 * wysokości jest zarezerwowana wyściółką, więc pasek przewijania zachowuje
 * się normalnie i nie skacze.
 */
function PhotoGrid( { assets, total, galleryId, coverId, onRemoved, onNeedMore, onCover } ) {
	const container = useRef( null );
	const [ viewport, setViewport ] = useState( { top: 0, height: 800, columns: 6 } );
	const arrange = useArrange( { galleryId, assets, onChanged: onRemoved } );

	useEffect( () => {
		const element = container.current;

		if ( ! element ) {
			return;
		}

		const measure = () => {
			const scroller = element.closest( '.kadr-app__main' ) || document.documentElement;
			const width = element.clientWidth || 1;

			setViewport( {
				top: Math.max( 0, scroller.scrollTop - element.offsetTop ),
				height: scroller.clientHeight,
				columns: Math.max( 2, Math.floor( width / ( ROW_HEIGHT + GAP ) ) ),
			} );
		};

		measure();

		const scroller = element.closest( '.kadr-app__main' ) || window;

		scroller.addEventListener( 'scroll', measure, { passive: true } );
		window.addEventListener( 'resize', measure );

		return () => {
			scroller.removeEventListener( 'scroll', measure );
			window.removeEventListener( 'resize', measure );
		};
	}, [ assets.length ] );

	const rowStride = ROW_HEIGHT + GAP;
	// Wysokość liczymy z CAŁEJ liczby zdjęć, nie z wczytanych: inaczej pasek
	// przewijania rósłby skokowo przy każdej doczytanej stronie i wyrywał
	// widok spod kursora.
	const rows = Math.ceil( Math.max( assets.length, total ) / viewport.columns );
	// Zapas dwóch wierszy nad i pod oknem: przy szybkim przewijaniu
	// użytkownik nie zdąży zobaczyć pustego miejsca.
	const firstRow = Math.max( 0, Math.floor( viewport.top / rowStride ) - 2 );
	const lastRow = Math.min( rows, Math.ceil( ( viewport.top + viewport.height ) / rowStride ) + 2 );

	const visible = assets.slice( firstRow * viewport.columns, lastRow * viewport.columns );

	// Doczytujemy, gdy widoczne okno zbliża się do końca wczytanej listy —
	// dwa wiersze zapasu wystarczą, żeby nie zobaczyć pustego miejsca.
	useEffect( () => {
		if ( assets.length < total && lastRow * viewport.columns >= assets.length - viewport.columns * 2 ) {
			onNeedMore();
		}
	}, [ lastRow, viewport.columns, assets.length, total, onNeedMore ] );

	const remove = async ( asset ) => {
		const confirmed = await confirmDestructive( {
			title: __( 'Usunąć zdjęcie?' ),
			message: `${ asset.name } ${ __( 'zniknie z galerii. Klient straci do niego dostęp natychmiast.' ) }`,
		} );

		if ( ! confirmed ) {
			return;
		}

		try {
			await api.delete( `assets/${ asset.id }` );
			onRemoved();
		} catch ( error ) {
			toast.error( error );
		}
	};

	return html`
		<${ArrangeBar} arrange=${ arrange } total=${ assets.length } />

		<div
			class="kadr-grid ${ arrange.count > 0 ? 'kadr-grid--picking' : '' }"
			ref=${ container }
			style=${ `height:${ rows * rowStride }px` }
			onKeyDown=${ ( event ) => {
				if ( 'Escape' === event.key && arrange.count > 0 ) {
					arrange.clear();
				}
			} }
		>
			<div class="kadr-grid__window" style=${ `transform:translateY(${ firstRow * rowStride }px)` }>
				${ visible.map(
					( asset ) => html`
						<figure
							class="kadr-grid__item ${ arrange.isSelected( asset ) ? 'kadr-grid__item--picked' : '' } ${ arrange.isDropTarget( asset ) ? 'kadr-grid__item--drop' : '' }"
							key=${ asset.id }
							...${ arrange.dragProps( asset ) }
						>
							<button
								type="button"
								class="kadr-grid__pick"
								aria-pressed=${ arrange.isSelected( asset ) }
								aria-label=${ `${ __( 'Zaznacz' ) }: ${ asset.name }` }
								title=${ __( 'Zaznacz (Shift — zakres)' ) }
								onClick=${ ( event ) => arrange.pick( asset, event ) }
							>✓</button>
							${ 'ready' === asset.status && asset.thumb
								? html`<img
										class="kadr-grid__image"
										src=${ asset.thumb }
										alt=${ asset.name }
										width=${ asset.width }
										height=${ asset.height }
										loading="lazy"
										decoding="async"
								  />`
								: html`<span class="kadr-grid__pending" aria-label=${ __( 'Przetwarzanie' ) }></span>` }
							${ coverId === asset.id
								? html`<span class="kadr-grid__badge">${ __( 'Okładka' ) }</span>`
								: null }
							<figcaption class="kadr-grid__caption">
								<span>${ asset.name }</span>
								<span class="kadr-grid__tools">
									${ 'ready' === asset.status && coverId !== asset.id
										? html`<button
												type="button"
												class="kadr-grid__tool"
												aria-label=${ `${ __( 'Ustaw jako okładkę' ) }: ${ asset.name }` }
												title=${ __( 'Ustaw jako okładkę' ) }
												onClick=${ () => onCover( asset ) }
										  >★</button>`
										: null }
									<button
										type="button"
										class="kadr-grid__tool kadr-grid__tool--danger"
										aria-label=${ `${ __( 'Usuń' ) } ${ asset.name }` }
										onClick=${ () => remove( asset ) }
									>×</button>
								</span>
							</figcaption>
						</figure>
					`
				) }
			</div>
		</div>
	`;
}
