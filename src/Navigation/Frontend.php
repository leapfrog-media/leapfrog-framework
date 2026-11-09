<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Navigation;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\PATH;

/**
 * On the site: when the page's Bricks header holds a Leapfrog navigation, prints
 * the navigation CSS (base + the site's style) and loads the mobile menu script.
 *
 * Preview mode (?lfn_preview=1&lfn_nonce=…[&lfn_header=<id>], administrators only)
 * uses the unsaved style, can show a chosen header template, hides the toolbar,
 * stops page caching and loads the builder's preview script.
 */
final class Frontend {
	public const NONCE = 'lfn_preview';

	private bool $preview = false;

	private int $header = 0;

	private bool $active = false;

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_preview' ) );
		add_filter( 'bricks/active_templates', array( $this, 'active_templates' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	public function maybe_preview(): void {
		if ( empty( $_GET['lfn_preview'] ) || is_admin() || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$nonce = isset( $_GET['lfn_nonce'] ) ? (string) wp_unslash( $_GET['lfn_nonce'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}
		$this->preview = true;
		$header        = isset( $_GET['lfn_header'] ) ? absint( $_GET['lfn_header'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $header && Store::is_header( $header ) ) {
			$this->header = $header;
		}
		show_admin_bar( false );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * Notes whether the page's header holds a Leapfrog navigation (in preview, shows the chosen header).
	 *
	 * @param array<string, mixed> $templates
	 * @return array<string, mixed>
	 */
	public function active_templates( $templates ) {
		if ( ! is_array( $templates ) ) {
			return $templates;
		}
		if ( $this->preview && $this->header ) {
			$templates['header'] = $this->header;
		}
		$header       = (int) ( $templates['header'] ?? 0 );
		$this->active = $header && Store::has_navigation( $header );
		if ( $this->active ) {
			$this->header = $header;
		}

		return $templates;
	}

	public function assets(): void {
		if ( ! $this->active ) {
			return;
		}
		$design = $this->preview ? Store::current() : Store::live();
		$url    = plugin_dir_url( FILE );

		wp_register_style( 'leapfrog-navigation', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'leapfrog-navigation' );
		// In preview the builder sends the style settings' CSS itself (so clearing one clears it), but it
		// starts from the same CSS so nothing jumps while the page loads.
		$max = Design::mobile_max( $design );
		wp_add_inline_style( 'leapfrog-navigation', Design::base_css( $design, $max ) . self::bricks_scrolled_css( $design, $max, $this->header ) );
		wp_register_style( 'leapfrog-navigation-styles', false, array( 'leapfrog-navigation' ), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'leapfrog-navigation-styles' );
		wp_add_inline_style( 'leapfrog-navigation-styles', Design::styles_css( $design, $max ) );

		wp_enqueue_script( 'leapfrog-navigation', $url . 'assets/frontend/navigation.js', array(), (string) filemtime( PATH . 'assets/frontend/navigation.js' ), true );
		wp_add_inline_script( 'leapfrog-navigation', 'window.leapfrogNavigation=' . wp_json_encode( array( 'mobileMax' => Design::mobile_max( $design ) ) ) . ';', 'before' );

		if ( $this->preview ) {
			wp_enqueue_script( 'leapfrog-navigation-preview', $url . 'assets/frontend/navigation-preview.js', array( 'leapfrog-navigation' ), (string) filemtime( PATH . 'assets/frontend/navigation-preview.js' ), true );
		}
	}

	/**
	 * The header template's Bricks sticky colours on the menu links (see Design::bricks_scrolled_css).
	 *
	 * @param array<string, mixed> $design
	 */
	private static function bricks_scrolled_css( array $design, int $max, int $header ): string {
		if ( ! $header || ! is_callable( array( '\Bricks\Helpers', 'get_template_settings' ) ) ) {
			return '';
		}
		$settings = \Bricks\Helpers::get_template_settings( $header );

		return is_array( $settings ) ? Design::bricks_scrolled_css( $design, $max, $settings ) : '';
	}
}
