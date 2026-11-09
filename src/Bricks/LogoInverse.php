<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

/**
 * Makes the Logo element's "Logo inverse" work with BricksExtras Header Row.
 *
 * Bricks only swaps to the inverse logo for its own sticky header. A sticky Header Row
 * swaps instead to the image in the logo's data-x-logo-inverse attribute, which
 * BricksExtras leaves to be added by hand. This fills that attribute from the Logo
 * inverse field. An attribute added by hand still wins, and nothing is added when the
 * header template uses Bricks' own sticky header (Bricks swaps the logo itself there).
 */
final class LogoInverse {
	public function register(): void {
		add_filter( 'bricks/element/set_root_attributes', array( $this, 'attributes' ), 10, 2 );
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @param object               $element
	 * @return array<string, mixed>
	 */
	public function attributes( $attributes, $element ) {
		if ( ! is_array( $attributes ) || ! is_object( $element ) || ( $element->name ?? '' ) !== 'logo' || isset( $attributes['data-x-logo-inverse'] ) ) {
			return $attributes;
		}
		$inverse = $element->settings['logoInverse'] ?? array();
		if ( ! is_array( $inverse ) || self::bricks_sticky_header() ) {
			return $attributes;
		}
		$url = self::image_url( $inverse );
		if ( '' !== $url ) {
			$attributes['data-x-logo-inverse'] = esc_url( $url );
		}
		return $attributes;
	}

	/**
	 * The inverse logo's address: the chosen size of a Media Library image, or an external address.
	 *
	 * @param array<string, mixed> $image
	 */
	private static function image_url( array $image ): string {
		$id = absint( $image['id'] ?? 0 );
		if ( $id ) {
			if ( class_exists( '\Bricks\Helpers' ) && method_exists( '\Bricks\Helpers', 'resolve_attachment_id' ) ) {
				$id = absint( \Bricks\Helpers::resolve_attachment_id( $id ) );
			}
			$src = wp_get_attachment_image_src( $id, ! empty( $image['size'] ) ? (string) $image['size'] : 'full' );
			return ! empty( $src[0] ) ? (string) $src[0] : '';
		}
		return ! empty( $image['url'] ) && is_string( $image['url'] ) ? $image['url'] : '';
	}

	/** Whether the active header template uses Bricks' own sticky header. */
	private static function bricks_sticky_header(): bool {
		if ( ! class_exists( '\Bricks\Database' ) || ! class_exists( '\Bricks\Helpers' ) ) {
			return false;
		}
		$header = \Bricks\Database::$active_templates['header'] ?? 0;
		if ( ! $header ) {
			return false;
		}
		$settings = \Bricks\Helpers::get_template_settings( $header );
		return is_array( $settings ) && isset( $settings['headerSticky'] );
	}
}
