<?php

declare(strict_types=1);

namespace Leapfrog\Framework;

use Leapfrog\Framework\Bricks\PaletteSync;
use Leapfrog\Framework\Color\Palette;
use Leapfrog\Framework\Color\Roles;
use Leapfrog\Framework\Color\ThemeCss;

/**
 * Generates everything that depends on the colours, so the palette, the roles
 * palette and the section theme CSS always match each other.
 */
final class Build {
	/**
	 * @param array<string, mixed> $settings Saved settings.
	 * @return array{generated:list<array<string,mixed>>,roles:list<array<string,mixed>>}
	 */
	public static function colours( array $settings ): array {
		$generated = Palette::generate( $settings['colors']['items'], $settings['colors']['shadeSteps'], $settings['colors']['alphaSteps'] );
		$roles     = Roles::resolve( $settings['roles']['map'], Roles::tokens( $generated ) );

		return array( 'generated' => $generated, 'roles' => $roles );
	}

	/**
	 * Writes both palettes to Bricks and stores the theme CSS.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 * @return array{palette:array<string,int|string>,roles:array<string,int|string>}
	 */
	public static function sync_colours( array $settings ): array {
		$built = self::colours( $settings );

		$palette = ( new PaletteSync() )->sync( Palette::bricks_rows( $built['generated'] ) );
		$roles   = PaletteSync::roles()->sync( Roles::bricks_rows( $built['roles'] ) );

		ThemeCss::store( ThemeCss::build( $built['generated'], $built['roles'], (bool) $settings['roles']['pageStyles'] ) );

		return array( 'palette' => $palette, 'roles' => $roles );
	}
}
