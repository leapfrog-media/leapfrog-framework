<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Color;

/**
 * The colour settings and everything generated from them.
 *
 * - Shades (10..90) are fixed OKLCH values, recalculated whenever a base changes.
 * - Transparency steps (a10..a90) are live: color-mix() of the base variable, so
 *   they follow the base colour's dark value and any section theme.
 * - Brand and status colours get a dark value (automatic unless chosen).
 */
final class Palette {
	public const GROUPS = array( 'brand', 'neutral', 'status', 'base', 'custom' );

	/** Colours every site keeps; the rest can be removed. */
	public const LOCKED = array( 'primary', 'neutral', 'success', 'warning', 'error', 'info', 'white', 'black' );

	/** OKLCH lightness for each shade step: 10 = lightest, 90 = darkest. */
	private const SHADE_LIGHTNESS = array(
		10 => 0.97,
		20 => 0.93,
		30 => 0.87,
		40 => 0.79,
		50 => 0.70,
		60 => 0.61,
		70 => 0.52,
		80 => 0.42,
		90 => 0.32,
	);

	/** Share of the base chroma kept at each step (pale and very dark shades are less saturated). */
	private const SHADE_CHROMA = array(
		10 => 0.18,
		20 => 0.32,
		30 => 0.52,
		40 => 0.75,
		50 => 0.92,
		60 => 1.00,
		70 => 0.92,
		80 => 0.80,
		90 => 0.62,
	);

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function default_items(): array {
		$item = static fn( string $key, string $label, string $group, string $value, bool $dark, bool $shades, bool $alpha ): array => array(
			'key'      => $key,
			'label'    => $label,
			'group'    => $group,
			'value'    => $value,
			'dark'     => '',
			'darkMode' => $dark,
			'shades'   => $shades,
			'alpha'    => $alpha,
		);

		return array(
			$item( 'primary', 'Primary', 'brand', '#2563eb', true, true, true ),
			$item( 'secondary', 'Secondary', 'brand', '#7c3aed', true, true, true ),
			$item( 'accent', 'Accent', 'brand', '#f59e0b', true, true, true ),
			$item( 'neutral', 'Neutral', 'neutral', '', false, true, true ),
			$item( 'success', 'Success', 'status', '#16a34a', true, true, false ),
			$item( 'warning', 'Warning', 'status', '#d97706', true, true, false ),
			$item( 'error', 'Error', 'status', '#dc2626', true, true, false ),
			$item( 'info', 'Info', 'status', '#0284c7', true, true, false ),
			$item( 'white', 'White', 'base', '#ffffff', false, false, true ),
			$item( 'black', 'Black', 'base', '#000000', false, false, true ),
		);
	}

	/**
	 * Cleans colour items from the editor. Invalid items are dropped; locked
	 * colours are always present.
	 *
	 * @param mixed $given Incoming items.
	 * @return list<array<string, mixed>>
	 */
	public static function sanitize_items( $given ): array {
		$defaults = array();
		foreach ( self::default_items() as $default ) {
			$defaults[ $default['key'] ] = $default;
		}

		$out  = array();
		$seen = array();
		foreach ( is_array( $given ) ? $given : array() as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$key = strtolower( trim( (string) ( $raw['key'] ?? '' ) ) );
			if ( ! self::valid_key( $key ) || isset( $seen[ $key ] ) ) {
				continue;
			}

			$base  = $defaults[ $key ] ?? null;
			$group = $base ? $base['group'] : 'custom';
			$value = Color::normalize_hex( (string) ( $raw['value'] ?? '' ) );
			if ( '' === $value && 'neutral' !== $key ) {
				$value = $base ? $base['value'] : '';
			}
			if ( '' === $value && 'neutral' !== $key ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
			$seen[ $key ] = true;
			$out[]        = array(
				'key'      => $key,
				'label'    => '' !== $label ? $label : ( $base ? $base['label'] : ucfirst( str_replace( '-', ' ', $key ) ) ),
				'group'    => $group,
				'value'    => $value,
				'dark'     => Color::normalize_hex( (string) ( $raw['dark'] ?? '' ) ),
				'darkMode' => 'base' === $group ? false : ! empty( $raw['darkMode'] ),
				'shades'   => 'base' === $group ? false : ! empty( $raw['shades'] ),
				'alpha'    => ! empty( $raw['alpha'] ),
			);
		}

		// Locked colours can't be removed: put back any that are missing.
		foreach ( self::LOCKED as $key ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$out[] = $defaults[ $key ];
			}
		}

		return $out;
	}

	/**
	 * Key = the CSS variable name. Letters and single hyphens only, so it can
	 * never collide with generated names such as primary-10 or primary-a20.
	 */
	public static function valid_key( string $key ): bool {
		return 1 === preg_match( '/^[a-z]+(?:-[a-z]+)*$/', $key ) && strlen( $key ) <= 32;
	}

	/**
	 * Everything the editor previews and Bricks receives.
	 *
	 * @param list<array<string, mixed>> $items        Sanitised items.
	 * @param list<int>                  $shade_steps  e.g. 10..90.
	 * @param list<int>                  $alpha_steps  e.g. 10..90.
	 * @return list<array<string, mixed>>
	 */
	public static function generate( array $items, array $shade_steps, array $alpha_steps ): array {
		$primary = '';
		foreach ( $items as $item ) {
			if ( 'primary' === $item['key'] ) {
				$primary = (string) $item['value'];
			}
		}

		$out = array();
		foreach ( $items as $item ) {
			$base = (string) $item['value'];
			$auto = false;
			if ( 'neutral' === $item['key'] && '' === $base ) {
				$base = self::neutral_from( $primary );
				$auto = true;
			}

			$dark      = null;
			$dark_auto = false;
			if ( $item['darkMode'] ) {
				$dark      = '' !== $item['dark'] ? (string) $item['dark'] : self::dark_value( $base );
				$dark_auto = '' === $item['dark'];
			}

			$shades = array();
			if ( $item['shades'] ) {
				foreach ( $shade_steps as $step ) {
					if ( isset( self::SHADE_LIGHTNESS[ $step ] ) ) {
						$shades[ $step ] = self::shade( $base, (int) $step );
					}
				}
			}

			$alpha = array();
			if ( $item['alpha'] ) {
				foreach ( $alpha_steps as $step ) {
					$step = (int) $step;
					if ( $step > 0 && $step < 100 ) {
						$alpha[ $step ] = sprintf( 'color-mix(in oklch, var(--%s) %d%%, transparent)', $item['key'], $step );
					}
				}
			}

			$on_white = Color::contrast( $base, '#ffffff' );
			$on_black = Color::contrast( $base, '#000000' );

			$out[] = array(
				'key'         => $item['key'],
				'label'       => $item['label'],
				'group'       => $item['group'],
				'locked'      => in_array( $item['key'], self::LOCKED, true ),
				'base'        => $base,
				'baseAuto'    => $auto,
				'dark'        => $dark,
				'darkAuto'    => $dark_auto,
				'shades'      => $shades,
				'alpha'       => $alpha,
				'contrast'    => array(
					'white' => $on_white,
					'black' => $on_black,
					'dark'  => null !== $dark ? Color::contrast( $dark, '#000000' ) : null,
				),
				'textOnColor' => $on_white >= $on_black ? '#ffffff' : '#000000',
			);
		}

		return $out;
	}

	/**
	 * The rows Bricks should hold, keyed by CSS variable name.
	 *
	 * @param list<array<string, mixed>> $generated From generate().
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks_rows( array $generated ): array {
		$rows = array();
		foreach ( $generated as $color ) {
			$key          = $color['key'];
			$rows[ $key ] = array(
				'raw'   => $key,
				'root'  => true,
				'light' => $color['base'],
				'dark'  => $color['dark'],
			);

			$light_index = 0;
			$dark_index  = 0;
			foreach ( $color['shades'] as $step => $hex ) {
				$type                   = $step <= 50 ? 'light' : 'dark';
				$rows[ "$key-$step" ] = array(
					'raw'    => "$key-$step",
					'root'   => false,
					'parent' => $key,
					'type'   => $type,
					'index'  => 'light' === $type ? $light_index++ : $dark_index++,
					'light'  => $hex,
				);
			}

			$alpha_index = 0;
			foreach ( $color['alpha'] as $step => $css ) {
				$rows[ "$key-a$step" ] = array(
					'raw'    => "$key-a$step",
					'root'   => false,
					'parent' => $key,
					'type'   => 'transparent',
					'index'  => $alpha_index++,
					'light'  => $css,
				);
			}
		}

		return $rows;
	}

	private static function shade( string $base, int $step ): string {
		$lch = Color::hex_to_oklch( $base ) ?? array( 0.5, 0.0, 0.0 );

		return Color::oklch_to_hex( self::SHADE_LIGHTNESS[ $step ], $lch[1] * self::SHADE_CHROMA[ $step ], $lch[2] );
	}

	/**
	 * Dark-mode version of a colour: lighter (for contrast on dark surfaces), slightly calmer.
	 */
	public static function dark_value( string $hex ): string {
		$lch = Color::hex_to_oklch( $hex );
		if ( ! $lch ) {
			return $hex;
		}
		[ $l, $c, $h ] = $lch;
		$target        = $l < 0.72 ? 0.72 + ( 0.72 - $l ) * 0.15 : $l;

		return Color::oklch_to_hex( $target, $c * 0.9, $h );
	}

	/**
	 * A grey tinted towards the primary hue, so neutrals feel part of the brand.
	 */
	private static function neutral_from( string $primary ): string {
		$lch = '' !== $primary ? Color::hex_to_oklch( $primary ) : null;
		$hue = ( $lch && $lch[1] > 0.02 ) ? $lch[2] : 0.0;
		$chroma = ( $lch && $lch[1] > 0.02 ) ? 0.015 : 0.0;

		return Color::oklch_to_hex( 0.55, $chroma, $hue );
	}
}
