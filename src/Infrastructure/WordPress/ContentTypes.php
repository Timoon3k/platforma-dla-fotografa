<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Typy treści dla części marketingowej.
 *
 * CPT używamy WYŁĄCZNIE do treści redakcyjnej (CLAUDE.md §4).
 * Dane transakcyjne trafią do własnych tabel w Session 3.
 */
final class ContentTypes {

	public const DOCUMENT = 'kadr_document';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ), 10 );
	}

	public function register(): void {
		register_post_type(
			self::DOCUMENT,
			array(
				'labels'             => array(
					'name'               => __( 'Dokumenty', 'kadr' ),
					'singular_name'      => __( 'Dokument', 'kadr' ),
					'add_new_item'       => __( 'Dodaj dokument', 'kadr' ),
					'edit_item'          => __( 'Edytuj dokument', 'kadr' ),
					'search_items'       => __( 'Szukaj dokumentów', 'kadr' ),
					'not_found'          => __( 'Nie ma jeszcze żadnego dokumentu.', 'kadr' ),
					'menu_name'          => __( 'Dokumenty', 'kadr' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-media-text',
				'menu_position'      => 26,
				'has_archive'        => false,
				'hierarchical'       => false,
				'rewrite'            => array(
					'slug'       => 'dokumenty',
					'with_front' => false,
				),
				'supports'           => array( 'title', 'editor', 'revisions', 'excerpt', 'custom-fields' ),
				'capability_type'    => 'page',
			)
		);

		register_post_meta(
			self::DOCUMENT,
			'kadr_document_version',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_pages' ),
			)
		);

		register_post_meta(
			self::DOCUMENT,
			'kadr_document_effective_from',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_pages' ),
			)
		);
	}
}
