<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use RuntimeException;

/**
 * Keeps one Bricks colour palette equal to the generated rows: "Leapfrog"
 * (brand colours) by default, or "Leapfrog roles" (colour roles) via roles().
 *
 * Rows are matched by CSS variable name, which never changes (Bricks gives new
 * colours random IDs). The first save creates the whole palette in one write;
 * later saves create, update and delete single colours, each through Bricks'
 * ownership-checked methods with a fresh read before every write.
 */
final class PaletteSync {
	public const PALETTE_NAME = 'Leapfrog';
	public const ROLES_NAME   = 'Leapfrog roles';

	private BricksGateway $gateway;
	private string $name;
	private string $id_option;

	/** @var array{created:int,updated:int,deleted:int,unchanged:int} */
	private array $summary = array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0 );

	public function __construct( ?BricksGateway $gateway = null, string $name = self::PALETTE_NAME, string $id_option = 'lf_bricks_palette_id' ) {
		$this->gateway   = $gateway ?? new BricksGateway();
		$this->name      = $name;
		$this->id_option = $id_option;
	}

	/**
	 * The palette for colour roles.
	 */
	public static function roles( ?BricksGateway $gateway = null ): self {
		return new self( $gateway, self::ROLES_NAME, 'lf_bricks_roles_palette_id' );
	}

	/**
	 * @param array<string, array<string, mixed>> $rows Desired rows from Palette::bricks_rows().
	 * @return array{created:int,updated:int,deleted:int,unchanged:int,paletteId:string}
	 */
	public function sync( array $rows ): array {
		$state = $this->read();

		if ( null === $state['palette'] ) {
			$this->create_palette( $rows, $state['ownership'] );
		} else {
			$this->reconcile( $rows );
		}

		CssFiles::rebuild();

		$final = $this->read();

		return $this->summary + array( 'paletteId' => (string) ( $final['palette']['id'] ?? '' ) );
	}

	/**
	 * Current Leapfrog palette (or null) plus the resource ownership for the next write.
	 *
	 * @return array{palette:?array<string,mixed>,ownership:array<string,mixed>}
	 */
	public function read(): array {
		$result    = $this->gateway->execute( 'bricks/list-color-palettes', array( 'perPage' => 100 ) );
		$palettes  = is_array( $result['items'] ?? null ) ? $result['items'] : array();
		$stored_id = (string) get_option( $this->id_option, '' );
		$found     = null;

		foreach ( $palettes as $palette ) {
			if ( '' !== $stored_id && ( $palette['id'] ?? '' ) === $stored_id ) {
				$found = $palette;
				break;
			}
		}
		if ( null === $found ) {
			foreach ( $palettes as $palette ) {
				if ( ( $palette['name'] ?? '' ) === $this->name ) {
					$found = $palette;
					break;
				}
			}
		}
		if ( $found && ( $found['id'] ?? '' ) !== $stored_id ) {
			update_option( $this->id_option, (string) $found['id'], false );
		}

		if ( ! is_array( $result['ownership'] ?? null ) ) {
			throw new RuntimeException( 'Bricks did not return palette ownership.' );
		}

		return array( 'palette' => $found, 'ownership' => $result['ownership'] );
	}

	/**
	 * First save: the whole palette in one write, with stable IDs.
	 *
	 * @param array<string, array<string, mixed>> $rows
	 * @param array<string, mixed>                $ownership
	 */
	private function create_palette( array $rows, array $ownership ): void {
		$colors = array();
		foreach ( $rows as $row ) {
			$colors[] = $this->row_to_color( $row, self::stable_id( $row['root'] ? $row['raw'] : $row['parent'] ), $row['root'] ? null : self::stable_id( $row['raw'] ) );
		}

		$result = $this->gateway->execute(
			'bricks/create-color-palette',
			array(
				'name'              => $this->name,
				'colors'            => $colors,
				'expectedOwnership' => $ownership,
			)
		);

		update_option( $this->id_option, (string) ( $result['palette']['id'] ?? '' ), false );
		$this->summary['created'] = count( $colors );
	}

	/**
	 * Later saves: delete what's gone, then create or update roots, then shades.
	 *
	 * @param array<string, array<string, mixed>> $rows
	 */
	private function reconcile( array $rows ): void {
		// 1. Delete colours that are no longer wanted (shades first; deleting a root also removes its shades).
		foreach ( array( false, true ) as $roots ) {
			foreach ( $this->existing()['colors'] as $key => $color ) {
				$is_root = empty( $color['parent'] ) && empty( $color['type'] );
				if ( $is_root !== $roots ) {
					continue;
				}
				if ( ! $this->wanted( $key, $rows ) ) {
					$this->delete( $key );
				}
			}
		}

		// 2. Roots, then 3. shades (shades need their parent's ID).
		foreach ( array( true, false ) as $roots ) {
			foreach ( $rows as $row ) {
				if ( (bool) $row['root'] !== $roots ) {
					continue;
				}
				$this->upsert( $row );
			}
		}
	}

	/**
	 * Existing colours keyed "root:name" or "shade:name:light|dark", with fresh ownership.
	 *
	 * @return array{paletteId:string,ownership:array<string,mixed>,colors:array<string,array<string,mixed>>,rootIds:array<string,string>}
	 */
	private function existing(): array {
		$state   = $this->read();
		$palette = $state['palette'];
		if ( null === $palette ) {
			throw new RuntimeException( 'The Leapfrog palette disappeared from Bricks during the save. Please save again.' );
		}

		$colors   = array();
		$root_ids = array();
		foreach ( (array) ( $palette['colors'] ?? array() ) as $color ) {
			$name = self::variable_name( (string) ( $color['raw'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$is_root = empty( $color['parent'] ) && empty( $color['type'] );
			if ( $is_root ) {
				$colors[ "root:$name" ] = $color;
				$root_ids[ $name ]      = (string) $color['id'];
			} else {
				$mode                            = isset( $color['light'] ) && '' !== $color['light'] ? 'light' : 'dark';
				$colors[ "shade:$name:$mode" ] = $color;
			}
		}

		return array(
			'paletteId' => (string) $palette['id'],
			'ownership' => $state['ownership'],
			'colors'    => $colors,
			'rootIds'   => $root_ids,
		);
	}

	/**
	 * @param array<string, array<string, mixed>> $rows
	 */
	private function wanted( string $key, array $rows ): bool {
		if ( str_starts_with( $key, 'root:' ) ) {
			$name = substr( $key, 5 );
			return isset( $rows[ $name ] ) && $rows[ $name ]['root'];
		}
		// Leapfrog only writes light-mode shade rows.
		if ( ! str_ends_with( $key, ':light' ) ) {
			return false;
		}
		$name = substr( $key, 6, -6 );

		return isset( $rows[ $name ] ) && ! $rows[ $name ]['root'];
	}

	private function delete( string $key ): void {
		$current = $this->existing();
		$color   = $current['colors'][ $key ] ?? null;
		if ( ! $color ) {
			return; // Already gone (for example removed with its root).
		}

		$this->gateway->execute(
			'bricks/delete-color',
			array(
				'colorId'           => (string) $color['id'],
				'paletteId'         => $current['paletteId'],
				'expectedOwnership' => $color['itemOwnership'],
				'allowOrphans'      => true,
			)
		);
		++$this->summary['deleted'];
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function upsert( array $row ): void {
		$current = $this->existing();
		$key     = $row['root'] ? 'root:' . $row['raw'] : 'shade:' . $row['raw'] . ':light';
		$color   = $current['colors'][ $key ] ?? null;

		$parent_id = null;
		if ( ! $row['root'] ) {
			$parent_id = $current['rootIds'][ $row['parent'] ] ?? null;
			if ( null === $parent_id ) {
				throw new RuntimeException( sprintf( 'Bricks has no parent colour for --%s.', $row['raw'] ) );
			}
		}

		$wanted = $this->row_to_color( $row, $parent_id );
		unset( $wanted['id'] );

		if ( null === $color ) {
			$this->gateway->execute(
				'bricks/create-color',
				array_merge( $wanted, array( 'paletteId' => $current['paletteId'], 'expectedOwnership' => $current['ownership'] ) )
			);
			++$this->summary['created'];
			return;
		}

		if ( $this->same( $color, $wanted ) ) {
			++$this->summary['unchanged'];
			return;
		}

		unset( $wanted['raw'] );
		if ( $row['root'] && null === $row['dark'] ) {
			// Turning dark mode off also removes dark values.
			unset( $wanted['dark'] );
			$wanted['darkModeEnabled'] = false;
		}

		$this->gateway->execute(
			'bricks/update-color',
			array(
				'colorId'           => (string) $color['id'],
				'paletteId'         => $current['paletteId'],
				'color'             => $wanted,
				'expectedOwnership' => $color['itemOwnership'],
			)
		);
		++$this->summary['updated'];
	}

	/**
	 * @param array<string, mixed> $existing Bricks colour.
	 * @param array<string, mixed> $wanted   Desired colour.
	 */
	private function same( array $existing, array $wanted ): bool {
		foreach ( array( 'light', 'dark', 'parent', 'type', 'index' ) as $field ) {
			$a = $existing[ $field ] ?? null;
			$b = $wanted[ $field ] ?? null;
			if ( (string) $a !== (string) $b ) {
				return false;
			}
		}
		$has_dark_mode = ! empty( $existing['darkModeEnabled'] ) || ! empty( $existing['dark'] );

		return $has_dark_mode === ! empty( $wanted['darkModeEnabled'] );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function row_to_color( array $row, ?string $parent_or_own_id, ?string $shade_id = null ): array {
		if ( $row['root'] ) {
			$color = array(
				'id'    => (string) $parent_or_own_id,
				'raw'   => 'var(--' . $row['raw'] . ')',
				'light' => $row['light'],
			);
			if ( null !== $row['dark'] ) {
				$color['dark']            = $row['dark'];
				$color['darkModeEnabled'] = true;
			}

			return $color;
		}

		return array(
			'id'     => (string) $shade_id,
			'raw'    => 'var(--' . $row['raw'] . ')',
			'light'  => $row['light'],
			'parent' => (string) $parent_or_own_id,
			'type'   => $row['type'],
			'index'  => (int) $row['index'],
		);
	}

	private static function stable_id( string $name ): string {
		return 'lf' . substr( md5( 'leapfrog:' . $name ), 0, 8 );
	}

	private static function variable_name( string $raw ): string {
		return preg_match( '/var\(\s*--([\w-]+)\s*\)/', $raw, $m ) ? $m[1] : '';
	}
}
