import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';

const when = ( seconds ) => new Date( seconds * 1000 ).toLocaleString( undefined, { dateStyle: 'medium', timeStyle: 'short' } );

/**
 * Rebuild everything in Bricks, and the last 20 versions with restore.
 */
export default function Review( { restPath } ) {
	const [ history, setHistory ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	const load = () =>
		apiFetch( { path: `${ restPath }/history` } )
			.then( setHistory )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );

	useEffect( () => {
		load();
	}, [ restPath ] );

	const report = ( res, intro ) => {
		const t = res.totals;
		setNotice( {
			status: 'success',
			text: sprintf(
				/* translators: 1: intro sentence, 2: updated, 3: added, 4: removed, 5: unchanged */
				__( '%1$s Bricks: %2$d updated, %3$d added, %4$d removed, %5$d unchanged.', 'leapfrog-framework' ),
				intro,
				t.updated,
				t.created,
				t.deleted,
				t.unchanged
			),
		} );
	};

	const rebuild = () => {
		setBusy( 'rebuild' );
		setNotice( null );
		apiFetch( { path: `${ restPath }/rebuild`, method: 'POST' } )
			.then( ( res ) => report( res, __( 'Everything was written to Bricks again.', 'leapfrog-framework' ) ) )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	const restore = ( entry ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( /* translators: %s date */ __( 'Restore the version from %s? The current version is kept here too, so this can be undone.', 'leapfrog-framework' ), when( entry.time ) ) ) ) {
			return;
		}
		setBusy( entry.id );
		setNotice( null );
		apiFetch( { path: `${ restPath }/history/restore`, method: 'POST', data: { id: entry.id, revision: history.revision } } )
			.then( ( res ) => {
				report( res, __( 'Version restored.', 'leapfrog-framework' ) );
				return load();
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	return (
		<section className="lf-screen">
			<h1 className="lf-screen__title">{ __( 'Review and save', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">{ __( 'Each screen saves straight to Bricks and reports what changed. Here you can write everything again, or go back to an earlier version.', 'leapfrog-framework' ) }</p>

			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Rebuild everything in Bricks', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Writes all colours, roles, scales, layout and component variables to Bricks again. Use it if something was changed or deleted in Bricks by mistake. Only differences are written.', 'leapfrog-framework' ) }</p>
				<Button variant="secondary" onClick={ rebuild } isBusy={ busy === 'rebuild' } disabled={ !! busy }>
					{ __( 'Rebuild everything', 'leapfrog-framework' ) }
				</Button>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'History', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'The last 20 versions, newest first. Each entry is the design system as it was just before the change named.', 'leapfrog-framework' ) }</p>
				{ ! history && <Spinner /> }
				{ history && ! history.entries.length && <p className="lf-empty">{ __( 'No earlier versions yet. They appear here after your next save.', 'leapfrog-framework' ) }</p> }
				{ history && history.entries.length > 0 && (
					<table className="lf-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Saved', 'leapfrog-framework' ) }</th>
								<th scope="col">{ __( 'Note', 'leapfrog-framework' ) }</th>
								<th scope="col">{ __( 'By', 'leapfrog-framework' ) }</th>
								<th scope="col"><span className="screen-reader-text">{ __( 'Actions', 'leapfrog-framework' ) }</span></th>
							</tr>
						</thead>
						<tbody>
							{ history.entries.map( ( entry ) => (
								<tr key={ entry.id }>
									<td className="lf-nowrap">{ when( entry.time ) }</td>
									<td>{ entry.label || '—' }</td>
									<td>{ entry.user || '—' }</td>
									<td>
										<Button variant="tertiary" onClick={ () => restore( entry ) } isBusy={ busy === entry.id } disabled={ !! busy }>
											{ __( 'Restore', 'leapfrog-framework' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>
		</section>
	);
}
