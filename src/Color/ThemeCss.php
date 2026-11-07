<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Color;

/**
 * CSS for section themes: data-lf-theme="light" | "dark" | "inverted".
 *
 * Bricks handles page dark mode (:root[data-brx-theme="dark"]). Inside a themed
 * section Leapfrog re-points the colours that change between modes: brand and
 * status colours with a dark value, every colour role, and every live
 * transparency step (a variable built from another variable is worked out where
 * it is defined, so these must be repeated inside the section to follow it).
 *
 * "Inverted" flips the page mode, or the mode of the nearest light/dark section
 * around it. The CSS is generated on save and printed in the page head.
 */
final class ThemeCss {
	public const OPTION = 'lf_theme_css';

	/**
	 * @param list<array<string, mixed>> $generated  From Palette::generate().
	 * @param list<array<string, mixed>> $roles      From Roles::resolve().
	 * @param bool                       $page_styles Also colour the page text, headings, links and background.
	 */
	public static function build( array $generated, array $roles, bool $page_styles ): string {
		$light = self::declarations( $generated, $roles, 'light' );
		$dark  = self::declarations( $generated, $roles, 'dark' );

		// One block per mode. The page-mode part sits in :where() (no specificity), so an inverted
		// element inside an explicit light or dark section flips that section, not the page.
		$dark_selectors  = '[data-lf-theme="dark"],:where(:root:not([data-brx-theme="dark"])) [data-lf-theme="inverted"],[data-lf-theme="light"] [data-lf-theme="inverted"]';
		$light_selectors = '[data-lf-theme="light"],:where(:root[data-brx-theme="dark"]) [data-lf-theme="inverted"],[data-lf-theme="dark"] [data-lf-theme="inverted"]';

		$css  = "/* Leapfrog Framework: section themes */\n";
		$css .= "{$dark_selectors}{{$dark}}\n";
		$css .= "{$light_selectors}{{$light}}\n";
		// A themed section takes its text and background from the roles; any style set in Bricks wins.
		$css .= ":where([data-lf-theme]){color:var(--color-text);background-color:var(--color-canvas)}\n";

		if ( $page_styles ) {
			$css .= "body{color:var(--color-text);background-color:var(--color-canvas)}\n";
			$css .= ":where(h1,h2,h3,h4,h5,h6){color:var(--color-heading)}\n";
			$css .= ":where(a){color:var(--color-link)}:where(a:hover){color:var(--color-link-hover)}\n";
			$css .= ":where(:focus-visible){outline-color:var(--color-focus)}\n";
		}

		return $css;
	}

	/**
	 * Saves the CSS that wp_head prints.
	 */
	public static function store( string $css ): void {
		update_option( self::OPTION, $css, true );
	}

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'print' ), 100 );
	}

	public static function print(): void {
		$css = (string) get_option( self::OPTION, '' );
		if ( '' === $css ) {
			return;
		}
		// The CSS is generated from validated hex values and token names only.
		echo "<style id=\"leapfrog-themes\">\n" . wp_strip_all_tags( $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param list<array<string, mixed>> $generated
	 * @param list<array<string, mixed>> $roles
	 */
	private static function declarations( array $generated, array $roles, string $mode ): string {
		$out = 'color-scheme:' . $mode . ';';

		foreach ( $generated as $color ) {
			if ( null !== $color['dark'] ) {
				$out .= '--' . $color['key'] . ':' . ( 'dark' === $mode ? $color['dark'] : $color['base'] ) . ';';
			}
		}
		foreach ( $roles as $role ) {
			$out .= '--color-' . $role['key'] . ':' . Roles::css( $role, $mode ) . ';';
		}
		foreach ( Roles::TRANSPARENT as $key => $def ) {
			$out .= '--color-' . $key . ':' . $def[1] . ';';
		}
		foreach ( $generated as $color ) {
			foreach ( $color['alpha'] as $step => $css ) {
				$out .= '--' . $color['key'] . '-a' . $step . ':' . $css . ';';
			}
		}

		return $out;
	}
}
