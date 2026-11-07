<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Settings;

/**
 * The last 20 versions of the framework settings, newest first, so a save can be undone.
 * One non-autoloaded option.
 */
final class History {
	public const OPTION = 'lf_framework_history';
	public const KEEP   = 20;

	/**
	 * @param array<string, mixed> $settings The version being replaced.
	 */
	public static function push( array $settings, string $label = '' ): void {
		$list = self::all();
		$user = wp_get_current_user();
		array_unshift(
			$list,
			array(
				'id'       => wp_generate_uuid4(),
				'time'     => time(),
				'revision' => (int) ( $settings['revision'] ?? 0 ),
				'label'    => sanitize_text_field( $label ),
				'user'     => $user && $user->exists() ? $user->display_name : '',
				'settings' => SettingsStore::content( $settings ),
			)
		);
		update_option( self::OPTION, array_slice( $list, 0, self::KEEP ), false );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function all(): array {
		$list = get_option( self::OPTION, array() );

		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Entries without their settings, for listing.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function summaries(): array {
		return array_map(
			static fn( array $entry ): array => array_diff_key( $entry, array( 'settings' => true ) ),
			self::all()
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function find( string $id ): ?array {
		foreach ( self::all() as $entry ) {
			if ( ( $entry['id'] ?? '' ) === $id ) {
				return $entry;
			}
		}

		return null;
	}
}
