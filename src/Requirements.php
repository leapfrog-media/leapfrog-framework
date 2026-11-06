<?php

declare(strict_types=1);

namespace Leapfrog\Framework;

/**
 * Checks that Bricks is present and new enough. Bricks is a theme, so call this
 * after themes have loaded (admin_init, rest_api_init or later).
 */
final class Requirements {
	/**
	 * @return list<string> Human-readable problems; empty when everything is ready.
	 */
	public static function problems(): array {
		$problems = array();

		if ( ! defined( 'BRICKS_VERSION' ) ) {
			$problems[] = __( 'Bricks must be the active theme.', 'leapfrog-framework' );
			return $problems;
		}

		if ( version_compare( (string) BRICKS_VERSION, MIN_BRICKS, '<' ) ) {
			/* translators: 1: required Bricks version, 2: installed version */
			$problems[] = sprintf( __( 'Bricks %1$s or newer is required (this site has %2$s).', 'leapfrog-framework' ), MIN_BRICKS, BRICKS_VERSION );
		}

		if ( ! class_exists( '\Bricks\Abilities\Design' ) ) {
			$problems[] = __( 'This Bricks version does not include the design abilities Leapfrog uses.', 'leapfrog-framework' );
		}

		return $problems;
	}

	public static function ready(): bool {
		return array() === self::problems();
	}
}
