<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Header;

/**
 * The style of one Bricks header template: what the Header Builder edits.
 *
 * The header's content (rows, logo, links, dropdowns, mega menus, buttons) is
 * built in Bricks and never changed here. A design holds:
 * - scroll: sticky, over the hero (with the space left under the header) and
 *   hide on scroll down. These become the template's Bricks sticky settings.
 * - styles: values for the style settings below. Empty = not set, so Bricks'
 *   own styling of that part shows; a value set here wins over Bricks.
 *
 * Each style setting says which parts it styles and how (selectors and a CSS
 * property, or declarations per choice). css() turns a design into CSS; the
 * builder's live preview does the same with the same definitions.
 */
final class Design {
	/** Every rule starts here, so a value set in the builder wins over Bricks' own styling. */
	public const PREFIX = 'html body #brx-header.lfh';

	// The parts of a Bricks header the settings reach.
	private const LINKS       = array( '.brxe-nav-nested .brx-nav-nested-items > li > a', '.brxe-nav-nested .brx-nav-nested-items > li > .brx-submenu-toggle', '.brxe-nav-menu .bricks-nav-menu > li > a', '.brxe-nav-menu .bricks-nav-menu > li > .brx-submenu-toggle' );
	private const LINK_INNER  = array( '.brxe-nav-nested .brx-nav-nested-items > li > .brx-submenu-toggle > *', '.brxe-nav-menu .bricks-nav-menu > li > .brx-submenu-toggle > *' );
	private const LINK_LISTS  = array( '.brxe-nav-nested .brx-nav-nested-items', '.brxe-nav-menu .bricks-nav-menu' );
	private const CURRENT     = array( '.brxe-nav-nested .brx-nav-nested-items > li > a[aria-current="page"]', '.brxe-nav-menu .bricks-nav-menu > .current-menu-item > a', '.brxe-nav-menu .bricks-nav-menu > .current-menu-ancestor > .brx-submenu-toggle > *' );
	private const BUTTONS     = array( '.brxe-button' );
	private const ROW_INNER   = array( '> * > .brxe-container', '> * > .brxe-block', '> * > .brxe-div' );
	private const MOBILE_LIST = array( '.brxe-nav-nested.brx-open .brx-nav-nested-items' );
	private const MOBILE_LINK = array( '.brxe-nav-nested.brx-open .brx-nav-nested-items > li > a', '.brxe-nav-nested.brx-open .brx-nav-nested-items > li > .brx-submenu-toggle > *' );
	private const TOGGLES     = array( '.brxe-toggle' );

	/**
	 * The style settings for a header with these rows.
	 * Each: group, label, kind (color, size, font-size, weight, shadow, radius, duration, choice),
	 * selectors, and either property (+ optional format with {v}) or choices (value => declarations).
	 * suggest = the Leapfrog token value "Use Leapfrog's header tokens" fills in.
	 *
	 * @param list<array{id: string, label: string}> $rows The header's top-level rows, top first.
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( array $rows ): array {
		$f = static function ( string $group, string $label, string $kind, array $selectors, string $property, string $suggest = '', array $extra = array() ): array {
			return array( 'group' => $group, 'label' => $label, 'kind' => $kind, 'selectors' => $selectors, 'property' => $property, 'suggest' => $suggest ) + $extra;
		};
		$states = static function ( array $selectors, string $state ): array {
			$out = array();
			foreach ( $selectors as $selector ) {
				if ( 'hover' === $state ) {
					array_push( $out, $selector . ':hover', $selector . ':focus-visible', $selector . '.lfh-force-hover' );
				}
			}
			return $out;
		};
		$scrolled = static fn( array $selectors ): array => array_map( static fn( string $s ): string => '&.scrolling ' . $s, $selectors );
		$hero     = static fn( array $selectors ): array => array_map( static fn( string $s ): string => '&.lfh-overlay:not(.scrolling) ' . $s, $selectors );

		$fields = array(
			'text-color'  => $f( 'Whole header', 'Text colour', 'color', array( '&' ), 'color', 'var(--header-color, var(--color-text))' ),
			'side-space'  => $f( 'Whole header', 'Side padding inside rows', 'size', self::ROW_INNER, 'padding-inline', 'var(--gutter)' ),
			'part-gap'    => $f( 'Whole header', 'Space between parts in a row', 'size', self::ROW_INNER, 'column-gap', 'var(--space-m)' ),
			'speed'       => $f( 'Whole header', 'Change speed (scrolling, hover)', 'duration', array( '&' ), '--lfh-speed', 'var(--duration-normal)' ),
		);

		// The main row gets the header tokens as suggestions: the first, unless that one is a top bar.
		$main_index = ( count( $rows ) > 1 && preg_match( '/top|bar|notice|announce/i', (string) ( $rows[0]['label'] ?? '' ) ) ) ? 1 : 0;

		foreach ( $rows as $index => $row ) {
			$id    = (string) $row['id'];
			$sel   = array( '> [id="brxe-' . $id . '"]' );
			$group = 'Row ' . ( $index + 1 ) . ( '' !== $row['label'] ? ': ' . $row['label'] : '' );
			$main  = $index === $main_index;
			$fields[ "row:$id:height" ]          = $f( $group, 'Height', 'size', $sel, 'height', $main ? 'var(--header-height)' : '' );
			$fields[ "row:$id:bg" ]              = $f( $group, 'Background', 'color', $sel, 'background-color', $main ? 'var(--header-bg)' : '' );
			$fields[ "row:$id:color" ]           = $f( $group, 'Text colour', 'color', $sel, 'color' );
			$fields[ "row:$id:border" ]          = $f( $group, 'Bottom border colour', 'color', $sel, 'border-bottom', $main ? 'var(--header-border-color)' : '', array( 'format' => '1px solid {v}' ) );
			$fields[ "row:$id:scrolled-height" ] = $f( $group, 'Height when scrolled', 'size', $scrolled( array( '[id="brxe-' . $id . '"]' ) ), 'height' );
			$fields[ "row:$id:scrolled-bg" ]     = $f( $group, 'Background when scrolled', 'color', $scrolled( array( '[id="brxe-' . $id . '"]' ) ), 'background-color' );
			$fields[ "row:$id:scrolled-shadow" ] = $f( $group, 'Shadow when scrolled', 'shadow', $scrolled( array( '[id="brxe-' . $id . '"]' ) ), 'box-shadow', $main ? 'var(--shadow-s)' : '' );
			$fields[ "row:$id:when-scrolled" ]   = $f(
				$group,
				'When scrolled',
				'choice',
				$scrolled( array( '[id="brxe-' . $id . '"]' ) ),
				'',
				'',
				array( 'choices' => array( 'show' => array( 'Keep showing', '' ), 'hide' => array( 'Hide', 'height:0;min-height:0;padding-block:0;overflow:hidden;opacity:0;border-width:0' ) ) )
			);
		}

		return $fields + array(
			'hero-bg'            => $f( 'Over the hero', 'Row background', 'color', $hero( array( '> *' ) ), 'background-color', 'transparent' ),
			'hero-color'         => $f( 'Over the hero', 'Text colour', 'color', array( '&.lfh-overlay:not(.scrolling)' ), 'color', 'var(--color-on-primary)' ),
			'hero-link-color'    => $f( 'Over the hero', 'Menu link colour', 'color', $hero( array_merge( self::LINKS, self::LINK_INNER ) ), 'color' ),
			'hero-border'        => $f( 'Over the hero', 'Row border colour', 'color', $hero( array( '> *' ) ), 'border-color', 'transparent' ),

			'logo-width'         => $f( 'Logo', 'Width (image logo)', 'size', array( '.brxe-logo img' ), 'width', '160px' ),
			'logo-scrolled-width' => $f( 'Logo', 'Width when scrolled', 'size', $scrolled( array( '.brxe-logo img' ) ), 'width' ),
			'logo-size'          => $f( 'Logo', 'Text size (text logo)', 'font-size', array( '.brxe-logo' ), 'font-size', 'var(--text-l)' ),
			'logo-weight'        => $f( 'Logo', 'Text weight (text logo)', 'weight', array( '.brxe-logo' ), 'font-weight', 'var(--heading-weight)' ),
			'logo-color'         => $f( 'Logo', 'Text colour (text logo)', 'color', array( '.brxe-logo' ), 'color' ),

			'nav-gap'            => $f( 'Menu links', 'Space between links', 'size', self::LINK_LISTS, 'gap', 'var(--space-2xs)' ),
			'link-color'         => $f( 'Menu links', 'Colour', 'color', array_merge( self::LINKS, self::LINK_INNER ), 'color' ),
			'link-size'          => $f( 'Menu links', 'Text size', 'font-size', self::LINKS, 'font-size', 'var(--text-s)' ),
			'link-weight'        => $f( 'Menu links', 'Text weight', 'weight', self::LINKS, 'font-weight', 'var(--font-weight-medium)' ),
			'link-spacing'       => $f( 'Menu links', 'Letter spacing', 'size', self::LINKS, 'letter-spacing' ),
			'link-case'          => $f( 'Menu links', 'Capitals', 'choice', self::LINKS, '', '', array( 'choices' => array( '' => array( 'Not set', '' ), 'none' => array( 'As typed', 'text-transform:none' ), 'uppercase' => array( 'All capitals', 'text-transform:uppercase' ) ) ) ),
			'link-pad-block'     => $f( 'Menu links', 'Padding above and below', 'size', self::LINKS, 'padding-block', 'var(--space-3xs)' ),
			'link-pad-inline'    => $f( 'Menu links', 'Padding at the sides', 'size', self::LINKS, 'padding-inline', 'var(--space-2xs)' ),
			'link-radius'        => $f( 'Menu links', 'Corner rounding', 'radius', self::LINKS, 'border-radius', 'var(--radius-s)' ),
			'link-hover-color'   => $f( 'Menu links', 'Hover colour', 'color', array_merge( $states( self::LINKS, 'hover' ), array_map( static fn( string $s ): string => str_replace( ' > *', ':hover > *', $s ), self::LINK_INNER ) ), 'color', 'var(--color-link-hover)' ),
			'link-hover-bg'      => $f( 'Menu links', 'Hover background', 'color', $states( self::LINKS, 'hover' ), 'background-color' ),
			'link-current-color' => $f( 'Menu links', 'Current page colour', 'color', self::CURRENT, 'color' ),
			'link-current-mark'  => $f( 'Menu links', 'Current page marker', 'choice', self::CURRENT, '', '', array( 'choices' => array( '' => array( 'Not set', '' ), 'underline' => array( 'Underline', 'text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:0.45em' ), 'none' => array( 'None', 'text-decoration:none' ) ) ) ),
			'link-scrolled-color' => $f( 'Menu links', 'Colour when scrolled', 'color', $scrolled( array_merge( self::LINKS, self::LINK_INNER ) ), 'color' ),

			'btn-bg'             => $f( 'Buttons', 'Background', 'color', self::BUTTONS, 'background-color', 'var(--primary)' ),
			'btn-color'          => $f( 'Buttons', 'Text colour', 'color', self::BUTTONS, 'color', 'var(--color-on-primary)' ),
			'btn-border'         => $f( 'Buttons', 'Border colour', 'color', self::BUTTONS, 'border-color' ),
			'btn-hover-bg'       => $f( 'Buttons', 'Hover background', 'color', $states( self::BUTTONS, 'hover' ), 'background-color' ),
			'btn-hover-color'    => $f( 'Buttons', 'Hover text colour', 'color', $states( self::BUTTONS, 'hover' ), 'color' ),
			'btn-radius'         => $f( 'Buttons', 'Corner rounding', 'radius', self::BUTTONS, 'border-radius', 'var(--btn-radius)' ),
			'btn-pad-block'      => $f( 'Buttons', 'Padding above and below', 'size', self::BUTTONS, 'padding-block', 'var(--btn-padding-block)' ),
			'btn-pad-inline'     => $f( 'Buttons', 'Padding at the sides', 'size', self::BUTTONS, 'padding-inline', 'var(--btn-padding-inline)' ),
			'btn-size'           => $f( 'Buttons', 'Text size', 'font-size', self::BUTTONS, 'font-size', 'var(--btn-font-size)' ),
			'btn-weight'         => $f( 'Buttons', 'Text weight', 'weight', self::BUTTONS, 'font-weight', 'var(--btn-font-weight)' ),

			'mobile-bg'          => $f( 'Mobile menu', 'Panel background', 'color', self::MOBILE_LIST, 'background-color', 'var(--header-bg)' ),
			'mobile-link-color'  => $f( 'Mobile menu', 'Link colour', 'color', self::MOBILE_LINK, 'color' ),
			'mobile-link-size'   => $f( 'Mobile menu', 'Link text size', 'font-size', array( '.brxe-nav-nested.brx-open .brx-nav-nested-items > li > a' ), 'font-size', 'var(--text-l)' ),
			'mobile-gap'         => $f( 'Mobile menu', 'Space between links', 'size', self::MOBILE_LIST, 'gap' ),
			'burger-color'       => $f( 'Mobile menu', 'Menu and close icon colour', 'color', self::TOGGLES, 'color' ),
		);
	}

	/**
	 * A new design for a Bricks header template: nothing styled yet, and scrolling
	 * as the template is set up in Bricks now (so a first save changes nothing there).
	 *
	 * @param array<string, mixed> $settings The template's Bricks settings.
	 * @return array<string, mixed>
	 */
	public static function create( int $template_id, array $settings = array() ): array {
		$sticky = ! empty( $settings['headerSticky'] );

		return self::clean(
			array(
				'templateId' => $template_id,
				'scroll'     => array(
					'sticky'       => $sticky,
					// Bricks' sticky header without "on scroll" is fixed over the page.
					'overlay'      => $sticky && empty( $settings['headerStickyOnScroll'] ),
					'hideOnScroll' => $sticky && ! empty( $settings['headerStickySlideUpAfter'] ),
					'hideAfter'    => (int) ( $settings['headerStickySlideUpAfter'] ?? 300 ) ?: 300,
				),
			),
			array()
		);
	}

	/**
	 * A design with every field present and safe.
	 *
	 * @param array<string, mixed> $raw
	 * @param array<string, array<string, mixed>> $fields The header's style settings (fields()).
	 * @return array<string, mixed>
	 */
	public static function clean( array $raw, array $fields ): array {
		$scroll = is_array( $raw['scroll'] ?? null ) ? $raw['scroll'] : array();
		$bool   = static fn( $value, bool $default ): bool => null === $value ? $default : (bool) $value;

		$styles = array();
		$given  = is_array( $raw['styles'] ?? null ) ? $raw['styles'] : array();
		foreach ( $given as $key => $value ) {
			$key = (string) $key;
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			if ( isset( $fields[ $key ]['choices'] ) ) {
				if ( is_string( $value ) && isset( $fields[ $key ]['choices'][ $value ] ) && '' !== $value ) {
					$styles[ $key ] = $value;
				}
				continue;
			}
			$clean = self::clean_css_value( $value );
			if ( null !== $clean && '' !== $clean ) {
				$styles[ $key ] = $clean;
			}
		}

		$offset = self::clean_css_value( $scroll['overlayOffset'] ?? '' );

		return array(
			'templateId' => absint( $raw['templateId'] ?? 0 ),
			'scroll'     => array(
				'sticky'        => $bool( $scroll['sticky'] ?? null, false ),
				'overlay'       => $bool( $scroll['overlay'] ?? null, false ),
				'overlayOffset' => null === $offset ? '' : $offset,
				'hideOnScroll'  => $bool( $scroll['hideOnScroll'] ?? null, false ),
				'hideAfter'     => max( 50, min( 2000, (int) ( $scroll['hideAfter'] ?? 300 ) ) ),
			),
			'styles'     => $styles,
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
		// Brackets must balance, so a value can't close the rule it sits in.
		return substr_count( $value, '(' ) === substr_count( $value, ')' ) ? $value : null;
	}

	/**
	 * The CSS for a design. The builder's preview builds the same CSS from the same fields.
	 *
	 * @param array<string, mixed> $design
	 * @param array<string, array<string, mixed>> $fields
	 */
	public static function css( array $design, array $fields ): string {
		$css = '';
		foreach ( (array) ( $design['styles'] ?? array() ) as $key => $value ) {
			$field = $fields[ $key ] ?? null;
			if ( ! $field || '' === (string) $value ) {
				continue;
			}
			if ( isset( $field['choices'] ) ) {
				$declarations = (string) ( $field['choices'][ $value ][1] ?? '' );
			} else {
				$declarations = $field['property'] . ':' . str_replace( '{v}', (string) $value, $field['format'] ?? '{v}' );
			}
			if ( '' === $declarations ) {
				continue;
			}
			$selectors = array_map(
				static fn( string $s ): string => str_starts_with( $s, '&' ) ? self::PREFIX . substr( $s, 1 ) : self::PREFIX . ' ' . $s,
				(array) $field['selectors']
			);
			$css .= implode( ',', $selectors ) . '{' . $declarations . '}';
		}

		// Over the hero: the page starts this far down, so content without a hero isn't hidden behind the header.
		if ( ! empty( $design['scroll']['overlay'] ) && '' !== (string) ( $design['scroll']['overlayOffset'] ?? '' ) ) {
			$css .= 'html body #brx-content{padding-top:' . $design['scroll']['overlayOffset'] . '}';
		}

		return $css;
	}

	/**
	 * The Bricks template settings for the design's scroll behaviour. Other settings
	 * (conditions and anything else set in Bricks) are kept.
	 *
	 * @param array<string, mixed> $design
	 * @param array<string, mixed> $existing
	 * @return array<string, mixed>
	 */
	public static function template_settings( array $design, array $existing ): array {
		$settings = $existing;
		unset( $settings['headerSticky'], $settings['headerStickyOnScroll'], $settings['headerStickySlideUpAfter'] );
		$scroll = $design['scroll'];

		if ( $scroll['sticky'] ) {
			// Bricks: sticky = fixed at the top over the page; "on scroll" = sticky in the page flow.
			$settings['headerSticky'] = true;
			if ( ! $scroll['overlay'] ) {
				$settings['headerStickyOnScroll'] = true;
			}
			if ( $scroll['hideOnScroll'] ) {
				$settings['headerStickySlideUpAfter'] = (string) $scroll['hideAfter'];
			}
		}

		return $settings;
	}

	/**
	 * Whether two designs need different template settings (the preview then reloads).
	 *
	 * @param array<string, mixed> $a
	 * @param array<string, mixed> $b
	 */
	public static function same_scroll( array $a, array $b ): bool {
		return $a['scroll'] === $b['scroll'];
	}
}
