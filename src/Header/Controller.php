<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Header;

use Leapfrog\Framework\Requirements;
use Leapfrog\Framework\Rest\RestController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for the Header Builder (administrators only):
 *   GET  /header              the Bricks headers, tokens and pages
 *   GET  /header/design?id=   one header: its parts, style settings and current design
 *   POST /header/draft        store unsaved changes
 *   POST /header/save         save (writes the header's sticky settings into Bricks)
 *   POST /header/discard      drop unsaved changes
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
				array( '/header', WP_REST_Server::READABLE, 'start' ),
				array( '/header/design', WP_REST_Server::READABLE, 'design' ),
				array( '/header/draft', WP_REST_Server::CREATABLE, 'draft' ),
				array( '/header/save', WP_REST_Server::CREATABLE, 'save' ),
				array( '/header/discard', WP_REST_Server::CREATABLE, 'discard' ),
			) as $route
		) {
			register_rest_route( $ns, $route[0], array( 'methods' => $route[1], 'callback' => array( $this, $route[2] ), 'permission_callback' => $admin ) );
		}
	}

	public function start(): WP_REST_Response {
		$drafts = get_option( Store::DRAFTS, array() );
		$saved  = get_option( Store::OPTION, array() );
		$list   = array();
		foreach ( Store::headers() as $header ) {
			$list[] = $header + array(
				'unsaved' => is_array( $drafts ) && isset( $drafts[ $header['id'] ] ),
				'styled'  => is_array( $saved ) && isset( $saved[ $header['id'] ] ),
			);
		}
		$pages = self::pages();

		return new WP_REST_Response(
			array(
				'problems'    => Requirements::problems(),
				'headers'     => $list,
				'newHeaderUrl' => admin_url( 'edit.php?post_type=bricks_template' ),
				'tokens'      => self::tokens(),
				'pages'       => $pages,
				'breakpoints' => self::breakpoints(),
				'displayPage' => self::display_page(),
				'nonce'       => wp_create_nonce( Frontend::NONCE ),
			)
		);
	}

	public function design( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( ! Store::is_header( $id ) ) {
			return new WP_Error( 'lfh_header', __( 'That Bricks header template doesn’t exist.', 'leapfrog-framework' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::summary( $id ) );
	}

	public function draft( WP_REST_Request $request ) {
		$design = self::design_from( $request );
		if ( is_wp_error( $design ) ) {
			return $design;
		}

		return new WP_REST_Response( Store::save_draft( $design ) + array( 'design' => $design ) );
	}

	public function save( WP_REST_Request $request ) {
		$design = self::design_from( $request );
		if ( is_wp_error( $design ) ) {
			return $design;
		}
		Store::save( $design );

		return new WP_REST_Response( self::summary( (int) $design['templateId'] ) );
	}

	public function discard( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( ! Store::is_header( $id ) ) {
			return new WP_Error( 'lfh_header', __( 'That Bricks header template doesn’t exist.', 'leapfrog-framework' ), array( 'status' => 404 ) );
		}
		Store::discard_draft( $id );

		return new WP_REST_Response( self::summary( $id ) );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function design_from( WP_REST_Request $request ) {
		$raw = $request->get_param( 'design' );
		$id  = is_array( $raw ) ? absint( $raw['templateId'] ?? 0 ) : 0;
		if ( ! Store::is_header( $id ) ) {
			return new WP_Error( 'lfh_design', __( 'No header design was sent.', 'leapfrog-framework' ), array( 'status' => 400 ) );
		}

		return Design::clean( $raw, Store::fields( $id ) );
	}

	/**
	 * Everything the builder needs for one header.
	 *
	 * @return array<string, mixed>
	 */
	private static function summary( int $id ): array {
		return array(
			'id'      => $id,
			'title'   => get_the_title( $id ),
			'design'  => Store::current( $id ),
			'unsaved' => null !== Store::draft( $id ),
			'styled'  => null !== Store::saved( $id ),
			'rows'    => Store::rows( $id ),
			'parts'   => Store::parts( $id ),
			'fields'  => Store::fields( $id ),
			'prefix'  => Design::PREFIX,
			'editUrl' => add_query_arg( 'bricks', 'run', (string) get_permalink( $id ) ),
			'live'    => 'publish' === get_post_status( $id ),
		);
	}

	/**
	 * Bricks' breakpoints (Bricks > Settings > Breakpoints, custom ones included), widest first,
	 * as preview sizes. Each shows at its Bricks width, so Bricks applies that breakpoint's
	 * styles; the base breakpoint shows at a typical desktop width. The height is a typical
	 * screen of that shape, so scrolling behaves as it will there.
	 *
	 * @return list<array{key: string, label: string, width: int, height: int, bricksWidth: int, icon: string, base: bool}>
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
			// Desktop-first: a breakpoint applies up to its width; mobile-first: from its width up.
			// One pixel inside that limit, as screen scaling can leave the preview a fraction wider.
			if ( $base && ! $mobile_first ) {
				$show = max( 1440, $width + 1 );
			} else {
				$show = max( 320, $mobile_first ? $width + 1 : $width - 1 );
			}
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
				'key'    => sanitize_key( (string) ( $bp['key'] ?? '' ) ),
				'label'  => wp_strip_all_tags( (string) ( $bp['label'] ?? '' ) ),
				'width'  => $show,
				'height' => $height,
				'bricksWidth' => $width,
				'icon'   => $icon,
				'base'   => $base,
			);
		}
		usort( $out, static fn( array $a, array $b ): int => $b['width'] <=> $a['width'] );

		return $out;
	}

	/**
	 * The long sample page for watching the header while scrolling, if the site has one.
	 *
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
				continue; // Bricks' sample colours.
			}
			foreach ( (array) ( $palette['colors'] ?? array() ) as $color ) {
				if ( empty( $color['raw'] ) || ! preg_match( '/^var\(--([\w-]+)\)$/', (string) $color['raw'], $m ) ) {
					continue;
				}
				$colors[] = array(
					'name'    => $m[1],
					'value'   => (string) $color['raw'],
					'swatch'  => $resolve( (string) ( $color['light'] ?? '' ) ),
					'palette' => (string) ( $palette['name'] ?? '' ),
				);
			}
		}

		$categories = array();
		foreach ( (array) get_option( 'bricks_global_variables_categories', array() ) as $category ) {
			$categories[ (string) ( $category['id'] ?? '' ) ] = (string) ( $category['name'] ?? '' );
		}
		$variables = array();
		foreach ( (array) get_option( 'bricks_global_variables', array() ) as $variable ) {
			if ( ! empty( $variable['name'] ) ) {
				$variables[] = array(
					'name'     => (string) $variable['name'],
					'value'    => 'var(--' . $variable['name'] . ')',
					'category' => $categories[ (string) ( $variable['category'] ?? '' ) ] ?? '',
				);
			}
		}

		return array( 'colors' => $colors, 'variables' => $variables );
	}

	/**
	 * Pages to preview the header on: the display page and the home page first.
	 *
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
		$post = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
		if ( $post ) {
			$pages[] = array( 'title' => __( 'Latest post: ', 'leapfrog-framework' ) . get_the_title( $post[0] ), 'url' => (string) get_permalink( $post[0] ) );
		}

		return $pages;
	}
}
