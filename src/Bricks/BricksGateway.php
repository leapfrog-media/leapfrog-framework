<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use RuntimeException;

/**
 * The only place Leapfrog talks to Bricks' design data.
 *
 * Bricks 2.4 registers its design abilities only when Bricks > Settings > AI
 * (the Abilities API / MCP switch) is on. When an ability is registered it is
 * used as-is. Otherwise the same Bricks callback is called directly, after its
 * own permission check, so Leapfrog works without exposing the MCP surface.
 * Either way the write goes through Bricks' ownership checks.
 *
 * Ported from CoreBridge's BricksAbilityAdapter.
 */
final class BricksGateway {
	/**
	 * Ability name => [callback class, callback method, permission class, permission method].
	 * Taken from Bricks 2.4.5 includes/abilities/manager.php.
	 */
	private const CALLBACKS = array(
		'bricks/list-global-variables'         => array( 'Design', 'list_global_variables', 'Design', 'global_variables_read_permission' ),
		'bricks/set-global-variables'          => array( 'Design', 'set_global_variables', 'Design', 'global_variables_write_permission' ),
		'bricks/set-global-variable-categories' => array( 'Design', 'set_global_variable_categories', 'Design', 'global_variables_write_permission' ),
		'bricks/delete-global-variable'        => array( 'Design', 'delete_global_variable', 'Design', 'global_variables_write_permission' ),
		'bricks/generate-scale-variables'      => array( 'Design', 'generate_scale_variables', 'Design', 'generate_scale_variables_permission' ),
		'bricks/list-color-palettes'           => array( 'Design', 'list_color_palettes', 'Design', 'color_palette_permission' ),
		'bricks/create-color-palette'          => array( 'Design', 'create_color_palette', 'Design', 'color_palette_permission' ),
		'bricks/update-color-palette'          => array( 'Design', 'update_color_palette', 'Design', 'color_palette_permission' ),
		'bricks/delete-color-palette'          => array( 'Design', 'delete_color_palette', 'Design', 'color_palette_permission' ),
		'bricks/create-color'                  => array( 'Design', 'create_color', 'Design', 'color_palette_permission' ),
		'bricks/update-color'                  => array( 'Design', 'update_color', 'Design', 'color_palette_permission' ),
		'bricks/delete-color'                  => array( 'Design', 'delete_color', 'Design', 'color_palette_permission' ),
		'bricks/get-style-manager'             => array( 'Style_Manager', 'get_style_manager', 'Style_Manager', 'read_permission' ),
		'bricks/set-style-manager'             => array( 'Style_Manager', 'set_style_manager', 'Style_Manager', 'write_permission' ),
		'bricks/regenerate-css-files'          => array( 'Maintenance', 'regenerate_css_files', 'Maintenance', 'admin_permission' ),
		'bricks/get-theme-styles'              => array( 'Design', 'get_theme_styles', 'Design', 'theme_styles_permission' ),
		'bricks/create-theme-style'            => array( 'Design', 'create_theme_style', 'Design', 'theme_styles_permission' ),
		'bricks/update-theme-style'            => array( 'Design', 'update_theme_style', 'Design', 'theme_styles_permission' ),
	);

	/**
	 * Runs one Bricks design ability.
	 *
	 * @param array<string, mixed> $parameters Ability input.
	 * @return array<string, mixed>
	 */
	public function execute( string $name, array $parameters = array() ): array {
		$result = $this->run( $name, $parameters );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		if ( ! is_array( $result ) ) {
			throw new RuntimeException( sprintf( 'Bricks returned an invalid result for %s.', $name ) );
		}

		return $result;
	}

	/**
	 * Whether the call goes through registered abilities or direct callbacks. Shown in the editor.
	 */
	public function mode(): string {
		return $this->registered( 'bricks/list-global-variables' ) ? 'abilities' : 'direct';
	}

	/**
	 * Every global variable and category, read across pages as one consistent snapshot.
	 *
	 * @return array{items:list<array<string,mixed>>,categories:list<array<string,mixed>>,variableOwnership:array<string,mixed>,categoryOwnership:array<string,mixed>}
	 */
	public function list_all_variables(): array {
		$items = array();
		$page  = 1;
		$first = null;

		do {
			$result  = $this->execute( 'bricks/list-global-variables', array( 'page' => $page, 'perPage' => 200 ) );
			$first ??= $result;

			if ( ! is_array( $result['items'] ?? null ) || ! is_array( $result['categories'] ?? null ) ) {
				throw new RuntimeException( 'Bricks returned an incomplete variable list.' );
			}
			foreach ( array( 'variableOwnership', 'categoryOwnership' ) as $key ) {
				if ( $this->ownership( $first, $key ) !== $this->ownership( $result, $key ) ) {
					throw new RuntimeException( 'Bricks variables changed while they were being read. Please try again.' );
				}
			}
			if ( ! empty( $result['hasMore'] ) && empty( $result['items'] ) ) {
				throw new RuntimeException( 'Bricks returned an empty page with more variables pending.' );
			}

			$items = array_merge( $items, $result['items'] );
			++$page;
		} while ( ! empty( $result['hasMore'] ) );

		return array(
			'items'             => $items,
			'categories'        => $first['categories'],
			'variableOwnership' => $this->ownership( $first, 'variableOwnership' ),
			'categoryOwnership' => $this->ownership( $first, 'categoryOwnership' ),
		);
	}

	/**
	 * @param array<string, mixed> $parameters Ability input.
	 * @return mixed
	 */
	private function run( string $name, array $parameters ) {
		if ( $this->registered( $name ) ) {
			return wp_get_ability( $name )->execute( $parameters );
		}

		$callback = self::CALLBACKS[ $name ] ?? null;
		if ( ! $callback ) {
			throw new RuntimeException( sprintf( 'Leapfrog does not know the Bricks ability %s.', $name ) );
		}

		[ $class, $method, $permission_class, $permission_method ] = $callback;
		$class            = '\\Bricks\\Abilities\\' . $class;
		$permission_class = '\\Bricks\\Abilities\\' . $permission_class;

		if ( ! is_callable( array( $class, $method ) ) || ! is_callable( array( $permission_class, $permission_method ) ) ) {
			throw new RuntimeException( sprintf( 'This Bricks version does not provide %s.', $name ) );
		}

		$allowed = call_user_func( array( $permission_class, $permission_method ), $parameters );
		if ( true !== $allowed ) {
			return is_wp_error( $allowed ) ? $allowed : new \WP_Error( 'leapfrog_forbidden', __( 'You are not allowed to change the design system.', 'leapfrog-framework' ) );
		}

		return call_user_func( array( $class, $method ), $parameters );
	}

	private function registered( string $name ): bool {
		// wp_has_ability() checks quietly; wp_get_ability() logs a notice for unknown names.
		return function_exists( 'wp_has_ability' ) && wp_has_ability( $name );
	}

	/**
	 * @param array<string, mixed> $result Ability result.
	 * @return array<string, mixed>
	 */
	private function ownership( array $result, string $key ): array {
		if ( ! is_array( $result[ $key ] ?? null ) ) {
			throw new RuntimeException( sprintf( 'Bricks did not return %s.', $key ) );
		}

		return $result[ $key ];
	}
}
