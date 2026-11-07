<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Rest;

use Leapfrog\Framework\Bricks\BricksGateway;
use Leapfrog\Framework\Bricks\ScaleSync;
use Leapfrog\Framework\Bricks\VariableSet;
use Leapfrog\Framework\Build;
use Leapfrog\Framework\Color\Palette;
use Leapfrog\Framework\Color\Roles;
use Leapfrog\Framework\Scale\Components;
use Leapfrog\Framework\Scale\Scales;
use Leapfrog\Framework\Scale\Shape;
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
			'/roles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_roles' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/roles/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_roles' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/roles/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_roles' ),
				'permission_callback' => $admin,
				'args'                => array(
					'revision' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/shape',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_shape' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/shape/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_shape' ),
				'permission_callback' => $admin,
				'args'                => array(
					'revision' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/components',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_components' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/components/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_components' ),
				'permission_callback' => $admin,
				'args'                => array(
					'revision' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scales',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_scales' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scales/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_scales' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scales/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_scales' ),
				'permission_callback' => $admin,
				'args'                => array(
					'revision' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/style-manager/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_style_manager' ),
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
			$saved = SettingsStore::save( $settings, (int) $request->get_param( 'revision' ), __( 'Colours', 'leapfrog-framework' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		$generated = Palette::generate( $saved['colors']['items'], $saved['colors']['shadeSteps'], $saved['colors']['alphaSteps'] );

		try {
			// Roles and section themes depend on the colours, so they are rebuilt too.
			$synced  = Build::sync_colours( $saved );
			$summary = $synced['palette'];
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

	public function get_roles(): WP_REST_Response {
		$settings = SettingsStore::get();

		return new WP_REST_Response( $this->roles_payload( $settings, $settings['roles']['map'] ) );
	}

	/**
	 * Resolved roles and contrast for unsaved choices.
	 */
	public function preview_roles( WP_REST_Request $request ): WP_REST_Response {
		$settings = SettingsStore::get();

		return new WP_REST_Response( $this->roles_payload( $settings, Roles::sanitize_map( $request->get_param( 'map' ) ) ) );
	}

	/**
	 * Saves the role choices, then rebuilds both palettes and the theme CSS.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_roles( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		$settings                        = SettingsStore::get();
		$settings['roles']['map']        = Roles::sanitize_map( $request->get_param( 'map' ) );
		$settings['roles']['pageStyles'] = (bool) $request->get_param( 'pageStyles' );

		try {
			$saved = SettingsStore::save( $settings, (int) $request->get_param( 'revision' ), __( 'Roles and themes', 'leapfrog-framework' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		try {
			$synced = Build::sync_colours( $saved );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'leapfrog_bricks_sync',
				sprintf( /* translators: %s: error from Bricks */ __( 'Your roles were saved, but Bricks could not be fully updated: %s Save again to retry.', 'leapfrog-framework' ), $error->getMessage() ),
				array( 'status' => 500, 'revision' => $saved['revision'] )
			);
		}

		return new WP_REST_Response( $this->roles_payload( $saved, $saved['roles']['map'] ) + array( 'summary' => $synced['roles'] ) );
	}

	public function get_shape(): WP_REST_Response {
		return new WP_REST_Response( $this->shape_payload( SettingsStore::get() ) );
	}

	/**
	 * Saves layout and shape values and writes them to Bricks.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_shape( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		$settings                     = SettingsStore::get();
		$settings['shape']['corners'] = (string) $request->get_param( 'corners' );
		$settings['shape']['values']  = Shape::sanitize( $request->get_param( 'values' ) );

		try {
			$saved = SettingsStore::save( $settings, (int) $request->get_param( 'revision' ), __( 'Layout and shape', 'leapfrog-framework' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		try {
			$summary = ( new VariableSet() )->sync( Shape::CATEGORY, Shape::variables( $saved['shape']['values'] ) );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'leapfrog_bricks_sync',
				sprintf( /* translators: %s: error from Bricks */ __( 'Your values were saved, but Bricks could not be fully updated: %s Save again to retry.', 'leapfrog-framework' ), $error->getMessage() ),
				array( 'status' => 500, 'revision' => $saved['revision'] )
			);
		}

		return new WP_REST_Response( $this->shape_payload( $saved ) + array( 'summary' => $summary ) );
	}

	public function get_components(): WP_REST_Response {
		return new WP_REST_Response( $this->components_payload( SettingsStore::get() ) );
	}

	/**
	 * Saves component tokens and writes them to Bricks.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_components( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		$settings                         = SettingsStore::get();
		$settings['components']['values'] = Components::sanitize( $request->get_param( 'values' ) );

		try {
			$saved = SettingsStore::save( $settings, (int) $request->get_param( 'revision' ), __( 'Components', 'leapfrog-framework' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		try {
			$summary = ( new VariableSet() )->sync( Components::CATEGORY, Components::variables( $saved['components']['values'] ) );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'leapfrog_bricks_sync',
				sprintf( /* translators: %s: error from Bricks */ __( 'Your values were saved, but Bricks could not be fully updated: %s Save again to retry.', 'leapfrog-framework' ), $error->getMessage() ),
				array( 'status' => 500, 'revision' => $saved['revision'] )
			);
		}

		return new WP_REST_Response( $this->components_payload( $saved ) + array( 'summary' => $summary ) );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function components_payload( array $settings ): array {
		$groups = array();
		foreach ( Components::groups() as $id => $group ) {
			$groups[] = array( 'id' => $id, 'label' => $group['label'], 'names' => array_keys( $group['values'] ) );
		}

		return array(
			'revision' => $settings['revision'],
			'values'   => $settings['components']['values'],
			'defaults' => Components::defaults(),
			'groups'   => $groups,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $map
	 * @return array<string, mixed>
	 */
	private function roles_payload( array $settings, array $map ): array {
		$generated = Palette::generate( $settings['colors']['items'], $settings['colors']['shadeSteps'], $settings['colors']['alphaSteps'] );
		$tokens    = Roles::tokens( $generated );
		$resolved  = Roles::resolve( $map, $tokens );

		$options = array();
		foreach ( $tokens as $name => $token ) {
			$options[] = array( 'name' => $name, 'light' => $token['light'], 'dark' => $token['dark'] );
		}
		$transparent = array();
		foreach ( Roles::TRANSPARENT as $key => $def ) {
			$transparent[] = array( 'key' => $key, 'label' => $def[0], 'variable' => '--color-' . $key, 'value' => $def[1] );
		}

		return array(
			'revision'    => $settings['revision'],
			'pageStyles'  => (bool) $settings['roles']['pageStyles'],
			'roles'       => $resolved,
			'tokens'      => $options,
			'transparent' => $transparent,
			'contrast'    => Roles::contrast( $resolved ),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function shape_payload( array $settings ): array {
		$groups = array();
		foreach ( Shape::groups() as $id => $group ) {
			$groups[] = array( 'id' => $id, 'label' => $group['label'], 'names' => array_keys( $group['values'] ) );
		}

		return array(
			'revision' => $settings['revision'],
			'corners'  => $settings['shape']['corners'],
			'values'   => $settings['shape']['values'],
			'defaults' => Shape::defaults(),
			'presets'  => Shape::CORNERS,
			'groups'   => $groups,
		);
	}

	/**
	 * Scale settings (Bricks wins when the scale was edited there), Style Manager basis and previews.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_scales() {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		try {
			$settings = SettingsStore::get();
			$sync     = new ScaleSync();
			$bricks   = $sync->read();
			$out      = array(
				'revision'   => $settings['revision'],
				'inBricks'   => array( 'typography' => null !== $bricks['typography'], 'spacing' => null !== $bricks['spacing'] ),
				'manual'     => array( 'typography' => ! empty( $bricks['typography']['manual'] ), 'spacing' => ! empty( $bricks['spacing']['manual'] ) ),
				'typography' => $this->with_bricks_scale( $settings['typography'], $bricks['typography'], Scales::default_typography() ),
				'spacing'    => $this->with_bricks_scale( $settings['spacing'], $bricks['spacing'], Scales::default_spacing() ),
				'steps'      => Scales::STEPS,
				'ratios'     => Scales::RATIOS,
			);
			$out += $this->scale_previews( $out['typography'], $out['spacing'] );
			$out['styleManager'] = $this->style_manager_summary( $out['basis'] );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_bricks_error', $error->getMessage(), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $out );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_scales( WP_REST_Request $request ) {
		try {
			$typography = Scales::clean_scale( array_merge( Scales::default_typography(), (array) $request->get_param( 'typography' ) ), Scales::default_typography() );
			$spacing    = Scales::clean_scale( array_merge( Scales::default_spacing(), (array) $request->get_param( 'spacing' ) ), Scales::default_spacing() );

			return new WP_REST_Response( array( 'typography' => $typography, 'spacing' => $spacing ) + $this->scale_previews( $typography, $spacing ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_bricks_error', $error->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Saves typography and spacing, then writes the scales and roles to Bricks.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_scales( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		$settings = SettingsStore::get();
		foreach ( array( 'typography', 'spacing' ) as $key ) {
			if ( is_array( $request->get_param( $key ) ) ) {
				$settings[ $key ] = array_replace_recursive( $settings[ $key ], $request->get_param( $key ) );
				// Lists are replaced, not merged.
				if ( isset( $request->get_param( $key )['steps'] ) ) {
					$settings[ $key ]['steps'] = array_values( (array) $request->get_param( $key )['steps'] );
				}
			}
		}

		try {
			$saved = SettingsStore::save( $settings, (int) $request->get_param( 'revision' ), __( 'Typography and spacing', 'leapfrog-framework' ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_conflict', $error->getMessage(), array( 'status' => 409 ) );
		}

		try {
			$summary = ( new ScaleSync() )->sync( $saved['typography'], $saved['spacing'] );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'leapfrog_bricks_sync',
				sprintf( /* translators: %s: error from Bricks */ __( 'Your scales were saved, but Bricks could not be fully updated: %s Save again to retry.', 'leapfrog-framework' ), $error->getMessage() ),
				array( 'status' => 500, 'revision' => $saved['revision'] )
			);
		}

		$response = $this->get_scales();
		if ( $response instanceof WP_REST_Response ) {
			$data            = $response->get_data();
			$data['summary'] = $summary;
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Saves screen widths and default mode to Bricks' Style Manager, then regenerates the scales.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_style_manager( WP_REST_Request $request ) {
		if ( ! Requirements::ready() ) {
			return new WP_Error( 'leapfrog_not_ready', implode( ' ', Requirements::problems() ), array( 'status' => 409 ) );
		}

		$min  = (int) $request->get_param( 'minScreenWidth' );
		$max  = (int) $request->get_param( 'maxScreenWidth' );
		$mode = (string) $request->get_param( 'defaultMode' );
		if ( $min < 240 || $max > 3840 || $min >= $max - 100 ) {
			return new WP_Error( 'leapfrog_invalid', __( 'The smallest screen must be at least 240px and at least 100px narrower than the largest (up to 3840px).', 'leapfrog-framework' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $mode, array( 'light', 'dark', 'auto' ), true ) ) {
			$mode = 'light';
		}

		try {
			$gateway = new BricksGateway();
			$current = (array) ( $gateway->execute( 'bricks/get-style-manager' )['styleManager'] ?? array() );
			$gateway->execute(
				'bricks/set-style-manager',
				array( 'styleManager' => array_merge( $current, array( 'minScreenWidth' => (string) $min, 'maxScreenWidth' => (string) $max, 'defaultMode' => $mode ) ) )
			);

			// The scales depend on the screen widths, so regenerate them if Leapfrog has created them.
			$sync    = new ScaleSync( $gateway );
			$bricks  = $sync->read();
			$summary = null;
			if ( null !== $bricks['typography'] || null !== $bricks['spacing'] ) {
				$settings = SettingsStore::get();
				$summary  = $sync->sync(
					$this->with_bricks_scale( $settings['typography'], $bricks['typography'], Scales::default_typography() ),
					$this->with_bricks_scale( $settings['spacing'], $bricks['spacing'], Scales::default_spacing() )
				);
			}
		} catch ( Throwable $error ) {
			return new WP_Error( 'leapfrog_bricks_error', $error->getMessage(), array( 'status' => 500 ) );
		}

		$response = $this->get_scales();
		if ( $response instanceof WP_REST_Response ) {
			$data            = $response->get_data();
			$data['summary'] = $summary;
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Leapfrog's scale settings with the scale numbers Bricks holds (Bricks wins: it may have been edited there).
	 *
	 * @param array<string, mixed>      $settings
	 * @param array<string, mixed>|null $bricks
	 * @param array<string, mixed>      $defaults
	 * @return array<string, mixed>
	 */
	private function with_bricks_scale( array $settings, ?array $bricks, array $defaults ): array {
		if ( $bricks ) {
			unset( $bricks['manual'] );
			$settings = array_merge( $settings, $bricks );
		}

		return Scales::clean_scale( $settings, $defaults );
	}

	/**
	 * Bricks-generated previews, with px sizes at the smallest and largest screens.
	 *
	 * @param array<string, mixed> $typography
	 * @param array<string, mixed> $spacing
	 * @return array{preview:array<string,list<array<string,mixed>>>,basis:array<string,mixed>}
	 */
	private function scale_previews( array $typography, array $spacing ): array {
		$sync    = new ScaleSync();
		$type    = $sync->preview( $typography, Scales::TYPE_CATEGORY );
		$space   = $sync->preview( $spacing, Scales::SPACE_CATEGORY );
		$html_px = (float) ( $type['basis']['htmlFontSizePx'] ?? 16 );

		$rows = static function ( array $variables ) use ( $html_px ): array {
			return array_map(
				static function ( array $v ) use ( $html_px ): array {
					$px = Scales::clamp_px( (string) $v['value'], $html_px );
					return array(
						'name'     => (string) $v['name'],
						'step'     => (string) ( $v['scale']['scaleName'] ?? '' ),
						'value'    => (string) $v['value'],
						'minPx'    => $px['min'] ?? null,
						'maxPx'    => $px['max'] ?? null,
						'reversed' => $px && $px['min'] > $px['max'],
					);
				},
				$variables
			);
		};

		return array(
			'preview' => array( 'typography' => $rows( $type['variables'] ), 'spacing' => $rows( $space['variables'] ) ),
			'basis'   => $type['basis'],
		);
	}

	/**
	 * @param array<string, mixed> $basis
	 * @return array<string, mixed>
	 */
	private function style_manager_summary( array $basis ): array {
		$current = (array) ( ( new BricksGateway() )->execute( 'bricks/get-style-manager' )['styleManager'] ?? array() );

		return array(
			'htmlFontSizePx' => (float) ( $basis['htmlFontSizePx'] ?? 10 ),
			'minScreenWidth' => (int) ( $basis['minScreenWidth'] ?? 360 ),
			'maxScreenWidth' => (int) ( $basis['maxScreenWidth'] ?? 1440 ),
			'defaultMode'    => (string) ( $current['defaultMode'] ?? 'light' ),
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
