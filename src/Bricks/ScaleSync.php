<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

use Leapfrog\Framework\Scale\Scales;
use RuntimeException;

/**
 * Writes the type and space scales as Bricks scale categories, lets Bricks
 * generate their clamp() values, and writes the role variables.
 *
 * Bricks refuses to change a scale category's settings while variables belong
 * to it (it can't regenerate them in the same write). So a settings change
 * runs in four guarded steps, the same order Bricks' own site foundation uses:
 *   1. move the scale's variables out of the category (values unchanged),
 *   2. save the categories,
 *   3. ask Bricks to generate the scale,
 *   4. write the generated variables back into the category, keeping their IDs.
 * The CSS keeps working between steps; a failed save is fixed by saving again.
 */
final class ScaleSync {
	private BricksGateway $gateway;

	/** @var array{created:int,updated:int,deleted:int,unchanged:int} */
	private array $summary = array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0 );

	public function __construct( ?BricksGateway $gateway = null ) {
		$this->gateway = $gateway ?? new BricksGateway();
	}

	/**
	 * The scale settings as Bricks holds them now (null when Leapfrog hasn't created them yet).
	 *
	 * @return array{typography:?array<string,mixed>,spacing:?array<string,mixed>}
	 */
	public function read(): array {
		$snapshot = $this->gateway->list_all_variables();
		$out      = array( 'typography' => null, 'spacing' => null );
		foreach ( $snapshot['categories'] as $category ) {
			if ( ( $category['id'] ?? '' ) === Scales::TYPE_CATEGORY['id'] ) {
				$out['typography'] = Scales::from_bricks_category( $category );
			}
			if ( ( $category['id'] ?? '' ) === Scales::SPACE_CATEGORY['id'] ) {
				$out['spacing'] = Scales::from_bricks_category( $category );
			}
		}

		return $out;
	}

	/**
	 * Bricks' own generation for a scale, without saving (for the editor preview).
	 *
	 * @param array<string, mixed>  $scale
	 * @param array<string, string> $category
	 * @return array{variables:list<array<string,mixed>>,basis:array<string,mixed>}
	 */
	public function preview( array $scale, array $category ): array {
		$result = $this->gateway->execute(
			'bricks/generate-scale-variables',
			array(
				'category'   => Scales::bricks_category( $scale, $category ),
				'scaleRange' => Scales::range( $scale['steps'] ),
			)
		);

		return array(
			'variables' => (array) ( $result['variables'] ?? array() ),
			'basis'     => (array) ( $result['basis'] ?? array() ),
		);
	}

	/**
	 * @param array<string, mixed> $typography Leapfrog typography settings.
	 * @param array<string, mixed> $spacing    Leapfrog spacing settings.
	 * @return array{created:int,updated:int,deleted:int,unchanged:int}
	 */
	public function sync( array $typography, array $spacing ): array {
		$scales = array(
			array( $typography, Scales::TYPE_CATEGORY ),
			array( $spacing, Scales::SPACE_CATEGORY ),
		);

		$this->save_categories( $scales );

		foreach ( $scales as [ $scale, $category ] ) {
			$this->write_scale( $scale, $category );
		}

		$this->write_roles( Scales::role_variables( $typography, $spacing ) );

		CssFiles::rebuild();

		return $this->summary;
	}

	/**
	 * Steps 1 and 2: make the four Leapfrog categories match, moving variables out of
	 * any scale whose settings change.
	 *
	 * @param list<array{0:array<string,mixed>,1:array<string,string>}> $scales
	 */
	private function save_categories( array $scales ): void {
		$snapshot = $this->gateway->list_all_variables();
		$current  = array();
		foreach ( $snapshot['categories'] as $category ) {
			$current[ (string) $category['id'] ] = $category;
		}

		$wanted = array();
		foreach ( $scales as [ $scale, $category ] ) {
			$wanted[ $category['id'] ] = Scales::bricks_category( $scale, $category );
		}
		foreach ( array( Scales::TYPE_ROLES, Scales::SPACE_ROLES ) as $roles ) {
			$wanted[ $roles['id'] ] = array( 'id' => $roles['id'], 'name' => $roles['name'] );
		}

		$changed_scales = array();
		$any_change     = false;
		foreach ( $wanted as $id => $category ) {
			$before = $current[ $id ] ?? null;
			if ( null === $before ) {
				$any_change = true;
				continue;
			}
			if ( ( $before['name'] ?? '' ) !== $category['name'] ) {
				$any_change = true;
			}
			if ( isset( $category['scale'] ) && $this->scale_differs( $before['scale'] ?? null, $category['scale'] ) ) {
				$changed_scales[] = $id;
				$any_change       = true;
			}
		}

		if ( ! $any_change ) {
			return;
		}

		// Step 1: park the variables of scales whose settings change (category '').
		$parked = array();
		foreach ( $snapshot['items'] as $item ) {
			if ( in_array( (string) ( $item['category'] ?? '' ), $changed_scales, true ) ) {
				$parked[] = self::clean_row( $item, '' );
			}
		}
		if ( $parked ) {
			$this->set_variables( $parked );
			$snapshot = $this->gateway->list_all_variables();
		}

		// Step 2: full category list, keeping everyone else's categories and fields.
		$list = array();
		foreach ( $snapshot['categories'] as $category ) {
			$id = (string) $category['id'];
			unset( $category['itemDigest'], $category['itemOwnership'] );
			if ( isset( $wanted[ $id ] ) ) {
				// Leapfrog's own categories are replaced outright (dropping manual values set in
				// Bricks); utility classes switched on in Bricks are kept.
				$next = $wanted[ $id ];
				if ( isset( $category['utilityClasses'] ) ) {
					$next['utilityClasses'] = $category['utilityClasses'];
				}
				$list[] = $next;
				unset( $wanted[ $id ] );
				continue;
			}
			$list[] = $category;
		}
		foreach ( $wanted as $category ) {
			$list[] = $category;
		}

		$this->gateway->execute(
			'bricks/set-global-variable-categories',
			array(
				'categories'                => $list,
				'expectedOwnership'         => $snapshot['categoryOwnership'],
				'expectedVariableOwnership' => $snapshot['variableOwnership'],
			)
		);
	}

	/**
	 * Steps 3 and 4: generate the scale with Bricks and write it, reusing IDs by name.
	 *
	 * @param array<string, mixed>  $scale
	 * @param array<string, string> $category
	 */
	private function write_scale( array $scale, array $category ): void {
		$generated = $this->gateway->execute(
			'bricks/generate-scale-variables',
			array(
				'categoryId' => $category['id'],
				'scaleRange' => Scales::range( $scale['steps'] ),
			)
		);
		$rows = (array) ( $generated['variables'] ?? array() );
		if ( ! $rows ) {
			throw new RuntimeException( sprintf( 'Bricks generated no values for the %s.', strtolower( $category['name'] ) ) );
		}

		$snapshot = $this->gateway->list_all_variables();
		$ours     = $this->owned( $snapshot['items'], $category, true );

		$write = array();
		$keep  = array();
		foreach ( $rows as $row ) {
			$name     = (string) $row['name'];
			$existing = $ours[ $name ] ?? null;
			$next     = array(
				'id'       => $existing ? (string) $existing['id'] : (string) $row['id'],
				'name'     => $name,
				'value'    => (string) $row['value'],
				'category' => $category['id'],
				'scale'    => $row['scale'],
			);
			$keep[ $name ] = true;

			if ( $existing && (string) $existing['value'] === $next['value'] && (string) $existing['category'] === $category['id'] ) {
				++$this->summary['unchanged'];
				continue;
			}
			$existing ? ++$this->summary['updated'] : ++$this->summary['created'];
			$write[] = $next;
		}

		if ( $write ) {
			$this->set_variables( $write );
		}

		// Steps that were removed from the scale.
		foreach ( $ours as $name => $item ) {
			if ( ! isset( $keep[ $name ] ) ) {
				$this->delete_variable( (string) $item['id'] );
			}
		}
	}

	/**
	 * @param list<array{name:string,value:string,category:string}> $roles
	 */
	private function write_roles( array $roles ): void {
		$snapshot = $this->gateway->list_all_variables();
		$by_name  = array();
		foreach ( $snapshot['items'] as $item ) {
			$by_name[ (string) $item['name'] ] = $item;
		}

		$write = array();
		$keep  = array();
		foreach ( $roles as $role ) {
			$existing           = $by_name[ $role['name'] ] ?? null;
			$keep[ $role['name'] ] = true;
			if ( $existing && ! in_array( (string) $existing['category'], array( Scales::TYPE_ROLES['id'], Scales::SPACE_ROLES['id'] ), true ) ) {
				// A variable with this name belongs to someone else; leave it alone.
				continue;
			}
			if ( $existing && (string) $existing['value'] === $role['value'] && (string) $existing['category'] === $role['category'] ) {
				++$this->summary['unchanged'];
				continue;
			}
			$existing ? ++$this->summary['updated'] : ++$this->summary['created'];
			$write[] = array(
				'id'       => $existing ? (string) $existing['id'] : self::stable_id( $role['name'] ),
				'name'     => $role['name'],
				'value'    => $role['value'],
				'category' => $role['category'],
			);
		}
		if ( $write ) {
			$this->set_variables( $write );
		}

		// Roles Leapfrog no longer writes.
		foreach ( $snapshot['items'] as $item ) {
			if ( in_array( (string) $item['category'], array( Scales::TYPE_ROLES['id'], Scales::SPACE_ROLES['id'] ), true ) && ! isset( $keep[ (string) $item['name'] ] ) ) {
				$this->delete_variable( (string) $item['id'] );
			}
		}
	}

	/**
	 * Variables of one scale, by name: in its category, or parked (category '') with its prefix.
	 *
	 * @param list<array<string,mixed>> $items
	 * @param array<string, string>     $category
	 * @return array<string, array<string, mixed>>
	 */
	private function owned( array $items, array $category, bool $include_parked ): array {
		$out = array();
		foreach ( $items as $item ) {
			$in_category = (string) ( $item['category'] ?? '' ) === $category['id'];
			$parked      = $include_parked && '' === (string) ( $item['category'] ?? '' ) && str_starts_with( (string) $item['name'], $category['prefix'] ) && ! empty( $item['scale'] );
			if ( $in_category || $parked ) {
				$out[ (string) $item['name'] ] = $item;
			}
		}

		return $out;
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	private function set_variables( array $rows ): void {
		$snapshot = $this->gateway->list_all_variables();
		foreach ( array_chunk( $rows, 80 ) as $chunk ) {
			$result = $this->gateway->execute(
				'bricks/set-global-variables',
				array(
					'variables'                 => $chunk,
					'expectedVariableOwnership' => $snapshot['variableOwnership'],
					'expectedCategoryOwnership' => $snapshot['categoryOwnership'],
				)
			);
			$snapshot['variableOwnership'] = (array) ( $result['variableOwnership'] ?? $snapshot['variableOwnership'] );
			$snapshot['categoryOwnership'] = (array) ( $result['categoryOwnership'] ?? $snapshot['categoryOwnership'] );
		}
	}

	private function delete_variable( string $id ): void {
		$snapshot = $this->gateway->list_all_variables();
		foreach ( $snapshot['items'] as $item ) {
			if ( (string) $item['id'] === $id ) {
				$this->gateway->execute(
					'bricks/delete-global-variable',
					array(
						'variableId'        => $id,
						'expectedOwnership' => $item['itemOwnership'],
						'allowOrphans'      => true,
					)
				);
				++$this->summary['deleted'];
				return;
			}
		}
	}

	/**
	 * @param mixed                $before Existing scale config.
	 * @param array<string, mixed> $after  Wanted scale config.
	 */
	private function scale_differs( $before, array $after ): bool {
		if ( ! is_array( $before ) ) {
			return true;
		}
		foreach ( $after as $key => $value ) {
			$was = $before[ $key ] ?? null;
			if ( is_array( $value ) ? array_values( (array) $was ) !== $value : (string) $was !== (string) $value ) {
				return true;
			}
		}

		// Manual values set in Bricks are replaced by Leapfrog's automatic scale.
		return ! empty( $before['isManual'] );
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private static function clean_row( array $item, string $category ): array {
		$row = array(
			'id'       => (string) $item['id'],
			'name'     => (string) $item['name'],
			'value'    => (string) $item['value'],
			'category' => $category,
		);
		if ( isset( $item['scale'] ) ) {
			$row['scale'] = $item['scale'];
		}

		return $row;
	}

	private static function stable_id( string $name ): string {
		return 'lfv' . substr( md5( 'leapfrog:' . $name ), 0, 8 );
	}
}
