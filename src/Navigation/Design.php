<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Navigation;

/**
 * The site's navigation style: what the Navigation Builder edits.
 *
 * It styles Leapfrog navigations (a Bricks Nav (Nestable) with the class "lfn",
 * as the starter makes): the menu bar, the button link, dropdowns, mega menus
 * and the mobile menu. Their content is edited in Bricks; the header rows,
 * sticky and scrolling stay with BricksExtras Header Row in Bricks.
 *
 * - mobile: when the mobile menu takes over (a Bricks breakpoint), where it comes
 *   from, drill-down sub-menus, the button at the bottom. These shape the base CSS.
 * - styles: values for the style settings. Empty = not set. Each setting says which
 *   parts it styles (selectors inside the navigation, "&" = the navigation itself,
 *   "^scrolled " = while the header is scrolled), where (desktop/mobile/both), and
 *   how (a property, or declarations per choice).
 *
 * css() turns a design into CSS; the builder's preview builds the style part the
 * same way from the same definitions.
 */
final class Design {
	/** Every style rule starts here, so a value set in the builder wins over Bricks' own styling. */
	public const PREFIX = 'html body #brx-header .brxe-nav-nested.lfn';

	private const TOP_LINK   = '.lfn-items > li > a:not(.lfn-btn)';
	private const TOP_TOGGLE = '.lfn-items > .brxe-dropdown > .brx-submenu-toggle';
	private const BTN        = '.lfn-items > li > a.lfn-btn';
	private const DD         = '.lfn-items > .brxe-dropdown:not(.brx-has-megamenu) > .brx-dropdown-content';
	private const MEGA       = '.lfn-items > .brx-has-megamenu > .brx-dropdown-content';

	/**
	 * The style settings. See the class comment for the format.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		$f = static function ( string $group, string $label, string $kind, array $selectors, string $property, string $suggest = '', string $media = '', array $extra = array() ): array {
			return array( 'group' => $group, 'label' => $label, 'kind' => $kind, 'selectors' => $selectors, 'property' => $property, 'suggest' => $suggest, 'media' => $media ) + $extra;
		};
		$hover = static function ( array $selectors ): array {
			$out = array();
			foreach ( $selectors as $s ) {
				array_push( $out, $s . ':hover', $s . ':focus-visible', $s . '.lfn-force-hover' );
			}
			return $out;
		};
		$case = array(
			''          => array( 'Not set', '' ),
			'none'      => array( 'As typed', 'text-transform:none' ),
			'uppercase' => array( 'All capitals', 'text-transform:uppercase' ),
		);

		$link_box  = array( self::TOP_LINK, self::TOP_TOGGLE );
		$link_text = array( self::TOP_LINK, self::TOP_TOGGLE . ' > span', self::TOP_TOGGLE . ' > button' );
		$link_hover = array_merge( $hover( array( self::TOP_LINK ) ), array( '.lfn-items > .brxe-dropdown:hover > .brx-submenu-toggle', '.lfn-items > .brxe-dropdown.open > .brx-submenu-toggle', '.lfn-items > .brxe-dropdown > .brx-submenu-toggle.lfn-force-hover' ) );
		$link_hover_text = array_merge( $hover( array( self::TOP_LINK ) ), array( '.lfn-items > .brxe-dropdown:hover > .brx-submenu-toggle > *', '.lfn-items > .brxe-dropdown.open > .brx-submenu-toggle > *', '.lfn-items > .brxe-dropdown > .brx-submenu-toggle.lfn-force-hover > *' ) );
		$current   = array( '.lfn-items > li > a:not(.lfn-btn)[aria-current="page"]' );
		$dd_link   = self::DD . ' > li > a';
		$animation = static function ( string $content, string $open ): array {
			return array(
				''        => array( 'Not set', '' ),
				'none'    => array( 'Appear at once', 'transition:none' ),
				'fade'    => array( 'Fade in', 'transition:opacity var(--lfn-speed,.25s) ease,visibility var(--lfn-speed,.25s);transform:none' ),
				'fade-up' => array( 'Fade in, moving up', 'transition:opacity var(--lfn-speed,.25s) ease,visibility var(--lfn-speed,.25s),transform var(--lfn-speed,.25s) ease;transform:translateY(10px)', array( array( array( $open ), 'transform:translateY(0)' ) ) ),
			);
		};

		return array(
			// The menu bar (desktop).
			'speed'              => $f( 'Menu bar', 'Change speed (hover, opening)', 'duration', array( '&' ), '--lfn-speed', 'var(--duration-normal)' ),
			'bar-gap'            => $f( 'Menu bar', 'Space between links', 'size', array( '.lfn-items' ), 'gap', 'var(--space-2xs)', 'desktop' ),
			'link-color'         => $f( 'Menu bar', 'Link colour', 'color', $link_text, 'color', 'var(--color-text)', 'desktop' ),
			'link-size'          => $f( 'Menu bar', 'Text size', 'font-size', $link_text, 'font-size', 'var(--text-s)', 'desktop' ),
			'link-weight'        => $f( 'Menu bar', 'Text weight', 'weight', $link_text, 'font-weight', 'var(--font-weight-medium)', 'desktop' ),
			'link-spacing'       => $f( 'Menu bar', 'Letter spacing', 'size', $link_text, 'letter-spacing', '', 'desktop' ),
			'link-case'          => $f( 'Menu bar', 'Capitals', 'choice', $link_text, '', '', 'desktop', array( 'choices' => $case ) ),
			'link-pad-block'     => $f( 'Menu bar', 'Padding above and below', 'size', $link_box, 'padding-block', 'var(--space-3xs)', 'desktop' ),
			'link-pad-inline'    => $f( 'Menu bar', 'Padding at the sides', 'size', $link_box, 'padding-inline', 'var(--space-2xs)', 'desktop' ),
			'link-radius'        => $f( 'Menu bar', 'Corner rounding', 'radius', $link_box, 'border-radius', 'var(--radius-s)', 'desktop' ),
			'link-hover-color'   => $f( 'Menu bar', 'Hover colour', 'color', $link_hover_text, 'color', 'var(--color-link-hover)', 'desktop' ),
			'link-hover-bg'      => $f( 'Menu bar', 'Hover background', 'color', $link_hover, 'background-color', '', 'desktop' ),
			'link-current-color' => $f( 'Menu bar', 'Current page colour', 'color', $current, 'color', '', 'desktop' ),
			'link-current-mark'  => $f( 'Menu bar', 'Current page marker', 'choice', $current, '', '', 'desktop', array( 'choices' => array( '' => array( 'Not set', '' ), 'underline' => array( 'Underline', 'text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:0.45em' ), 'none' => array( 'None', 'text-decoration:none' ) ) ) ),
			'link-scrolled-color' => $f( 'Menu bar', 'Link colour while the header is scrolled', 'color', array_map( static fn( string $s ): string => '^scrolled ' . $s, $link_text ), 'color', '', 'desktop' ),
			'chevron-size'       => $f( 'Menu bar', 'Dropdown arrow size', 'size', array( self::TOP_TOGGLE . ' > button' ), 'font-size', '0.7em', 'desktop' ),
			'chevron-open'       => $f( 'Menu bar', 'Dropdown arrow when open', 'choice', array( '.lfn-items > .brxe-dropdown.open > .brx-submenu-toggle > button > svg' ), '', '', 'desktop', array( 'choices' => array( '' => array( 'Not set', '' ), 'flip' => array( 'Flip', 'transform:rotate(180deg)' ), 'none' => array( 'Stay', 'transform:none' ) ) ) ),

			// The button link (everywhere).
			'btn-bg'             => $f( 'Button link', 'Background', 'color', array( self::BTN ), 'background-color', 'var(--primary)' ),
			'btn-color'          => $f( 'Button link', 'Text colour', 'color', array( self::BTN ), 'color', 'var(--color-on-primary)' ),
			'btn-border'         => $f( 'Button link', 'Border colour', 'color', array( self::BTN ), 'border', '', '', array( 'format' => '1px solid {v}' ) ),
			'btn-hover-bg'       => $f( 'Button link', 'Hover background', 'color', $hover( array( self::BTN ) ), 'background-color' ),
			'btn-hover-color'    => $f( 'Button link', 'Hover text colour', 'color', $hover( array( self::BTN ) ), 'color' ),
			'btn-radius'         => $f( 'Button link', 'Corner rounding', 'radius', array( self::BTN ), 'border-radius', 'var(--btn-radius)' ),
			'btn-pad-block'      => $f( 'Button link', 'Padding above and below', 'size', array( self::BTN ), 'padding-block', 'var(--btn-padding-block)' ),
			'btn-pad-inline'     => $f( 'Button link', 'Padding at the sides', 'size', array( self::BTN ), 'padding-inline', 'var(--btn-padding-inline)' ),
			'btn-size'           => $f( 'Button link', 'Text size', 'font-size', array( self::BTN ), 'font-size', 'var(--btn-font-size)' ),
			'btn-weight'         => $f( 'Button link', 'Text weight', 'weight', array( self::BTN ), 'font-weight', 'var(--btn-font-weight)' ),
			'btn-space'          => $f( 'Button link', 'Space before it', 'size', array( '.lfn-items > li:has(> a.lfn-btn)' ), 'margin-inline-start', 'var(--space-xs)', 'desktop' ),

			// Dropdowns (desktop).
			'dd-bg'              => $f( 'Dropdowns', 'Background', 'color', array( self::DD ), 'background-color', 'var(--color-surface)', 'desktop' ),
			'dd-radius'          => $f( 'Dropdowns', 'Corner rounding', 'radius', array( self::DD ), 'border-radius', 'var(--radius-m)', 'desktop' ),
			'dd-shadow'          => $f( 'Dropdowns', 'Shadow', 'shadow', array( self::DD ), 'box-shadow', 'var(--shadow-m)', 'desktop' ),
			'dd-border'          => $f( 'Dropdowns', 'Border colour', 'color', array( self::DD ), 'border', '', 'desktop', array( 'format' => '1px solid {v}' ) ),
			'dd-pad'             => $f( 'Dropdowns', 'Padding inside', 'size', array( self::DD ), 'padding', 'var(--space-2xs)', 'desktop' ),
			'dd-width'           => $f( 'Dropdowns', 'Minimum width', 'size', array( self::DD ), 'min-width', '16em', 'desktop' ),
			'dd-offset'          => $f( 'Dropdowns', 'Gap below the menu bar', 'size', array( self::DD ), 'margin-top', '', 'desktop' ),
			'dd-animation'       => $f( 'Dropdowns', 'Opening', 'choice', array( self::DD ), '', '', 'desktop', array( 'choices' => $animation( self::DD, '.lfn-items > .brxe-dropdown.open:not(.brx-has-megamenu) > .brx-dropdown-content' ) ) ),
			'dd-link-color'      => $f( 'Dropdowns', 'Link colour', 'color', array( $dd_link ), 'color', 'var(--color-text)', 'desktop' ),
			'dd-link-size'       => $f( 'Dropdowns', 'Link text size', 'font-size', array( $dd_link ), 'font-size', 'var(--text-s)', 'desktop' ),
			'dd-link-weight'     => $f( 'Dropdowns', 'Link text weight', 'weight', array( $dd_link ), 'font-weight', '', 'desktop' ),
			'dd-link-pad-block'  => $f( 'Dropdowns', 'Link padding above and below', 'size', array( $dd_link ), 'padding-block', 'var(--space-3xs)', 'desktop' ),
			'dd-link-pad-inline' => $f( 'Dropdowns', 'Link padding at the sides', 'size', array( $dd_link ), 'padding-inline', 'var(--space-xs)', 'desktop' ),
			'dd-link-radius'     => $f( 'Dropdowns', 'Link corner rounding', 'radius', array( $dd_link ), 'border-radius', 'var(--radius-s)', 'desktop' ),
			'dd-link-hover-color' => $f( 'Dropdowns', 'Link hover colour', 'color', $hover( array( $dd_link ) ), 'color', 'var(--color-link-hover)', 'desktop' ),
			'dd-link-hover-bg'   => $f( 'Dropdowns', 'Link hover background', 'color', $hover( array( $dd_link ) ), 'background-color', 'var(--color-surface-hover)', 'desktop' ),

			// Mega menus (desktop).
			'mega-bg'            => $f( 'Mega menus', 'Background', 'color', array( self::MEGA ), 'background-color', 'var(--color-surface)', 'desktop' ),
			'mega-radius'        => $f( 'Mega menus', 'Corner rounding', 'radius', array( self::MEGA ), 'border-radius', 'var(--radius-m)', 'desktop' ),
			'mega-shadow'        => $f( 'Mega menus', 'Shadow', 'shadow', array( self::MEGA ), 'box-shadow', 'var(--shadow-l)', 'desktop' ),
			'mega-border'        => $f( 'Mega menus', 'Border colour', 'color', array( self::MEGA ), 'border', '', 'desktop', array( 'format' => '1px solid {v}' ) ),
			'mega-pad'           => $f( 'Mega menus', 'Padding inside', 'size', array( self::MEGA ), 'padding', 'var(--space-s)', 'desktop' ),
			'mega-gap'           => $f( 'Mega menus', 'Space between rows and the call to action', 'size', array( self::MEGA ), 'gap', 'var(--space-s)', 'desktop' ),
			'mega-offset'        => $f( 'Mega menus', 'Gap below the menu bar', 'size', array( self::MEGA ), 'margin-top', '', 'desktop' ),
			'mega-columns'       => $f( 'Mega menus', 'Columns of rows', 'choice', array( self::MEGA . ' .lfn-mega-rows' ), '', '', 'desktop', array( 'choices' => array( '' => array( 'Not set (2)', '' ), '1' => array( 'One', '--lfn-mega-cols:1' ), '2' => array( 'Two', '--lfn-mega-cols:2' ), '3' => array( 'Three', '--lfn-mega-cols:3' ), '4' => array( 'Four', '--lfn-mega-cols:4' ) ) ) ),
			'mega-animation'     => $f( 'Mega menus', 'Opening', 'choice', array( self::MEGA ), '', '', 'desktop', array( 'choices' => $animation( self::MEGA, '.lfn-items > .brx-has-megamenu.open > .brx-dropdown-content' ) ) ),
			'row-gap'            => $f( 'Mega menu rows', 'Space between rows', 'size', array( '.lfn-mega-rows' ), 'gap', 'var(--space-3xs)' ),
			'row-pad'            => $f( 'Mega menu rows', 'Padding inside a row', 'size', array( '.lfn-mega-row' ), 'padding', 'var(--space-2xs)' ),
			'row-radius'         => $f( 'Mega menu rows', 'Corner rounding', 'radius', array( '.lfn-mega-row' ), 'border-radius', 'var(--radius-s)' ),
			'row-hover-bg'       => $f( 'Mega menu rows', 'Hover background', 'color', $hover( array( '.lfn-mega-row' ) ), 'background-color', 'var(--color-surface-hover)' ),
			'row-title-color'    => $f( 'Mega menu rows', 'Title colour', 'color', array( '.lfn-mega-title' ), 'color', 'var(--color-heading)' ),
			'row-title-size'     => $f( 'Mega menu rows', 'Title size', 'font-size', array( '.lfn-mega-title' ), 'font-size', 'var(--text-s)' ),
			'row-title-weight'   => $f( 'Mega menu rows', 'Title weight', 'weight', array( '.lfn-mega-title' ), 'font-weight', 'var(--font-weight-semibold)' ),
			'row-text-color'     => $f( 'Mega menu rows', 'Text colour', 'color', array( '.lfn-mega-text' ), 'color', 'var(--color-text-muted)' ),
			'row-text-size'      => $f( 'Mega menu rows', 'Text size', 'font-size', array( '.lfn-mega-text' ), 'font-size', 'var(--text-xs)' ),
			'row-arrow-color'    => $f( 'Mega menu rows', 'Arrow colour', 'color', array( '.lfn-mega-arrow' ), 'color', 'var(--accent)' ),
			'row-arrow'          => $f( 'Mega menu rows', 'Arrow', 'choice', array( '.lfn-mega-arrow' ), '', '', '', array( 'choices' => array( '' => array( 'Not set (slides in on hover)', '' ), 'always' => array( 'Always shown', 'opacity:1;transform:none' ), 'hidden' => array( 'Hidden', 'display:none' ) ) ) ),
			'cta-bg'             => $f( 'Mega menu call to action', 'Background', 'color', array( '.lfn-mega-cta' ), 'background-color', 'var(--color-surface-muted)' ),
			'cta-color'          => $f( 'Mega menu call to action', 'Text colour', 'color', array( '.lfn-mega-cta' ), 'color' ),
			'cta-radius'         => $f( 'Mega menu call to action', 'Corner rounding', 'radius', array( '.lfn-mega-cta' ), 'border-radius', 'var(--radius-m)' ),
			'cta-pad'            => $f( 'Mega menu call to action', 'Padding inside', 'size', array( '.lfn-mega-cta' ), 'padding', 'var(--space-s)' ),
			'cta-width'          => $f( 'Mega menu call to action', 'Width', 'size', array( '.lfn-mega-cta' ), 'flex-basis', '28%', 'desktop' ),
			'cta-title-size'     => $f( 'Mega menu call to action', 'Title size', 'font-size', array( '.lfn-mega-cta-title' ), 'font-size', 'var(--text-m)' ),
			'cta-title-weight'   => $f( 'Mega menu call to action', 'Title weight', 'weight', array( '.lfn-mega-cta-title' ), 'font-weight', 'var(--heading-weight)' ),
			'cta-text-size'      => $f( 'Mega menu call to action', 'Text size', 'font-size', array( '.lfn-mega-cta-text' ), 'font-size', 'var(--text-xs)' ),
			'cta-link-color'     => $f( 'Mega menu call to action', 'Link colour', 'color', array( '.lfn-mega-cta-link' ), 'color', 'var(--color-link)' ),

			// The mobile menu.
			'm-bg'               => $f( 'Mobile menu', 'Panel background', 'color', array( '.lfn-panel', '.lfn-items > .brxe-dropdown > .brx-dropdown-content' ), 'background-color', 'var(--color-surface)', 'mobile' ),
			'm-color'            => $f( 'Mobile menu', 'Text colour', 'color', array( '.lfn-panel' ), 'color', 'var(--color-text)', 'mobile' ),
			'm-width'            => $f( 'Mobile menu', 'Panel width (from the side)', 'size', array( '&' ), '--lfn-panel-width', 'min(380px, 100vw)', 'mobile' ),
			'm-pad'              => $f( 'Mobile menu', 'Padding inside', 'size', array( '&' ), '--lfn-pad', 'var(--space-m)', 'mobile' ),
			'm-shadow'           => $f( 'Mobile menu', 'Panel shadow', 'shadow', array( '.lfn-panel' ), 'box-shadow', 'var(--shadow-l)', 'mobile' ),
			'm-link-color'       => $f( 'Mobile menu', 'Link colour', 'color', array( self::TOP_LINK, self::TOP_TOGGLE . ' > span', self::TOP_TOGGLE . ' > button', '.lfn-items .brx-dropdown-content > li > a' ), 'color', '', 'mobile' ),
			'm-link-size'        => $f( 'Mobile menu', 'Link text size', 'font-size', array( self::TOP_LINK, self::TOP_TOGGLE . ' > span' ), 'font-size', 'var(--text-l)', 'mobile' ),
			'm-link-weight'      => $f( 'Mobile menu', 'Link text weight', 'weight', array( self::TOP_LINK, self::TOP_TOGGLE . ' > span' ), 'font-weight', '', 'mobile' ),
			'm-link-pad'         => $f( 'Mobile menu', 'Space above and below links', 'size', array( self::TOP_LINK, self::TOP_TOGGLE, '.lfn-items .brx-dropdown-content > li > a' ), 'padding-block', 'var(--space-xs)', 'mobile' ),
			'm-divider'          => $f( 'Mobile menu', 'Line between links', 'color', array( '&' ), '--lfn-divider', 'var(--color-border)', 'mobile' ),
			'm-sub-size'         => $f( 'Mobile menu', 'Sub-menu link size', 'font-size', array( '.lfn-items .brx-dropdown-content > li > a', '.lfn-mega-title' ), 'font-size', 'var(--text-m)', 'mobile' ),
			'm-back-color'       => $f( 'Mobile menu', 'Back button colour', 'color', array( '.lfn-back' ), 'color', '', 'mobile' ),
			'm-back-size'        => $f( 'Mobile menu', 'Back button text size', 'font-size', array( '.lfn-back' ), 'font-size', 'var(--text-s)', 'mobile' ),
			'm-icon-color'       => $f( 'Mobile menu', 'Open and close icon colour', 'color', array( '.lfn-burger', '.lfn-close' ), 'color', '' ),
			'm-icon-size'        => $f( 'Mobile menu', 'Open and close icon size', 'size', array( '.lfn-burger', '.lfn-close' ), 'font-size', '24px', 'mobile' ),
			'm-logo-width'       => $f( 'Mobile menu', 'Logo width in the panel', 'size', array( '.lfn-bar-logo img' ), 'width', '120px', 'mobile' ),
		);
	}

	/**
	 * A design with every field present and safe.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function clean( array $raw ): array {
		$fields = self::fields();
		$mobile = is_array( $raw['mobile'] ?? null ) ? $raw['mobile'] : array();
		$bool   = static fn( $value, bool $default ): bool => null === $value ? $default : (bool) $value;

		$styles = array();
		foreach ( is_array( $raw['styles'] ?? null ) ? $raw['styles'] : array() as $key => $value ) {
			$key = (string) $key;
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			if ( isset( $fields[ $key ]['choices'] ) ) {
				if ( is_string( $value ) && '' !== $value && isset( $fields[ $key ]['choices'][ $value ] ) ) {
					$styles[ $key ] = $value;
				}
				continue;
			}
			$clean = self::clean_css_value( $value );
			if ( null !== $clean && '' !== $clean ) {
				$styles[ $key ] = $clean;
			}
		}

		$breakpoint = sanitize_key( (string) ( $mobile['breakpoint'] ?? '' ) );

		return array(
			'mobile' => array(
				'breakpoint'   => '' !== $breakpoint ? $breakpoint : 'tablet_portrait',
				'from'         => in_array( $mobile['from'] ?? '', array( 'right', 'left', 'top', 'full' ), true ) ? $mobile['from'] : 'right',
				'drill'        => $bool( $mobile['drill'] ?? null, true ),
				'buttonBottom' => $bool( $mobile['buttonBottom'] ?? null, true ),
			),
			'styles' => $styles,
		);
	}

	/**
	 * A CSS value that can't break out of its declaration, or null when it isn't one.
	 */
	public static function clean_css_value( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( strlen( $value ) > 200 || preg_match( '/[;{}<>\\\\"\']|\/\*|url\s*\(|expression\s*\(|@import|!important/i', $value ) ) {
			return null;
		}

		return substr_count( $value, '(' ) === substr_count( $value, ')' ) ? $value : null;
	}

	/**
	 * The width at and below which the mobile menu takes over, from the Bricks breakpoint.
	 */
	public static function mobile_max( array $design ): int {
		$key = (string) ( $design['mobile']['breakpoint'] ?? 'tablet_portrait' );
		if ( is_callable( array( '\Bricks\Breakpoints', 'get_breakpoints' ) ) ) {
			foreach ( (array) \Bricks\Breakpoints::get_breakpoints() as $bp ) {
				if ( ( $bp['key'] ?? '' ) === $key && ! empty( $bp['width'] ) ) {
					return (int) $bp['width'];
				}
			}
		}

		return 991;
	}

	/**
	 * The style settings' CSS. The builder's preview builds the same from the same fields.
	 *
	 * @param array<string, mixed> $design
	 */
	public static function styles_css( array $design, int $mobile_max ): string {
		$out    = array( '' => '', 'desktop' => '', 'mobile' => '' );
		$fields = self::fields();
		foreach ( (array) ( $design['styles'] ?? array() ) as $key => $value ) {
			$field = $fields[ $key ] ?? null;
			if ( ! $field || '' === (string) $value ) {
				continue;
			}
			$rules = array();
			if ( isset( $field['choices'] ) ) {
				$choice = $field['choices'][ $value ] ?? array( '', '' );
				$rules[] = array( $field['selectors'], (string) ( $choice[1] ?? '' ) );
				foreach ( (array) ( $choice[2] ?? array() ) as $extra ) {
					$rules[] = $extra;
				}
			} else {
				$rules[] = array( $field['selectors'], $field['property'] . ':' . str_replace( '{v}', (string) $value, $field['format'] ?? '{v}' ) );
			}
			foreach ( $rules as $rule ) {
				if ( '' === $rule[1] ) {
					continue;
				}
				$out[ $field['media'] ] .= implode( ',', self::selectors( (array) $rule[0] ) ) . '{' . $rule[1] . '}';
			}
		}

		return $out[''] . ( '' !== $out['desktop'] ? '@media (min-width:' . ( $mobile_max + 1 ) . 'px){' . $out['desktop'] . '}' : '' ) . ( '' !== $out['mobile'] ? '@media (max-width:' . $mobile_max . 'px){' . $out['mobile'] . '}' : '' );
	}

	/**
	 * Full selectors for selectors written inside the navigation.
	 *
	 * @param list<string> $selectors
	 * @return list<string>
	 */
	private static function selectors( array $selectors ): array {
		$out = array();
		foreach ( $selectors as $s ) {
			if ( str_starts_with( $s, '^scrolled ' ) ) {
				$inner = substr( $s, 10 );
				$out[] = 'html body #brx-header.scrolling .brxe-nav-nested.lfn ' . $inner;
				$out[] = 'html body #brx-header.x-header_sticky-active .brxe-nav-nested.lfn ' . $inner;
			} elseif ( str_starts_with( $s, '&' ) ) {
				$out[] = self::PREFIX . substr( $s, 1 );
			} else {
				$out[] = self::PREFIX . ' ' . $s;
			}
		}

		return $out;
	}

	/**
	 * The CSS that makes a Leapfrog navigation work: the menu bar on desktop, the
	 * slide-in panel with drill-down sub-menus on mobile. Plain looks only; the
	 * style settings do the rest.
	 *
	 * @param array<string, mixed> $design
	 */
	public static function base_css( array $design, int $max ): string {
		$m    = $design['mobile'];
		$n    = '#brx-header .lfn';
		$from = (string) $m['from'];
		$hide = array( 'right' => 'translateX(100%)', 'left' => 'translateX(-100%)', 'top' => 'translateY(-100%)', 'full' => 'none' )[ $from ];
		$side = array(
			'right' => 'top:0;bottom:0;right:0;width:var(--lfn-panel-width,min(380px,100vw))',
			'left'  => 'top:0;bottom:0;left:0;width:var(--lfn-panel-width,min(380px,100vw))',
			'top'   => 'top:0;left:0;right:0;max-height:100dvh',
			'full'  => 'inset:0',
		)[ $from ];
		$open_extra = 'full' === $from ? 'opacity:1;' : '';
		$closed_extra = 'full' === $from ? 'opacity:0;' : '';

		// Bricks blocks are columns by default: the bar and the panel are rows on desktop.
		$css = "$n .lfn-panel{display:flex;flex-direction:row;align-items:center}"
			. "$n .lfn-items{display:flex;flex-direction:row;align-items:center;margin:0;padding:0;list-style:none}"
			. "$n .lfn-close > i,$n .lfn-burger > i{font-size:1em}"
			. "$n .lfn-items > li > a{text-decoration:none}"
			. "$n .lfn-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;white-space:nowrap}"
			. "$n .lfn-items > .brxe-dropdown > .brx-submenu-toggle > button > svg{transition:transform var(--lfn-speed,.25s) ease}"
			. "$n .lfn-mega-content{display:flex;flex-direction:row;align-items:stretch}"
			. "$n .lfn-mega-rows{display:grid;grid-template-columns:repeat(var(--lfn-mega-cols,2),minmax(0,1fr));flex:1}"
			. "$n .lfn-mega-row{display:flex;flex-direction:column;gap:.2em;text-decoration:none;color:inherit;transition:background-color var(--lfn-speed,.25s)}"
			. "$n .lfn-mega-line{display:flex;align-items:center;justify-content:space-between;gap:.75em}"
			. "$n .lfn-mega-arrow{flex:none;opacity:0;transform:translateX(-4px);transition:opacity var(--lfn-speed,.25s),transform var(--lfn-speed,.25s)}"
			. "$n .lfn-mega-row:hover .lfn-mega-arrow,$n .lfn-mega-row:focus-visible .lfn-mega-arrow,$n .lfn-mega-row.lfn-force-hover .lfn-mega-arrow{opacity:1;transform:none}"
			. "$n .lfn-mega-cta{display:flex;flex-direction:column;gap:.5em;flex:0 0 28%}"
			. "$n .lfn-mega-cta-text{margin:0}"
			. "$n .lfn-back,$n .lfn-close,$n .lfn-burger{background:none;border:0;color:inherit;cursor:pointer}"
			. "$n .lfn-burger,$n .lfn-close{font-size:24px}"
			. "html.lfn-lock,html.lfn-lock body{overflow:hidden}"
			. "@media (prefers-reduced-motion:reduce){ $n *,$n .lfn-panel{transition:none!important}}";

		$css .= '@media (min-width:' . ( $max + 1 ) . 'px){'
			. "$n .lfn-bar,$n .lfn-burger{display:none!important}"
			. '}';

		$css .= '@media (max-width:' . $max . 'px){'
			// Bricks hides toggles inside a nav whose own mobile menu is off ("never"): show ours.
			. "$n .lfn-burger,$n .lfn-close{display:inline-flex;align-items:center;justify-content:center}"
			. "$n .lfn-panel{position:fixed;z-index:10000;$side;flex-direction:column;align-items:stretch;overflow-x:hidden;overflow-y:auto;background:#fff;color:#1a1a1a;transform:$hide;{$closed_extra}visibility:hidden;transition:transform var(--lfn-speed,.3s) ease,opacity var(--lfn-speed,.3s) ease,visibility 0s linear var(--lfn-speed,.3s)}"
			. "body.admin-bar $n .lfn-panel{top:var(--wp-admin--admin-bar--height,0px)}"
			. "$n.brx-open .lfn-panel{transform:none;{$open_extra}visibility:visible;transition:transform var(--lfn-speed,.3s) ease,opacity var(--lfn-speed,.3s) ease}"
			. "$n .lfn-bar{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;justify-content:space-between;gap:1rem;position:sticky;top:0;z-index:3;padding:calc(var(--lfn-pad,20px) / 2) var(--lfn-pad,20px);background:inherit}"
			. "$n .lfn-bar-logo{margin-inline:auto}"
			. "$n .lfn-back{display:inline-flex;align-items:center;gap:.35em;padding:0;visibility:hidden}"
			. "$n.lfn-sub-open .lfn-back{visibility:visible}"
			. "$n .lfn-items{position:static;flex:1;flex-direction:column;align-items:stretch;gap:0;padding:0 var(--lfn-pad,20px) var(--lfn-pad,20px)}"
			. "$n .lfn-items > li{position:static;border-bottom:1px solid var(--lfn-divider,rgb(0 0 0 / .08))}"
			. "$n .lfn-items > li > a:not(.lfn-btn),$n .lfn-items > .brxe-dropdown > .brx-submenu-toggle{display:flex;align-items:center;justify-content:space-between;width:100%;padding-inline:0;border-radius:0;background:none}"
			. "$n .lfn-items > .brxe-dropdown > .brx-submenu-toggle > span{flex:1}";

		if ( $m['buttonBottom'] ) {
			$css .= "$n .lfn-items > li:has(> a.lfn-btn){margin-top:auto;border-bottom:0;padding-top:var(--lfn-pad,20px)}"
				. "$n .lfn-items > li > a.lfn-btn{display:flex;width:100%}";
		}

		$content = "$n .lfn-items > .brxe-dropdown > .brx-dropdown-content";
		if ( $m['drill'] ) {
			// Each sub-menu slides in over the panel, under its top bar; Back closes it.
			$css .= "$n .lfn-items > .brxe-dropdown > .brx-submenu-toggle > button > svg{transform:rotate(-90deg)}"
				// Full width rather than left + right: Bricks' "overflows the screen" classes force left or right to auto.
				. "$content{position:absolute!important;top:var(--lfn-bar-h,56px)!important;left:0!important;right:0!important;bottom:0!important;width:100%!important;max-width:none!important;box-sizing:border-box;min-width:0!important;margin:0!important;display:flex;flex-direction:column;overflow-y:auto;padding:0 var(--lfn-pad,20px) var(--lfn-pad,20px)!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:#fff;transform:translateX(100%);visibility:hidden;opacity:1!important;transition:transform var(--lfn-speed,.3s) ease,visibility 0s linear var(--lfn-speed,.3s)}"
				. "$n .lfn-items > .brxe-dropdown.open > .brx-dropdown-content{transform:none;visibility:visible;transition:transform var(--lfn-speed,.3s) ease}"
				. "$n.lfn-sub-open .lfn-panel{overflow-y:hidden}";
		} else {
			// Sub-menus open underneath their link.
			$css .= "$n .lfn-items > .brxe-dropdown > .brx-submenu-toggle > button > svg{transition:transform var(--lfn-speed,.3s)}"
				. "$n .lfn-items > .brxe-dropdown.open > .brx-submenu-toggle > button > svg{transform:rotate(180deg)}"
				. "$content{position:static!important;width:auto!important;min-width:0!important;margin:0!important;display:none;flex-direction:column;padding:0 0 .5rem 1rem!important;border:0!important;box-shadow:none!important;background:none!important;transform:none!important;visibility:visible!important;opacity:1!important}"
				. "$n .lfn-items > .brxe-dropdown.open > .brx-dropdown-content{display:flex}";
		}

		$css .= "$content > li{border-bottom:1px solid var(--lfn-divider,rgb(0 0 0 / .08))}"
			. "$content > li > a{display:block;padding-inline:0}"
			. "$n .lfn-mega-content{flex-direction:column;gap:var(--lfn-pad,20px)}"
			. "$n .lfn-mega-rows{grid-template-columns:1fr}"
			. "$n .lfn-mega-row{padding-inline:0!important;border-bottom:1px solid var(--lfn-divider,rgb(0 0 0 / .08));border-radius:0!important}"
			. "$n .lfn-mega-arrow{opacity:1;transform:none}"
			. "$n .lfn-mega-cta{flex-basis:auto}"
			. '}';

		return $css;
	}

	/**
	 * The header template's Bricks sticky colours ("Scrolling text color" and its hover) on the
	 * menu bar's links while the header is scrolled. The navigation's own link colours are
	 * stronger than Bricks' rules, so without this they never change. Printed before the style
	 * settings with the same selectors, so a scrolled colour set in the Navigation Builder wins.
	 *
	 * @param array<string, mixed> $design
	 * @param array<string, mixed> $template_settings The header template's Bricks settings.
	 */
	public static function bricks_scrolled_css( array $design, int $max, array $template_settings ): string {
		if ( empty( $template_settings['headerSticky'] ) ) {
			return '';
		}
		$color = self::bricks_color( $template_settings['headerStickyScrollingColor'] ?? null );
		$hover = self::bricks_color( $template_settings['headerStickyScrollingColorHover'] ?? null );
		if ( '' === $hover && '' !== $color ) {
			// No scrolled hover colour: keep the menu's own hover colour rather than losing hover.
			$hover = (string) ( $design['styles']['link-hover-color'] ?? '' );
		}
		$fields = self::fields();
		$css    = '';
		if ( '' !== $color ) {
			$css .= implode( ',', self::selectors( $fields['link-scrolled-color']['selectors'] ) ) . '{color:' . $color . '}';
		}
		if ( '' !== $hover ) {
			$selectors = array_map( static fn( string $s ): string => '^scrolled ' . $s, $fields['link-hover-color']['selectors'] );
			$css      .= implode( ',', self::selectors( $selectors ) ) . '{color:' . $hover . '}';
		}

		return '' !== $css ? '@media (min-width:' . ( $max + 1 ) . 'px){' . $css . '}' : '';
	}

	/**
	 * A Bricks colour setting as a safe CSS value, or '' when it isn't set.
	 *
	 * @param mixed $color
	 */
	private static function bricks_color( $color ): string {
		if ( ! is_array( $color ) ) {
			return '';
		}
		$value = is_callable( array( '\Bricks\Assets', 'generate_css_color' ) )
			? \Bricks\Assets::generate_css_color( $color )
			: ( $color['raw'] ?? $color['rgb'] ?? $color['hex'] ?? '' );

		return (string) self::clean_css_value( $value );
	}

	/**
	 * Everything for the site: base CSS, then the style settings.
	 *
	 * @param array<string, mixed> $design
	 */
	public static function css( array $design ): string {
		$max = self::mobile_max( $design );

		return self::base_css( $design, $max ) . self::styles_css( $design, $max );
	}
}
