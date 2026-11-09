<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Navigation;

/**
 * The site's navigation style and the Bricks headers that hold a Leapfrog
 * navigation (a Nav (Nestable) element with the class "lfn").
 *
 * - Saved style: option lf_navigation (not autoloaded).
 * - Unsaved changes: option lf_navigation_draft; the live preview shows them.
 * - History: the last 20 saved versions (lf_navigation_history).
 */
final class Store {
	public const OPTION       = 'lf_navigation';
	public const DRAFT        = 'lf_navigation_draft';
	public const HISTORY      = 'lf_navigation_history';
	public const HISTORY_KEEP = 20;

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function elements( int $id ): array {
		$data = get_post_meta( $id, defined( 'BRICKS_DB_PAGE_HEADER' ) ? BRICKS_DB_PAGE_HEADER : '_bricks_page_header_2', true );

		return is_array( $data ) ? array_values( array_filter( $data, 'is_array' ) ) : array();
	}

	/**
	 * Whether a header template holds a Leapfrog navigation.
	 */
	public static function has_navigation( int $id ): bool {
		foreach ( self::elements( $id ) as $element ) {
			if ( 'nav-nested' === ( $element['name'] ?? '' ) && preg_match( '/(^|\s)lfn(\s|$)/', (string) ( $element['settings']['_cssClasses'] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Bricks header templates (not in the bin), with whether each holds a Leapfrog navigation.
	 *
	 * @return list<array{id: int, title: string, status: string, navigation: bool}>
	 */
	public static function headers(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'bricks_template',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => '_bricks_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'header', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		return array_map(
			static fn( \WP_Post $post ): array => array(
				'id'         => (int) $post->ID,
				'title'      => $post->post_title ?: '#' . $post->ID,
				'status'     => $post->post_status,
				'navigation' => self::has_navigation( (int) $post->ID ),
			),
			$posts
		);
	}

	public static function is_header( int $id ): bool {
		return $id > 0 && 'bricks_template' === get_post_type( $id ) && 'header' === get_post_meta( $id, '_bricks_template_type', true ) && 'trash' !== get_post_status( $id );
	}

	/**
	 * Whether another published header template already has display conditions.
	 */
	public static function other_headers_have_conditions( int $except ): bool {
		foreach ( self::headers() as $header ) {
			if ( $header['id'] === $except || 'publish' !== $header['status'] ) {
				continue;
			}
			$settings = get_post_meta( $header['id'], '_bricks_template_settings', true );
			if ( is_array( $settings ) && ! empty( $settings['templateConditions'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function saved(): ?array {
		$saved = get_option( self::OPTION, null );

		return is_array( $saved ) ? Design::clean( $saved ) : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function draft(): ?array {
		$draft = get_option( self::DRAFT, null );

		return is_array( $draft ) ? Design::clean( $draft ) : null;
	}

	/**
	 * Unsaved changes, else the saved style, else the starting style.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array {
		return self::draft() ?? self::saved() ?? Design::clean( array() );
	}

	/**
	 * What the site uses: the saved style, else the starting style.
	 *
	 * @return array<string, mixed>
	 */
	public static function live(): array {
		return self::saved() ?? Design::clean( array() );
	}

	/**
	 * @param array<string, mixed> $design A cleaned design.
	 * @return array{reload: bool}
	 */
	public static function save_draft( array $design ): array {
		$previous = self::current();
		update_option( self::DRAFT, $design, false );

		// The mobile settings change the base CSS and the script's breakpoint: the preview reloads.
		return array( 'reload' => $previous['mobile'] !== $design['mobile'] );
	}

	public static function discard_draft(): void {
		delete_option( self::DRAFT );
	}

	/**
	 * @param array<string, mixed> $design A cleaned design.
	 */
	public static function save( array $design ): void {
		$previous = get_option( self::OPTION, null );
		if ( is_array( $previous ) ) {
			$list = get_option( self::HISTORY, array() );
			$list = is_array( $list ) ? $list : array();
			$user = wp_get_current_user();
			array_unshift( $list, array( 'id' => wp_generate_uuid4(), 'time' => time(), 'user' => $user->exists() ? $user->display_name : '', 'design' => $previous ) );
			update_option( self::HISTORY, array_slice( $list, 0, self::HISTORY_KEEP ), false );
		}
		update_option( self::OPTION, $design, false );
		self::discard_draft();
	}
}
