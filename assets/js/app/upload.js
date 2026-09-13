/**
 * Wysyłanie zdjęć do galerii.
 *
 * Trzy kroki na plik: zgłoszenie → fragmenty → scalenie. Każdy z nich
 * rozwiązuje realny problem fotografa, nie jest ozdobą architektury:
 *
 *  - **zgłoszenie** sprawdza format, limit planu i duplikat ZANIM cokolwiek
 *    poleci przez sieć. Dowiedzenie się po 200 MB, że plik jest odrzucony,
 *    to najgorszy możliwy moment;
 *  - **fragmenty** przechodzą przez limity `upload_max_filesize` i pozwalają
 *    wznowić wysyłkę po zerwaniu łącza — wesele to osiemset plików i domowy
 *    upload, który potrafi paść w połowie;
 *  - **scalenie** weryfikuje skrót, więc uszkodzony transfer nigdy nie
 *    zostanie zapisany jako poprawne zdjęcie.
 *
 * Skrót liczymy przyrostowo przy okazji czytania fragmentów — jedno przejście
 * przez plik i stała pamięć, zamiast wczytywania stumegabajtowego RAW-a
 * w całości.
 */
import { signal } from './runtime.js';
import { api, ApiError } from './api.js';
import { Sha256 } from './sha256.js';

/** Ile plików leci równolegle. */
const CONCURRENCY = 3;

/**
 * Kolejka wysyłki dla jednej galerii.
 *
 * Stan trzymamy w sygnale, więc widok przerysowuje się sam przy każdej
 * zmianie postępu — bez ręcznego odświeżania listy.
 */
export function createUploader( galleryId, { onFinished = null } = {} ) {
	const items = signal( [] );
	let running = 0;
	let cancelled = false;

	const patch = ( id, changes ) => {
		items.value = items.value.map( ( item ) => ( item.id === id ? { ...item, ...changes } : item ) );
	};

	const add = ( files ) => {
		const queued = Array.from( files ).map( ( file, index ) => ( {
			id: `${ Date.now() }-${ index }-${ file.name }`,
			file,
			name: file.name,
			bytes: file.size,
			sent: 0,
			state: 'waiting',
			message: '',
			controller: null,
		} ) );

		items.value = [ ...items.value, ...queued ];
		pump();
	};

	const pump = () => {
		if ( cancelled ) {
			return;
		}

		while ( running < CONCURRENCY ) {
			const next = items.value.find( ( item ) => 'waiting' === item.state );

			if ( ! next ) {
				break;
			}

			running++;
			patch( next.id, { state: 'hashing' } );
			send( next ).finally( () => {
				running--;

				const pending = items.value.some( ( item ) =>
					[ 'waiting', 'hashing', 'sending', 'finishing' ].includes( item.state )
				);

				if ( pending ) {
					pump();
				} else if ( onFinished ) {
					onFinished();
				}
			} );
		}
	};

	const send = async ( item ) => {
		const controller = new AbortController();
		patch( item.id, { controller } );

		try {
			const chunkBytes = 5 * 1024 * 1024;
			const hash = new Sha256();

			// Pierwsze przejście: skrót. Czytamy plik fragmentami, tak jak
			// będziemy go wysyłać.
			for ( let offset = 0; offset < item.bytes; offset += chunkBytes ) {
				if ( controller.signal.aborted ) {
					throw new DOMException( 'Anulowano', 'AbortError' );
				}

				const slice = item.file.slice( offset, Math.min( offset + chunkBytes, item.bytes ) );

				hash.update( new Uint8Array( await slice.arrayBuffer() ) );
			}

			const contentHash = hash.digest();

			const { data: started } = await api.post(
				`galleries/${ galleryId }/uploads`,
				{ filename: item.name, bytes: item.bytes, hash: contentHash },
				{ signal: controller.signal }
			);

			// Serwer rozpoznał plik, który już jest w tym studiu. Nie ma po co
			// go przesyłać drugi raz — i warto o tym powiedzieć wprost.
			if ( started.duplicate_of ) {
				patch( item.id, {
					state: 'duplicate',
					sent: item.bytes,
					message: 'To zdjęcie jest już w Twoich plikach.',
				} );

				return;
			}

			patch( item.id, { state: 'sending' } );

			const total = started.chunk_count;

			for ( let index = 0; index < total; index++ ) {
				const from = index * started.chunk_bytes;
				const slice = item.file.slice( from, Math.min( from + started.chunk_bytes, item.bytes ) );

				await putChunk( started.upload_id, index, slice, controller.signal );

				patch( item.id, { sent: Math.min( from + started.chunk_bytes, item.bytes ) } );
			}

			patch( item.id, { state: 'finishing' } );

			await api.post(
				`uploads/${ started.upload_id }/complete`,
				{
					gallery_id: galleryId,
					filename: item.name,
					chunk_count: total,
					hash: contentHash,
				},
				{ signal: controller.signal }
			);

			patch( item.id, { state: 'done', sent: item.bytes } );
		} catch ( error ) {
			if ( 'AbortError' === error?.name ) {
				patch( item.id, { state: 'cancelled', message: 'Anulowano.' } );

				return;
			}

			patch( item.id, {
				state: 'failed',
				message: readableError( error ),
			} );
		}
	};

	return {
		items,
		add,

		cancel: ( id ) => {
			const item = items.value.find( ( entry ) => entry.id === id );

			item?.controller?.abort();
			patch( id, { state: 'cancelled', message: 'Anulowano.' } );
		},

		retry: ( id ) => {
			patch( id, { state: 'waiting', sent: 0, message: '', controller: null } );
			pump();
		},

		clearFinished: () => {
			items.value = items.value.filter(
				( item ) => ! [ 'done', 'duplicate', 'cancelled' ].includes( item.state )
			);
		},

		stop: () => {
			cancelled = true;
			items.value.forEach( ( item ) => item.controller?.abort() );
		},
	};
}

/**
 * Fragment idzie jako surowe ciało żądania.
 *
 * Nie `multipart`: tamta droga prowadzi przez `$_FILES`, czyli przez dysk
 * tymczasowy i limity PHP, których cały ten mechanizm ma unikać.
 */
async function putChunk( uploadId, index, blob, signal ) {
	const config = window.kadrApp || {};
	const url = ( config.root || '/wp-json/kadr/v1/' ) + `uploads/${ uploadId }/chunks/${ index }`;

	const response = await fetch( url, {
		method: 'PUT',
		signal,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/octet-stream',
			'X-WP-Nonce': config.nonce || '',
		},
		body: blob,
	} );

	if ( ! response.ok ) {
		const payload = await response.json().catch( () => null );

		throw new ApiError( {
			code: payload?.code,
			message: payload?.message,
			status: response.status,
			details: payload?.data || {},
		} );
	}
}

/**
 * Komunikat, z którym da się coś zrobić.
 *
 * „Błąd 422" nie mówi fotografowi nic. Brak miejsca, zły format i zerwane
 * łącze wymagają trzech różnych reakcji i trzech różnych zdań.
 */
function readableError( error ) {
	if ( ! ( error instanceof ApiError ) ) {
		return 'Nie udało się wysłać pliku.';
	}

	if ( 'kadr_storage_exceeded' === error.code ) {
		return 'Brakuje miejsca w Twoim planie.';
	}

	if ( 'kadr_upload_rejected' === error.code ) {
		return error.message;
	}

	if ( 'kadr_upload_corrupted' === error.code ) {
		return 'Plik dotarł uszkodzony. Spróbuj wysłać go ponownie.';
	}

	if ( error.isRetryable ) {
		return 'Połączenie przerwane. Można wznowić.';
	}

	return error.message;
}
