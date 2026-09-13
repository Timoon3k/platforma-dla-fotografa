/**
 * Zaznaczanie wielu kadrów i układanie kolejności.
 *
 * Kolejność nie jest kosmetyką: klientka ogląda galerię od góry i pierwsze
 * dwadzieścia kadrów decyduje, czy przewinie dalej. Fotograf układa je
 * ręcznie, zanim wyśle link — i robi to dziesiątkami ruchów pod rząd.
 *
 * Dwie decyzje warsztatowe, które warto rozumieć:
 *
 *  1. **Przeciąganie działa w obrębie tego, co widać.** Siatka jest
 *     wirtualizowana, więc kadr spod kursora nie może być upuszczony
 *     „gdzieś tysiąc pozycji niżej" — tam nie ma elementu DOM, na który
 *     dałoby się celować. Dalekie ruchy robi zaznaczenie plus jeden
 *     przycisk („Na początek"), i to jest ruch, którego fotograf naprawdę
 *     potrzebuje: wybrać otwarcie galerii.
 *  2. **Żądanie opisuje zamiar, nie gotową listę.** Przeglądarka ma wczytaną
 *     tylko część galerii; gdyby przysyłała „całą" kolejność, kadry, do
 *     których jeszcze nie doszła, wypadłyby na koniec.
 */
import { html, useState, useRef, useCallback, formatNumber, Plural, __ } from '../runtime.js';
import { api } from '../api.js';
import { toast } from '../toast.js';
import { confirmDestructive } from '../dialog.js';

export function useArrange( { galleryId, assets, onChanged } ) {
	const [ selected, setSelected ] = useState( () => [] );
	const [ dropTarget, setDropTarget ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const anchor = useRef( null );

	const ids = assets.map( ( asset ) => asset.id );
	const chosen = new Set( selected );

	const clear = useCallback( () => {
		setSelected( [] );
		anchor.current = null;
	}, [] );

	/**
	 * Kliknięcie z Shiftem zaznacza zakres od poprzedniego kliknięcia.
	 * Bez tego wybranie czterdziestu kadrów to czterdzieści kliknięć.
	 */
	const pick = ( asset, event ) => {
		const range = event?.shiftKey && null !== anchor.current;

		if ( range ) {
			const from = ids.indexOf( anchor.current );
			const to = ids.indexOf( asset.id );

			if ( from >= 0 && to >= 0 ) {
				const slice = ids.slice( Math.min( from, to ), Math.max( from, to ) + 1 );

				setSelected( ( previous ) => Array.from( new Set( [ ...previous, ...slice ] ) ) );

				return;
			}
		}

		anchor.current = asset.id;

		setSelected( ( previous ) =>
			previous.includes( asset.id )
				? previous.filter( ( id ) => id !== asset.id )
				: [ ...previous, asset.id ]
		);
	};

	/**
	 * Kadry do przeniesienia — w kolejności, w jakiej leżą w galerii,
	 * a nie w kolejności klikania. Fotograf zaznacza je wzrokiem, nie listą.
	 */
	const ordered = () => ids.filter( ( id ) => chosen.has( id ) );

	const move = async ( before ) => {
		const moving = ordered();

		if ( 0 === moving.length || busy ) {
			return;
		}

		setBusy( true );

		try {
			await api.patch( `galleries/${ galleryId }/assets/order`, {
				move: moving,
				before: before || '',
			} );

			clear();
			await onChanged();
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
			setDropTarget( null );
		}
	};

	const removeSelected = async () => {
		const removing = ordered();

		if ( 0 === removing.length ) {
			return;
		}

		const confirmed = await confirmDestructive( {
			title: __( 'Usunąć zaznaczone zdjęcia?' ),
			message: `${ formatNumber( removing.length ) } ${ Plural.photos( removing.length ) } ${ __( 'zniknie z galerii. Klientka straci do nich dostęp natychmiast.' ) }`,
		} );

		if ( ! confirmed ) {
			return;
		}

		setBusy( true );

		try {
			// Po kolei, nie równolegle: przy stu kadrach równoległe żądania
			// zalałyby serwer, a pasek postępu i tak pokazywałby jedno.
			for ( const id of removing ) {
				await api.delete( `assets/${ id }` );
			}

			clear();
			await onChanged();
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
		}
	};

	/**
	 * Przeciąganie kadru, którego nie ma w zaznaczeniu, przenosi tylko jego —
	 * inaczej chwyt „obok" niepostrzeżenie ruszałby czterdzieści zdjęć.
	 */
	const dragProps = ( asset ) => ( {
		draggable: true,
		onDragStart: ( event ) => {
			if ( ! chosen.has( asset.id ) ) {
				setSelected( [ asset.id ] );
				anchor.current = asset.id;
			}

			event.dataTransfer.effectAllowed = 'move';
			// Pusty ładunek z typem: bez `setData` Firefox nie zaczyna
			// przeciągania w ogóle.
			event.dataTransfer.setData( 'text/plain', asset.id );
		},
		onDragOver: ( event ) => {
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			setDropTarget( asset.id );
		},
		onDragLeave: () => setDropTarget( ( current ) => ( current === asset.id ? null : current ) ),
		onDrop: ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			move( asset.id );
		},
		onDragEnd: () => setDropTarget( null ),
	} );

	return {
		selected,
		dragProps,
		count: selected.length,
		isSelected: ( asset ) => chosen.has( asset.id ),
		isDropTarget: ( asset ) => dropTarget === asset.id,
		busy,
		pick,
		clear,
		selectAll: () => setSelected( ids ),
		moveToFront: () => move( ids.find( ( id ) => ! chosen.has( id ) ) || '' ),
		moveToEnd: () => move( '' ),
		removeSelected,
	};
}

/**
 * Pasek zaznaczenia.
 *
 * Pojawia się dopiero wtedy, gdy coś jest zaznaczone — pusty pasek zajmowałby
 * miejsce i uczył, że można go ignorować.
 */
export function ArrangeBar( { arrange, total } ) {
	if ( 0 === arrange.count ) {
		return null;
	}

	return html`
		<div class="kadr-selectbar" role="group" aria-label=${ __( 'Zaznaczone zdjęcia' ) }>
			<span class="kadr-selectbar__count">
				${ formatNumber( arrange.count ) } ${ Plural.chosen( arrange.count ) }
			</span>

			<div class="kadr-selectbar__actions">
				<button
					type="button"
					class="kadr-btn kadr-btn--secondary"
					disabled=${ arrange.busy }
					onClick=${ arrange.moveToFront }
				>${ __( 'Na początek' ) }</button>

				<button
					type="button"
					class="kadr-btn kadr-btn--secondary"
					disabled=${ arrange.busy }
					onClick=${ arrange.moveToEnd }
				>${ __( 'Na koniec' ) }</button>

				${ arrange.count < total
					? html`<button type="button" class="kadr-btn kadr-btn--ghost" onClick=${ arrange.selectAll }>
							${ __( 'Zaznacz wszystkie wczytane' ) }
					  </button>`
					: null }

				<button
					type="button"
					class="kadr-btn kadr-btn--ghost kadr-btn--danger-ghost"
					disabled=${ arrange.busy }
					onClick=${ arrange.removeSelected }
				>${ __( 'Usuń zaznaczone' ) }</button>

				<button type="button" class="kadr-btn kadr-btn--ghost" onClick=${ arrange.clear }>
					${ __( 'Odznacz' ) }
				</button>
			</div>
		</div>
	`;
}
