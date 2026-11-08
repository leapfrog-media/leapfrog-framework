<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Admin;

use Leapfrog\Framework\Rest\RestController;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\PATH;
use const Leapfrog\Framework\VERSION;

/**
 * Leapfrog > Header Builder: the builder app with its live preview.
 */
final class HeaderPage {
	public const SLUG = 'leapfrog-header';

	private string $hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			LeapfrogMenu::SLUG,
			__( 'Header Builder', 'leapfrog-framework' ),
			__( 'Header Builder', 'leapfrog-framework' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		echo '<div id="leapfrog-header-app" class="lf-app-root"><p class="lf-app-loading">' . esc_html__( 'Loading the Header Builder…', 'leapfrog-framework' ) . '</p></div>';
	}

	public function assets( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}
		$asset_file = PATH . 'build/header.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		$url   = plugin_dir_url( FILE );

		wp_enqueue_media();
		wp_enqueue_script( 'leapfrog-header-builder', $url . 'build/header.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( PATH . 'build/header.css' ) ) {
			wp_enqueue_style( 'leapfrog-header-builder', $url . 'build/header.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_add_inline_script(
			'leapfrog-header-builder',
			'window.leapfrogHeader = ' . wp_json_encode(
				array(
					'version'  => VERSION,
					'logoUrl'  => $url . 'assets/brand/leapfrog-mark.svg',
					'restPath' => '/' . RestController::NAMESPACE,
				)
			) . ';',
			'before'
		);
	}

	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && $screen->id === $this->hook ? $classes . ' lf-app-page' : $classes;
	}
}
