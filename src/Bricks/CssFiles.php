<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

/**
 * Keeps Bricks' generated CSS files current after a Leapfrog save.
 *
 * - style-manager.min.css (palette and scale variables) is always rebuilt, as Bricks' builder does.
 * - When Bricks > Settings > Performance > CSS loading method is "External files", the
 *   variable and palette files are rebuilt too. Bricks only loads those classes in that
 *   mode, so they are loaded here the same way Bricks' own maintenance code does.
 * With the default inline CSS loading, Bricks prints the CSS into each page instead.
 */
final class CssFiles {
	private const FILE_CLASSES = array(
		'\Bricks\Assets_Global_Variables' => 'includes/assets/global-variables.php',
		'\Bricks\Assets_Color_Palettes'   => 'includes/assets/color-palettes.php',
	);

	public static function rebuild(): void {
		if ( is_callable( array( '\Bricks\Ajax', 'generate_style_manager_css_file' ) ) ) {
			\Bricks\Ajax::generate_style_manager_css_file(); // Also sets up $wp_filesystem.
		}

		if ( ! self::external_files() ) {
			return;
		}

		foreach ( self::FILE_CLASSES as $class => $file ) {
			if ( ! class_exists( $class, false ) && defined( 'BRICKS_PATH' ) && is_readable( BRICKS_PATH . $file ) ) {
				require_once BRICKS_PATH . $file;
			}
		}

		if ( is_callable( array( '\Bricks\Assets_Global_Variables', 'generate_css_file' ) ) ) {
			\Bricks\Assets_Global_Variables::generate_css_file( get_option( 'bricks_global_variables', array() ) );
		}
		if ( is_callable( array( '\Bricks\Assets_Color_Palettes', 'generate_css_file' ) ) ) {
			\Bricks\Assets_Color_Palettes::generate_css_file( get_option( 'bricks_color_palette', array() ) );
		}
	}

	private static function external_files(): bool {
		return is_callable( array( '\Bricks\Database', 'get_setting' ) ) && 'file' === \Bricks\Database::get_setting( 'cssLoading' );
	}
}
