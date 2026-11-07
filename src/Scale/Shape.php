<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Scale;

/**
 * Layout and shape variables: content widths, corners, borders, shadows, motion and layers.
 * Saved in the Bricks variable category "Layout and shape".
 */
final class Shape {
	public const CATEGORY = array( 'id' => 'lfshape', 'name' => 'Layout and shape' );

	/** Corner presets set the four radius values at once. */
	public const CORNERS = array(
		'sharp' => array( 'radius-s' => '0px', 'radius-m' => '2px', 'radius-l' => '4px' ),
		'soft'  => array( 'radius-s' => '4px', 'radius-m' => '8px', 'radius-l' => '16px' ),
		'round' => array( 'radius-s' => '8px', 'radius-m' => '16px', 'radius-l' => '28px' ),
	);

	/**
	 * Groups of variables with their default values, in display order.
	 *
	 * @return array<string, array{label:string,values:array<string,string>}>
	 */
	public static function groups(): array {
		$shadow = static fn( string $layers ): string => $layers;

		return array(
			'widths'  => array(
				'label'  => 'Content widths',
				'values' => array(
					'width-content' => '1280px',
					'width-wide'    => '1440px',
					'width-narrow'  => '960px',
					'width-text'    => '68ch',
				),
			),
			'sizes'   => array(
				'label'  => 'Layout and sizes',
				'values' => array(
					'sidebar-width'    => '320px',
					'form-width'       => '640px',
					'scroll-offset'    => 'calc(var(--header-height) + var(--space-s))',
					'icon-s'           => '16px',
					'icon-m'           => '24px',
					'icon-l'           => '32px',
					'avatar-s'         => '32px',
					'avatar-m'         => '48px',
					'avatar-l'         => '80px',
					'control-height-s' => '36px',
					'control-height'   => '44px',
					'control-height-l' => '52px',
				),
			),
			'grids'   => array(
				'label'  => 'Grids',
				'values' => self::grid_values(),
			),
			'corners' => array(
				'label'  => 'Corners',
				'values' => self::CORNERS['soft'] + array( 'radius-full' => '9999px' ),
			),
			'borders' => array(
				'label'  => 'Borders',
				'values' => array(
					'border-thin'  => '1px',
					'border-thick' => '2px',
				),
			),
			'focus'   => array(
				'label'  => 'Focus and accessibility',
				'values' => array(
					'focus-ring-width'        => '2px',
					'focus-ring-offset'       => '2px',
					'focus-ring'              => 'var(--focus-ring-width) solid var(--color-focus)',
					'target-size-min'         => '44px',
					'disabled-opacity'        => '0.5',
					'placeholder-opacity'     => '0.6',
					'divider-width'           => 'var(--border-thin)',
					'skip-link-offset'        => 'var(--space-s)',
					'reduced-motion-duration' => '0.01ms',
				),
			),
			'shadows' => array(
				'label'  => 'Shadows',
				'values' => array(
					'shadow-s' => $shadow( '0 1px 2px color-mix(in oklch, var(--black) 8%, transparent)' ),
					'shadow-m' => $shadow( '0 4px 12px color-mix(in oklch, var(--black) 10%, transparent), 0 1px 3px color-mix(in oklch, var(--black) 6%, transparent)' ),
					'shadow-l' => $shadow( '0 16px 40px color-mix(in oklch, var(--black) 14%, transparent), 0 4px 10px color-mix(in oklch, var(--black) 6%, transparent)' ),
				),
			),
			'motion'  => array(
				'label'  => 'Motion',
				'values' => array(
					'duration-instant' => '60ms',
					'duration-fast'   => '120ms',
					'duration-normal' => '200ms',
					'duration-slow'   => '320ms',
					'ease-out'        => 'cubic-bezier(0.2, 0, 0, 1)',
					'ease-in'         => 'cubic-bezier(0.4, 0, 1, 1)',
					'ease-in-out'     => 'cubic-bezier(0.4, 0, 0.2, 1)',
				),
			),
			'layers'  => array(
				'label'  => 'Layers (z-index)',
				'values' => array(
					'z-base'     => '0',
					'z-raised'   => '10',
					'z-sticky'   => '100',
					'z-header'   => '200',
					'z-dropdown' => '300',
					'z-overlay'  => '400',
					'z-backdrop' => '450',
					'z-drawer'   => '480',
					'z-modal'    => '500',
					'z-toast'    => '600',
					'z-tooltip'  => '700',
				),
			),
		);
	}

	/**
	 * Ready-made grid-template-columns values: equal columns, uneven splits and wrapping card grids.
	 *
	 * @return array<string, string>
	 */
	private static function grid_values(): array {
		$out = array();
		for ( $n = 1; $n <= 12; $n++ ) {
			$out[ "grid-$n" ] = "repeat($n, minmax(0, 1fr))";
		}
		foreach ( array( array( 1, 2 ), array( 2, 1 ), array( 1, 3 ), array( 3, 1 ) ) as [ $a, $b ] ) {
			$out[ "grid-$a-$b" ] = "minmax(0, {$a}fr) minmax(0, {$b}fr)";
		}
		// min(..., 100%) keeps a card from overflowing a phone narrower than --grid-min.
		$out['grid-min']       = '300px';
		$out['grid-auto-fit']  = 'repeat(auto-fit, minmax(min(var(--grid-min), 100%), 1fr))';
		$out['grid-auto-fill'] = 'repeat(auto-fill, minmax(min(var(--grid-min), 100%), 1fr))';

		return $out;
	}

	/**
	 * @return array<string, string> name => default value
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( self::groups() as $group ) {
			$out += $group['values'];
		}

		return $out;
	}

	/**
	 * Known names only; values must be a single safe CSS value.
	 *
	 * @param mixed $given
	 * @return array<string, string>
	 */
	public static function sanitize( $given ): array {
		$out = self::defaults();
		foreach ( is_array( $given ) ? $given : array() as $name => $value ) {
			if ( ! isset( $out[ $name ] ) ) {
				continue;
			}
			$value = trim( (string) $value );
			if ( '' !== $value && strlen( $value ) <= 240 && ! preg_match( '/[;{}<>\\\\]|\/\*|url\s*\(|expression\s*\(/i', $value ) ) {
				$out[ $name ] = $value;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, string> $values
	 * @return list<array{name:string,value:string,category:string}>
	 */
	public static function variables( array $values ): array {
		$rows = array();
		foreach ( self::sanitize( $values ) as $name => $value ) {
			$rows[] = array( 'name' => $name, 'value' => $value, 'category' => self::CATEGORY['id'] );
		}

		return $rows;
	}
}
