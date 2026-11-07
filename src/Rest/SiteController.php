<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Rest;

use Leapfrog\Framework\Bricks\ScaleSync;
use Leapfrog\Framework\Build;
use Leapfrog\Framework\Color\Palette;
use Leapfrog\Framework\Color\Roles;
use Leapfrog\Framework\Fonts\FontInstaller;
use Leapfrog\Framework\Requirements;
use Leapfrog\Framework\Scale\Scales;
use Leapfrog\Framework\Settings\History;
use Leapfrog\Framework\Settings\SettingsStore;
use Leapfrog\Framework\Settings\Starter;
use Leapfrog\Framework\Settings\Transfer;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Whole-framework routes: brand starter, backups, rebuild, export/import and presets.
 * Every route that changes settings saves them (so the old version goes to History)
 * and then writes everything to Bricks.
 */
final class SiteController {
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$ns    = RestController::NAMESPACE;
		$admin = static fn(): bool => current_user_can( 'manage_options' );
		$route = static function ( string $path, string $methods, callable $callback ) use ( $ns, $admin ): void {
			register_rest_route( $ns, $path, array( 'methods' => $methods, 'callback' => $callback, 'permission_callback' => $admin ) );
		};

		$route( '/starter', WP_REST_Server::READABLE, array( $this, 'get_starter' ) );
		$route( '/starter/preview', WP_REST_Server::CREATABLE, array( $this, 'preview_starter' ) );
		$route( '/starter/apply', WP_REST_Server::CREATABLE, array( $this, 'apply_starter' ) );
		$route( '/history', WP_REST_Server::READABLE, array( $this, 'get_history' ) );
		$route( '/history/restore', WP_REST_Server::CREATABLE, array( $this, 'restore' ) );
		$route( '/rebuild', WP_REST_Server::CREATABLE, array( $this, 'rebuild' ) );
		$route( '/export', WP_REST_Server::READABLE, array( $this, 'export' ) );
		$route( '/import/preview', WP_REST_Server::CREATABLE, array( $this, 'preview_import' ) );
		$route( '/import', WP_REST_Server::CREATABLE, array( $this, 'import' ) );
		$route( '/presets', WP_REST_Server::READABLE, array( $this, 'get_presets' ) );
		$route( '/presets/save', WP_REST_Server::CREATABLE, array( $this, 'save_preset' ) );
		$route( '/presets/apply', WP_REST_Server::CREATABLE, array( $this, 'apply_preset' ) );
		$route( '/presets/delete', WP_REST_Server::CREATABLE, array( $this, 'delete_preset' ) );
		$route( '/fonts/status', WP_REST_Server::CREATABLE, array( $this, 'font_status' ) );
		$route( '/fonts/install', WP_REST_Server::CREATABLE, array( $this, 'install_fonts' ) );
	}

	// ----- Fonts -----

	public function font_status( WP_REST_Request $request ): WP_REST_Response {
		$families = array_map( 'strval', (array) $request->get_param( 'families' ) );

		return new WP_REST_Response( array( 'fonts' => FontInstaller::status( array_slice( $families, 0, 4 ) ), 'themeStyle' => (string) get_option( FontInstaller::THEME_STYLE_OPTION, '' ) ) );
	}

	/**
	 * Installs the heading and body fonts from Google (when missing) and sets them in the site-wide theme style.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function install_fonts( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}
		$heading = trim( (string) $request->get_param( 'heading' ) );
		$body    = trim( (string) $request->get_param( 'body' ) );

		$results = array();
		foreach ( array_unique( array_filter( array( $heading, $body ) ) ) as $family ) {
			$results[] = FontInstaller::install( $family );
		}
		try {
			$style = FontInstaller::apply_theme_style( $heading, $body );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_theme_style', $error->getMessage(), array( 'status' => 500, 'fonts' => $results ) );
		}

		return new WP_REST_Response( array( 'fonts' => $results, 'themeStyle' => $style, 'status' => FontInstaller::status( array( $heading, $body ) ) ) );
	}

	// ----- Brand starter -----

	public function get_starter(): WP_REST_Response {
		$settings = SettingsStore::get();

		return new WP_REST_Response(
			array(
				'revision' => $settings['revision'],
				'inputs'      => Starter::from_settings( $settings ),
				'fonts'       => $this->bricks_fonts(),
				'googleFonts' => array_keys( FontInstaller::google() ),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_starter( WP_REST_Request $request ) {
		try {
			$settings = Starter::apply( SettingsStore::get(), $request->get_param( 'inputs' ) );
			$built    = Build::colours( $settings );
			$type     = ( new ScaleSync() )->preview( $settings['typography'], Scales::TYPE_CATEGORY );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_preview', $error->getMessage(), array( 'status' => 500 ) );
		}

		$html_px = (float) ( $type['basis']['htmlFontSizePx'] ?? 10 );
		$sizes   = array();
		foreach ( $type['variables'] as $variable ) {
			$px = Scales::clamp_px( (string) $variable['value'], $html_px );
			$sizes[ (string) ( $variable['scale']['scaleName'] ?? '' ) ] = $px['max'] ?? null;
		}

		return new WP_REST_Response(
			array(
				'generated'  => $built['generated'],
				'roles'      => $built['roles'],
				'contrast'   => Roles::contrast( $built['roles'] ),
				'typography' => array( 'fonts' => $settings['typography']['fonts'], 'headings' => $settings['typography']['headings'], 'sizes' => $sizes, 'bodyStep' => $settings['typography']['sizes']['body'] ),
				'radius'     => $settings['shape']['values'],
				'btnRadius'  => $settings['components']['values']['btn-radius'],
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_starter( WP_REST_Request $request ) {
		$current = SettingsStore::get();

		return $this->save_and_build( Starter::apply( $current, $request->get_param( 'inputs' ) ), (int) $request->get_param( 'revision' ), __( 'Before brand starter', 'leapfrog-framework' ) );
	}

	// ----- History and rebuild -----

	public function get_history(): WP_REST_Response {
		return new WP_REST_Response( array( 'revision' => SettingsStore::get()['revision'], 'entries' => History::summaries() ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore( WP_REST_Request $request ) {
		$entry = History::find( (string) $request->get_param( 'id' ) );
		if ( ! $entry ) {
			return new WP_Error( 'leapfrog_missing', __( 'That backup no longer exists.', 'leapfrog-framework' ), array( 'status' => 404 ) );
		}

		return $this->save_and_build( SettingsStore::from_outside( $entry['settings'] ), (int) $request->get_param( 'revision' ), __( 'Before restoring a backup', 'leapfrog-framework' ) );
	}

	/**
	 * Writes the current settings to Bricks again (repairs anything changed or removed in Bricks).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rebuild() {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}
		try {
			$summary = Build::sync_all( SettingsStore::get() );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_bricks_sync', $error->getMessage(), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'summary' => $summary, 'totals' => Build::totals( $summary ) ) );
	}

	// ----- Export, import, presets -----

	public function export(): WP_REST_Response {
		return new WP_REST_Response( Transfer::export() );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_import( WP_REST_Request $request ) {
		try {
			$settings = Transfer::read_file( $request->get_param( 'file' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_invalid', $error->getMessage(), array( 'status' => 400 ) );
		}
		$file = (array) $request->get_param( 'file' );

		return new WP_REST_Response(
			array(
				'from'     => array( 'site' => (string) ( $file['site'] ?? '' ), 'exported' => (string) ( $file['exported'] ?? '' ), 'version' => (string) ( $file['version'] ?? '' ) ),
				'contents' => Transfer::describe( $settings ),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		try {
			$settings = Transfer::read_file( $request->get_param( 'file' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_invalid', $error->getMessage(), array( 'status' => 400 ) );
		}

		return $this->save_and_build( $settings, (int) $request->get_param( 'revision' ), __( 'Before import', 'leapfrog-framework' ) );
	}

	public function get_presets(): WP_REST_Response {
		$list = array(
			array( 'id' => 'defaults', 'name' => __( 'Leapfrog defaults', 'leapfrog-framework' ), 'created' => 0, 'builtIn' => true, 'contents' => Transfer::describe( Transfer::preset_settings( 'defaults' ) ) ),
		);
		foreach ( Transfer::presets() as $preset ) {
			$list[] = array(
				'id'       => $preset['id'],
				'name'     => $preset['name'],
				'created'  => $preset['created'],
				'builtIn'  => false,
				'contents' => Transfer::describe( SettingsStore::from_outside( $preset['settings'] ) ),
			);
		}

		return new WP_REST_Response( array( 'revision' => SettingsStore::get()['revision'], 'presets' => $list ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_preset( WP_REST_Request $request ) {
		try {
			Transfer::save_preset( (string) $request->get_param( 'name' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_invalid', $error->getMessage(), array( 'status' => 400 ) );
		}

		return $this->get_presets();
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_preset( WP_REST_Request $request ) {
		try {
			$settings = Transfer::preset_settings( (string) $request->get_param( 'id' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_missing', $error->getMessage(), array( 'status' => 404 ) );
		}

		return $this->save_and_build( $settings, (int) $request->get_param( 'revision' ), __( 'Before applying a preset', 'leapfrog-framework' ) );
	}

	public function delete_preset( WP_REST_Request $request ): WP_REST_Response {
		Transfer::delete_preset( (string) $request->get_param( 'id' ) );

		return $this->get_presets();
	}

	// ----- Helpers -----

	/**
	 * Saves settings (the old version goes to History with $label) and writes everything to Bricks.
	 *
	 * @param array<string, mixed> $settings
	 * @return WP_REST_Response|WP_Error
	 */
	private function save_and_build( array $settings, int $revision, string $label ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		try {
			$saved = SettingsStore::save( $settings, $revision, $label );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		$start = microtime( true );
		try {
			$summary = Build::sync_all( $saved );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'leapfrog_bricks_sync',
				sprintf( /* translators: %s: error from Bricks */ __( 'The settings were saved, but Bricks could not be fully updated: %s Use "Rebuild everything" to retry.', 'leapfrog-framework' ), $error->getMessage() ),
				array( 'status' => 500, 'revision' => $saved['revision'] )
			);
		}

		return new WP_REST_Response(
			array(
				'revision' => $saved['revision'],
				'summary'  => $summary,
				'totals'   => Build::totals( $summary ),
				'seconds'  => round( microtime( true ) - $start, 1 ),
				'inputs'   => Starter::from_settings( $saved ),
			)
		);
	}

	/**
	 * Font families added in Bricks > Custom Fonts.
	 *
	 * @return list<string>
	 */
	private function bricks_fonts(): array {
		if ( ! post_type_exists( 'bricks_fonts' ) ) {
			return array();
		}
		$posts = get_posts( array( 'post_type' => 'bricks_fonts', 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids' ) );

		return array_values( array_filter( array_map( 'get_the_title', $posts ) ) );
	}
}
