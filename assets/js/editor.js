/**
 * Kadr — edytor bloków marketingowych.
 *
 * Bloki są renderowane po stronie serwera (render.php), więc w edytorze
 * potrzebujemy wyłącznie warstwy edycji. Zamiast dwunastu plików JSX
 * wymagających kroku budowania, interfejs powstaje z deklaratywnej
 * specyfikacji poniżej — dzięki temu wtyczka działa zaraz po rozpakowaniu,
 * bez `npm install` (ADR-014).
 *
 * Granice dla administratora: edytuje treść i wybrane warianty.
 * Kolorów, odstępów i typografii nie da się tu zmienić — pilnuje tego
 * `supports` w block.json (CLAUDE.md §7).
 */
( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { RichText, InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl, ToggleControl, RangeControl, SelectControl, Button, BaseControl } = wp.components;
	const { __ } = wp.i18n;

	/* --------------------------------------------------------------------
	 * Specyfikacja bloków
	 * ------------------------------------------------------------------ */

	const HEADING = ( attr, tag, className, placeholder ) => ( {
		attr,
		type: 'rich',
		tag,
		className,
		placeholder,
	} );

	const SPEC = {
		'kadr/hero': {
			canvas: [
				{ attr: 'eyebrow', type: 'plain', tag: 'p', className: 'kadr-eyebrow', placeholder: __( 'Etykieta', 'kadr' ) },
				HEADING( 'title', 'h1', 'kadr-hero__title', __( 'Obietnica rezultatu w jednym zdaniu', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-hero__lead', __( 'Rozwinięcie — co dokładnie robi produkt', 'kadr' ) ),
				{ attr: 'note', type: 'plain', tag: 'p', className: 'kadr-hero__note', placeholder: __( 'Dopisek pod przyciskiem', 'kadr' ) },
			],
			media: { attr: 'imageId', label: __( 'Zrzut ekranu produktu', 'kadr' ) },
			panel: [
				{ attr: 'ctaLabel', type: 'text', label: __( 'Przycisk główny — etykieta', 'kadr' ) },
				{ attr: 'ctaUrl', type: 'url', label: __( 'Przycisk główny — adres', 'kadr' ) },
				{ attr: 'altLabel', type: 'text', label: __( 'Przycisk drugi — etykieta', 'kadr' ) },
				{ attr: 'altUrl', type: 'url', label: __( 'Przycisk drugi — adres', 'kadr' ) },
				{ attr: 'imageCaption', type: 'textarea', label: __( 'Podpis pod zrzutem', 'kadr' ) },
			],
		},

		'kadr/problem': {
			canvas: [
				HEADING( 'title', 'h2', 'kadr-problem__title', __( 'Nagłówek sekcji', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-problem__lead', __( 'Zdanie wprowadzające', 'kadr' ) ),
			],
			repeater: {
				attr: 'items',
				label: __( 'Punkty', 'kadr' ),
				addLabel: __( 'Dodaj punkt', 'kadr' ),
				blank: { text: '' },
				fields: [ { key: 'text', type: 'rich', placeholder: __( 'Na czym polega problem', 'kadr' ) } ],
			},
			after: [ HEADING( 'conclusion', 'p', 'kadr-problem__conclusion', __( 'Podsumowanie', 'kadr' ) ) ],
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
			],
		},

		'kadr/journey': {
			canvas: [
				HEADING( 'title', 'h2', '', __( 'Nagłówek sekcji', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-journey__lead', __( 'Zdanie wprowadzające', 'kadr' ) ),
			],
			repeater: {
				attr: 'steps',
				label: __( 'Etapy', 'kadr' ),
				addLabel: __( 'Dodaj etap', 'kadr' ),
				blank: { label: '', detail: '', actor: 'auto' },
				fields: [
					{ key: 'label', type: 'plain', placeholder: __( 'Nazwa etapu', 'kadr' ) },
					{ key: 'detail', type: 'rich', placeholder: __( 'Co się wtedy dzieje', 'kadr' ) },
					{
						key: 'actor',
						type: 'select',
						label: __( 'Kto', 'kadr' ),
						options: [
							{ value: 'client', label: __( 'Widzi klient', 'kadr' ) },
							{ value: 'photographer', label: __( 'Robisz Ty', 'kadr' ) },
							{ value: 'auto', label: __( 'Dzieje się samo', 'kadr' ) },
						],
					},
				],
			},
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
			],
		},

		'kadr/feature': {
			canvas: [
				HEADING( 'title', 'h2', '', __( 'Nazwa funkcji', 'kadr' ) ),
				HEADING( 'body', 'p', 'kadr-feature__body', __( 'Co ta funkcja daje fotografowi', 'kadr' ) ),
			],
			media: { attr: 'imageId', label: __( 'Zrzut ekranu funkcji', 'kadr' ) },
			repeater: {
				attr: 'bullets',
				label: __( 'Wypunktowanie', 'kadr' ),
				addLabel: __( 'Dodaj punkt', 'kadr' ),
				blank: { text: '' },
				fields: [ { key: 'text', type: 'rich', placeholder: __( 'Konkret, nie ogólnik', 'kadr' ) } ],
			},
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
				{
					attr: 'mediaSide',
					type: 'select',
					label: __( 'Strona obrazu', 'kadr' ),
					options: [
						{ value: 'right', label: __( 'Po prawej', 'kadr' ) },
						{ value: 'left', label: __( 'Po lewej', 'kadr' ) },
					],
				},
				{ attr: 'linkLabel', type: 'text', label: __( 'Odnośnik — etykieta', 'kadr' ) },
				{ attr: 'linkUrl', type: 'url', label: __( 'Odnośnik — adres', 'kadr' ) },
			],
		},

		'kadr/proof': {
			canvas: [
				HEADING( 'title', 'h2', '', __( 'Nagłówek sekcji', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-proof__lead', __( 'Zdanie wprowadzające', 'kadr' ) ),
			],
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
				{ attr: 'packageSize', type: 'range', label: __( 'Zdjęć w pakiecie', 'kadr' ), min: 1, max: 100 },
				{ attr: 'selected', type: 'range', label: __( 'Zdjęć wybranych', 'kadr' ), min: 1, max: 200 },
				{ attr: 'extraPrice', type: 'range', label: __( 'Cena zdjęcia ponad pakiet (zł)', 'kadr' ), min: 5, max: 300, step: 5 },
				{ attr: 'footnote', type: 'textarea', label: __( 'Przypis', 'kadr' ) },
			],
		},

		'kadr/pricing': {
			canvas: [
				HEADING( 'title', 'h2', '', __( 'Nagłówek cennika', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-pricing__lead', __( 'Zdanie wprowadzające', 'kadr' ) ),
			],
			notice: __( 'Ceny, limity i dodatki pochodzą z rejestru planów w kodzie i nie są edytowalne w tym miejscu — dzięki temu cennik nie może rozejść się z produktem.', 'kadr' ),
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
				{ attr: 'ctaUrl', type: 'url', label: __( 'Adres przycisku planu', 'kadr' ) },
				{ attr: 'showFree', type: 'toggle', label: __( 'Pokaż plan darmowy', 'kadr' ) },
				{ attr: 'showAddons', type: 'toggle', label: __( 'Pokaż dodatki', 'kadr' ) },
			],
		},

		'kadr/faq': {
			canvas: [ HEADING( 'title', 'h2', '', __( 'Nagłówek sekcji', 'kadr' ) ) ],
			repeater: {
				attr: 'items',
				label: __( 'Pytania', 'kadr' ),
				addLabel: __( 'Dodaj pytanie', 'kadr' ),
				blank: { question: '', answer: '' },
				fields: [
					{ key: 'question', type: 'plain', placeholder: __( 'Pytanie, które naprawdę zadają', 'kadr' ) },
					{ key: 'answer', type: 'rich', placeholder: __( 'Uczciwa odpowiedź', 'kadr' ) },
				],
			},
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
			],
		},

		'kadr/testimonials': {
			canvas: [
				HEADING( 'title', 'h2', '', __( 'Nagłówek sekcji', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-testimonials__lead', __( 'Wyjaśnienie', 'kadr' ) ),
			],
			notice: __( 'Nie wpisuj tu wymyślonych opinii ani ocen. Puste sloty są świadomym rozwiązaniem do czasu, aż pojawią się prawdziwe wypowiedzi.', 'kadr' ),
			repeater: {
				attr: 'items',
				label: __( 'Prawdziwe opinie', 'kadr' ),
				addLabel: __( 'Dodaj opinię', 'kadr' ),
				blank: { quote: '', author: '', role: '' },
				fields: [
					{ key: 'quote', type: 'rich', placeholder: __( 'Cytat — wyłącznie za zgodą autora', 'kadr' ) },
					{ key: 'author', type: 'plain', placeholder: __( 'Imię i nazwisko', 'kadr' ) },
					{ key: 'role', type: 'plain', placeholder: __( 'Specjalizacja, miasto', 'kadr' ) },
				],
			},
			panel: [
				{ attr: 'eyebrow', type: 'text', label: __( 'Etykieta sekcji', 'kadr' ) },
				{ attr: 'number', type: 'text', label: __( 'Numer sekcji', 'kadr' ) },
				{ attr: 'slots', type: 'range', label: __( 'Łączna liczba miejsc', 'kadr' ), min: 0, max: 6 },
			],
		},

		'kadr/cta': {
			canvas: [
				HEADING( 'title', 'h2', 'kadr-cta__title', __( 'Ostatnia obietnica', 'kadr' ) ),
				HEADING( 'lead', 'p', 'kadr-cta__lead', __( 'Jedno zdanie rozwinięcia', 'kadr' ) ),
			],
			panel: [
				{ attr: 'ctaLabel', type: 'text', label: __( 'Etykieta przycisku', 'kadr' ) },
				{ attr: 'ctaUrl', type: 'url', label: __( 'Adres przycisku', 'kadr' ) },
				{ attr: 'note', type: 'text', label: __( 'Dopisek', 'kadr' ) },
			],
		},
	};

	/* --------------------------------------------------------------------
	 * Generyczne kontrolki
	 * ------------------------------------------------------------------ */

	function textField( field, value, onChange ) {
		const common = {
			key: field.attr || field.key,
			label: field.label,
			value: value ?? '',
			onChange,
			__nextHasNoMarginBottom: true,
		};

		switch ( field.type ) {
			case 'textarea':
				return el( TextareaControl, common );
			case 'url':
				return el( TextControl, { ...common, type: 'url', placeholder: '/adres-strony' } );
			case 'toggle':
				return el( ToggleControl, {
					key: field.attr,
					label: field.label,
					checked: !! value,
					onChange,
					__nextHasNoMarginBottom: true,
				} );
			case 'range':
				return el( RangeControl, {
					key: field.attr,
					label: field.label,
					value: Number( value ) || field.min || 0,
					min: field.min ?? 0,
					max: field.max ?? 100,
					step: field.step ?? 1,
					onChange,
					__nextHasNoMarginBottom: true,
				} );
			case 'select':
				return el( SelectControl, { ...common, options: field.options || [] } );
			default:
				return el( TextControl, common );
		}
	}

	function canvasField( field, attributes, setAttributes ) {
		const value = attributes[ field.attr ] ?? '';

		return el( RichText, {
			key: field.attr,
			tagName: field.tag || 'p',
			className: field.className || undefined,
			value,
			placeholder: field.placeholder,
			// `plain` nie dopuszcza formatowania — np. nazwa etapu ma zostać czystym tekstem.
			allowedFormats: 'plain' === field.type ? [] : [ 'core/bold', 'core/italic', 'core/link' ],
			onChange: ( next ) => setAttributes( { [ field.attr ]: next } ),
		} );
	}

	function repeater( spec, attributes, setAttributes ) {
		const items = Array.isArray( attributes[ spec.attr ] ) ? attributes[ spec.attr ] : [];

		const update = ( index, key, next ) => {
			const copy = items.map( ( item, i ) => ( i === index ? { ...item, [ key ]: next } : item ) );
			setAttributes( { [ spec.attr ]: copy } );
		};

		const move = ( index, delta ) => {
			const target = index + delta;
			if ( target < 0 || target >= items.length ) {
				return;
			}
			const copy = items.slice();
			[ copy[ index ], copy[ target ] ] = [ copy[ target ], copy[ index ] ];
			setAttributes( { [ spec.attr ]: copy } );
		};

		const remove = ( index ) => {
			setAttributes( { [ spec.attr ]: items.filter( ( _, i ) => i !== index ) } );
		};

		return el(
			'div',
			{ className: 'kadr-repeater', key: spec.attr },
			el( 'p', { className: 'kadr-repeater__label' }, spec.label ),
			items.map( ( item, index ) =>
				el(
					'div',
					{ className: 'kadr-repeater__item', key: index },
					el(
						'div',
						{ className: 'kadr-repeater__fields' },
						spec.fields.map( ( field ) =>
							'select' === field.type
								? el( SelectControl, {
									key: field.key,
									label: field.label,
									value: item[ field.key ] ?? '',
									options: field.options,
									onChange: ( next ) => update( index, field.key, next ),
									__nextHasNoMarginBottom: true,
								} )
								: el( RichText, {
									key: field.key,
									tagName: 'p',
									className: 'kadr-repeater__text',
									value: item[ field.key ] ?? '',
									placeholder: field.placeholder,
									allowedFormats: 'plain' === field.type ? [] : [ 'core/bold', 'core/italic', 'core/link' ],
									onChange: ( next ) => update( index, field.key, next ),
								} )
						)
					),
					el(
						'div',
						{ className: 'kadr-repeater__actions' },
						el( Button, {
							icon: 'arrow-up-alt2',
							size: 'small',
							label: __( 'Przenieś wyżej', 'kadr' ),
							disabled: 0 === index,
							onClick: () => move( index, -1 ),
						} ),
						el( Button, {
							icon: 'arrow-down-alt2',
							size: 'small',
							label: __( 'Przenieś niżej', 'kadr' ),
							disabled: index === items.length - 1,
							onClick: () => move( index, 1 ),
						} ),
						el( Button, {
							icon: 'trash',
							size: 'small',
							isDestructive: true,
							label: __( 'Usuń', 'kadr' ),
							onClick: () => remove( index ),
						} )
					)
				)
			),
			el(
				Button,
				{
					variant: 'secondary',
					size: 'compact',
					onClick: () => setAttributes( { [ spec.attr ]: [ ...items, { ...spec.blank } ] } ),
				},
				spec.addLabel
			)
		);
	}

	function mediaField( spec, attributes, setAttributes ) {
		const id = Number( attributes[ spec.attr ] ) || 0;

		return el(
			MediaUploadCheck,
			{ key: spec.attr },
			el( BaseControl, { label: spec.label, __nextHasNoMarginBottom: true },
				el( MediaUpload, {
					allowedTypes: [ 'image' ],
					value: id,
					onSelect: ( media ) => setAttributes( { [ spec.attr ]: media.id } ),
					render: ( { open } ) =>
						el(
							'div',
							{ className: 'kadr-media' },
							el(
								Button,
								{ variant: 'secondary', onClick: open },
								id ? __( 'Zmień obraz', 'kadr' ) : __( 'Wybierz obraz', 'kadr' )
							),
							id
								? el(
									Button,
									{
										variant: 'tertiary',
										isDestructive: true,
										onClick: () => setAttributes( { [ spec.attr ]: 0 } ),
									},
									__( 'Usuń', 'kadr' )
								)
								: null
						),
				} )
			)
		);
	}

	/* --------------------------------------------------------------------
	 * Rejestracja
	 * ------------------------------------------------------------------ */

	Object.keys( SPEC ).forEach( ( name ) => {
		const spec = SPEC[ name ];

		registerBlockType( name, {
			edit( { attributes, setAttributes } ) {
				const blockProps = useBlockProps( { className: 'kadr-edit' } );

				const inspector = el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Ustawienia', 'kadr' ), initialOpen: true },
						( spec.panel || [] ).map( ( field ) =>
							textField( field, attributes[ field.attr ], ( next ) =>
								setAttributes( { [ field.attr ]: next } )
							)
						),
						spec.media ? mediaField( spec.media, attributes, setAttributes ) : null
					)
				);

				return el(
					Fragment,
					{},
					inspector,
					el(
						'div',
						blockProps,
						spec.notice ? el( 'p', { className: 'kadr-edit__notice' }, spec.notice ) : null,
						( spec.canvas || [] ).map( ( field ) => canvasField( field, attributes, setAttributes ) ),
						spec.repeater ? repeater( spec.repeater, attributes, setAttributes ) : null,
						( spec.after || [] ).map( ( field ) => canvasField( field, attributes, setAttributes ) )
					)
				);
			},

			// Blok dynamiczny — HTML powstaje w render.php, nie jest zapisywany w treści.
			save() {
				return null;
			},
		} );
	} );
} )( window.wp );
