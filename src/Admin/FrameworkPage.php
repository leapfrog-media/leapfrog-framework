<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Admin;

use Leapfrog\Framework\Rest\RestController;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\PATH;
use const Leapfrog\Framework\VERSION;

/**
 * Leapfrog > Framework: the full-screen editor app.
 */
final class FrameworkPage {
	public const SLUG = 'leapfrog-framework';

	private string $hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	public function menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'Leapfrog Framework', 'leapfrog-framework' ),
			__( 'Leapfrog', 'leapfrog-framework' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			self::menu_icon(),
			59
		);

		// Rename the first submenu item from "Leapfrog" to "Framework".
		add_submenu_page( self::SLUG, __( 'Leapfrog Framework', 'leapfrog-framework' ), __( 'Framework', 'leapfrog-framework' ), 'manage_options', self::SLUG );
	}

	/**
	 * The Leapfrog Media mark as a WordPress menu icon.
	 *
	 * WordPress recolours SVG menu icons to match the admin colour scheme (grey, white
	 * on hover, highlighted when active) by rewriting their fill, so this is a
	 * single-colour version of assets/brand/leapfrog-mark.svg with a square viewBox so
	 * the tall mark sits centred. At 20px the mark's thin line is under a pixel, so the
	 * icon is the solid arch: the mark's outer curve, closed along the bottom (chosen
	 * 7 Nov 2026). If the mark's path ever changes shape, the outline is used instead.
	 */
	public static function menu_icon(): string {
		$svg = (string) @file_get_contents( PATH . 'assets/brand/leapfrog-mark.svg' );
		if ( ! preg_match( '/<path[^>]*\sd="([^"]+)"/', $svg, $m ) ) {
			return 'dashicons-art';
		}

		// The path runs up the outer curve to the bottom-right corner, then back along the
		// inner curve. Keeping only the outer part and closing it gives the solid arch.
		$path  = $m[1];
		$inner = strpos( $path, 'C420.986,730' );
		if ( false !== $inner ) {
			$path = substr( $path, 0, $inner ) . 'Z';
		}

		// The mark is 422 × 801: a square viewBox centred on it.
		$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-189.5 0 801 801"><path fill="black" d="' . $path . '"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $icon );
	}

	public function render(): void {
		echo '<div id="leapfrog-framework-app" class="lf-app-root"><p class="lf-app-loading">' . esc_html__( 'Loading Leapfrog Framework…', 'leapfrog-framework' ) . '</p></div>';
	}

	public function assets( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}

		$asset_file = PATH . 'build/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Leapfrog Framework: the editor has not been built yet. Run "npm run build" in the plugin folder.', 'leapfrog-framework' ) . '</p></div>';
				}
			);
			return;
		}

		$asset = require $asset_file;
		$url   = plugin_dir_url( FILE );

		wp_enqueue_script( 'leapfrog-framework-editor', $url . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( PATH . 'build/index.css' ) ) {
			wp_enqueue_style( 'leapfrog-framework-editor', $url . 'build/index.css', array( 'wp-components' ), $asset['version'] );
		}

		wp_add_inline_script(
			'leapfrog-framework-editor',
			'window.leapfrogFramework = ' . wp_json_encode(
				array(
					'version'      => VERSION,
					'logoUrl'      => $url . 'assets/brand/leapfrog-mark.svg',
					'restPath'     => '/' . RestController::NAMESPACE,
					'dashboardUrl' => admin_url(),
					'bricksUrl'    => admin_url( 'admin.php?page=bricks' ),
					'siteName'     => get_bloginfo( 'name' ),
				)
			) . ';',
			'before'
		);
	}

	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->id === $this->hook ) {
			$classes .= ' lf-fullscreen';
		}

		return $classes;
	}
}
