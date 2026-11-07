<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Fonts;

use Leapfrog\Framework\Bricks\ThemeStyle;
use RuntimeException;
use Throwable;

/**
 * Installs Google fonts on the site as Bricks custom fonts, self-hosted.
 *
 * Variable fonts are installed as one file per character set covering the whole
 * weight range (font-weight: 100 900), so any weight works; fonts that are not
 * variable get one file per weight. Only Latin and Latin Extended are installed,
 * which covers English and European languages. Then the heading and body fonts are
 * set in a site-wide Bricks theme style, so the site uses them straight away.
 */
final class FontInstaller {
	public const SUBSETS = array( 'latin', 'latin-ext' );

	/** The theme style Leapfrog sets fonts in (see ThemeStyle). */
	public const THEME_STYLE_OPTION = ThemeStyle::OPTION;

	private const FALLBACK = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

	private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

	/** @var array<string, array{normal:list<int>,italic:list<int>}>|null */
	private static ?array $google = null;

	/**
	 * Google font families Bricks knows, with their weights.
	 *
	 * @return array<string, array{normal:list<int>,italic:list<int>}>
	 */
	public static function google(): array {
		if ( null !== self::$google ) {
			return self::$google;
		}
		self::$google = array();
		$file         = get_template_directory() . '/assets/fonts/google-fonts.min.json';
		$list         = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		foreach ( is_array( $list ) ? $list : array() as $font ) {
			$family = (string) ( $font['family'] ?? '' );
			if ( '' === $family ) {
				continue;
			}
			$weights = array( 'normal' => array(), 'italic' => array() );
			foreach ( (array) ( $font['variants'] ?? array() ) as $variant ) {
				$variant = (string) $variant;
				$italic  = str_contains( $variant, 'italic' );
				$weight  = (int) preg_replace( '/\D/', '', $variant );
				$weights[ $italic ? 'italic' : 'normal' ][] = $weight ?: 400;
			}
			sort( $weights['normal'] );
			sort( $weights['italic'] );
			self::$google[ $family ] = $weights;
		}

		return self::$google;
	}

	/**
	 * Bricks custom font post ID for a family, or 0.
	 */
	public static function installed_id( string $family ): int {
		if ( ! defined( 'BRICKS_DB_CUSTOM_FONTS' ) || '' === $family ) {
			return 0;
		}
		$ids = get_posts( array( 'post_type' => BRICKS_DB_CUSTOM_FONTS, 'title' => $family, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );

		return (int) ( $ids[0] ?? 0 );
	}

	/**
	 * For each family: installed on the site, and available from Google.
	 *
	 * @param list<string> $families
	 * @return array<string, array{installed:bool,google:bool}>
	 */
	public static function status( array $families ): array {
		$google = self::google();
		$out    = array();
		foreach ( array_unique( array_filter( array_map( 'trim', $families ) ) ) as $family ) {
			$out[ $family ] = array( 'installed' => self::installed_id( $family ) > 0, 'google' => isset( $google[ $family ] ) );
		}

		return $out;
	}

	/**
	 * Installs one Google font family as a Bricks custom font.
	 *
	 * @return array<string, mixed> status: installed | exists | not-google | error.
	 */
	public static function install( string $family ): array {
		$family = trim( $family );
		if ( self::installed_id( $family ) ) {
			return array( 'family' => $family, 'status' => 'exists' );
		}
		$weights = self::google()[ $family ] ?? null;
		if ( ! $weights || ! defined( 'BRICKS_DB_CUSTOM_FONTS' ) ) {
			return array( 'family' => $family, 'status' => 'not-google' );
		}

		$post_id = 0;
		try {
			$faces = self::fetch_faces( $family, $weights );

			$post_id = wp_insert_post( array( 'post_title' => $family, 'post_type' => BRICKS_DB_CUSTOM_FONTS, 'post_status' => 'publish' ), true );
			if ( is_wp_error( $post_id ) ) {
				throw new RuntimeException( $post_id->get_error_message() );
			}

			$meta     = array();
			$files    = 0;
			$variable = false;
			$cache    = array();
			self::allow_woff2( true );
			foreach ( $faces as $face ) {
				$url = $face['url'];
				if ( ! isset( $cache[ $url ] ) ) {
					$cache[ $url ] = self::sideload( $url, sprintf( '%s-%s-%s-%s.woff2', sanitize_title( $family ), str_replace( ' ', '-', $face['weight'] ), $face['style'], $face['subset'] ), (int) $post_id );
					++$files;
				}
				$subset = array( 'woff2' => $cache[ $url ] );
				if ( '' !== $face['range'] ) {
					$subset['unicode-range'] = $face['range'];
				}
				if ( str_contains( $face['weight'], ' ' ) ) {
					$subset['font-weight-range'] = $face['weight'];
					$variable                    = true;
				}
				$meta[ self::face_key( $face['weight'], $face['style'] ) ][] = $subset;
			}
			self::allow_woff2( false );

			update_post_meta( (int) $post_id, BRICKS_DB_CUSTOM_FONT_FACES, $meta );
			if ( class_exists( '\Bricks\Custom_Fonts' ) ) {
				\Bricks\Custom_Fonts::refresh_font_face_rules();
			}
		} catch ( Throwable $error ) {
			self::allow_woff2( false );
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				foreach ( get_attached_media( '', (int) $post_id ) as $attachment ) {
					wp_delete_attachment( $attachment->ID, true );
				}
				wp_delete_post( (int) $post_id, true );
			}

			return array( 'family' => $family, 'status' => 'error', 'message' => $error->getMessage() );
		}

		return array(
			'family'   => $family,
			'status'   => 'installed',
			'id'       => (int) $post_id,
			'variable' => $variable,
			'files'    => $files,
			'weights'  => $variable ? ( $weights['normal'][0] ?? 400 ) . '–' . ( end( $weights['normal'] ) ?: 400 ) : implode( ', ', $weights['normal'] ),
			'italic'   => ! empty( $weights['italic'] ),
		);
	}

	/**
	 * Sets the heading and body fonts in Leapfrog's site-wide theme style.
	 * Uses the style Leapfrog set fonts in before, else the site's only site-wide
	 * style, else creates one called "Leapfrog".
	 *
	 * @return array<string, mixed>
	 */
	public static function apply_theme_style( string $heading, string $body ): array {
		$typography = array();
		foreach ( array( 'typographyHeadings' => $heading, 'typographyBody' => $body ) as $key => $family ) {
			$id = self::installed_id( $family );
			if ( $id ) {
				$typography[ $key ] = array( 'font-family' => 'custom_font_' . $id, 'fallback' => self::FALLBACK );
			}
		}
		if ( ! $typography ) {
			return array( 'status' => 'skipped' );
		}

		// Typography keys are merged into the style; everything else in it is left alone.
		return ThemeStyle::patch( array( 'typography' => $typography ) );
	}

	/**
	 * The @font-face rules Google serves for a family, Latin subsets only.
	 *
	 * @param array{normal:list<int>,italic:list<int>} $weights
	 * @return list<array{url:string,weight:string,style:string,range:string,subset:string}>
	 */
	private static function fetch_faces( string $family, array $weights ): array {
		$name   = str_replace( ' ', '+', $family );
		$tuples = array();
		// First ask for the whole range (variable fonts); if Google refuses, ask for each weight.
		foreach ( array( 'range', 'each' ) as $mode ) {
			$parts = array();
			foreach ( array( 0 => $weights['normal'], 1 => $weights['italic'] ) as $ital => $list ) {
				if ( ! $list ) {
					continue;
				}
				if ( 'range' === $mode && count( $list ) > 1 ) {
					$parts[] = $ital . ',' . $list[0] . '..' . end( $list );
				} else {
					foreach ( $list as $w ) {
						$parts[] = $ital . ',' . $w;
					}
				}
			}
			$query = $weights['italic'] ? 'ital,wght@' . implode( ';', $parts ) : 'wght@' . implode( ';', array_map( static fn( $p ) => substr( $p, 2 ), $parts ) );
			$css   = self::get( 'https://fonts.googleapis.com/css2?family=' . $name . ':' . $query . '&display=swap' );
			if ( null !== $css && str_contains( $css, '@font-face' ) ) {
				$tuples = self::parse( $css );
				if ( $tuples ) {
					return $tuples;
				}
			}
		}

		throw new RuntimeException( sprintf( /* translators: %s font family */ __( 'Google Fonts did not return files for %s.', 'leapfrog-framework' ), $family ) );
	}

	/**
	 * @return list<array{url:string,weight:string,style:string,range:string,subset:string}>
	 */
	private static function parse( string $css ): array {
		preg_match_all( '#/\*\s*([a-z0-9-]+)\s*\*/\s*@font-face\s*\{([^}]+)\}#i', $css, $blocks, PREG_SET_ORDER );
		$out = array();
		foreach ( $blocks as [ , $subset, $body ] ) {
			if ( ! in_array( strtolower( $subset ), self::SUBSETS, true ) ) {
				continue;
			}
			if ( ! preg_match( '#url\(([^)]+\.woff2)\)#', $body, $url ) ) {
				continue;
			}
			preg_match( '/font-weight:\s*([\d ]+);/', $body, $weight );
			preg_match( '/font-style:\s*(\w+)/', $body, $style );
			preg_match( '/unicode-range:\s*([^;]+);/', $body, $range );
			$url = trim( $url[1], '\'"' );
			if ( ! str_starts_with( $url, 'https://fonts.gstatic.com/' ) ) {
				continue; // Only Google's own font host.
			}
			$out[] = array(
				'url'    => $url,
				'weight' => trim( $weight[1] ?? '400' ),
				'style'  => 'italic' === strtolower( $style[1] ?? '' ) ? 'italic' : 'normal',
				'range'  => trim( $range[1] ?? '' ),
				'subset' => strtolower( $subset ),
			);
		}

		return $out;
	}

	/** Bricks font-face key: weight plus "italic" (e.g. 400, 400italic). A range uses 400 when it includes it. */
	private static function face_key( string $weight, string $style ): string {
		$parts = array_map( 'intval', preg_split( '/\s+/', trim( $weight ) ) ?: array( 400 ) );
		$base  = 2 === count( $parts ) ? ( $parts[0] <= 400 && $parts[1] >= 400 ? 400 : $parts[0] ) : ( $parts[0] ?: 400 );

		return $base . ( 'italic' === $style ? 'italic' : '' );
	}

	private static function get( string $url ): ?string {
		$response = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'User-Agent' => self::USER_AGENT ) ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	private static function sideload( string $url, string $name, int $post_id ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$body = self::get( $url );
		if ( null === $body || '' === $body || 'wOF2' !== substr( $body, 0, 4 ) ) {
			throw new RuntimeException( __( 'A font file could not be downloaded from Google Fonts.', 'leapfrog-framework' ) );
		}
		$tmp = wp_tempnam( $name );
		file_put_contents( $tmp, $body );
		$id = media_handle_sideload( array( 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp, 'type' => 'font/woff2' ), $post_id );
		if ( is_file( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}

		return (int) $id;
	}

	/** Lets WordPress accept .woff2 uploads while Leapfrog installs fonts. */
	private static function allow_woff2( bool $on ): void {
		static $mimes = null, $check = null;
		if ( $on ) {
			$mimes = static fn( array $types ): array => $types + array( 'woff2' => 'font/woff2' );
			$check = static function ( $data, $file, $filename ) {
				return str_ends_with( strtolower( (string) $filename ), '.woff2' ) ? array( 'ext' => 'woff2', 'type' => 'font/woff2', 'proper_filename' => false ) : $data;
			};
			add_filter( 'upload_mimes', $mimes );
			add_filter( 'wp_check_filetype_and_ext', $check, 10, 3 );
		} elseif ( $mimes ) {
			remove_filter( 'upload_mimes', $mimes );
			remove_filter( 'wp_check_filetype_and_ext', $check, 10 );
			$mimes = null;
			$check = null;
		}
	}
}
