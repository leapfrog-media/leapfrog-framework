<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use Leapfrog\Framework\Settings\SettingsStore;
use Throwable;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\VERSION;

/**
 * Adds a Leapfrog row to Bricks' own Visual grid builder: Leapfrog grid layouts and
 * gaps, bento layouts and "Stack on mobile". Bricks' builder does the rest (tracks,
 * placing elements, breakpoints, classes and query loops), so Leapfrog only writes
 * the same Bricks settings it would.
 */
final class GridExtras {
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 46 );
	}

	public function assets(): void {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}
		$url = plugin_dir_url( FILE ) . 'assets/builder/';
		wp_enqueue_style( 'leapfrog-grid-extras', $url . 'grid-extras.css', array(), VERSION );
		wp_enqueue_script( 'leapfrog-grid-extras', $url . 'grid-extras.js', array(), VERSION, true );
		wp_add_inline_script( 'leapfrog-grid-extras', 'window.leapfrogGridExtras = ' . wp_json_encode( self::data() ) . ';', 'before' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		try {
			$settings = SettingsStore::get();
		} catch ( Throwable $error ) {
			$settings = SettingsStore::defaults();
		}

		$layouts = array();
		for ( $n = 1; $n <= 12; $n++ ) {
			/* translators: %d number of columns */
			$layouts[] = array( 'value' => "var(--grid-$n)", 'label' => 1 === $n ? __( '1 column', 'leapfrog-framework' ) : sprintf( __( '%d equal columns', 'leapfrog-framework' ), $n ) );
		}
		$layouts[] = array( 'value' => 'var(--grid-1-2)', 'label' => __( '1 : 2 (narrow, wide)', 'leapfrog-framework' ) );
		$layouts[] = array( 'value' => 'var(--grid-2-1)', 'label' => __( '2 : 1 (wide, narrow)', 'leapfrog-framework' ) );
		$layouts[] = array( 'value' => 'var(--grid-1-3)', 'label' => __( '1 : 3 (sidebar, main)', 'leapfrog-framework' ) );
		$layouts[] = array( 'value' => 'var(--grid-3-1)', 'label' => __( '3 : 1 (main, sidebar)', 'leapfrog-framework' ) );
		$layouts[] = array( 'value' => 'var(--grid-auto-fit)', 'label' => __( 'Cards that wrap and stretch (auto-fit)', 'leapfrog-framework' ) );
		$layouts[] = array( 'value' => 'var(--grid-auto-fill)', 'label' => __( 'Cards that wrap, keeping their width (auto-fill)', 'leapfrog-framework' ) );

		$gaps = array(
			array( 'value' => 'var(--gap)', 'label' => __( 'Gap (default)', 'leapfrog-framework' ) ),
			array( 'value' => 'var(--stack-gap)', 'label' => __( 'Stack gap', 'leapfrog-framework' ) ),
			array( 'value' => 'var(--cluster-gap)', 'label' => __( 'Cluster gap', 'leapfrog-framework' ) ),
			array( 'value' => 'var(--inline-gap)', 'label' => __( 'Inline gap', 'leapfrog-framework' ) ),
		);
		foreach ( (array) ( $settings['spacing']['steps'] ?? array() ) as $step ) {
			/* translators: %s space scale step, e.g. m */
			$gaps[] = array( 'value' => 'var(--space-' . $step . ')', 'label' => sprintf( __( 'Space %s', 'leapfrog-framework' ), $step ) );
		}

		return array(
			'layouts' => $layouts,
			'gaps'    => $gaps,
			'i18n'    => array(
				'title'        => __( 'Leapfrog', 'leapfrog-framework' ),
				'layout'       => __( 'Layout…', 'leapfrog-framework' ),
				'gap'          => __( 'Gap…', 'leapfrog-framework' ),
				'bento'        => __( 'Bento…', 'leapfrog-framework' ),
				'bentoTiles'   => __( '%d tiles', 'leapfrog-framework' ),
				'stack'        => __( 'Stack on mobile', 'leapfrog-framework' ),
				'stackHelp'    => __( 'One column at the smallest breakpoint, with every item in normal order.', 'leapfrog-framework' ),
				'breakpointAt' => __( 'Applies to: %s', 'leapfrog-framework' ),
				'needsElement' => __( 'Bento places this grid’s children one by one, so it needs at least one child and no query loop.', 'leapfrog-framework' ),
				'stackLoop'    => __( 'One column on mobile. Query loop items keep the placement Bricks gave them, so reset them at the smallest breakpoint if they overlap.', 'leapfrog-framework' ),
				'done'         => __( 'Done', 'leapfrog-framework' ),
			),
		);
	}
}
