<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Color;

/**
 * Colour roles: named jobs (text, surface, border...) that point at palette
 * colours, with one choice for light mode and one for dark mode.
 *
 * Roles are saved as the Bricks palette "Leapfrog roles", each root colour
 * holding var(--palette-colour) for light and for dark, so page dark mode
 * switches them with no extra CSS. Section themes re-point them (ThemeCss).
 */
final class Roles {
	public const AUTO = 'auto';

	/**
	 * Role definitions: key => [label, group, default light, default dark].
	 * "auto" picks white or black, whichever contrasts more with the colour named in "on".
	 */
	private const DEFINITIONS = array(
		'text'            => array( 'Text', 'text', 'neutral-90', 'neutral-10' ),
		'text-muted'      => array( 'Muted text', 'text', 'neutral-70', 'neutral-30' ),
		'text-subtle'     => array( 'Subtle text (hints, meta)', 'text', 'neutral-60', 'neutral-40' ),
		'text-disabled'   => array( 'Disabled text', 'text', 'neutral-40', 'neutral-60' ),
		'heading'         => array( 'Headings', 'text', 'neutral-90', 'white' ),
		'link'            => array( 'Links', 'text', 'primary', 'primary' ),
		'link-hover'      => array( 'Links on hover', 'text', 'primary-80', 'primary-20' ),
		'icon'            => array( 'Icons', 'text', 'neutral-70', 'neutral-20' ),
		'icon-muted'      => array( 'Muted icons', 'text', 'neutral-50', 'neutral-50' ),
		'selection-text'  => array( 'Selected text', 'text', 'neutral-90', 'white' ),
		'canvas'          => array( 'Page background', 'surface', 'white', 'neutral-90' ),
		'surface'         => array( 'Surface (cards, panels)', 'surface', 'white', 'neutral-80' ),
		'surface-muted'   => array( 'Muted surface', 'surface', 'neutral-10', 'neutral-80' ),
		'surface-raised'  => array( 'Raised surface (menus, popovers)', 'surface', 'white', 'neutral-70' ),
		'surface-selected' => array( 'Selected surface (active tab, chosen item)', 'surface', 'primary-10', 'primary-80' ),
		'surface-inverse' => array( 'Inverse surface', 'surface', 'neutral-90', 'neutral-10' ),
		'selection-bg'    => array( 'Text selection background', 'surface', 'primary-20', 'primary-70' ),
		'border'          => array( 'Borders', 'border', 'neutral-20', 'neutral-70' ),
		'border-strong'   => array( 'Strong borders', 'border', 'neutral-40', 'neutral-50' ),
		'divider'         => array( 'Dividers', 'border', 'neutral-20', 'neutral-70' ),
		'focus'           => array( 'Focus ring', 'border', 'primary', 'primary' ),
		'on-primary'      => array( 'Text on primary', 'on', 'auto', 'auto' ),
		'on-secondary'    => array( 'Text on secondary', 'on', 'auto', 'auto' ),
		'on-accent'       => array( 'Text on accent', 'on', 'auto', 'auto' ),
		'on-success'      => array( 'Text on success', 'on', 'auto', 'auto' ),
		'on-warning'      => array( 'Text on warning', 'on', 'auto', 'auto' ),
		'on-error'        => array( 'Text on error', 'on', 'auto', 'auto' ),
		'on-info'         => array( 'Text on info', 'on', 'auto', 'auto' ),
		'success-surface' => array( 'Success background', 'status', 'success-10', 'success-90' ),
		'warning-surface' => array( 'Warning background', 'status', 'warning-10', 'warning-90' ),
		'error-surface'   => array( 'Error background', 'status', 'error-10', 'error-90' ),
		'info-surface'    => array( 'Info background', 'status', 'info-10', 'info-90' ),
	);

	/** Live mixes of other roles: not edited, recalculated in every theme. */
	public const TRANSPARENT = array(
		'border-subtle' => array( 'Subtle borders', 'color-mix(in oklch, var(--color-text) 12%, transparent)' ),
		'surface-hover' => array( 'Hover tint', 'color-mix(in oklch, var(--color-text) 6%, transparent)' ),
		'overlay'       => array( 'Overlay (behind modals)', 'color-mix(in oklch, var(--black) 55%, transparent)' ),
	);

	/** Text roles checked against surface roles for contrast. */
	public const CONTRAST_TEXT     = array( 'text', 'text-muted', 'heading', 'link' );
	public const CONTRAST_SURFACES = array( 'canvas', 'surface', 'surface-muted' );

	/**
	 * @return array<string, array{light:string,dark:string}>
	 */
	public static function default_map(): array {
		$out = array();
		foreach ( self::DEFINITIONS as $key => $def ) {
			$out[ $key ] = array( 'light' => $def[2], 'dark' => $def[3] );
		}

		return $out;
	}

	/**
	 * Hex values of every palette token: name => [light hex, dark hex|null]. Transparency steps are left out.
	 *
	 * @param list<array<string, mixed>> $generated From Palette::generate().
	 * @return array<string, array{light:string,dark:?string}>
	 */
	public static function tokens( array $generated ): array {
		$out = array();
		foreach ( $generated as $color ) {
			$out[ $color['key'] ] = array( 'light' => $color['base'], 'dark' => $color['dark'] );
			foreach ( $color['shades'] as $step => $hex ) {
				$out[ $color['key'] . '-' . $step ] = array( 'light' => $hex, 'dark' => null );
			}
		}

		return $out;
	}

	/**
	 * Every role with its choices checked against the palette and resolved to hex.
	 *
	 * @param array<string, mixed>                          $map    Saved choices.
	 * @param array<string, array{light:string,dark:?string}> $tokens From tokens().
	 * @return list<array<string, mixed>>
	 */
	public static function resolve( array $map, array $tokens ): array {
		$defaults = self::default_map();
		$out      = array();

		foreach ( self::DEFINITIONS as $key => $def ) {
			$row = array( 'key' => $key, 'label' => $def[0], 'group' => $def[1], 'variable' => '--color-' . $key );
			foreach ( array( 'light', 'dark' ) as $mode ) {
				$choice = (string) ( $map[ $key ][ $mode ] ?? $defaults[ $key ][ $mode ] );
				$valid  = ( self::AUTO === $choice && 'on' === $def[1] ) || isset( $tokens[ $choice ] );
				if ( ! $valid ) {
					$choice = isset( $tokens[ $defaults[ $key ][ $mode ] ] ) || 'on' === $def[1] ? $defaults[ $key ][ $mode ] : 'neutral';
				}
				$row[ $mode ]          = $choice;
				$row[ $mode . 'Hex' ]  = self::hex( $key, $choice, $mode, $tokens );
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Saved choices cleaned against the definitions.
	 *
	 * @param mixed $given
	 * @return array<string, array{light:string,dark:string}>
	 */
	public static function sanitize_map( $given ): array {
		$out = self::default_map();
		foreach ( is_array( $given ) ? $given : array() as $key => $choice ) {
			if ( ! isset( $out[ $key ] ) || ! is_array( $choice ) ) {
				continue;
			}
			foreach ( array( 'light', 'dark' ) as $mode ) {
				$value = strtolower( trim( (string) ( $choice[ $mode ] ?? '' ) ) );
				if ( self::AUTO === $value || 1 === preg_match( '/^[a-z]+(?:-[a-z]+)*(?:-\d{2})?$/', $value ) ) {
					$out[ $key ][ $mode ] = $value;
				}
			}
		}

		return $out;
	}

	/**
	 * Rows for the Bricks palette "Leapfrog roles" (same shape as Palette::bricks_rows()).
	 *
	 * @param list<array<string, mixed>> $resolved From resolve().
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks_rows( array $resolved ): array {
		$rows = array();
		foreach ( $resolved as $role ) {
			$name          = 'color-' . $role['key'];
			$rows[ $name ] = array(
				'raw'   => $name,
				'root'  => true,
				'light' => self::css( $role, 'light' ),
				'dark'  => self::css( $role, 'dark' ),
			);
		}
		foreach ( self::TRANSPARENT as $key => $def ) {
			$rows[ 'color-' . $key ] = array(
				'raw'   => 'color-' . $key,
				'root'  => true,
				'light' => $def[1],
				'dark'  => null,
			);
		}

		return $rows;
	}

	/**
	 * CSS value of a role in a mode: var(--token), or a hex for "auto".
	 *
	 * @param array<string, mixed> $role Resolved role.
	 */
	public static function css( array $role, string $mode ): string {
		return self::AUTO === $role[ $mode ] ? (string) $role[ $mode . 'Hex' ] : 'var(--' . $role[ $mode ] . ')';
	}

	/**
	 * Text-on-surface contrast in both modes.
	 *
	 * @param list<array<string, mixed>> $resolved
	 * @return list<array{text:string,surface:string,light:float,dark:float}>
	 */
	public static function contrast( array $resolved ): array {
		$by = array();
		foreach ( $resolved as $role ) {
			$by[ $role['key'] ] = $role;
		}
		$out = array();
		foreach ( self::CONTRAST_TEXT as $text ) {
			foreach ( self::CONTRAST_SURFACES as $surface ) {
				$out[] = array(
					'text'    => $text,
					'surface' => $surface,
					'light'   => Color::contrast( $by[ $text ]['lightHex'], $by[ $surface ]['lightHex'] ),
					'dark'    => Color::contrast( $by[ $text ]['darkHex'], $by[ $surface ]['darkHex'] ),
				);
			}
		}

		return $out;
	}

	/**
	 * @param array<string, array{light:string,dark:?string}> $tokens
	 */
	private static function hex( string $key, string $choice, string $mode, array $tokens ): string {
		if ( self::AUTO === $choice ) {
			$base   = substr( $key, 3 ); // on-primary -> primary
			$colour = $tokens[ $base ] ?? array( 'light' => '#000000', 'dark' => null );
			$bg     = 'dark' === $mode && $colour['dark'] ? $colour['dark'] : $colour['light'];

			return Color::contrast( '#ffffff', $bg ) >= Color::contrast( '#000000', $bg ) ? '#ffffff' : '#000000';
		}
		$token = $tokens[ $choice ] ?? array( 'light' => '#000000', 'dark' => null );

		// A palette colour with its own dark value shows that value in dark mode.
		return 'dark' === $mode && $token['dark'] ? $token['dark'] : $token['light'];
	}
}
