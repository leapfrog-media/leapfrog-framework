<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use Leapfrog\Framework\Build;
use Leapfrog\Framework\Color\Roles;
use Leapfrog\Framework\Settings\SettingsStore;
use Throwable;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\VERSION;

/**
 * Loads the contextual variable picker into the Bricks builder, with every
 * Bricks variable and every palette colour (Leapfrog's colours and roles, plus
 * any other palettes). Ported from CoreBridge's ContextualVariablePicker.
 */
final class VariablePicker {
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 45 );
	}

	public function assets(): void {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}
		$url = plugin_dir_url( FILE ) . 'assets/builder/';
		wp_enqueue_style( 'leapfrog-variable-picker', $url . 'variable-picker.css', array(), VERSION );
		wp_enqueue_script( 'leapfrog-variable-rules', $url . 'variable-rules.js', array(), VERSION, true );
		wp_enqueue_script( 'leapfrog-variable-picker', $url . 'variable-picker.js', array( 'leapfrog-variable-rules' ), VERSION, true );
		wp_add_inline_script( 'leapfrog-variable-picker', 'window.leapfrogVariablePicker = ' . wp_json_encode( $this->data() ) . ';', 'before' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function data(): array {
		try {
			$snapshot = ( new BricksGateway() )->list_all_variables();
			$palettes = ( new BricksGateway() )->execute( 'bricks/list-color-palettes', array( 'perPage' => 100 ) );
		} catch ( Throwable $error ) {
			return array( 'enabled' => false, 'variables' => array(), 'categories' => array(), 'error' => $error->getMessage() );
		}

		$categories = array();
		foreach ( $snapshot['categories'] as $category ) {
			$categories[ (string) $category['id'] ] = (string) $category['name'];
		}

		$variables = array();
		foreach ( $snapshot['items'] as $item ) {
			if ( ! empty( $item['name'] ) && isset( $item['value'] ) ) {
				$variables[] = array( 'name' => (string) $item['name'], 'value' => (string) $item['value'], 'category' => (string) ( $item['category'] ?? '' ) );
			}
		}

		// Palette colours: picked like variables, with a swatch the builder can show.
		$swatches = $this->swatches();
		foreach ( (array) ( $palettes['items'] ?? array() ) as $palette ) {
			$palette_id                = 'palette:' . (string) ( $palette['id'] ?? '' );
			$categories[ $palette_id ] = (string) ( $palette['name'] ?? 'Palette' );
			foreach ( (array) ( $palette['colors'] ?? array() ) as $color ) {
				if ( ! preg_match( '/^var\(\s*--([\w-]+)\s*\)$/', (string) ( $color['raw'] ?? '' ), $m ) ) {
					continue;
				}
				$value = (string) ( $color['light'] ?? $color['dark'] ?? '' );
				if ( '' === $value ) {
					continue; // A dark-only shade row; its light twin is listed.
				}
				$variables[] = array(
					'name'     => $m[1],
					'value'    => $value,
					'category' => $palette_id,
					'type'     => 'colour',
					'swatch'   => $swatches[ $m[1] ] ?? ( str_contains( $value, 'var(' ) ? '' : $value ),
				);
			}
		}

		return array( 'enabled' => true, 'variables' => $variables, 'categories' => $categories );
	}

	/**
	 * Resolved light-mode colours for Leapfrog's palette and roles.
	 *
	 * @return array<string, string>
	 */
	private function swatches(): array {
		try {
			$built = Build::colours( SettingsStore::get() );
		} catch ( Throwable $error ) {
			return array();
		}

		$out  = array();
		$text = '#000000';
		foreach ( $built['generated'] as $color ) {
			$out[ $color['key'] ] = $color['base'];
			foreach ( $color['shades'] as $step => $hex ) {
				$out[ $color['key'] . '-' . $step ] = $hex;
			}
			foreach ( array_keys( $color['alpha'] ) as $step ) {
				$out[ $color['key'] . '-a' . $step ] = sprintf( 'color-mix(in oklch, %s %d%%, transparent)', $color['base'], $step );
			}
		}
		foreach ( $built['roles'] as $role ) {
			$out[ 'color-' . $role['key'] ] = $role['lightHex'];
			if ( 'text' === $role['key'] ) {
				$text = $role['lightHex'];
			}
		}
		foreach ( Roles::TRANSPARENT as $key => $def ) {
			$out[ 'color-' . $key ] = str_replace( array( 'var(--color-text)', 'var(--black)' ), array( $text, '#000000' ), $def[1] );
		}

		return $out;
	}
}
