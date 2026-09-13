/**
 * SHA-256 liczony przyrostowo, blok po bloku.
 *
 * Po co własna implementacja, skoro przeglądarka ma `crypto.subtle.digest`:
 * tamta funkcja przyjmuje CAŁĄ zawartość naraz. Plik RAW z wesela potrafi
 * mieć sto megabajtów, a fotograf wysyła ich osiemset — wczytanie każdego
 * w całości do pamięci, żeby policzyć skrót, jest kosztem, którego nie ma
 * po co ponosić.
 *
 * Tutaj skrót powstaje przy okazji czytania fragmentów, które i tak lecą
 * na serwer: jedno przejście przez plik, stała pamięć.
 *
 * Implementacja jest wierna RFC 6234. Sprawdzana wektorami testowymi
 * w `tools/check-panel.mjs` — bez tego byłaby to najgorsza możliwa rzecz:
 * kryptografia napisana na oko.
 */

const K = new Uint32Array( [
	0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
	0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
	0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
	0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
	0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
	0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
	0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
	0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
] );

const rotr = ( value, bits ) => ( value >>> bits ) | ( value << ( 32 - bits ) );

export class Sha256 {
	constructor() {
		this.state = new Uint32Array( [
			0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a,
			0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19,
		] );

		// Bufor na niepełny blok: dane przychodzą kawałkami dowolnej
		// długości, a algorytm przetwarza dokładnie po 64 bajty.
		this.buffer = new Uint8Array( 64 );
		this.buffered = 0;
		this.totalBytes = 0;
		this.words = new Uint32Array( 64 );
		this.result = null;
	}

	/**
	 * @param {Uint8Array} bytes
	 */
	update( bytes ) {
		if ( null !== this.result ) {
			throw new Error( 'Sha256: nie można dopisywać danych po policzeniu skrótu.' );
		}

		this.totalBytes += bytes.length;

		let offset = 0;

		if ( this.buffered > 0 ) {
			const needed = Math.min( 64 - this.buffered, bytes.length );

			this.buffer.set( bytes.subarray( 0, needed ), this.buffered );
			this.buffered += needed;
			offset = needed;

			if ( 64 === this.buffered ) {
				this.block( this.buffer, 0 );
				this.buffered = 0;
			}
		}

		while ( offset + 64 <= bytes.length ) {
			this.block( bytes, offset );
			offset += 64;
		}

		if ( offset < bytes.length ) {
			this.buffer.set( bytes.subarray( offset ), 0 );
			this.buffered = bytes.length - offset;
		}

		return this;
	}

	/**
	 * @return {string} skrót zapisany szesnastkowo
	 */
	digest() {
		// Policzenie skrótu domyka stan (przetwarza dopełnienie), więc drugie
		// wywołanie zwróciłoby śmieci. Zapamiętujemy wynik: w ścieżce wysyłania
		// zły skrót oznacza odrzucenie poprawnie przesłanego pliku, a takiej
		// awarii nie widać — wygląda jak uszkodzony transfer.
		if ( null !== this.result ) {
			return this.result;
		}

		const bitLength = this.totalBytes * 8;
		// Dopełnienie: bajt 0x80, zera, a na końcu długość w bitach
		// jako 64-bitowa liczba big-endian.
		const padding = new Uint8Array( this.buffered < 56 ? 64 : 128 );

		padding.set( this.buffer.subarray( 0, this.buffered ), 0 );
		padding[ this.buffered ] = 0x80;

		const view = new DataView( padding.buffer );

		// Długość w bitach nie mieści się w 32 bitach dla plików powyżej
		// pół gigabajta, więc zapisujemy ją jako liczbę 64-bitową.
		view.setUint32( padding.length - 8, Math.floor( bitLength / 0x100000000 ), false );
		view.setUint32( padding.length - 4, bitLength >>> 0, false );

		for ( let offset = 0; offset < padding.length; offset += 64 ) {
			this.block( padding, offset );
		}

		let hex = '';

		for ( let i = 0; i < 8; i++ ) {
			hex += this.state[ i ].toString( 16 ).padStart( 8, '0' );
		}

		this.result = hex;

		return hex;
	}

	/**
	 * @param {Uint8Array} bytes
	 * @param {number} offset
	 */
	block( bytes, offset ) {
		const w = this.words;

		for ( let i = 0; i < 16; i++ ) {
			const p = offset + i * 4;

			w[ i ] = ( bytes[ p ] << 24 ) | ( bytes[ p + 1 ] << 16 ) | ( bytes[ p + 2 ] << 8 ) | bytes[ p + 3 ];
		}

		for ( let i = 16; i < 64; i++ ) {
			const s0 = rotr( w[ i - 15 ], 7 ) ^ rotr( w[ i - 15 ], 18 ) ^ ( w[ i - 15 ] >>> 3 );
			const s1 = rotr( w[ i - 2 ], 17 ) ^ rotr( w[ i - 2 ], 19 ) ^ ( w[ i - 2 ] >>> 10 );

			w[ i ] = ( w[ i - 16 ] + s0 + w[ i - 7 ] + s1 ) >>> 0;
		}

		let [ a, b, c, d, e, f, g, h ] = this.state;

		for ( let i = 0; i < 64; i++ ) {
			const s1 = rotr( e, 6 ) ^ rotr( e, 11 ) ^ rotr( e, 25 );
			const ch = ( e & f ) ^ ( ~e & g );
			const temp1 = ( h + s1 + ch + K[ i ] + w[ i ] ) >>> 0;
			const s0 = rotr( a, 2 ) ^ rotr( a, 13 ) ^ rotr( a, 22 );
			const maj = ( a & b ) ^ ( a & c ) ^ ( b & c );
			const temp2 = ( s0 + maj ) >>> 0;

			h = g;
			g = f;
			f = e;
			e = ( d + temp1 ) >>> 0;
			d = c;
			c = b;
			b = a;
			a = ( temp1 + temp2 ) >>> 0;
		}

		this.state[ 0 ] = ( this.state[ 0 ] + a ) >>> 0;
		this.state[ 1 ] = ( this.state[ 1 ] + b ) >>> 0;
		this.state[ 2 ] = ( this.state[ 2 ] + c ) >>> 0;
		this.state[ 3 ] = ( this.state[ 3 ] + d ) >>> 0;
		this.state[ 4 ] = ( this.state[ 4 ] + e ) >>> 0;
		this.state[ 5 ] = ( this.state[ 5 ] + f ) >>> 0;
		this.state[ 6 ] = ( this.state[ 6 ] + g ) >>> 0;
		this.state[ 7 ] = ( this.state[ 7 ] + h ) >>> 0;
	}
}

/**
 * Skrót całego pliku, liczony fragment po fragmencie.
 *
 * @param {Blob} file
 * @param {number} chunkBytes
 * @param {(read: number) => void} onProgress
 */
export async function hashFile( file, chunkBytes = 5 * 1024 * 1024, onProgress = null ) {
	const hash = new Sha256();

	for ( let offset = 0; offset < file.size; offset += chunkBytes ) {
		const slice = file.slice( offset, Math.min( offset + chunkBytes, file.size ) );

		hash.update( new Uint8Array( await slice.arrayBuffer() ) );

		if ( onProgress ) {
			onProgress( Math.min( offset + chunkBytes, file.size ) );
		}
	}

	return hash.digest();
}
