<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Settings;

use Leapfrog\Framework\Color\Palette;
use Leapfrog\Framework\Color\Roles;
use Leapfrog\Framework\Scale\Components;
use Leapfrog\Framework\Scale\Scales;
use Leapfrog\Framework\Scale\Shape;
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
			'colors'        => array(
				'items'      => Palette::default_items(),
				'shadeSteps' => array( 10, 20, 30, 40, 50, 60, 70, 80, 90 ),
				'alphaSteps' => array( 10, 20, 30, 40, 50, 60, 70, 80, 90 ),
			),
			'typography'    => Scales::default_typography(),
			'spacing'       => Scales::default_spacing(),
			'roles'         => array(
				'map'        => Roles::default_map(),
				'pageStyles' => true,
			),
			'shape'         => array(
				'corners' => 'soft',
				'values'  => Shape::defaults(),
			),
			'components'    => array(
				'values' => Components::defaults(),
			),
		);
	}

	/**
	 * Shape cleaning plus the rules that need more than types.
	 *
	 * @param mixed $value Incoming settings.
	 * @return array<string, mixed>
	 */
	private static function normalise( $value ): array {
		$out               = self::clean( $value, self::defaults() );
		$out['typography'] = Scales::clean_scale( $out['typography'], Scales::default_typography() );
		$out['spacing']    = Scales::clean_scale( $out['spacing'], Scales::default_spacing() );
		$out['roles']['map'] = Roles::sanitize_map( $value['roles']['map'] ?? array() );
		$out['shape']['values'] = Shape::sanitize( $value['shape']['values'] ?? array() );
		$out['components']['values'] = Components::sanitize( $value['components']['values'] ?? array() );
		if ( ! isset( Shape::CORNERS[ $out['shape']['corners'] ] ) ) {
			$out['shape']['corners'] = 'custom';
		}

		return $out;
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

		return self::normalise( $stored );
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

		$next             = self::normalise( $incoming );
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

			if ( 'items' === $key && is_array( $default ) && is_array( $default[0] ?? null ) ) {
				// Colour items have their own rules.
				$out[ $key ] = Palette::sanitize_items( $given );
			} elseif ( is_array( $default ) && array_is_list( $default ) ) {
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
