<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\VERSION;

/**
 * Builder helpers that Bricks doesn't have: grid guides on the canvas (toolbar
 * button, Ctrl+Shift+G), header and footer buttons that hide either on the
 * canvas while editing, a BEM class generator (right-click an element >
 * "Add BEM classes…"), an elements panel toolbar (1–4 columns, open or close
 * all categories) and a show/hide switch on each elements panel group. Bricks already suggests variables as you type var(--,
 * so Leapfrog adds no autocomplete of its own.
 */
final class BuilderHelpers {
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 47 );
	}

	public function assets(): void {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}
		$url = plugin_dir_url( FILE ) . 'assets/builder/';
		wp_enqueue_style( 'leapfrog-builder-helpers', $url . 'builder-helpers.css', array(), VERSION );
		wp_enqueue_script( 'leapfrog-grid-guides', $url . 'grid-guides.js', array(), VERSION, true );
		wp_enqueue_script( 'leapfrog-bem', $url . 'bem.js', array(), VERSION, true );
		wp_enqueue_script( 'leapfrog-elements-panel', $url . 'elements-panel.js', array( 'leapfrog-grid-guides' ), VERSION, true );
		wp_enqueue_script( 'leapfrog-header-footer', $url . 'header-footer.js', array( 'leapfrog-grid-guides' ), VERSION, true );
		wp_enqueue_script( 'leapfrog-element-groups', $url . 'element-groups.js', array( 'leapfrog-grid-guides' ), VERSION, true );
		wp_add_inline_script( 'leapfrog-grid-guides', 'window.leapfrogBuilderHelpers = ' . wp_json_encode( self::data() ) . ';', 'before' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		return array(
			'guideColumns' => 12,
			// A header or footer template being edited keeps its own part on show.
			'editingType'  => (string) get_post_meta( (int) get_the_ID(), '_bricks_template_type', true ),
			'i18n'         => array(
				'guides'              => __( 'Grid guides (Ctrl+Shift+G)', 'leapfrog-framework' ),
				'hide_header'         => __( 'Hide the header while editing', 'leapfrog-framework' ),
				'show_header'         => __( 'Show the header', 'leapfrog-framework' ),
				'hide_footer'         => __( 'Hide the footer while editing', 'leapfrog-framework' ),
				'show_footer'         => __( 'Show the footer', 'leapfrog-framework' ),
				'elementsToolbar'     => __( 'Elements panel layout', 'leapfrog-framework' ),
				'oneColumn'           => __( '1 column', 'leapfrog-framework' ),
				/* translators: %d number of columns */
				'columns'             => __( '%d columns', 'leapfrog-framework' ),
				'expandAll'           => __( 'Open all categories', 'leapfrog-framework' ),
				'collapseAll'         => __( 'Close all categories', 'leapfrog-framework' ),
				'hideGroup'           => __( 'Hide this group (moves it to the bottom)', 'leapfrog-framework' ),
				'showGroup'           => __( 'Show this group', 'leapfrog-framework' ),
				'menu'                => __( 'Add BEM classes…', 'leapfrog-framework' ),
				'title'               => __( 'Add BEM classes', 'leapfrog-framework' ),
				'block'               => __( 'Block name', 'leapfrog-framework' ),
				'modifier'            => __( 'Modifier (optional)', 'leapfrog-framework' ),
				'modifierPlaceholder' => __( 'e.g. featured', 'leapfrog-framework' ),
				'include'             => __( 'Add a class to this element', 'leapfrog-framework' ),
				'component'           => __( 'component', 'leapfrog-framework' ),
				'rename'              => __( 'Rename the elements to match', 'leapfrog-framework' ),
				'move'                => __( 'Move styles set on the elements onto their new classes', 'leapfrog-framework' ),
				'cancel'              => __( 'Cancel', 'leapfrog-framework' ),
				'apply'               => __( 'Add classes', 'leapfrog-framework' ),
				/* translators: %s class name */
				'invalid'             => __( '"%s" is not a valid class name.', 'leapfrog-framework' ),
				/* translators: 1: elements, 2: new classes */
				'done'                => __( 'BEM classes added to %d elements (%d new classes).', 'leapfrog-framework' ),
			),
		);
	}
}
