<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Admin;

use const Leapfrog\Framework\PATH;

/**
 * The shared "Leapfrog" admin menu.
 *
 * Every Leapfrog plugin carries a copy of this logic (Leapfrog Admin has it in
 * includes/menu.php), so the menu works with any one of them installed: whichever
 * plugin runs first adds the top-level link, each plugin adds its own sub-item,
 * and the sub-items are put in a fixed order. Keep the copies in step.
 */
final class LeapfrogMenu {
	public const SLUG  = 'leapfrog';
	// Admin first: clicking Leapfrog opens it with the sub-items expanded beneath.
	// (Framework is a full-screen editor without the sidebar, so it can't come first.)
	private const ORDER = array(
		'leapfrog-admin'       => 1,
		'leapfrog-framework'   => 2,
		'leapfrog-navigation'  => 3,
		'leapfrog-performance' => 4,
	);

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add' ), 9 );
		add_action( 'admin_menu', array( self::class, 'order' ), 999 );
	}

	public static function add(): void {
		global $admin_page_hooks;
		if ( ! empty( $admin_page_hooks[ self::SLUG ] ) ) {
			return;
		}
		// Not translated: WordPress builds sub-page hook names from this title.
		$hook = (string) add_menu_page( 'Leapfrog', 'Leapfrog', 'manage_options', self::SLUG, array( self::class, 'page' ), self::icon(), 59 );
		add_action( 'load-' . $hook, array( self::class, 'redirect' ) );
	}

	/**
	 * Drops WordPress's automatic "Leapfrog" sub-item and puts the plugins in order.
	 */
	public static function order(): void {
		global $submenu;
		if ( ! isset( $submenu[ self::SLUG ] ) ) {
			return;
		}
		$items = array();
		foreach ( array_values( $submenu[ self::SLUG ] ) as $index => $item ) {
			if ( self::SLUG !== $item[2] ) {
				$items[] = array( self::ORDER[ $item[2] ] ?? 50, $index, $item );
			}
		}
		sort( $items );
		$submenu[ self::SLUG ] = array_column( $items, 2 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( ! $submenu[ self::SLUG ] ) {
			remove_menu_page( self::SLUG );
		}
	}

	/**
	 * The top-level link goes to the first sub-item; this catches old or typed links.
	 */
	public static function redirect(): void {
		global $submenu, $_wp_real_parent_file;
		// WordPress moves the sub-items under the first one's slug once the menu is built.
		$items = $submenu[ self::SLUG ] ?? $submenu[ $_wp_real_parent_file[ self::SLUG ] ?? '' ] ?? array();
		$first = reset( $items );
		if ( $first && self::SLUG !== $first[2] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $first[2] ) );
			exit;
		}
	}

	public static function page(): void {
		echo '<div class="wrap"><h1>Leapfrog</h1><p>' . esc_html__( 'No Leapfrog screens are available to you.', 'leapfrog-framework' ) . '</p></div>';
	}

	/**
	 * The Leapfrog Media mark (outline) as a menu icon. WordPress recolours SVG menu
	 * icons to match the colour scheme, so it is drawn in one colour, with a square
	 * viewBox so the tall mark sits centred.
	 */
	public static function icon(): string {
		static $icon = null;
		if ( null === $icon ) {
			$svg  = (string) @file_get_contents( PATH . 'assets/brand/leapfrog-mark.svg' ); // phpcs:ignore
			$icon = preg_match( '/<path[^>]*\sd="([^"]+)"/', $svg, $m )
				? 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-189.5 0 801 801"><path fill="black" d="' . $m[1] . '"/></svg>' )
				: 'dashicons-admin-generic';
		}
		return $icon;
	}
}
