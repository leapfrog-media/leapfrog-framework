<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Color;

/**
 * Colour maths: hex <-> sRGB <-> OKLab/OKLCH, gamut mapping and WCAG contrast.
 *
 * OKLCH keeps lightness perceptually even, so shade ramps step evenly and
 * keep their hue. Conversion constants are Björn Ottosson's OKLab reference.
 */
final class Color {
	/**
	 * Parses #rgb or #rrggbb. Returns [r, g, b] in 0..1, or null.
	 *
	 * @return array{0:float,1:float,2:float}|null
	 */
	public static function hex_to_rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );
		if ( preg_match( '/^[0-9a-f]{3}$/i', $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return null;
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);
	}

	/**
	 * Normalises #rgb / #rrggbb to lowercase #rrggbb, or '' when invalid.
	 */
	public static function normalize_hex( string $hex ): string {
		$rgb = self::hex_to_rgb( $hex );

		return $rgb ? self::rgb_to_hex( $rgb ) : '';
	}

	/**
	 * @param array{0:float,1:float,2:float} $rgb sRGB 0..1.
	 */
	public static function rgb_to_hex( array $rgb ): string {
		$out = '#';
		foreach ( $rgb as $channel ) {
			$out .= str_pad( dechex( (int) round( max( 0.0, min( 1.0, $channel ) ) * 255 ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * @return array{0:float,1:float,2:float}|null [L 0..1, C >= 0, H 0..360)
	 */
	public static function hex_to_oklch( string $hex ): ?array {
		$rgb = self::hex_to_rgb( $hex );

		return $rgb ? self::rgb_to_oklch( $rgb ) : null;
	}

	/**
	 * OKLCH to hex, reducing chroma until the colour fits in sRGB.
	 */
	public static function oklch_to_hex( float $l, float $c, float $h ): string {
		return self::rgb_to_hex( self::oklch_to_rgb_in_gamut( $l, $c, $h ) );
	}

	/**
	 * @param array{0:float,1:float,2:float} $rgb sRGB 0..1.
	 * @return array{0:float,1:float,2:float}
	 */
	public static function rgb_to_oklch( array $rgb ): array {
		[ $r, $g, $b ] = array_map( array( self::class, 'to_linear' ), $rgb );

		$l = self::cbrt( 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b );
		$m = self::cbrt( 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b );
		$s = self::cbrt( 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b );

		$big_l = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
		$a     = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
		$bb    = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

		$c = sqrt( $a * $a + $bb * $bb );
		$h = $c < 1e-6 ? 0.0 : fmod( rad2deg( atan2( $bb, $a ) ) + 360.0, 360.0 );

		return array( $big_l, $c, $h );
	}

	/**
	 * Unclamped OKLCH -> sRGB (may be out of gamut).
	 *
	 * @return array{0:float,1:float,2:float}
	 */
	public static function oklch_to_rgb( float $l, float $c, float $h ): array {
		$a = $c * cos( deg2rad( $h ) );
		$b = $c * sin( deg2rad( $h ) );

		$l_ = ( $l + 0.3963377774 * $a + 0.2158037573 * $b ) ** 3;
		$m_ = ( $l - 0.1055613458 * $a - 0.0638541728 * $b ) ** 3;
		$s_ = ( $l - 0.0894841775 * $a - 1.2914855480 * $b ) ** 3;

		$r  = 4.0767416621 * $l_ - 3.3077115913 * $m_ + 0.2309699292 * $s_;
		$g  = -1.2684380046 * $l_ + 2.6097574011 * $m_ - 0.3413193965 * $s_;
		$bl = -0.0041960863 * $l_ - 0.7034186147 * $m_ + 1.7076147010 * $s_;

		return array( self::from_linear( $r ), self::from_linear( $g ), self::from_linear( $bl ) );
	}

	/**
	 * Keeps lightness and hue; lowers chroma (binary search) until in sRGB gamut.
	 *
	 * @return array{0:float,1:float,2:float}
	 */
	public static function oklch_to_rgb_in_gamut( float $l, float $c, float $h ): array {
		$l = max( 0.0, min( 1.0, $l ) );
		$rgb = self::oklch_to_rgb( $l, $c, $h );
		if ( self::in_gamut( $rgb ) ) {
			return $rgb;
		}

		$low  = 0.0;
		$high = $c;
		for ( $i = 0; $i < 24; $i++ ) {
			$mid = ( $low + $high ) / 2;
			if ( self::in_gamut( self::oklch_to_rgb( $l, $mid, $h ) ) ) {
				$low = $mid;
			} else {
				$high = $mid;
			}
		}

		return array_map( static fn( float $v ): float => max( 0.0, min( 1.0, $v ) ), self::oklch_to_rgb( $l, $low, $h ) );
	}

	/**
	 * WCAG 2 relative luminance.
	 */
	public static function luminance( string $hex ): float {
		$rgb = self::hex_to_rgb( $hex );
		if ( ! $rgb ) {
			return 0.0;
		}
		[ $r, $g, $b ] = array_map( array( self::class, 'to_linear' ), $rgb );

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	/**
	 * WCAG 2 contrast ratio, 1..21, rounded to 2 decimals.
	 */
	public static function contrast( string $hex_a, string $hex_b ): float {
		$a = self::luminance( $hex_a );
		$b = self::luminance( $hex_b );

		return round( ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 ), 2 );
	}

	/**
	 * @param array{0:float,1:float,2:float} $rgb
	 */
	private static function in_gamut( array $rgb ): bool {
		foreach ( $rgb as $v ) {
			if ( $v < -0.0005 || $v > 1.0005 || is_nan( $v ) ) {
				return false;
			}
		}

		return true;
	}

	private static function to_linear( float $c ): float {
		return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
	}

	private static function from_linear( float $c ): float {
		$sign = $c < 0 ? -1.0 : 1.0;
		$c    = abs( $c );

		return $sign * ( $c <= 0.0031308 ? 12.92 * $c : 1.055 * ( $c ** ( 1 / 2.4 ) ) - 0.055 );
	}

	private static function cbrt( float $x ): float {
		return $x < 0 ? -( ( -$x ) ** ( 1 / 3 ) ) : $x ** ( 1 / 3 );
	}
}
