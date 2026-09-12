<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja bloków Gutenberga z katalogu /blocks.
 *
 * Każdy blok ma block.json (metadane) i render.php (renderowanie po stronie
 * serwera). Bloki są dynamiczne — HTML nie jest zapisywany w treści wpisu,
 * dzięki czemu zmiana szablonu nie unieważnia istniejących stron.
 */
final class Blocks {

	/**
	 * Bloki dostępne w edytorze, w kolejności, w jakiej mają się pojawiać.
	 *
	 * @var list<string>
	 */
	private const BLOCKS = array(
		'hero',
		'problem',
		'journey',
		'feature',
		'proof',
		'pricing',
		'faq',
		'testimonials',
		'cta',
	);

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
	}

	public function register(): void {
		foreach ( self::BLOCKS as $block ) {
			$dir = Paths::dir( "blocks/$block" );

			if ( ! is_readable( $dir . '/block.json' ) ) {
				continue;
			}

			register_block_type( $dir );
		}
	}

	/**
	 * @param array<int, array{slug: string, title: string, icon?: string|null}> $categories
	 * @return array<int, array{slug: string, title: string, icon?: string|null}>
	 */
	public function register_category( array $categories ): array {
		array_unshift(
			$categories,
			array(
				'slug'  => 'kadr',
				'title' => __( 'Kadr', 'kadr' ),
				'icon'  => null,
			)
		);

		return $categories;
	}
}
