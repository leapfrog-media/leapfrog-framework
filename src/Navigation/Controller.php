<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Navigation;

use Leapfrog\Framework\Requirements;
use Leapfrog\Framework\Rest\RestController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for the Navigation Builder (administrators only):
 *   GET  /navigation           everything the builder needs
 *   POST /navigation/starter   a new header template with the starter navigation ({ name })
 *   POST /navigation/draft     store unsaved changes ({ design })
 *   POST /navigation/save      save the site's navigation style ({ design })
 *   POST /navigation/discard   drop unsaved changes
 */
final class Controller {
	public const PREVIEW_SLUG = 'header-display';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$ns    = RestController::NAMESPACE;
		$admin = static fn(): bool => current_user_can( 'manage_options' );
		foreach (
			array(
				array( '/navigation', WP_REST_Server::READABLE, 'start' ),
				array( '/navigation/starter', WP_REST_Server::CREATABLE, 'starter' ),
				array( '/navigation/draft', WP_REST_Server::CREATABLE, 'draft' ),
				array( '/navigation/save', WP_REST_Server::CREATABLE, 'save' ),
				array( '/navigation/discard', WP_REST_Server::CREATABLE, 'discard' ),
			) as $route
		) {
			register_rest_route( $ns, $route[0], array( 'methods' => $route[1], 'callback' => array( $this, $route[2] ), 'permission_callback' => $admin ) );
		}
	}

	public function start(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'problems'    => self::problems(),
				'headers'     => Store::headers(),
				'design'      => Store::current(),
				'unsaved'     => null !== Store::draft(),
				'fields'      => Design::fields(),
				'prefix'      => Design::PREFIX,
				'breakpoints' => self::breakpoints(),
				'tokens'      => self::tokens(),
				'pages'       => self::pages(),
				'displayPage' => null !== self::display_page(),
				'nonce'       => wp_create_nonce( Frontend::NONCE ),
				'templatesUrl' => admin_url( 'edit.php?post_type=bricks_template' ),
			)
		);
	}

	public function starter( WP_REST_Request $request ) {
		if ( self::problems() ) {
			return new WP_Error( 'lfn_requirements', implode( ' ', self::problems() ), array( 'status' => 400 ) );
		}
		$id = Starter::create( sanitize_text_field( (string) $request->get_param( 'name' ) ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$settings = get_post_meta( $id, '_bricks_template_settings', true );

		return new WP_REST_Response(
			array(
				'id'         => $id,
				'headers'    => Store::headers(),
				'wholeSite'  => is_array( $settings ) && ! empty( $settings['templateConditions'] ),
				'editUrl'    => add_query_arg( 'bricks', 'run', (string) get_permalink( $id ) ),
			)
		);
	}

	public function draft( WP_REST_Request $request ): WP_REST_Response {
		$design = Design::clean( (array) $request->get_param( 'design' ) );

		return new WP_REST_Response( Store::save_draft( $design ) + array( 'design' => $design ) );
	}

	public function save( WP_REST_Request $request ): WP_REST_Response {
		$design = Design::clean( (array) $request->get_param( 'design' ) );
		Store::save( $design );

		return new WP_REST_Response( array( 'design' => $design, 'unsaved' => false ) );
	}

	public function discard(): WP_REST_Response {
		Store::discard_draft();

		return new WP_REST_Response( array( 'design' => Store::current(), 'unsaved' => false ) );
	}

	/**
	 * What's missing for the Navigation Builder to work.
	 *
	 * @return list<string>
	 */
	public static function problems(): array {
		$problems = Requirements::problems();
		if ( ! class_exists( '\BricksExtras\Plugin' ) ) {
			$problems[] = __( 'The Navigation Builder needs BricksExtras, for its Header Row element.', 'leapfrog-framework' );
		} elseif ( '1' !== (string) get_option( 'bricksextras_header_row', '0' ) ) {
			$problems[] = __( 'Switch on Header Row in BricksExtras (BricksExtras > Elements): the starter header is built on it.', 'leapfrog-framework' );
		}

		return $problems;
	}

	/**
	 * Bricks' breakpoints (custom ones included), widest first, as preview sizes: each one
	 * pixel inside its Bricks width (screen scaling can leave the preview a fraction wider),
	 * the base at a typical desktop width; heights are typical screens of that shape.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function breakpoints(): array {
		$list = is_callable( array( '\Bricks\Breakpoints', 'get_breakpoints' ) ) ? (array) \Bricks\Breakpoints::get_breakpoints() : array();
		if ( ! $list ) {
			$list = array( array( 'key' => 'desktop', 'label' => 'Desktop', 'width' => 1279, 'icon' => 'laptop', 'base' => true ) );
		}
		$mobile_first = ! empty( \Bricks\Breakpoints::$is_mobile_first );
		$out          = array();
		foreach ( $list as $bp ) {
			$width = (int) ( $bp['width'] ?? 0 );
			$icon  = (string) ( $bp['icon'] ?? '' );
			$base  = ! empty( $bp['base'] );
			$show  = $base && ! $mobile_first ? max( 1440, $width + 1 ) : max( 320, $mobile_first ? $width + 1 : $width - 1 );
			if ( str_contains( $icon, 'landscape' ) ) {
				$height = (int) round( $show * ( str_contains( $icon, 'phone' ) ? 0.48 : 0.75 ) );
			} elseif ( $show >= 1100 ) {
				$height = 900;
			} elseif ( $show >= 600 ) {
				$height = (int) round( $show * 1.33 );
			} else {
				$height = (int) round( $show * 2.05 );
			}
			$out[] = array(
				'key'         => sanitize_key( (string) ( $bp['key'] ?? '' ) ),
				'label'       => wp_strip_all_tags( (string) ( $bp['label'] ?? '' ) ),
				'width'       => $show,
				'height'      => $height,
				'bricksWidth' => $width,
				'icon'        => $icon,
				'base'        => $base,
			);
		}
		usort( $out, static fn( array $a, array $b ): int => $b['width'] <=> $a['width'] );

		return $out;
	}

	/**
	 * @return array{title: string, url: string}|null
	 */
	private static function display_page(): ?array {
		$page = get_page_by_path( self::PREVIEW_SLUG );
		if ( ! $page || ! in_array( $page->post_status, array( 'publish', 'private', 'draft' ), true ) ) {
			return null;
		}

		return array(
			'title' => __( 'Header display page', 'leapfrog-framework' ),
			'url'   => 'draft' === $page->post_status ? (string) get_preview_post_link( $page ) : (string) get_permalink( $page ),
		);
	}

	/**
	 * @return list<array{title: string, url: string}>
	 */
	private static function pages(): array {
		$pages = array();
		if ( $display = self::display_page() ) {
			$pages[] = $display;
		}
		$pages[] = array( 'title' => __( 'Home page', 'leapfrog-framework' ), 'url' => home_url( '/' ) );
		$skip    = array( (int) get_option( 'page_on_front' ) );
		$found   = get_page_by_path( self::PREVIEW_SLUG );
		if ( $found ) {
			$skip[] = (int) $found->ID;
		}
		foreach ( get_pages( array( 'post_status' => 'publish', 'number' => 60, 'sort_column' => 'menu_order,post_title' ) ) as $page ) {
			if ( ! in_array( (int) $page->ID, $skip, true ) ) {
				$pages[] = array( 'title' => $page->post_title ?: '#' . $page->ID, 'url' => (string) get_permalink( $page ) );
			}
		}

		return $pages;
	}

	/**
	 * Colours (with a swatch) and variables, for the style pickers.
	 *
	 * @return array{colors: list<array<string, string>>, variables: list<array<string, string>>}
	 */
	private static function tokens(): array {
		$raw_to_light = array();
		$palettes     = get_option( 'bricks_color_palette', array() );
		$palettes     = is_array( $palettes ) ? $palettes : array();
		foreach ( $palettes as $palette ) {
			foreach ( (array) ( $palette['colors'] ?? array() ) as $color ) {
				if ( ! empty( $color['raw'] ) ) {
					$raw_to_light[ (string) $color['raw'] ] = (string) ( $color['light'] ?? $color['hex'] ?? '' );
				}
			}
		}
		$resolve = static function ( string $value ) use ( $raw_to_light ): string {
			for ( $i = 0; $i < 5 && isset( $raw_to_light[ $value ] ); $i++ ) {
				$value = $raw_to_light[ $value ];
			}
			return $value;
		};

		$colors = array();
		foreach ( $palettes as $palette ) {
			if ( 'Default' === ( $palette['name'] ?? '' ) ) {
				continue;
			}
			foreach ( (array) ( $palette['colors'] ?? array() ) as $color ) {
				if ( empty( $color['raw'] ) || ! preg_match( '/^var\(--([\w-]+)\)$/', (string) $color['raw'], $m ) ) {
					continue;
				}
				$colors[] = array( 'name' => $m[1], 'value' => (string) $color['raw'], 'swatch' => $resolve( (string) ( $color['light'] ?? '' ) ), 'palette' => (string) ( $palette['name'] ?? '' ) );
			}
		}

		$categories = array();
		foreach ( (array) get_option( 'bricks_global_variables_categories', array() ) as $category ) {
			$categories[ (string) ( $category['id'] ?? '' ) ] = (string) ( $category['name'] ?? '' );
		}
		$variables = array();
		foreach ( (array) get_option( 'bricks_global_variables', array() ) as $variable ) {
			if ( ! empty( $variable['name'] ) ) {
				$variables[] = array( 'name' => (string) $variable['name'], 'value' => 'var(--' . $variable['name'] . ')', 'category' => $categories[ (string) ( $variable['category'] ?? '' ) ] ?? '' );
			}
		}

		return array( 'colors' => $colors, 'variables' => $variables );
	}
}
