<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Header;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\PATH;

/**
 * On the site: when the page's Bricks header has a saved Header Builder design,
 * adds the header classes, loads header.css and prints the design's CSS.
 *
 * Preview mode (?lfh_preview=<header template id>&lfh_nonce=…, administrators only)
 * shows that header with its unsaved design (styles and scroll settings), hides
 * the toolbar, stops page caching and loads a small script that takes style and
 * state changes from the builder without reloading.
 */
final class Frontend {
	public const NONCE = 'lfh_preview';

	/** @var array<string, mixed>|null The design this page's header uses. */
	private ?array $design = null;

	private int $preview = 0;

	private bool $reading_settings = false;

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_preview' ) );
		add_filter( 'bricks/active_templates', array( $this, 'active_templates' ), 20 );
		add_filter( 'bricks/header/attributes', array( $this, 'header_attributes' ) );
		add_filter( 'get_post_metadata', array( $this, 'preview_template_settings' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	public function maybe_preview(): void {
		$id = isset( $_GET['lfh_preview'] ) ? absint( $_GET['lfh_preview'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id || is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$nonce = isset( $_GET['lfh_nonce'] ) ? (string) wp_unslash( $_GET['lfh_nonce'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! Store::is_header( $id ) ) {
			return;
		}
		// The design first: reading it may read the template's settings, which preview mode changes.
		$this->design  = Store::current( $id );
		$this->preview = $id;
		show_admin_bar( false );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * In preview, the page uses the header being designed; otherwise note its saved design.
	 *
	 * @param array<string, mixed> $templates
	 * @return array<string, mixed>
	 */
	public function active_templates( $templates ) {
		if ( ! is_array( $templates ) ) {
			return $templates;
		}
		if ( $this->preview ) {
			$templates['header'] = $this->preview;
			return $templates;
		}
		$header = (int) ( $templates['header'] ?? 0 );
		if ( $header ) {
			$this->design = Store::saved( $header );
		}

		return $templates;
	}

	/**
	 * In preview, Bricks reads the header's sticky settings from the unsaved design.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function preview_template_settings( $value, $object_id, $meta_key, $single ) {
		if ( ! $this->preview || ! $this->design || (int) $object_id !== $this->preview || '_bricks_template_settings' !== $meta_key || $this->reading_settings ) {
			return $value;
		}
		$this->reading_settings = true;
		$settings               = get_post_meta( $this->preview, '_bricks_template_settings', true );
		$this->reading_settings = false;
		$settings               = Design::template_settings( $this->design, is_array( $settings ) ? $settings : array() );

		return $single ? array( $settings ) : array( $settings );
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @return array<string, mixed>
	 */
	public function header_attributes( $attributes ) {
		if ( ! $this->design || ! is_array( $attributes ) ) {
			return $attributes;
		}
		$classes   = isset( $attributes['class'] ) ? (array) $attributes['class'] : array();
		$classes[] = 'lfh';
		if ( ! empty( $this->design['scroll']['overlay'] ) ) {
			$classes[] = 'lfh-overlay';
		}
		$attributes['class']    = $classes;
		$attributes['data-lfh'] = (string) $this->design['templateId'];

		return $attributes;
	}

	public function assets(): void {
		if ( ! $this->design ) {
			return;
		}
		$url = plugin_dir_url( FILE );
		wp_enqueue_style( 'leapfrog-header', $url . 'assets/frontend/header.css', array(), (string) filemtime( PATH . 'assets/frontend/header.css' ) );
		wp_add_inline_style( 'leapfrog-header', Design::css( $this->design, Store::fields( (int) $this->design['templateId'] ) ) );

		if ( $this->preview ) {
			wp_enqueue_script( 'leapfrog-header-preview', $url . 'assets/frontend/header-preview.js', array(), (string) filemtime( PATH . 'assets/frontend/header-preview.js' ), true );
		}
	}
}
