<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Admin;

use Leapfrog\Framework\Rest\RestController;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\PATH;
use const Leapfrog\Framework\VERSION;

/**
 * Leapfrog > Navigation Builder: the builder app with its live preview.
 */
final class NavigationPage {
	public const SLUG = 'leapfrog-navigation';

	private string $hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		// The first version's address. On admin_menu: WordPress refuses an unknown page before admin_init.
		add_action( 'admin_menu', array( $this, 'old_address' ), 1 );
	}

	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			LeapfrogMenu::SLUG,
			__( 'Navigation Builder', 'leapfrog-framework' ),
			__( 'Navigation Builder', 'leapfrog-framework' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function old_address(): void {
		if ( isset( $_GET['page'] ) && 'leapfrog-header' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
			exit;
		}
	}

	public function render(): void {
		echo '<div id="leapfrog-navigation-app" class="lf-app-root"><p class="lf-app-loading">' . esc_html__( 'Loading the Navigation Builder…', 'leapfrog-framework' ) . '</p></div>';
	}

	public function assets( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}
		$asset_file = PATH . 'build/navigation.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		$url   = plugin_dir_url( FILE );

		wp_enqueue_script( 'leapfrog-navigation-builder', $url . 'build/navigation.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( PATH . 'build/navigation.css' ) ) {
			wp_enqueue_style( 'leapfrog-navigation-builder', $url . 'build/navigation.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_add_inline_script(
			'leapfrog-navigation-builder',
			'window.leapfrogNavigationBuilder = ' . wp_json_encode(
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
