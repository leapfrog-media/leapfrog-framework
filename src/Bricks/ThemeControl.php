<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\VERSION;

/**
 * A Theme control on Bricks Section, Container, Block and Div: Inherit, Light, Dark, Inverted.
 *
 * Renders data-lf-theme on the element; Leapfrog's theme CSS does the rest.
 * In the builder a small script mirrors the setting onto the canvas and shows a
 * badge in the Structure panel. Ported from CoreBridge's ThemeModeIntegration;
 * sections themed with CoreBridge keep working (its setting is read as a fallback).
 */
final class ThemeControl {
	public const SETTING_KEY = 'lfTheme';

	private const LEGACY_KEYS = array( 'corebridgeThemeMode', 'plinthColourMode' );

	private const TARGETS = array( 'section', 'container', 'block', 'div' );

	private const MODES = array( 'light', 'dark', 'inverted' );

	public function register(): void {
		foreach ( self::TARGETS as $name ) {
			add_filter( "bricks/elements/{$name}/controls", array( $this, 'controls' ), 35 );
		}
		add_filter( 'bricks/element/set_root_attributes', array( $this, 'root_attributes' ), 25, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'builder_assets' ), 30 );
	}

	/**
	 * Adds the Theme select right after Bricks' HTML tag control.
	 *
	 * @param array<string, mixed> $controls
	 * @return array<string, mixed>
	 */
	public function controls( array $controls ): array {
		if ( isset( $controls[ self::SETTING_KEY ] ) ) {
			return $controls;
		}

		$control = array(
			'tab'            => 'content',
			'label'          => __( 'Theme', 'leapfrog-framework' ),
			'type'           => 'select',
			'options'        => array(
				''         => __( 'Inherit', 'leapfrog-framework' ),
				'light'    => __( 'Light', 'leapfrog-framework' ),
				'dark'     => __( 'Dark', 'leapfrog-framework' ),
				'inverted' => __( 'Inverted', 'leapfrog-framework' ),
			),
			'inline'         => true,
			'clearable'      => true,
			'placeholder'    => __( 'Inherit', 'leapfrog-framework' ),
			'hasDynamicData' => false,
			'description'    => __( 'Leapfrog colour theme for this element and everything inside it. Inverted flips the page (or the section around it).', 'leapfrog-framework' ),
		);

		$result   = array();
		$inserted = false;
		foreach ( $controls as $key => $existing ) {
			$result[ $key ] = $existing;
			if ( 'tag' === $key ) {
				$result[ self::SETTING_KEY ] = $control;
				$inserted                    = true;
			}
		}

		return $inserted ? $result : array( self::SETTING_KEY => $control ) + $result;
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @return array<string, mixed>
	 */
	public function root_attributes( array $attributes, object $element ): array {
		if ( ! in_array( (string) ( $element->name ?? '' ), self::TARGETS, true ) ) {
			return $attributes;
		}

		$settings = is_array( $element->settings ?? null ) ? $element->settings : (array) ( $element->element['settings'] ?? array() );
		$mode     = self::mode( $settings );
		if ( '' !== $mode ) {
			$attributes['data-lf-theme'] = $mode;
		}

		return $attributes;
	}

	public function builder_assets(): void {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}

		$url = plugin_dir_url( FILE ) . 'assets/builder/';
		wp_enqueue_style( 'leapfrog-theme-control', $url . 'theme-control.css', array(), VERSION );
		wp_enqueue_script( 'leapfrog-theme-control', $url . 'theme-control.js', array(), VERSION, true );
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function mode( array $settings ): string {
		$value = $settings[ self::SETTING_KEY ] ?? '';
		if ( '' === $value ) {
			foreach ( self::LEGACY_KEYS as $key ) {
				if ( ! empty( $settings[ $key ] ) ) {
					$value = $settings[ $key ];
					break;
				}
			}
		}
		$mode = strtolower( trim( (string) $value ) );

		return in_array( $mode, self::MODES, true ) ? $mode : '';
	}
}
