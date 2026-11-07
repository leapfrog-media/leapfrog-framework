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
					'z-modal'    => '500',
					'z-toast'    => '600',
				),
			),
		);
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
