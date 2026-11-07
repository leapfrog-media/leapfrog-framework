<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Rest;

use Leapfrog\Framework\Bricks\BricksGateway;
use Leapfrog\Framework\Bricks\PaletteSync;
use Leapfrog\Framework\Color\Palette;
use Leapfrog\Framework\Requirements;
use Leapfrog\Framework\Settings\SettingsStore;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use const Leapfrog\Framework\VERSION;

/**
 * REST routes used by the Framework editor. Administrators only.
 */
final class RestController {
	public const NAMESPACE = 'leapfrog/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$admin = array( $this, 'can_manage' );

		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/bricks/variables',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'variables' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/colours/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_colours' ),
				'permission_callback' => $admin,
				'args'                => array(
					'items' => array( 'type' => 'array', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/colours/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_colours' ),
				'permission_callback' => $admin,
				'args'                => array(
					'items'    => array( 'type' => 'array', 'required' => true ),
					'revision' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $admin,
					'args'                => array(
						'settings' => array( 'type' => 'object', 'required' => true ),
						'revision' => array( 'type' => 'integer', 'required' => true ),
					),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function status(): WP_REST_Response {
		$problems = Requirements::problems();

		return new WP_REST_Response(
			array(
				'version'       => VERSION,
				'ready'         => ! $problems,
				'problems'      => $problems,
				'bricksVersion' => defined( 'BRICKS_VERSION' ) ? BRICKS_VERSION : null,
				'gatewayMode'   => $problems ? null : ( new BricksGateway() )->mode(),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function variables() {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		try {
			$snapshot = ( new BricksGateway() )->list_all_variables();
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_bricks_error', $error->getMessage(), array( 'status' => 500 ) );
		}

		$items = array_map(
			static fn( array $row ): array => array(
				'id'       => (string) ( $row['id'] ?? '' ),
				'name'     => (string) ( $row['name'] ?? '' ),
				'value'    => (string) ( $row['value'] ?? '' ),
				'category' => (string) ( $row['category'] ?? '' ),
			),
			$snapshot['items']
		);

		$categories = array_map(
			static fn( array $row ): array => array(
				'id'       => (string) ( $row['id'] ?? '' ),
				'name'     => (string) ( $row['name'] ?? '' ),
				'hasScale' => ! empty( $row['scale'] ),
			),
			$snapshot['categories']
		);

		return new WP_REST_Response(
			array(
				'items'      => $items,
				'categories' => $categories,
				'total'      => count( $items ),
			)
		);
	}

	/**
	 * Generated colours for the editor's live preview. Saves nothing.
	 */
	public function preview_colours( WP_REST_Request $request ): WP_REST_Response {
		$settings = SettingsStore::get();
		$items    = Palette::sanitize_items( $request->get_param( 'items' ) );

		return new WP_REST_Response(
			array(
				'items'     => $items,
				'generated' => Palette::generate( $items, $settings['colors']['shadeSteps'], $settings['colors']['alphaSteps'] ),
			)
		);
	}

	/**
	 * Saves the colour settings, then makes the Bricks palette match.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_colours( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		$settings                    = SettingsStore::get();
		$settings['colors']['items'] = $request->get_param( 'items' );

		try {
			$saved = SettingsStore::save( $settings, (int) $request->get_param( 'revision' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		$generated = Palette::generate( $saved['colors']['items'], $saved['colors']['shadeSteps'], $saved['colors']['alphaSteps'] );

		try {
			$summary = ( new PaletteSync() )->sync( Palette::bricks_rows( $generated ) );
		} catch ( Throwable $error ) {
			// Settings are saved; Bricks can be brought up to date by saving again.
			return new WP_Error(
				'leapfrog_bricks_sync',
				sprintf( /* translators: %s: error from Bricks */ __( 'Your colours were saved, but Bricks could not be fully updated: %s Save again to retry.', 'leapfrog-framework' ), $error->getMessage() ),
				array( 'status' => 500, 'settings' => $saved )
			);
		}

		return new WP_REST_Response(
			array(
				'settings'  => $saved,
				'generated' => $generated,
				'summary'   => $summary,
			)
		);
	}

	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( SettingsStore::get() );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		try {
			$saved = SettingsStore::save( (array) $request->get_param( 'settings' ), (int) $request->get_param( 'revision' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( $saved );
	}
}
