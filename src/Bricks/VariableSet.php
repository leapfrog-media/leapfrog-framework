<?php

declare(strict_types=1);

namespace Leapfrog\Framework\Bricks;

/**
 * Keeps one Bricks variable category (no scale) equal to a list of variables:
 * creates the category if needed, upserts by name, removes variables Leapfrog no
 * longer writes. Variables with the same name owned by someone else are left alone.
 */
final class VariableSet {
	private BricksGateway $gateway;

	public function __construct( ?BricksGateway $gateway = null ) {
		$this->gateway = $gateway ?? new BricksGateway();
	}

	/**
	 * @param array{id:string,name:string}                           $category
	 * @param list<array{name:string,value:string,category:string}> $rows
	 * @return array{created:int,updated:int,deleted:int,unchanged:int,skipped:list<string>}
	 */
	public function sync( array $category, array $rows ): array {
		$summary = array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0, 'skipped' => array() );

		$snapshot = $this->gateway->list_all_variables();
		$exists   = false;
		foreach ( $snapshot['categories'] as $cat ) {
			if ( ( $cat['id'] ?? '' ) === $category['id'] ) {
				$exists = true;
			}
		}
		if ( ! $exists ) {
			$list = array();
			foreach ( $snapshot['categories'] as $cat ) {
				unset( $cat['itemDigest'], $cat['itemOwnership'] );
				$list[] = $cat;
			}
			$list[] = array( 'id' => $category['id'], 'name' => $category['name'] );
			$this->gateway->execute(
				'bricks/set-global-variable-categories',
				array(
					'categories'                => $list,
					'expectedOwnership'         => $snapshot['categoryOwnership'],
					'expectedVariableOwnership' => $snapshot['variableOwnership'],
				)
			);
			$snapshot = $this->gateway->list_all_variables();
		}

		$by_name = array();
		foreach ( $snapshot['items'] as $item ) {
			$by_name[ (string) $item['name'] ] = $item;
		}

		$write = array();
		$keep  = array();
		foreach ( $rows as $row ) {
			$existing             = $by_name[ $row['name'] ] ?? null;
			$keep[ $row['name'] ] = true;
			if ( $existing && (string) $existing['category'] !== $category['id'] ) {
				$summary['skipped'][] = $row['name'];
				continue;
			}
			if ( $existing && (string) $existing['value'] === $row['value'] ) {
				++$summary['unchanged'];
				continue;
			}
			$existing ? ++$summary['updated'] : ++$summary['created'];
			$write[] = array(
				'id'       => $existing ? (string) $existing['id'] : 'lfv' . substr( md5( 'leapfrog:' . $row['name'] ), 0, 8 ),
				'name'     => $row['name'],
				'value'    => $row['value'],
				'category' => $category['id'],
			);
		}

		if ( $write ) {
			$this->gateway->execute(
				'bricks/set-global-variables',
				array(
					'variables'                 => $write,
					'expectedVariableOwnership' => $snapshot['variableOwnership'],
					'expectedCategoryOwnership' => $snapshot['categoryOwnership'],
				)
			);
		}

		foreach ( $snapshot['items'] as $item ) {
			if ( (string) $item['category'] === $category['id'] && ! isset( $keep[ (string) $item['name'] ] ) ) {
				$fresh = $this->gateway->list_all_variables();
				foreach ( $fresh['items'] as $candidate ) {
					if ( (string) $candidate['id'] === (string) $item['id'] ) {
						$this->gateway->execute(
							'bricks/delete-global-variable',
							array(
								'variableId'        => (string) $item['id'],
								'expectedOwnership' => $candidate['itemOwnership'],
								'allowOrphans'      => true,
							)
						);
						++$summary['deleted'];
					}
				}
			}
		}

		CssFiles::rebuild();

		return $summary;
	}
}
