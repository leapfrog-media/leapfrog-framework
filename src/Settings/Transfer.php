<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Settings;

use RuntimeException;

use const Leapfrog\Framework\VERSION;

/**
 * Framework files (export/import between sites) and presets (named versions kept on this site).
 */
final class Transfer {
	public const FORMAT         = 'leapfrog-framework';
	public const PRESETS_OPTION = 'lf_framework_presets';

	/**
	 * The current settings as a framework file.
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		return array(
			'format'   => self::FORMAT,
			'version'  => VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
			'settings' => SettingsStore::content( SettingsStore::get() ),
		);
	}

	/**
	 * Settings from a framework file, cleaned to the current shape.
	 *
	 * @param mixed $file Decoded JSON.
	 * @return array<string, mixed>
	 */
	public static function read_file( $file ): array {
		if ( ! is_array( $file ) || ( $file['format'] ?? '' ) !== self::FORMAT || ! is_array( $file['settings'] ?? null ) ) {
			throw new RuntimeException( __( 'This is not a Leapfrog Framework file.', 'leapfrog-framework' ) );
		}

		return SettingsStore::from_outside( $file['settings'] );
	}

	/**
	 * A short description of what some settings contain, for previews.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function describe( array $settings ): array {
		$colours = array();
		foreach ( $settings['colors']['items'] as $item ) {
			if ( in_array( $item['group'], array( 'brand', 'custom' ), true ) ) {
				$colours[] = array( 'key' => $item['key'], 'value' => $item['value'] );
			}
		}

		return array(
			'colours'     => $colours,
			'colourCount' => count( $settings['colors']['items'] ),
			'fonts'       => $settings['typography']['fonts'],
			'typeSize'    => array( $settings['typography']['minSize'], $settings['typography']['maxSize'] ),
			'spaceSize'   => array( $settings['spacing']['minSize'], $settings['spacing']['maxSize'] ),
			'corners'     => $settings['shape']['corners'],
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function presets(): array {
		$list = get_option( self::PRESETS_OPTION, array() );

		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Saves the current settings as a named preset.
	 *
	 * @return array<string, mixed> The new preset.
	 */
	public static function save_preset( string $name ): array {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			throw new RuntimeException( __( 'Give the preset a name.', 'leapfrog-framework' ) );
		}
		$preset = array(
			'id'       => wp_generate_uuid4(),
			'name'     => $name,
			'created'  => time(),
			'settings' => SettingsStore::content( SettingsStore::get() ),
		);
		$list   = self::presets();
		$list[] = $preset;
		update_option( self::PRESETS_OPTION, $list, false );

		return $preset;
	}

	public static function delete_preset( string $id ): void {
		update_option( self::PRESETS_OPTION, array_values( array_filter( self::presets(), static fn( array $p ): bool => ( $p['id'] ?? '' ) !== $id ) ), false );
	}

	/**
	 * Settings of a preset; "defaults" is Leapfrog's built-in starting point.
	 *
	 * @return array<string, mixed>
	 */
	public static function preset_settings( string $id ): array {
		if ( 'defaults' === $id ) {
			return SettingsStore::from_outside( SettingsStore::defaults() );
		}
		foreach ( self::presets() as $preset ) {
			if ( ( $preset['id'] ?? '' ) === $id ) {
				return SettingsStore::from_outside( $preset['settings'] );
			}
		}
		throw new RuntimeException( __( 'That preset no longer exists.', 'leapfrog-framework' ) );
	}
}
