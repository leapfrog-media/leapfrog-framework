<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Rest;

use Leapfrog\Framework\Bricks\BricksGateway;
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
