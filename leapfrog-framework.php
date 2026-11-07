<?php
/**
 * Plugin Name:       Leapfrog Framework
 * Plugin URI:        https://github.com/leapfrog-media/leapfrog-framework
 * Description:       Design system editor for Bricks. Generates native Bricks colour palettes, fluid scales and variables.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Leapfrog Media
 * License:           GPL-2.0-or-later
 * Text Domain:       leapfrog-framework
 */

declare(strict_types=1);

namespace Leapfrog\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION    = '0.2.0';
const FILE       = __FILE__;
const PATH       = __DIR__ . '/';
const MIN_BRICKS = '2.4';

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$file = PATH . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		( new Plugin() )->register();
	}
);
