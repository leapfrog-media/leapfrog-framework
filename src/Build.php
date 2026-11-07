<?php

declare(strict_types=1);

namespace Leapfrog\Framework;

use Leapfrog\Framework\Bricks\PaletteSync;
use Leapfrog\Framework\Bricks\ScaleSync;
use Leapfrog\Framework\Bricks\VariableSet;
use Leapfrog\Framework\Color\Palette;
use Leapfrog\Framework\Color\Roles;
use Leapfrog\Framework\Color\ThemeCss;
use Leapfrog\Framework\Scale\Components;
use Leapfrog\Framework\Scale\Shape;

/**
 * Generates and writes what the settings describe.
 *
 * sync_colours() keeps the palette, roles palette and theme CSS matching each
 * other; sync_all() writes everything (used by the brand starter, imports,
 * presets, restoring a backup and "Rebuild everything").
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

	/**
	 * Writes everything to Bricks.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 * @return array<string, array<string, mixed>> Summary per part.
	 */
	public static function sync_all( array $settings ): array {
		$colours = self::sync_colours( $settings );

		return array(
			'palette'    => $colours['palette'],
			'roles'      => $colours['roles'],
			'scales'     => ( new ScaleSync() )->sync( $settings['typography'], $settings['spacing'] ),
			'shape'      => ( new VariableSet() )->sync( Shape::CATEGORY, Shape::variables( $settings['shape']['values'] ) ),
			'components' => ( new VariableSet() )->sync( Components::CATEGORY, Components::variables( $settings['components']['values'] ) ),
		);
	}

	/**
	 * Totals across a sync_all() summary.
	 *
	 * @param array<string, array<string, mixed>> $summary
	 * @return array{created:int,updated:int,deleted:int,unchanged:int}
	 */
	public static function totals( array $summary ): array {
		$out = array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0 );
		foreach ( $summary as $part ) {
			foreach ( array_keys( $out ) as $key ) {
				$out[ $key ] += (int) ( $part[ $key ] ?? 0 );
			}
		}

		return $out;
	}
}
