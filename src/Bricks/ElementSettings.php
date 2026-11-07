<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use Throwable;

/**
 * Element settings (1.2): better defaults, six CSS properties Bricks has no controls
 * for, the Overflow field as a list and search in long dropdowns. Each group can be
 * switched off on Leapfrog's Settings screen.
 *
 * Everything goes through Bricks' own control filters, so values are ordinary Bricks
 * settings and Bricks writes the CSS. The new CSS controls only output CSS while
 * Leapfrog is active (their values stay saved either way).
 */
final class ElementSettings {
	public const OPTION = 'lf_element_settings';

	/** Switch => [label, description]. All on by default. */
	public const GROUPS = array(
		'defaults'          => array( 'Better defaults', 'Basic Text as p and images without captions (site-wide, through the theme style); new images as figure; new buttons without a link as button; multi-line heading text.' ),
		'backdropFilter'    => array( 'Backdrop filter', 'Style > CSS, beside Bricks’ filters: frosted-glass effects.' ),
		'backgroundClip'    => array( 'Background clip', 'Style > Background: border, padding or content box, or text.' ),
		'whiteSpace'        => array( 'White space', 'Style > Layout: nowrap, pre and the other white-space values.' ),
		'columns'           => array( 'Columns and breaks', 'Style > Layout: column count, width, gap, fill and rule, and break before, inside and after.' ),
		'contentVisibility' => array( 'Content visibility', 'Style > Layout: content-visibility and contain-intrinsic-size, for faster long pages.' ),
		'overflowList'      => array( 'Overflow as a list', 'Bricks’ Overflow field offers visible, hidden, clip, auto and scroll; other values can still be typed.' ),
		'dropdownSearch'    => array( 'Search in dropdowns', 'Bricks dropdowns with more than 8 options get a search box.' ),
	);

	private const SEARCH_FROM = 9;

	/** @var array<string, bool>|null */
	private static ?array $cache = null;

	/**
	 * @return array<string, bool>
	 */
	public static function get(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array();
			foreach ( array_keys( self::GROUPS ) as $key ) {
				self::$cache[ $key ] = ! is_array( $stored ) || ! array_key_exists( $key, $stored ) || (bool) $stored[ $key ];
			}
		}

		return self::$cache;
	}

	public static function on( string $group ): bool {
		return self::get()[ $group ] ?? false;
	}

	/**
	 * Saves the switches and sets (or clears) the two theme style defaults.
	 *
	 * @param mixed $given
	 * @return array{settings:array<string,bool>,themeStyle:array<string,mixed>}
	 */
	public static function save( $given ): array {
		$clean = array();
		foreach ( array_keys( self::GROUPS ) as $key ) {
			$clean[ $key ] = is_array( $given ) && array_key_exists( $key, $given ) ? (bool) $given[ $key ] : true;
		}
		update_option( self::OPTION, $clean, false );
		self::$cache = null;

		return array( 'settings' => self::get(), 'themeStyle' => self::apply_theme_style() );
	}

	/**
	 * Basic Text as p and images without captions, for every element not set by hand.
	 *
	 * @return array<string, mixed>
	 */
	public static function apply_theme_style(): array {
		$on = self::on( 'defaults' );
		try {
			return ThemeStyle::patch(
				array(
					'text-basic' => array( 'tag' => $on ? 'p' : null ),
					'image'      => array( 'caption' => $on ? 'none' : null ),
				)
			);
		} catch ( Throwable $error ) {
			return array( 'status' => 'error', 'message' => $error->getMessage() );
		}
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_element_filters' ), 999 );
		add_filter( 'bricks/element/render_attributes', array( $this, 'button_type' ), 30, 3 );
		add_action( 'admin_init', array( $this, 'first_run' ) );
	}

	/**
	 * The defaults are on from the start, so set the two theme style values once,
	 * the first time an administrator opens wp-admin after updating.
	 */
	public function first_run(): void {
		if ( get_option( self::OPTION . '_applied' ) || ! current_user_can( 'manage_options' ) || ! class_exists( '\Bricks\Abilities\Design' ) ) {
			return;
		}
		update_option( self::OPTION . '_applied', '1', false );
		self::apply_theme_style();
	}

	public function register_element_filters(): void {
		if ( ! class_exists( '\Bricks\Elements' ) || ! is_array( \Bricks\Elements::$elements ?? null ) ) {
			return;
		}
		foreach ( \Bricks\Elements::$elements as $element ) {
			$name = is_array( $element ) ? (string) ( $element['name'] ?? '' ) : '';
			if ( '' !== $name ) {
				add_filter( "bricks/elements/{$name}/controls", fn( $controls ) => is_array( $controls ) ? $this->controls( $name, $controls ) : $controls, 30 );
			}
		}
	}

	/**
	 * @param array<string, mixed> $controls
	 * @return array<string, mixed>
	 */
	public function controls( string $name, array $controls ): array {
		if ( self::on( 'defaults' ) ) {
			$controls = $this->defaults( $name, $controls );
		}
		if ( self::on( 'backdropFilter' ) && isset( $controls['_cssFilters'] ) ) {
			$controls = self::insert_after(
				$controls,
				'_cssFilters',
				array(
					// A text field: Bricks' "filters" control type always writes "filter:", whatever property it is given.
					'_lfBackdropFilter' => array(
						'tab'            => 'style',
						'group'          => $controls['_cssFilters']['group'] ?? '_css',
						'label'          => __( 'Backdrop filter', 'leapfrog-framework' ),
						'type'           => 'text',
						'hasVariables'   => true,
						'hasDynamicData' => false,
						'placeholder'    => 'blur(12px) saturate(150%)',
						'css'            => array( array( 'property' => 'backdrop-filter' ), array( 'property' => '-webkit-backdrop-filter' ) ),
						'description'    => __( 'Filters what is behind the element, for example blur(12px) for frosted glass. Needs a semi-transparent background.', 'leapfrog-framework' ),
					),
				)
			);
		}
		if ( self::on( 'backgroundClip' ) && isset( $controls['_background'] ) ) {
			$controls = self::insert_after(
				$controls,
				'_background',
				array(
					'_lfBackgroundClip' => array(
						'tab'     => 'style',
						'group'   => $controls['_background']['group'] ?? '_background',
						'label'   => __( 'Background clip', 'leapfrog-framework' ),
						'type'    => 'select',
						'inline'  => true,
						'options' => self::options( array( 'border-box', 'padding-box', 'content-box', 'text' ) ),
						'css'     => array( array( 'property' => 'background-clip' ), array( 'property' => '-webkit-background-clip' ) ),
						'description' => __( '"text" shows the background through the text: also set the text colour to transparent. For gradient text, Bricks’ Gradient control has "Apply to: text".', 'leapfrog-framework' ),
					),
				)
			);
		}

		$layout = array();
		if ( self::on( 'whiteSpace' ) ) {
			$layout['_lfWhiteSpace'] = array(
				'label'   => __( 'White space', 'leapfrog-framework' ),
				'type'    => 'select',
				'inline'  => true,
				'options' => self::options( array( 'normal', 'nowrap', 'pre', 'pre-wrap', 'pre-line', 'break-spaces' ) ),
				'css'     => array( array( 'property' => 'white-space' ) ),
			);
		}
		if ( self::on( 'contentVisibility' ) ) {
			$layout['_lfContentVisibility']   = array(
				'label'   => __( 'Content visibility', 'leapfrog-framework' ),
				'type'    => 'select',
				'inline'  => true,
				'options' => self::options( array( 'visible', 'auto', 'hidden' ) ),
				'css'     => array( array( 'property' => 'content-visibility' ) ),
				'description' => __( '"auto" lets the browser skip drawing this element until it is near the screen. Set a contain-intrinsic-size so the page doesn’t jump.', 'leapfrog-framework' ),
			);
			$layout['_lfContainIntrinsicSize'] = array(
				'label'          => __( 'Contain intrinsic size', 'leapfrog-framework' ),
				'type'           => 'text',
				'inline'         => true,
				'hasVariables'   => true,
				'hasDynamicData' => false,
				'placeholder'    => 'auto 600px',
				'css'            => array( array( 'property' => 'contain-intrinsic-size' ) ),
				'required'       => array( '_lfContentVisibility', '=', 'auto' ),
			);
		}
		if ( self::on( 'columns' ) ) {
			$layout['_lfColumnsSeparator'] = array(
				'type'  => 'separator',
				'label' => __( 'Columns', 'leapfrog-framework' ),
				'description' => __( 'Flows the content into newspaper-style columns (with display block).', 'leapfrog-framework' ),
			);
			$layout['_lfColumnCount'] = array(
				'label'       => __( 'Column count', 'leapfrog-framework' ),
				'type'        => 'number',
				'units'       => false,
				'min'         => 1,
				'inline'      => true,
				'css'         => array( array( 'property' => 'column-count' ) ),
			);
			$layout['_lfColumnWidth'] = array(
				'label'  => __( 'Column width', 'leapfrog-framework' ),
				'type'   => 'number',
				'units'  => true,
				'inline' => true,
				'css'    => array( array( 'property' => 'column-width' ) ),
			);
			if ( ! isset( $controls['_columnGap'] ) ) {
				$layout['_lfColumnGap'] = array(
					'label'  => __( 'Column gap', 'leapfrog-framework' ),
					'type'   => 'number',
					'units'  => true,
					'inline' => true,
					'css'    => array( array( 'property' => 'column-gap' ) ),
				);
			}
			$layout['_lfColumnFill'] = array(
				'label'   => __( 'Column fill', 'leapfrog-framework' ),
				'type'    => 'select',
				'inline'  => true,
				'options' => self::options( array( 'balance', 'auto' ) ),
				'css'     => array( array( 'property' => 'column-fill' ) ),
			);
			$layout['_lfColumnRule'] = array(
				'label'          => __( 'Column rule', 'leapfrog-framework' ),
				'type'           => 'text',
				'inline'         => true,
				'hasVariables'   => true,
				'hasDynamicData' => false,
				'placeholder'    => '1px solid var(--color-border)',
				'css'            => array( array( 'property' => 'column-rule' ) ),
			);
			$breaks = array( 'auto', 'avoid', 'column', 'avoid-column', 'page', 'avoid-page' );
			$layout['_lfBreakBefore'] = array( 'label' => __( 'Break before', 'leapfrog-framework' ), 'type' => 'select', 'inline' => true, 'options' => self::options( $breaks ), 'css' => array( array( 'property' => 'break-before' ) ) );
			$layout['_lfBreakInside'] = array( 'label' => __( 'Break inside', 'leapfrog-framework' ), 'type' => 'select', 'inline' => true, 'options' => self::options( array( 'auto', 'avoid', 'avoid-column', 'avoid-page' ) ), 'css' => array( array( 'property' => 'break-inside' ) ) );
			$layout['_lfBreakAfter']  = array( 'label' => __( 'Break after', 'leapfrog-framework' ), 'type' => 'select', 'inline' => true, 'options' => self::options( $breaks ), 'css' => array( array( 'property' => 'break-after' ) ) );
		}
		if ( $layout && isset( $controls['_overflow'] ) ) {
			foreach ( $layout as $key => $control ) {
				$layout[ $key ] = array( 'tab' => 'style', 'group' => $controls['_overflow']['group'] ?? '_layout' ) + $control;
			}
			$controls = self::insert_after( $controls, '_overflow', $layout );
		}

		if ( self::on( 'overflowList' ) && isset( $controls['_overflow'] ) && 'text' === ( $controls['_overflow']['type'] ?? '' ) ) {
			$controls['_overflow']['type']    = 'select';
			$controls['_overflow']['options'] = self::options( array( 'visible', 'hidden', 'clip', 'auto', 'scroll' ) );
			$controls['_overflow']['add']     = true; // Other values (e.g. "hidden auto") can still be typed.
		}

		if ( self::on( 'dropdownSearch' ) ) {
			foreach ( $controls as $key => $control ) {
				if ( is_array( $control ) && 'select' === ( $control['type'] ?? '' ) && ! array_key_exists( 'searchable', $control ) && count( (array) ( $control['options'] ?? array() ) ) >= self::SEARCH_FROM ) {
					$controls[ $key ]['searchable'] = true;
				}
			}
		}

		return $controls;
	}

	/**
	 * @param array<string, mixed> $controls
	 * @return array<string, mixed>
	 */
	private function defaults( string $name, array $controls ): array {
		if ( 'image' === $name && isset( $controls['tag'] ) ) {
			$controls['tag']['default'] = 'figure';
		}
		if ( 'button' === $name && isset( $controls['tag'] ) ) {
			$controls['tag']['default'] = 'button';
		}
		if ( 'heading' === $name && 'text' === ( $controls['text']['type'] ?? '' ) ) {
			$controls['text']['type'] = 'textarea';
		}

		return $controls;
	}

	/**
	 * Buttons rendered as <button> (no link) get type="button", so they never submit a form.
	 *
	 * @param array<string, mixed> $attributes
	 * @return array<string, mixed>
	 */
	public function button_type( array $attributes, string $key, object $element ): array {
		if ( '_root' !== $key || 'button' !== (string) ( $element->name ?? '' ) ) {
			return $attributes;
		}
		$settings = is_array( $element->settings ?? null ) ? $element->settings : array();
		if ( 'button' === strtolower( trim( (string) ( $settings['tag'] ?? '' ) ) ) && empty( $settings['link'] ) && empty( $attributes['_root']['type'] ) ) {
			$attributes['_root']['type'] = 'button';
		}

		return $attributes;
	}

	/**
	 * @param list<string> $values
	 * @return array<string, string>
	 */
	private static function options( array $values ): array {
		return array_combine( $values, $values );
	}

	/**
	 * @param array<string, mixed> $controls
	 * @param array<string, mixed> $add
	 * @return array<string, mixed>
	 */
	private static function insert_after( array $controls, string $anchor, array $add ): array {
		$out = array();
		foreach ( $controls as $key => $control ) {
			$out[ $key ] = $control;
			if ( $key === $anchor ) {
				foreach ( $add as $new_key => $new ) {
					if ( ! isset( $controls[ $new_key ] ) ) {
						$out[ $new_key ] = $new;
					}
				}
			}
		}

		return $out;
	}
}
