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
				),
			),
			'cards'   => array(
				'label'  => 'Cards',
				'values' => array(
					'card-padding'      => 'var(--space-l)',
					'card-gap'          => 'var(--space-s)',
					'card-radius'       => 'var(--radius-l)',
					'card-bg'           => 'var(--color-surface)',
					'card-border-color' => 'var(--color-border)',
					'card-shadow'       => 'var(--shadow-s)',
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
