<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

/**
 * The one site-wide Bricks theme style Leapfrog writes to (fonts, element defaults).
 *
 * It is the style Leapfrog wrote to before, else the site's only site-wide style,
 * else a new one called "Leapfrog". Settings are merged in, so everything else in
 * the style is left alone; a null value clears that key.
 */
final class ThemeStyle {
	public const OPTION = 'lf_theme_style_id';

	/**
	 * @param array<string, mixed> $settings Theme style settings to merge, grouped by section.
	 * @return array{status:string,id:string,label:string}
	 */
	public static function patch( array $settings ): array {
		$gateway = new BricksGateway();
		$all     = $gateway->execute( 'bricks/get-theme-styles' );
		$styles  = (array) ( $all['styles'] ?? array() );
		$id      = (string) get_option( self::OPTION, '' );

		if ( '' === $id || ! isset( $styles[ $id ] ) ) {
			$site_wide = array_keys(
				array_filter(
					$styles,
					static fn( $style ): bool => in_array( 'any', array_column( (array) ( $style['conditions'] ?? array() ), 'main' ), true )
				)
			);
			$id = 1 === count( $site_wide ) ? (string) $site_wide[0] : '';
		}

		if ( '' === $id ) {
			// A new style only gets the values being set; clearing keys needs no style.
			$values = self::without_nulls( $settings );
			if ( ! $values ) {
				return array( 'status' => 'skipped', 'id' => '', 'label' => '' );
			}
			$created = $gateway->execute(
				'bricks/create-theme-style',
				array(
					'label'      => 'Leapfrog',
					'conditions' => array( array( 'main' => 'any' ) ),
					'settings'   => $values,
				)
			);
			$id     = (string) $created['id'];
			$status = 'created';
			$label  = 'Leapfrog';
		} else {
			$one = $gateway->execute( 'bricks/get-theme-styles', array( 'style' => $id ) );
			$gateway->execute(
				'bricks/update-theme-style',
				array(
					'id'                => $id,
					'settings'          => $settings,
					'expectedOwnership' => (array) $one['ownership'] + array( 'itemDigest' => (string) ( $one['itemDigests'][ $id ] ?? '' ) ),
				)
			);
			$status = 'updated';
			$label  = (string) ( $styles[ $id ]['label'] ?? $id );
		}

		update_option( self::OPTION, $id, false );
		CssFiles::rebuild();

		return array( 'status' => $status, 'id' => $id, 'label' => $label );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function without_nulls( array $settings ): array {
		$out = array();
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::without_nulls( $value );
				if ( $value ) {
					$out[ $key ] = $value;
				}
			} elseif ( null !== $value ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}
}
