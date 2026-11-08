<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Header;

/**
 * Header designs, one per Bricks header template, and what the builder reads
 * from those templates.
 *
 * - Saved designs: option lf_headers (not autoloaded), by template id.
 * - Unsaved changes: option lf_header_drafts, by template id; the live preview shows them.
 * - History: the last 20 saved versions (lf_header_history).
 * Saving writes only the template's sticky settings; its elements are never changed.
 */
final class Store {
	public const OPTION       = 'lf_headers';
	public const DRAFTS       = 'lf_header_drafts';
	public const HISTORY      = 'lf_header_history';
	public const HISTORY_KEEP = 20;

	/**
	 * Bricks header templates (not in the bin).
	 *
	 * @return list<array{id: int, title: string, status: string}>
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
			static fn( \WP_Post $post ): array => array( 'id' => (int) $post->ID, 'title' => $post->post_title ?: '#' . $post->ID, 'status' => $post->post_status ),
			$posts
		);
	}

	public static function is_header( int $id ): bool {
		return $id > 0 && 'bricks_template' === get_post_type( $id ) && 'header' === get_post_meta( $id, '_bricks_template_type', true ) && 'trash' !== get_post_status( $id );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function elements( int $id ): array {
		$data = get_post_meta( $id, defined( 'BRICKS_DB_PAGE_HEADER' ) ? BRICKS_DB_PAGE_HEADER : '_bricks_page_header_2', true );

		return is_array( $data ) ? array_values( array_filter( $data, 'is_array' ) ) : array();
	}

	/**
	 * The header's top-level rows, top first, with a name to show.
	 *
	 * @return list<array{id: string, label: string, name: string}>
	 */
	public static function rows( int $id ): array {
		$names = array( 'xheaderrow' => 'Header Row', 'section' => 'Section', 'container' => 'Container', 'block' => 'Block', 'div' => 'Div', 'xnotificationbar' => 'Notification Bar' );
		$rows  = array();
		foreach ( self::elements( $id ) as $element ) {
			if ( empty( $element['parent'] ) && ! empty( $element['id'] ) && preg_match( '/^[a-z0-9]+$/i', (string) $element['id'] ) ) {
				$name   = (string) ( $element['name'] ?? '' );
				$rows[] = array(
					'id'    => (string) $element['id'],
					'label' => sanitize_text_field( (string) ( $element['label'] ?? '' ) ) ?: ( $names[ $name ] ?? $name ),
					'name'  => $name,
				);
			}
		}

		return $rows;
	}

	/**
	 * How many of each part the header has, so the builder shows only the settings that apply.
	 *
	 * @return array<string, int>
	 */
	public static function parts( int $id ): array {
		$parts = array( 'logo' => 0, 'nav' => 0, 'dropdown' => 0, 'button' => 0, 'toggle' => 0 );
		foreach ( self::elements( $id ) as $element ) {
			switch ( $element['name'] ?? '' ) {
				case 'logo':
					$parts['logo']++;
					break;
				case 'nav-nested':
				case 'nav-menu':
					$parts['nav']++;
					break;
				case 'dropdown':
					$parts['dropdown']++;
					break;
				case 'button':
					$parts['button']++;
					break;
				case 'toggle':
					$parts['toggle']++;
					break;
			}
		}

		return $parts;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( int $id ): array {
		return Design::fields( self::rows( $id ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function saved( int $id ): ?array {
		$all = get_option( self::OPTION, array() );

		return is_array( $all ) && isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? Design::clean( $all[ $id ] + array( 'templateId' => $id ), self::fields( $id ) ) : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function draft( int $id ): ?array {
		$drafts = get_option( self::DRAFTS, array() );

		return is_array( $drafts ) && isset( $drafts[ $id ] ) && is_array( $drafts[ $id ] ) ? Design::clean( $drafts[ $id ] + array( 'templateId' => $id ), self::fields( $id ) ) : null;
	}

	/**
	 * What the builder and the preview show: unsaved changes, else the saved design, else a new one.
	 *
	 * @return array<string, mixed>
	 */
	public static function current( int $id ): array {
		if ( $design = self::draft( $id ) ?? self::saved( $id ) ) {
			return $design;
		}
		$settings = get_post_meta( $id, '_bricks_template_settings', true );

		return Design::create( $id, is_array( $settings ) ? $settings : array() );
	}

	/**
	 * Stores unsaved changes.
	 *
	 * @param array<string, mixed> $design A cleaned design.
	 * @return array{scrollChanged: bool}
	 */
	public static function save_draft( array $design ): array {
		$id       = (int) $design['templateId'];
		$previous = self::current( $id );
		$drafts   = get_option( self::DRAFTS, array() );
		$drafts   = is_array( $drafts ) ? $drafts : array();
		$drafts[ $id ] = $design;
		update_option( self::DRAFTS, $drafts, false );

		return array( 'scrollChanged' => ! Design::same_scroll( $previous, $design ) );
	}

	public static function discard_draft( int $id ): void {
		$drafts = get_option( self::DRAFTS, array() );
		if ( is_array( $drafts ) && isset( $drafts[ $id ] ) ) {
			unset( $drafts[ $id ] );
			update_option( self::DRAFTS, $drafts, false );
		}
	}

	/**
	 * Saves a design and writes its sticky settings into the Bricks template.
	 *
	 * @param array<string, mixed> $design A cleaned design.
	 */
	public static function save( array $design ): void {
		$id  = (int) $design['templateId'];
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		if ( isset( $all[ $id ] ) && is_array( $all[ $id ] ) ) {
			self::push_history( $all[ $id ] );
		}
		$all[ $id ] = $design;
		update_option( self::OPTION, $all, false );
		self::discard_draft( $id );

		$settings = get_post_meta( $id, '_bricks_template_settings', true );
		update_post_meta( $id, '_bricks_template_settings', Design::template_settings( $design, is_array( $settings ) ? $settings : array() ) );
		clean_post_cache( $id );
	}

	/**
	 * @param array<string, mixed> $design
	 */
	private static function push_history( array $design ): void {
		$list = get_option( self::HISTORY, array() );
		$list = is_array( $list ) ? $list : array();
		$user = wp_get_current_user();
		array_unshift(
			$list,
			array(
				'id'     => wp_generate_uuid4(),
				'time'   => time(),
				'user'   => $user->exists() ? $user->display_name : '',
				'design' => $design,
			)
		);
		update_option( self::HISTORY, array_slice( $list, 0, self::HISTORY_KEEP ), false );
	}
}
