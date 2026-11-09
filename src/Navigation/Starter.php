<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Navigation;

use WP_Error;

/**
 * The starter navigation: a new Bricks header template holding a BricksExtras
 * Header Row with a logo and a Leapfrog navigation (Bricks Nav (Nestable)):
 * two links, a dropdown, a mega menu and a link styled as a button, plus the
 * mobile menu's top bar (Back, logo, close) and its open button.
 *
 * Created once; from then on its content is edited in Bricks. The parts carry
 * Leapfrog classes (lfn-*) so the Navigation Builder can style them and the
 * mobile menu script can find them. Bricks' own mobile menu is switched off
 * (mobileMenu "never"): the Leapfrog styles and script do the mobile menu.
 */
final class Starter {
	/** @var array<string, array<string, mixed>> */
	private array $elements = array();

	/**
	 * @return int|WP_Error The new template's id.
	 */
	public static function create( string $name ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return new WP_Error( 'lfn_name', __( 'Give the new header template a name.', 'leapfrog-framework' ), array( 'status' => 400 ) );
		}

		$post = wp_insert_post(
			array(
				'post_type'   => 'bricks_template',
				'post_status' => 'publish',
				'post_title'  => $name,
			),
			true
		);
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$self = new self();
		$self->build();
		update_post_meta( $post, '_bricks_template_type', 'header' );
		update_post_meta( $post, defined( 'BRICKS_DB_PAGE_HEADER' ) ? BRICKS_DB_PAGE_HEADER : '_bricks_page_header_2', array_values( $self->elements ) );

		// The whole site, unless another header already shows somewhere: then the conditions are left to Bricks.
		$settings = array();
		if ( ! Store::other_headers_have_conditions( (int) $post ) ) {
			$settings['templateConditions'] = array( array( 'id' => self::id(), 'main' => 'any' ) );
		}
		update_post_meta( $post, '_bricks_template_settings', $settings );

		return (int) $post;
	}

	/** A Bricks element id: 6 lower-case letters and digits, starting with a letter. */
	private static function id(): string {
		return chr( wp_rand( 97, 122 ) ) . strtolower( wp_generate_password( 5, false, false ) );
	}

	/**
	 * Adds an element and returns its id.
	 *
	 * @param array<string, mixed> $settings
	 */
	private function add( string $parent, string $name, array $settings, string $label = '' ): string {
		$id                    = self::id();
		$this->elements[ $id ] = array(
			'id'       => $id,
			'name'     => $name,
			'parent'   => '' === $parent ? 0 : $parent,
			'children' => array(),
			'settings' => $settings,
		);
		if ( '' !== $label ) {
			$this->elements[ $id ]['label'] = $label;
		}
		if ( '' !== $parent ) {
			$this->elements[ $parent ]['children'][] = $id;
		}

		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function link( string $url = '#' ): array {
		return array( 'type' => 'external', 'url' => $url );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function logo_settings( string $class ): array {
		$settings = array( '_cssClasses' => $class, 'logoText' => get_bloginfo( 'name' ) );
		$logo     = (int) get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			$settings['logo'] = array( 'id' => $logo, 'url' => (string) wp_get_attachment_url( $logo ), 'size' => 'full' );
		}

		return $settings;
	}

	private function build(): void {
		$row  = $this->add( '', 'xheaderrow', array(), 'Header row' );
		$wrap = $this->add(
			$row,
			'container',
			array(
				'tag'             => 'div',
				'_alignItems'     => 'center',
				'_direction'      => 'row',
				'_justifyContent' => 'space-between',
				'_hidden'         => array( '_cssClasses' => 'x-header_wrap' ),
			),
			'Header wrap'
		);
		$this->add( $wrap, 'logo', self::logo_settings( 'lfn-logo' ), 'Logo' );

		$nav = $this->add(
			$wrap,
			'nav-nested',
			array(
				'_cssClasses' => 'lfn',
				'mobileMenu'  => 'never',
				'ariaLabel'   => 'Main',
			),
			'Navigation (Leapfrog)'
		);

		// The panel: a row of links on desktop, the slide-in menu on mobile.
		$panel = $this->add( $nav, 'block', array( '_cssClasses' => 'lfn-panel' ), 'Menu panel' );

		$bar = $this->add( $panel, 'block', array( '_cssClasses' => 'lfn-bar' ), 'Mobile menu top bar' );
		$this->add(
			$bar,
			'button',
			array(
				'tag'          => 'button',
				'text'         => 'Back',
				'icon'         => array( 'library' => 'themify', 'icon' => 'ti-angle-left' ),
				'iconPosition' => 'left',
				'_cssClasses'  => 'lfn-back',
				'_attributes'  => array( array( 'id' => self::id(), 'name' => 'aria-label', 'value' => 'Back to the menu' ) ),
			),
			'Back'
		);
		$this->add( $bar, 'logo', self::logo_settings( 'lfn-bar-logo' ), 'Mobile menu logo' );
		$this->add(
			$bar,
			'toggle',
			array(
				'_cssClasses'    => 'lfn-close',
				'icon'           => array( 'library' => 'themify', 'icon' => 'ti-close' ),
				'ariaLabel'      => 'Close the menu',
				'toggleSelector' => '.lfn',
			),
			'Close (mobile)'
		);

		$items = $this->add(
			$panel,
			'block',
			array(
				'tag'         => 'ul',
				'_hidden'     => array( '_cssClasses' => 'brx-nav-nested-items' ),
				'_cssClasses' => 'lfn-items',
			),
			'Nav items'
		);

		$this->add( $items, 'text-link', array( 'text' => 'Link one', 'link' => self::link(), '_cssClasses' => 'lfn-link' ), 'Link' );
		$this->add( $items, 'text-link', array( 'text' => 'Link two', 'link' => self::link(), '_cssClasses' => 'lfn-link' ), 'Link' );

		// A dropdown of links.
		$dropdown = $this->add( $items, 'dropdown', array( 'text' => 'Dropdown', 'toggleOn' => 'both', '_cssClasses' => 'lfn-dropdown' ), 'Dropdown' );
		$list     = $this->add( $dropdown, 'div', array( 'tag' => 'ul', '_hidden' => array( '_cssClasses' => 'brx-dropdown-content' ) ), 'Dropdown content' );
		foreach ( array( 'Sub-link one', 'Sub-link two', 'Sub-link three' ) as $text ) {
			$this->add( $list, 'text-link', array( 'text' => $text, 'link' => self::link() ), 'Dropdown link' );
		}

		// A mega menu: rows with a title, a line of text and an arrow, and a call to action.
		$mega    = $this->add(
			$items,
			'dropdown',
			array(
				'text'             => 'Mega menu',
				'toggleOn'         => 'both',
				'megaMenu'         => true,
				'megaMenuSelector' => '#brx-header .x-header_wrap',
				'_cssClasses'      => 'lfn-dropdown lfn-mega',
			),
			'Mega menu'
		);
		$content = $this->add( $mega, 'div', array( '_hidden' => array( '_cssClasses' => 'brx-dropdown-content' ), '_cssClasses' => 'lfn-mega-content' ), 'Mega menu content' );
		$rows    = $this->add( $content, 'div', array( '_cssClasses' => 'lfn-mega-rows' ), 'Rows' );
		$samples = array(
			array( 'First option', 'A short line about what this page offers.' ),
			array( 'Second option', 'A short line about what this page offers.' ),
			array( 'Third option', 'A short line about what this page offers.' ),
			array( 'Fourth option', 'A short line about what this page offers.' ),
		);
		foreach ( $samples as $sample ) {
			$row_link = $this->add( $rows, 'block', array( 'tag' => 'a', 'link' => self::link(), '_cssClasses' => 'lfn-mega-row' ), 'Row' );
			$this->add( $row_link, 'heading', array( 'text' => $sample[0], 'tag' => 'span', '_cssClasses' => 'lfn-mega-title' ), 'Row title' );
			$line = $this->add( $row_link, 'div', array( '_cssClasses' => 'lfn-mega-line' ), 'Row text and arrow' );
			$this->add( $line, 'text-basic', array( 'text' => $sample[1], 'tag' => 'span', '_cssClasses' => 'lfn-mega-text' ), 'Row text' );
			$this->add( $line, 'icon', array( 'icon' => array( 'library' => 'themify', 'icon' => 'ti-arrow-right' ), '_cssClasses' => 'lfn-mega-arrow' ), 'Row arrow' );
		}
		$cta = $this->add( $content, 'div', array( '_cssClasses' => 'lfn-mega-cta' ), 'Call to action' );
		$this->add( $cta, 'heading', array( 'text' => 'Not sure where to start?', 'tag' => 'span', '_cssClasses' => 'lfn-mega-cta-title' ), 'Call to action title' );
		$this->add( $cta, 'text-basic', array( 'text' => 'Tell us what you need and we’ll point you the right way.', 'tag' => 'p', '_cssClasses' => 'lfn-mega-cta-text' ), 'Call to action text' );
		$this->add( $cta, 'text-link', array( 'text' => 'Talk to us', 'link' => self::link(), '_cssClasses' => 'lfn-mega-cta-link' ), 'Call to action link' );

		// The rightmost link, styled as a button.
		$this->add( $items, 'text-link', array( 'text' => 'Get in touch', 'link' => self::link(), '_cssClasses' => 'lfn-link lfn-btn' ), 'Button link' );

		// Opens the mobile menu (shown at the mobile breakpoint only).
		$this->add(
			$nav,
			'toggle',
			array(
				'_cssClasses'    => 'lfn-burger',
				'ariaLabel'      => 'Open the menu',
				'toggleSelector' => '.lfn',
			),
			'Open (mobile)'
		);
	}
}
