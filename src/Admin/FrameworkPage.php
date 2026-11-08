<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Admin;

use Leapfrog\Framework\Rest\RestController;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\PATH;
use const Leapfrog\Framework\VERSION;

/**
 * Leapfrog > Leapfrog Framework: the editor app, inside the normal admin.
 */
final class FrameworkPage {
	public const SLUG = 'leapfrog-framework';

	private string $hook = '';

	public function register(): void {
		LeapfrogMenu::register();
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Leapfrog > Framework, in the menu shared by all Leapfrog plugins.
	 */
	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			LeapfrogMenu::SLUG,
			__( 'Leapfrog Framework', 'leapfrog-framework' ),
			__( 'Leapfrog Framework', 'leapfrog-framework' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
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
			$classes .= ' lf-app-page';
		}

		return $classes;
	}
}
