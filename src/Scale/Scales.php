<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Scale;

/**
 * Type and space scales, and the role variables that point at them.
 *
 * The scales themselves are Bricks scale categories: Bricks does the clamp()
 * maths, so editing a scale in Leapfrog or in Bricks' Style Manager gives the
 * same result. Role variables (--h1, --body-size, --gutter...) are plain
 * variables that reference scale steps.
 */
final class Scales {
	public const TYPE_CATEGORY  = array( 'id' => 'lftype', 'name' => 'Type scale', 'scope' => 'typography', 'prefix' => 'text-' );
	public const SPACE_CATEGORY = array( 'id' => 'lfspace', 'name' => 'Space scale', 'scope' => 'spacing', 'prefix' => 'space-' );
	public const TYPE_ROLES     = array( 'id' => 'lftyperoles', 'name' => 'Typography' );
	public const SPACE_ROLES    = array( 'id' => 'lfspaceroles', 'name' => 'Spacing' );

	/** T-shirt steps in order. Bricks names scale steps the same way. */
	public const STEPS = array( '4xs', '3xs', '2xs', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl' );

	/** Ratios offered by name; anything else is saved as a custom ratio. */
	public const RATIOS = array(
		'1.067' => 'Minor second',
		'1.125' => 'Major second',
		'1.2'   => 'Minor third',
		'1.25'  => 'Major third',
		'1.333' => 'Perfect fourth',
		'1.414' => 'Augmented fourth',
		'1.5'   => 'Perfect fifth',
		'1.618' => 'Golden ratio',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function default_typography(): array {
		return array(
			'steps'       => array( '2xs', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl', '4xl' ),
			'minSize'     => 16.0,
			'maxSize'     => 18.0,
			'minRatio'    => 1.2,
			'maxRatio'    => 1.25,
			'headings'    => array( 'h1' => '4xl', 'h2' => '3xl', 'h3' => '2xl', 'h4' => 'xl', 'h5' => 'l', 'h6' => 'm' ),
			'sizes'       => array( 'body' => 'm', 'small' => 's', 'lead' => 'l' ),
			'fonts'       => array(
				'body'    => 'system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
				'heading' => 'system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
				'mono'    => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
			),
			'lineHeights' => array( 'body' => 1.6, 'heading' => 1.15 ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_spacing(): array {
		return array(
			'steps'    => array( '3xs', '2xs', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl' ),
			'minSize'  => 16.0,
			'maxSize'  => 24.0,
			'minRatio' => 1.5,
			'maxRatio' => 1.5,
			'roles'    => array(
				'gutter'          => 'm',
				'gap'             => 'm',
				'flow'            => 's',
				'section'         => '2xl',
				'sectionCompact'  => 'xl',
				'sectionSpacious' => '3xl',
			),
		);
	}

	/**
	 * Keeps a scale valid: a contiguous run of steps that includes m, sensible sizes and ratios.
	 *
	 * @param array<string, mixed> $scale
	 * @return array<string, mixed>
	 */
	public static function clean_scale( array $scale, array $defaults ): array {
		$steps = array_values( array_intersect( self::STEPS, (array) ( $scale['steps'] ?? array() ) ) );
		if ( ! $steps || ! in_array( 'm', $steps, true ) ) {
			$steps = $defaults['steps'];
		}
		// Make contiguous from the smallest to the largest chosen step.
		$from  = array_search( $steps[0], self::STEPS, true );
		$to    = array_search( end( $steps ), self::STEPS, true );
		$steps = array_slice( self::STEPS, (int) $from, (int) $to - (int) $from + 1 );

		$num = static fn( $v, float $d, float $min, float $max ): float => is_numeric( $v ) ? max( $min, min( $max, round( (float) $v, 3 ) ) ) : $d;

		$scale['steps']    = $steps;
		$scale['minSize']  = $num( $scale['minSize'] ?? null, (float) $defaults['minSize'], 4, 200 );
		$scale['maxSize']  = $num( $scale['maxSize'] ?? null, (float) $defaults['maxSize'], 4, 200 );
		$scale['minRatio'] = $num( $scale['minRatio'] ?? null, (float) $defaults['minRatio'], 1, 3 );
		$scale['maxRatio'] = $num( $scale['maxRatio'] ?? null, (float) $defaults['maxRatio'], 1, 3 );

		foreach ( array( 'headings', 'sizes', 'roles' ) as $map ) {
			if ( isset( $scale[ $map ] ) && is_array( $scale[ $map ] ) ) {
				foreach ( $scale[ $map ] as $role => $step ) {
					if ( ! in_array( $step, $steps, true ) ) {
						$scale[ $map ][ $role ] = $defaults[ $map ][ $role ] ?? 'm';
					}
				}
			}
		}

		return $scale;
	}

	/**
	 * The Bricks scale category for a scale.
	 *
	 * @param array<string, mixed>  $scale     Leapfrog scale settings.
	 * @param array<string, string> $category  TYPE_CATEGORY or SPACE_CATEGORY.
	 * @return array<string, mixed>
	 */
	public static function bricks_category( array $scale, array $category ): array {
		$ratio = static function ( float $value ): array {
			$key = rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
			return array( $value, isset( self::RATIOS[ $key ] ) ? $value : 'custom' );
		};
		[ $min_ratio, $min_select ] = $ratio( (float) $scale['minRatio'] );
		[ $max_ratio, $max_select ] = $ratio( (float) $scale['maxRatio'] );

		return array(
			'id'    => $category['id'],
			'name'  => $category['name'],
			'scale' => array(
				'scaleScope'          => $category['scope'],
				'scaleType'           => 'tshirt',
				'scaleNames'          => array_values( $scale['steps'] ),
				'prefix'              => $category['prefix'],
				'minFontSize'         => (float) $scale['minSize'],
				'minScaleRatio'       => $min_ratio,
				'minScaleRatioSelect' => $min_select,
				'maxFontSize'         => (float) $scale['maxSize'],
				'maxScaleRatio'       => $max_ratio,
				'maxScaleRatioSelect' => $max_select,
				'baseline'            => 'm',
			),
		);
	}

	/**
	 * Reads a Bricks scale category back into Leapfrog's shape (for edits made in Bricks).
	 *
	 * @param array<string, mixed> $category Bricks category with a scale.
	 * @return array<string, mixed>|null
	 */
	public static function from_bricks_category( array $category ): ?array {
		$scale = $category['scale'] ?? null;
		if ( ! is_array( $scale ) ) {
			return null;
		}
		$pick = static fn( $select, $value ) => ( 'custom' === $select || ! is_numeric( $select ) ) ? (float) $value : (float) $select;

		return array(
			'steps'    => array_values( (array) ( $scale['scaleNames'] ?? array() ) ),
			'minSize'  => (float) ( $scale['minFontSize'] ?? 16 ),
			'maxSize'  => (float) ( $scale['maxFontSize'] ?? 20 ),
			'minRatio' => $pick( $scale['minScaleRatioSelect'] ?? 'custom', $scale['minScaleRatio'] ?? 1.2 ),
			'maxRatio' => $pick( $scale['maxScaleRatioSelect'] ?? 'custom', $scale['maxScaleRatio'] ?? 1.25 ),
			'manual'   => ! empty( $scale['isManual'] ),
		);
	}

	/**
	 * Step range relative to m, as Bricks' generator wants it.
	 *
	 * @param list<string> $steps
	 * @return array{from:int,to:int}
	 */
	public static function range( array $steps ): array {
		$m = (int) array_search( 'm', self::STEPS, true );

		return array(
			'from' => (int) array_search( $steps[0], self::STEPS, true ) - $m,
			'to'   => (int) array_search( end( $steps ), self::STEPS, true ) - $m,
		);
	}

	/**
	 * Role variables that point at scale steps.
	 *
	 * @param array<string, mixed> $typography
	 * @param array<string, mixed> $spacing
	 * @return list<array{name:string,value:string,category:string}>
	 */
	public static function role_variables( array $typography, array $spacing ): array {
		$type  = self::TYPE_ROLES['id'];
		$space = self::SPACE_ROLES['id'];
		$text  = static fn( string $step ): string => 'var(--' . self::TYPE_CATEGORY['prefix'] . $step . ')';
		$gap   = static fn( string $step ): string => 'var(--' . self::SPACE_CATEGORY['prefix'] . $step . ')';
		$font  = static fn( string $stack ): string => trim( (string) preg_replace( '/[;{}<>]/', '', $stack ) );
		$rows  = array();

		foreach ( $typography['fonts'] as $role => $stack ) {
			$rows[] = array( 'name' => "font-$role", 'value' => $font( (string) $stack ), 'category' => $type );
		}
		foreach ( $typography['headings'] as $tag => $step ) {
			$rows[] = array( 'name' => $tag, 'value' => $text( (string) $step ), 'category' => $type );
		}
		foreach ( $typography['sizes'] as $role => $step ) {
			$rows[] = array( 'name' => "$role-size", 'value' => $text( (string) $step ), 'category' => $type );
		}
		foreach ( $typography['lineHeights'] as $role => $value ) {
			$rows[] = array( 'name' => "line-height-$role", 'value' => (string) (float) $value, 'category' => $type );
		}

		$names = array(
			'gutter'          => 'gutter',
			'gap'             => 'gap',
			'flow'            => 'flow',
			'section'         => 'section-space',
			'sectionCompact'  => 'section-space-compact',
			'sectionSpacious' => 'section-space-spacious',
		);
		foreach ( $spacing['roles'] as $role => $step ) {
			if ( isset( $names[ $role ] ) ) {
				$rows[] = array( 'name' => $names[ $role ], 'value' => $gap( (string) $step ), 'category' => $space );
			}
		}

		return $rows;
	}

	/**
	 * Smallest and largest px size from a Bricks clamp() value.
	 *
	 * @return array{min:float,max:float}|null
	 */
	public static function clamp_px( string $value, float $html_px ): ?array {
		if ( ! preg_match( '/^clamp\(\s*([\d.]+)(rem|px)\s*,.*,\s*([\d.]+)(rem|px)\s*\)$/', trim( $value ), $m ) ) {
			return null;
		}
		$to_px = static fn( string $n, string $unit ): float => round( 'rem' === $unit ? (float) $n * $html_px : (float) $n, 2 );

		return array( 'min' => $to_px( $m[1], $m[2] ), 'max' => $to_px( $m[3], $m[4] ) );
	}
}
