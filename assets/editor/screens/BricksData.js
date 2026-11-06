import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, SearchControl, SelectControl, Spinner } from '@wordpress/components';

/**
 * Shows what Bricks currently holds: connection status, categories and every variable.
 */
export default function BricksData( { status, restPath } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ category, setCategory ] = useState( '' );

	const load = () => {
		setData( null );
		setError( '' );
		apiFetch( { path: `${ restPath }/bricks/variables` } )
			.then( setData )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	};

	useEffect( load, [ restPath ] );

	const categoryNames = useMemo( () => {
		const map = {};
		( data?.categories || [] ).forEach( ( c ) => ( map[ c.id ] = c.name ) );
		return map;
	}, [ data ] );

	const rows = useMemo( () => {
		const needle = search.trim().toLowerCase();
		return ( data?.items || [] ).filter( ( v ) => {
			if ( category && v.category !== category ) {
				return false;
			}
			return ! needle || v.name.toLowerCase().includes( needle ) || v.value.toLowerCase().includes( needle );
		} );
	}, [ data, search, category ] );

	return (
		<section className="lf-screen">
			<h1 className="lf-screen__title">{ __( 'Bricks data', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">
				{ __( 'What Bricks holds right now. Leapfrog reads and writes these through Bricks’ own ownership-checked methods.', 'leapfrog-framework' ) }
			</p>

			<dl className="lf-facts">
				<div><dt>{ __( 'Bricks', 'leapfrog-framework' ) }</dt><dd>{ status.bricksVersion }</dd></div>
				<div>
					<dt>{ __( 'Connection', 'leapfrog-framework' ) }</dt>
					<dd>
						{ status.gatewayMode === 'abilities'
							? __( 'Abilities API', 'leapfrog-framework' )
							: __( 'Direct (Bricks AI switch off)', 'leapfrog-framework' ) }
					</dd>
				</div>
				<div><dt>{ __( 'Variables', 'leapfrog-framework' ) }</dt><dd>{ data ? data.total : '…' }</dd></div>
				<div><dt>{ __( 'Categories', 'leapfrog-framework' ) }</dt><dd>{ data ? data.categories.length : '…' }</dd></div>
			</dl>

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ ! data && ! error && <Spinner /> }

			{ data && (
				<>
					<div className="lf-toolbar">
						<SearchControl
							__nextHasNoMarginBottom
							label={ __( 'Search variables', 'leapfrog-framework' ) }
							value={ search }
							onChange={ setSearch }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Category', 'leapfrog-framework' ) }
							hideLabelFromVision
							value={ category }
							options={ [
								{ label: __( 'All categories', 'leapfrog-framework' ), value: '' },
								...data.categories.map( ( c ) => ( { label: c.name, value: c.id } ) ),
							] }
							onChange={ setCategory }
						/>
						<Button variant="secondary" onClick={ load }>{ __( 'Reload', 'leapfrog-framework' ) }</Button>
					</div>

					{ data.total === 0 ? (
						<p className="lf-empty">
							{ __( 'Bricks has no variables yet. They will appear here once Leapfrog generates them.', 'leapfrog-framework' ) }
						</p>
					) : (
						<table className="lf-table">
							<caption className="screen-reader-text">{ __( 'Bricks variables', 'leapfrog-framework' ) }</caption>
							<thead>
								<tr>
									<th scope="col">{ __( 'Name', 'leapfrog-framework' ) }</th>
									<th scope="col">{ __( 'Value', 'leapfrog-framework' ) }</th>
									<th scope="col">{ __( 'Category', 'leapfrog-framework' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ rows.map( ( v ) => (
									<tr key={ v.id }>
										<td><code>--{ v.name }</code></td>
										<td><code>{ v.value }</code></td>
										<td>{ categoryNames[ v.category ] || v.category || '—' }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
					{ data.total > 0 && (
						<p className="lf-muted">
							{ sprintf(
								/* translators: 1: shown count, 2: total count */
								__( 'Showing %1$d of %2$d.', 'leapfrog-framework' ),
								rows.length,
								data.total
							) }
						</p>
					) }
				</>
			) }
		</section>
	);
}
