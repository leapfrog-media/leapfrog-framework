<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Scale;

/**
 * Component tokens: buttons, form fields, cards and the header.
 * Defaults point at the scales and roles, so most sites never change them.
 * Saved in the Bricks variable category "Components".
 */
final class Components {
	public const CATEGORY = array( 'id' => 'lfcomponents', 'name' => 'Components' );

	/**
	 * @return array<string, array{label:string,values:array<string,string>}>
	 */
	public static function groups(): array {
		return array(
			'buttons' => array(
				'label'  => 'Buttons',
				'values' => array(
					'btn-font'           => 'var(--font-body)',
					'btn-font-size'      => 'var(--body-size)',
					'btn-font-size-sm'   => 'var(--small-size)',
					'btn-font-size-lg'   => 'var(--lead-size)',
					'btn-font-weight'    => '600',
					'btn-line-height'    => '1.2',
					'btn-padding-block'  => 'var(--space-xs)',
					'btn-padding-inline' => 'var(--space-m)',
					'btn-gap'            => 'var(--space-2xs)',
					'btn-min-height'     => '44px',
					'btn-radius'         => 'var(--radius-m)',
					'btn-border-width'   => 'var(--border-thin)',
					'btn-icon-size'      => '1.25em',
					'btn-letter-spacing' => '0em',
					'btn-disabled-opacity' => 'var(--disabled-opacity)',
					'btn-focus-ring'     => 'var(--focus-ring)',
					'btn-transition'     => 'var(--duration-fast) var(--ease-out)',
				),
			),
			'fields'  => array(
				'label'  => 'Form fields',
				'values' => array(
					'field-padding-block'  => 'var(--space-xs)',
					'field-padding-inline' => 'var(--space-s)',
					'field-gap'            => 'var(--space-xs)',
					'field-radius'         => 'var(--radius-s)',
					'field-border-width'   => 'var(--border-thin)',
					'field-bg'             => 'var(--color-surface)',
					'field-color'          => 'var(--color-text)',
					'field-border-color'   => 'var(--color-border-strong)',
					'field-focus-color'    => 'var(--color-focus)',
					'field-height'         => 'var(--control-height)',
					'field-font-size'      => 'var(--body-size)',
					'field-placeholder-color' => 'var(--color-text-subtle)',
					'field-border-hover'   => 'var(--color-text-muted)',
					'field-border-error'   => 'var(--error)',
					'field-disabled-bg'    => 'var(--color-surface-muted)',
					'field-disabled-color' => 'var(--color-text-disabled)',
					'field-focus-ring'     => 'var(--focus-ring)',
					'textarea-min-height'  => '120px',
					'choice-size'          => '20px',
					'choice-gap'           => 'var(--inline-gap)',
					'form-gap'             => 'var(--space-m)',
					'form-help-size'       => 'var(--small-size)',
					'form-help-color'      => 'var(--color-text-muted)',
					'form-error-color'     => 'var(--error)',
				),
			),
			'cards'   => array(
				'label'  => 'Cards and media',
				'values' => array(
					'card-padding'      => 'var(--space-l)',
					'card-gap'          => 'var(--space-s)',
					'card-radius'       => 'var(--radius-l)',
					'card-bg'           => 'var(--color-surface)',
					'card-color'        => 'var(--color-text)',
					'card-border-color' => 'var(--color-border)',
					'card-shadow'       => 'var(--shadow-s)',
					'card-hover-shadow' => 'var(--shadow-m)',
					'media-radius'      => 'var(--radius-m)',
					'media-bg'          => 'var(--color-surface-muted)',
					'figure-gap'        => 'var(--space-xs)',
				),
			),
			'header'  => array(
				'label'  => 'Header',
				'values' => array(
					'header-height'        => '72px',
					'header-padding-block' => 'var(--space-s)',
					'header-bg'            => 'var(--color-canvas)',
					'header-color'         => 'var(--color-text)',
					'header-border-color'  => 'var(--color-border)',
				),
			),
			'nav'     => array(
				'label'  => 'Navigation and footer',
				'values' => array(
					'nav-gap'                 => 'var(--space-m)',
					'nav-link-size'           => 'var(--body-size)',
					'nav-link-weight'         => 'var(--font-weight-medium)',
					'nav-link-color'          => 'var(--color-text)',
					'nav-link-hover'          => 'var(--color-link)',
					'nav-link-active'         => 'var(--color-link)',
					'nav-link-padding-block'  => 'var(--space-2xs)',
					'nav-link-padding-inline' => 'var(--space-xs)',
					'submenu-bg'              => 'var(--color-surface-raised)',
					'submenu-padding'         => 'var(--space-xs)',
					'submenu-radius'          => 'var(--radius-m)',
					'submenu-shadow'          => 'var(--shadow-m)',
					'footer-bg'               => 'var(--color-surface-muted)',
					'footer-color'            => 'var(--color-text)',
					'footer-gap'              => 'var(--space-l)',
					'footer-space'            => 'var(--section-space-compact)',
				),
			),
			'overlays' => array(
				'label'  => 'Tabs, accordions, modals, tooltips and alerts',
				'values' => array(
					'tab-padding-block'   => 'var(--space-xs)',
					'tab-padding-inline'  => 'var(--space-s)',
					'tab-indicator-color' => 'var(--primary)',
					'tab-indicator-width' => 'var(--border-thick)',
					'disclosure-gap'      => 'var(--space-xs)',
					'disclosure-padding'  => 'var(--space-s)',
					'modal-width'         => '640px',
					'modal-padding'       => 'var(--space-l)',
					'modal-radius'        => 'var(--radius-l)',
					'modal-bg'            => 'var(--color-surface-raised)',
					'modal-shadow'        => 'var(--shadow-l)',
					'drawer-width'        => '400px',
					'popover-width'       => '320px',
					'popover-padding'     => 'var(--space-s)',
					'tooltip-bg'          => 'var(--color-surface-inverse)',
					'tooltip-color'       => 'var(--color-canvas)',
					'tooltip-padding'     => 'var(--space-3xs) var(--space-xs)',
					'alert-padding'       => 'var(--space-s)',
					'alert-radius'        => 'var(--radius-m)',
				),
			),
			'tables'  => array(
				'label'  => 'Tables and lists',
				'values' => array(
					'table-cell-padding-block'  => 'var(--space-xs)',
					'table-cell-padding-inline' => 'var(--space-s)',
					'table-border-color'        => 'var(--color-border)',
					'table-header-bg'           => 'var(--color-surface-muted)',
					'table-stripe-bg'           => 'var(--color-surface-muted)',
					'table-row-hover-bg'        => 'var(--color-surface-hover)',
					'marker-color'              => 'var(--primary)',
					'breadcrumb-gap'            => 'var(--space-2xs)',
					'pagination-gap'            => 'var(--space-2xs)',
				),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( self::groups() as $group ) {
			$out += $group['values'];
		}

		return $out;
	}

	/**
	 * @param mixed $given
	 * @return array<string, string>
	 */
	public static function sanitize( $given ): array {
		$out = self::defaults();
		foreach ( is_array( $given ) ? $given : array() as $name => $value ) {
			if ( ! isset( $out[ $name ] ) ) {
				continue;
			}
			$value = trim( (string) $value );
			if ( '' !== $value && strlen( $value ) <= 240 && ! preg_match( '/[;{}<>\\\\]|\/\*|url\s*\(|expression\s*\(/i', $value ) ) {
				$out[ $name ] = $value;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, string> $values
	 * @return list<array{name:string,value:string,category:string}>
	 */
	public static function variables( array $values ): array {
		$rows = array();
		foreach ( self::sanitize( $values ) as $name => $value ) {
			$rows[] = array( 'name' => $name, 'value' => $value, 'category' => self::CATEGORY['id'] );
		}

		return $rows;
	}
}
