<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use const Leapfrog\Framework\FILE;
use const Leapfrog\Framework\VERSION;

/**
 * Adds flow-relative sizing controls (Inline Size, Block Size and their min/max)
 * beside Bricks' Width and Height controls on every element that has them.
 *
 * The physical controls stay registered, so existing values keep working. A
 * "Logical sizing only" switch at the top of the Sizing group hides the physical
 * ones in the builder; it changes visibility only. Ported from CoreBridge.
 */
final class LogicalSizing {
	private const OPTION       = 'lf_logical_sizing_only';
	private const AJAX_ACTION  = 'lf_set_logical_sizing_only';
	private const NONCE_ACTION = 'lf_logical_sizing_preference';

	private const CONTROLS = array(
		'_inlineSize'    => array( 'anchor' => '_width', 'label' => 'Inline size', 'property' => 'inline-size', 'physical' => 'Width' ),
		'_inlineSizeMin' => array( 'anchor' => '_widthMin', 'label' => 'Min. inline size', 'property' => 'min-inline-size', 'physical' => 'Min. width' ),
		'_inlineSizeMax' => array( 'anchor' => '_widthMax', 'label' => 'Max. inline size', 'property' => 'max-inline-size', 'physical' => 'Max. width' ),
		'_blockSize'     => array( 'anchor' => '_height', 'label' => 'Block size', 'property' => 'block-size', 'physical' => 'Height' ),
		'_blockSizeMin'  => array( 'anchor' => '_heightMin', 'label' => 'Min. block size', 'property' => 'min-block-size', 'physical' => 'Min. height' ),
		'_blockSizeMax'  => array( 'anchor' => '_heightMax', 'label' => 'Max. block size', 'property' => 'max-block-size', 'physical' => 'Max. height' ),
	);

	public function register(): void {
		add_action( 'init', array( $this, 'register_element_filters' ), 998 );
		add_action( 'wp_enqueue_scripts', array( $this, 'builder_assets' ), 40 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'save_preference' ) );
	}

	/**
	 * Uses Bricks' live element registry, so any element with Width/Height controls gets the logical ones.
	 */
	public function register_element_filters(): void {
		if ( ! class_exists( '\Bricks\Elements' ) || ! is_array( \Bricks\Elements::$elements ?? null ) ) {
			return;
		}
		foreach ( \Bricks\Elements::$elements as $element ) {
			$name = is_array( $element ) ? (string) ( $element['name'] ?? '' ) : '';
			if ( '' !== $name ) {
				add_filter( "bricks/elements/{$name}/controls", array( $this, 'controls' ), 25 );
			}
		}
	}

	/**
	 * @param array<string, mixed> $controls
	 * @return array<string, mixed>
	 */
	public function controls( array $controls ): array {
		foreach ( self::CONTROLS as $key => $def ) {
			$anchor = $def['anchor'];
			if ( isset( $controls[ $key ] ) || ! is_array( $controls[ $anchor ] ?? null ) ) {
				continue;
			}
			$physical = $controls[ $anchor ];
			$control  = array(
				'tab'   => (string) ( $physical['tab'] ?? 'style' ),
				'group' => (string) ( $physical['group'] ?? '_layout' ),
				'label' => $def['label'],
				'type'  => 'number',
				'units' => $physical['units'] ?? true,
				'css'   => $this->logical_css( $physical, $def['property'] ),
				'info'  => sprintf(
					/* translators: %s: physical control label, e.g. Width */
					__( 'Flow-relative version of %s. Avoid setting both on the same element and breakpoint.', 'leapfrog-framework' ),
					$def['physical']
				),
			);
			if ( isset( $physical['required'] ) ) {
				$control['required'] = $physical['required'];
			}

			$next = array();
			foreach ( $controls as $existing_key => $existing ) {
				$next[ $existing_key ] = $existing;
				if ( $existing_key === $anchor ) {
					$next[ $key ] = $control;
				}
			}
			$controls = $next;
		}

		return $controls;
	}

	public function builder_assets(): void {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}
		$url = plugin_dir_url( FILE ) . 'assets/builder/';
		wp_enqueue_style( 'leapfrog-logical-sizing', $url . 'logical-sizing.css', array(), VERSION );
		wp_enqueue_script( 'leapfrog-logical-sizing', $url . 'logical-sizing.js', array(), VERSION, true );
		wp_add_inline_script(
			'leapfrog-logical-sizing',
			'window.leapfrogLogicalSizing = ' . wp_json_encode(
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'action'    => self::AJAX_ACTION,
					'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
					'enabled'   => '1' === (string) get_option( self::OPTION, '0' ),
					'canManage' => current_user_can( 'manage_options' ),
				)
			) . ';',
			'before'
		);
	}

	public function save_preference(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change this setting.', 'leapfrog-framework' ) ), 403 );
		}
		$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
		update_option( self::OPTION, $enabled ? '1' : '0', false );
		wp_send_json_success( array( 'enabled' => $enabled ) );
	}

	/**
	 * The physical control's CSS rules with the property swapped for its logical one.
	 *
	 * @param array<string, mixed> $physical
	 * @return list<array<string, mixed>>
	 */
	private function logical_css( array $physical, string $property ): array {
		$out = array();
		foreach ( is_array( $physical['css'] ?? null ) ? $physical['css'] : array() as $rule ) {
			if ( is_array( $rule ) ) {
				$rule['property'] = $property;
				$out[]            = $rule;
			}
		}

		return $out ? $out : array( array( 'property' => $property, 'selector' => '' ) );
	}
}
