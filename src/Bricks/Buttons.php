<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\VERSION;

/**
 * Connects Bricks' own Button controls (Style, Size, Outline, Circle) to
 * Leapfrog's button styles: Bricks' colour and size classes are swapped for
 * lf-btn classes, styled from the --btn-* component tokens and colour roles.
 * Ported from CoreBridge, without Core Framework.
 */
final class Buttons {
	private const STYLES = array( 'primary', 'secondary', 'accent', 'neutral' );

	/** Classes Leapfrog manages on a button; anything else (your own classes) is kept. */
	private const MANAGED = array( 'lf-btn', 'lf-btn--secondary', 'lf-btn--accent', 'lf-btn--neutral', 'lf-btn--outline', 'lf-btn--circle', 'lf-btn--sm', 'lf-btn--lg', 'sm', 'md', 'lg', 'xl' );

	public function register(): void {
		add_filter( 'bricks/elements/button/controls', array( $this, 'controls' ), 20 );
		add_filter( 'bricks/element/render_attributes', array( $this, 'render_attributes' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	/**
	 * Keeps Bricks' controls; Style offers the Leapfrog button styles.
	 *
	 * @param array<string, mixed> $controls
	 * @return array<string, mixed>
	 */
	public function controls( array $controls ): array {
		if ( is_array( $controls['style'] ?? null ) ) {
			$controls['style']['options']     = array(
				'primary'   => __( 'Primary', 'leapfrog-framework' ),
				'secondary' => __( 'Secondary', 'leapfrog-framework' ),
				'accent'    => __( 'Accent', 'leapfrog-framework' ),
				'neutral'   => __( 'Neutral', 'leapfrog-framework' ),
			);
			$controls['style']['default']     = 'primary';
			$controls['style']['placeholder'] = __( 'Primary', 'leapfrog-framework' );
			$controls['style']['description'] = __( 'Leapfrog button style, using your brand colours and the text-on-colour roles.', 'leapfrog-framework' );
		}
		if ( is_array( $controls['size'] ?? null ) ) {
			$controls['size']['options']     = array(
				'sm' => __( 'Small', 'leapfrog-framework' ),
				'md' => __( 'Medium', 'leapfrog-framework' ),
				'lg' => __( 'Large', 'leapfrog-framework' ),
			);
			$controls['size']['placeholder'] = __( 'Medium', 'leapfrog-framework' );
		}
		if ( is_array( $controls['outline'] ?? null ) ) {
			$controls['outline']['description'] = __( 'Transparent until hovered, then filled with the style colour.', 'leapfrog-framework' );
		}
		if ( is_array( $controls['circle'] ?? null ) ) {
			$controls['circle']['description'] = __( 'Fully rounded ends; keeps the button’s size and padding.', 'leapfrog-framework' );
		}

		return $controls;
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @return array<string, mixed>
	 */
	public function render_attributes( array $attributes, string $key, object $element ): array {
		if ( 'button' !== (string) ( $element->name ?? '' ) ) {
			return $attributes;
		}
		$classes = $attributes[ $key ]['class'] ?? array();
		if ( is_string( $classes ) ) {
			$classes = preg_split( '/\s+/', trim( $classes ) ) ?: array();
		}
		if ( ! is_array( $classes ) || ! in_array( 'bricks-button', $classes, true ) ) {
			return $attributes;
		}

		$classes = array_values(
			array_filter(
				array_map( 'strval', $classes ),
				static fn( string $c ): bool => '' !== $c
					&& ! in_array( $c, self::MANAGED, true )
					&& ! str_starts_with( $c, 'bricks-background-' )
					&& ! str_starts_with( $c, 'bricks-color-' )
			)
		);

		$settings                     = is_array( $element->settings ?? null ) ? $element->settings : array();
		$attributes[ $key ]['class'] = array_values( array_unique( array_merge( $classes, self::classes( $settings ) ) ) );

		return $attributes;
	}

	/**
	 * The lf-btn classes for a button's settings (the builder script mirrors this).
	 *
	 * @param array<string, mixed> $settings
	 * @return list<string>
	 */
	public static function classes( array $settings ): array {
		$out   = array( 'lf-btn' );
		$style = strtolower( trim( (string) ( $settings['style'] ?? 'primary' ) ) );
		if ( in_array( $style, self::STYLES, true ) && 'primary' !== $style ) {
			$out[] = 'lf-btn--' . $style;
		}
		$size = (string) ( $settings['size'] ?? 'md' );
		if ( 'sm' === $size || 'lg' === $size ) {
			$out[] = 'lf-btn--' . $size;
		}
		if ( ! empty( $settings['outline'] ) ) {
			$out[] = 'lf-btn--outline';
		}
		if ( ! empty( $settings['circle'] ) ) {
			$out[] = 'lf-btn--circle';
		}

		return $out;
	}

	public function assets(): void {
		$url = plugin_dir_url( FILE ) . 'assets/';
		wp_enqueue_style( 'leapfrog-buttons', $url . 'frontend/buttons.css', array(), VERSION );

		if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
			wp_enqueue_script( 'leapfrog-buttons-builder', $url . 'builder/buttons.js', array(), VERSION, true );
		}
	}
}
