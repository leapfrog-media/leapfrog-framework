<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Settings;

use RuntimeException;

/**
 * Leapfrog's own copy of the brand inputs and generator settings.
 *
 * Everything Leapfrog writes to Bricks is generated from this. It is one
 * non-autoloaded option with a revision number, so two open editors cannot
 * silently overwrite each other.
 */
final class SettingsStore {
	public const OPTION = 'lf_framework';
	public const SCHEMA = 1;

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'schemaVersion' => self::SCHEMA,
			'revision'      => 0,
			'updated'       => 0,
			'brand'         => array(
				'colors' => array(
					'primary'   => '',
					'secondary' => '',
					'accent'    => '',
					'neutral'   => '',
				),
				'fonts'  => array(
					'body'    => '',
					'heading' => '',
					'mono'    => '',
				),
				'corners' => 'soft',
			),
			'colors'        => array(
				'shadeSteps' => array( 10, 20, 30, 40, 50, 60, 70, 80, 90 ),
				'alphaSteps' => array( 10, 20, 30, 40, 50, 60, 70, 80, 90 ),
			),
			'typography'    => array(
				'prefix'   => 'text-',
				'steps'    => array( '2xs', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl', '4xl' ),
				'baseline' => 'm',
				'minSize'  => 16.0,
				'maxSize'  => 18.0,
				'minRatio' => 1.2,
				'maxRatio' => 1.25,
			),
			'spacing'       => array(
				'prefix'   => 'space-',
				'steps'    => array( '3xs', '2xs', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl' ),
				'baseline' => 'm',
				'minSize'  => 16.0,
				'maxSize'  => 24.0,
				'minRatio' => 1.5,
				'maxRatio' => 1.5,
			),
		);
	}

	/**
	 * Stored settings merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return self::clean( $stored, self::defaults() );
	}

	/**
	 * Saves settings if nobody else has saved since $expected_revision was read.
	 *
	 * @param array<string, mixed> $incoming Settings from the editor.
	 * @return array<string, mixed> The saved settings.
	 */
	public static function save( array $incoming, int $expected_revision ): array {
		$current = self::get();
		if ( (int) $current['revision'] !== $expected_revision ) {
			throw new RuntimeException( __( 'The framework settings were changed somewhere else. Reload to see the latest version.', 'leapfrog-framework' ) );
		}

		$next             = self::clean( $incoming, self::defaults() );
		$next['revision'] = $expected_revision + 1;
		$next['updated']  = time();
		$next['schemaVersion'] = self::SCHEMA;

		update_option( self::OPTION, $next, false );

		return $next;
	}

	/**
	 * Keeps only known keys, cast to the type of their default value.
	 *
	 * @param mixed                $value    Incoming value.
	 * @param array<string, mixed> $defaults Shape to follow.
	 * @return array<string, mixed>
	 */
	private static function clean( $value, array $defaults ): array {
		$value = is_array( $value ) ? $value : array();
		$out   = array();

		foreach ( $defaults as $key => $default ) {
			$given = $value[ $key ] ?? $default;

			if ( is_array( $default ) && array_is_list( $default ) ) {
				$out[ $key ] = self::clean_list( $given, $default );
			} elseif ( is_array( $default ) ) {
				$out[ $key ] = self::clean( $given, $default );
			} elseif ( is_float( $default ) ) {
				$out[ $key ] = is_numeric( $given ) ? (float) $given : $default;
			} elseif ( is_int( $default ) ) {
				$out[ $key ] = is_numeric( $given ) ? (int) $given : $default;
			} elseif ( is_bool( $default ) ) {
				$out[ $key ] = (bool) $given;
			} else {
				$out[ $key ] = is_scalar( $given ) ? sanitize_text_field( (string) $given ) : $default;
			}
		}

		return $out;
	}

	/**
	 * @param mixed     $given   Incoming list.
	 * @param list<mixed> $default Default list (its first item sets the type).
	 * @return list<mixed>
	 */
	private static function clean_list( $given, array $default ): array {
		if ( ! is_array( $given ) ) {
			return $default;
		}
		$as_number = isset( $default[0] ) && is_int( $default[0] );
		$out       = array();
		foreach ( $given as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$out[] = $as_number ? (int) $item : sanitize_text_field( (string) $item );
		}

		return $out;
	}
}
