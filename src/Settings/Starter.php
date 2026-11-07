<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Settings;

use Leapfrog\Framework\Color\Color;
use Leapfrog\Framework\Scale\Shape;

/**
 * The brand starter: a few brand choices in, the whole framework's settings out.
 */
final class Starter {
	/** Type contrast: ratio on the smallest and largest screens. */
	public const TYPE_CONTRAST = array(
		'calm'     => array( 1.125, 1.2 ),
		'balanced' => array( 1.2, 1.25 ),
		'bold'     => array( 1.25, 1.333 ),
	);

	/** Space density: m step on the smallest and largest screens (px), and the ratio. */
	public const SPACE_DENSITY = array(
		'compact'     => array( 12.0, 16.0, 1.5 ),
		'comfortable' => array( 16.0, 24.0, 1.5 ),
		'spacious'    => array( 20.0, 32.0, 1.5 ),
	);

	/** Role choices that make dark backgrounds neutral grey or the brand colour. */
	private const DARK_BACKGROUNDS = array(
		'neutral' => array(
			'canvas'          => array( 'dark' => 'neutral-90' ),
			'surface'         => array( 'dark' => 'neutral-80' ),
			'surface-muted'   => array( 'dark' => 'neutral-80' ),
			'surface-inverse' => array( 'light' => 'neutral-90' ),
			'border'          => array( 'dark' => 'neutral-70' ),
		),
		'brand'   => array(
			'canvas'          => array( 'dark' => 'primary-90' ),
			'surface'         => array( 'dark' => 'primary-80' ),
			'surface-muted'   => array( 'dark' => 'primary-80' ),
			'surface-inverse' => array( 'light' => 'primary-90' ),
			'border'          => array( 'dark' => 'primary-70' ),
		),
	);

	/**
	 * The starter's choices as they stand in the current settings.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function from_settings( array $settings ): array {
		$colors = array( 'primary' => '', 'secondary' => '', 'accent' => '', 'neutral' => '' );
		foreach ( $settings['colors']['items'] as $item ) {
			if ( array_key_exists( $item['key'], $colors ) ) {
				$colors[ $item['key'] ] = (string) $item['value'];
			}
		}

		$t        = $settings['typography'];
		$contrast = 'balanced';
		$best     = PHP_FLOAT_MAX;
		foreach ( self::TYPE_CONTRAST as $name => $ratios ) {
			$distance = abs( $ratios[0] - $t['minRatio'] ) + abs( $ratios[1] - $t['maxRatio'] );
			if ( $distance < $best ) {
				$best     = $distance;
				$contrast = $name;
			}
		}

		$density = 'comfortable';
		foreach ( self::SPACE_DENSITY as $name => $space ) {
			if ( (float) $settings['spacing']['minSize'] === $space[0] && (float) $settings['spacing']['maxSize'] === $space[1] ) {
				$density = $name;
			}
		}

		$canvas_dark = (string) ( $settings['roles']['map']['canvas']['dark'] ?? '' );

		return array(
			'colors'          => $colors,
			'fonts'           => array(
				'heading' => self::family( (string) $t['fonts']['heading'] ),
				'body'    => self::family( (string) $t['fonts']['body'] ),
			),
			'text'            => array( 'minSize' => (float) $t['minSize'], 'maxSize' => (float) $t['maxSize'], 'contrast' => $contrast ),
			'space'           => $density,
			'corners'         => isset( Shape::CORNERS[ $settings['shape']['corners'] ] ) ? $settings['shape']['corners'] : 'soft',
			'buttons'         => 'var(--radius-full)' === ( $settings['components']['values']['btn-radius'] ?? '' ) ? 'pill' : 'rounded',
			'darkBackgrounds' => str_starts_with( $canvas_dark, 'primary' ) ? 'brand' : 'neutral',
		);
	}

	/**
	 * Settings with the starter's choices applied (nothing is saved here).
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @param mixed                $inputs   Choices from the editor.
	 * @return array<string, mixed>
	 */
	public static function apply( array $settings, $inputs ): array {
		$in = is_array( $inputs ) ? $inputs : array();

		// Colours: primary is required; secondary and accent are removed when left empty; neutral empty = automatic.
		$colors  = (array) ( $in['colors'] ?? array() );
		$primary = Color::normalize_hex( (string) ( $colors['primary'] ?? '' ) );
		$items   = array();
		foreach ( $settings['colors']['items'] as $item ) {
			$key = $item['key'];
			if ( 'primary' === $key && '' !== $primary ) {
				$item['value'] = $primary;
				$item['dark']  = '';
			}
			if ( in_array( $key, array( 'secondary', 'accent' ), true ) && array_key_exists( $key, $colors ) ) {
				$hex = Color::normalize_hex( (string) $colors[ $key ] );
				if ( '' === $hex ) {
					continue; // Removed.
				}
				$item['value'] = $hex;
				$item['dark']  = '';
			}
			if ( 'neutral' === $key && array_key_exists( 'neutral', $colors ) ) {
				$item['value'] = Color::normalize_hex( (string) $colors['neutral'] );
			}
			$items[] = $item;
		}
		// Secondary or accent added back after being removed.
		foreach ( array( 'secondary' => 'Secondary', 'accent' => 'Accent' ) as $key => $label ) {
			$hex = Color::normalize_hex( (string) ( $colors[ $key ] ?? '' ) );
			if ( '' !== $hex && ! in_array( $key, array_column( $items, 'key' ), true ) ) {
				array_splice( $items, 1, 0, array( array( 'key' => $key, 'label' => $label, 'group' => 'brand', 'value' => $hex, 'dark' => '', 'darkMode' => true, 'shades' => true, 'alpha' => true ) ) );
			}
		}
		$settings['colors']['items'] = $items;

		// Fonts.
		foreach ( array( 'heading', 'body' ) as $role ) {
			$name = trim( (string) ( $in['fonts'][ $role ] ?? '' ) );
			if ( '' !== $name ) {
				$settings['typography']['fonts'][ $role ] = self::stack( $name );
			}
		}

		// Type size and contrast.
		$text = (array) ( $in['text'] ?? array() );
		if ( is_numeric( $text['minSize'] ?? null ) ) {
			$settings['typography']['minSize'] = (float) $text['minSize'];
		}
		if ( is_numeric( $text['maxSize'] ?? null ) ) {
			$settings['typography']['maxSize'] = (float) $text['maxSize'];
		}
		$ratios                             = self::TYPE_CONTRAST[ $text['contrast'] ?? '' ] ?? self::TYPE_CONTRAST['balanced'];
		$settings['typography']['minRatio'] = $ratios[0];
		$settings['typography']['maxRatio'] = $ratios[1];

		// Space density.
		$space                           = self::SPACE_DENSITY[ $in['space'] ?? '' ] ?? self::SPACE_DENSITY['comfortable'];
		$settings['spacing']['minSize']  = $space[0];
		$settings['spacing']['maxSize']  = $space[1];
		$settings['spacing']['minRatio'] = $space[2];
		$settings['spacing']['maxRatio'] = $space[2];

		// Corners and buttons.
		$corners                       = isset( Shape::CORNERS[ $in['corners'] ?? '' ] ) ? $in['corners'] : 'soft';
		$settings['shape']['corners']  = $corners;
		$settings['shape']['values']   = array_merge( $settings['shape']['values'], Shape::CORNERS[ $corners ] );
		$settings['components']['values']['btn-radius'] = 'pill' === ( $in['buttons'] ?? '' ) ? 'var(--radius-full)' : 'var(--radius-m)';

		// Dark backgrounds.
		$dark = self::DARK_BACKGROUNDS[ $in['darkBackgrounds'] ?? '' ] ?? self::DARK_BACKGROUNDS['neutral'];
		foreach ( $dark as $role => $choice ) {
			$settings['roles']['map'][ $role ] = array_merge( $settings['roles']['map'][ $role ] ?? array(), $choice );
		}

		return SettingsStore::from_outside( $settings ) + array( 'revision' => $settings['revision'] ?? 0 );
	}

	/**
	 * "Figtree" → "Figtree", system-ui, sans-serif. A full stack (with commas) is kept as typed.
	 */
	private static function stack( string $name ): string {
		if ( str_contains( $name, ',' ) ) {
			return $name;
		}
		$name = trim( $name, " \"'" );

		return '"' . $name . '", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
	}

	/**
	 * The first family of a stack, without quotes, or '' for the default system stack.
	 */
	private static function family( string $stack ): string {
		$first = trim( explode( ',', $stack )[0] ?? '', " \"'" );

		return 'system-ui' === $first ? '' : $first;
	}
}
