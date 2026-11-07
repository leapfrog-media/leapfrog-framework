import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';

function Contents( { contents } ) {
	return (
		<span className="lf-contents">
			{ contents.colours.map( ( c ) => (
				<span key={ c.key } className="lf-contents__chip" style={ { background: c.value } } title={ `--${ c.key } ${ c.value }` } />
			) ) }
			<span className="lf-muted">
				{ [ contents.fonts.heading, contents.fonts.body ].map( ( f ) => f.split( ',' )[ 0 ].replace( /"/g, '' ) ).filter( ( f, i, a ) => a.indexOf( f ) === i ).join( ' / ' ) }
				{ ' · ' }
				{ sprintf( /* translators: 1: min px, 2: max px */ __( 'text %1$s–%2$spx', 'leapfrog-framework' ), contents.typeSize[ 0 ], contents.typeSize[ 1 ] ) }
				{ ' · ' }
				{ contents.corners }
			</span>
		</span>
	);
}

export default function ExportPresets( { restPath } ) {
	const [ presets, setPresets ] = useState( null );
	const [ name, setName ] = useState( '' );
	const [ file, setFile ] = useState( null );
	const [ filePreview, setFilePreview ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const input = useRef();

	const load = () =>
		apiFetch( { path: `${ restPath }/presets` } )
			.then( setPresets )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );

	useEffect( () => {
		load();
	}, [ restPath ] );

	const fail = ( e ) => setNotice( { status: 'error', text: e.message } );
	const done = ( res, intro ) => {
		const t = res.totals;
		setNotice( { status: 'success', text: sprintf( /* translators: 1: intro, 2: updated, 3: added */ __( '%1$s Bricks: %2$d updated, %3$d added.', 'leapfrog-framework' ), intro, t.updated, t.created ) } );
		return load();
	};

	const exportFile = () => {
		setBusy( 'export' );
		apiFetch( { path: `${ restPath }/export` } )
			.then( ( data ) => {
				const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
				const url = URL.createObjectURL( blob );
				const a = document.createElement( 'a' );
				const site = ( data.site || 'site' ).replace( /^https?:\/\//, '' ).replace( /[^a-z0-9.-]+/gi, '-' );
				a.href = url;
				a.download = `leapfrog-framework-${ site }-${ data.exported.slice( 0, 10 ) }.json`;
				a.click();
				URL.revokeObjectURL( url );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const pick = ( event ) => {
		const chosen = event.target.files?.[ 0 ];
		setFile( null );
		setFilePreview( null );
		setNotice( null );
		if ( ! chosen ) {
			return;
		}
		chosen.text().then( ( text ) => {
			let parsed;
			try {
				parsed = JSON.parse( text );
			} catch ( e ) {
				fail( new Error( __( 'That file is not valid JSON.', 'leapfrog-framework' ) ) );
				return;
			}
			apiFetch( { path: `${ restPath }/import/preview`, method: 'POST', data: { file: parsed } } )
				.then( ( res ) => {
					setFile( parsed );
					setFilePreview( res );
				} )
				.catch( fail );
		} );
	};

	const importFile = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Replace this site’s design system with the imported one? The current version is kept under Review and save.', 'leapfrog-framework' ) ) ) {
			return;
		}
		setBusy( 'import' );
		apiFetch( { path: `${ restPath }/import`, method: 'POST', data: { file, revision: presets.revision } } )
			.then( ( res ) => {
				setFile( null );
				setFilePreview( null );
				if ( input.current ) {
					input.current.value = '';
				}
				return done( res, __( 'Imported.', 'leapfrog-framework' ) );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const savePreset = () => {
		setBusy( 'save' );
		apiFetch( { path: `${ restPath }/presets/save`, method: 'POST', data: { name } } )
			.then( ( res ) => {
				setPresets( res );
				setName( '' );
				setNotice( { status: 'success', text: __( 'Preset saved.', 'leapfrog-framework' ) } );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const applyPreset = ( preset ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( /* translators: %s preset name */ __( 'Apply "%s"? The current version is kept under Review and save.', 'leapfrog-framework' ), preset.name ) ) ) {
			return;
		}
		setBusy( preset.id );
		apiFetch( { path: `${ restPath }/presets/apply`, method: 'POST', data: { id: preset.id, revision: presets.revision } } )
			.then( ( res ) => done( res, sprintf( /* translators: %s preset name */ __( '"%s" applied.', 'leapfrog-framework' ), preset.name ) ) )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const deletePreset = ( preset ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( /* translators: %s preset name */ __( 'Delete the preset "%s"? This cannot be undone.', 'leapfrog-framework' ), preset.name ) ) ) {
			return;
		}
		setBusy( preset.id );
		apiFetch( { path: `${ restPath }/presets/delete`, method: 'POST', data: { id: preset.id } } )
			.then( setPresets )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	return (
		<section className="lf-screen">
			<h1 className="lf-screen__title">{ __( 'Export and presets', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">{ __( 'Move a design system between sites with a framework file, or keep named versions on this site as presets.', 'leapfrog-framework' ) }</p>

			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Export', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Downloads a .json file with every Leapfrog setting on this site.', 'leapfrog-framework' ) }</p>
				<Button variant="secondary" onClick={ exportFile } isBusy={ busy === 'export' } disabled={ !! busy }>{ __( 'Export framework file', 'leapfrog-framework' ) }</Button>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Import', 'leapfrog-framework' ) }</h2>
				<input ref={ input } type="file" accept="application/json,.json" onChange={ pick } aria-label={ __( 'Framework file to import', 'leapfrog-framework' ) } />
				{ filePreview && (
					<div className="lf-import-preview">
						<p>
							{ sprintf( /* translators: 1: site, 2: date */ __( 'From %1$s, exported %2$s.', 'leapfrog-framework' ), filePreview.from.site || '?', filePreview.from.exported.slice( 0, 10 ) ) }
						</p>
						<Contents contents={ filePreview.contents } />
						<p>
							<Button variant="primary" onClick={ importFile } isBusy={ busy === 'import' } disabled={ !! busy }>{ __( 'Import and save to Bricks', 'leapfrog-framework' ) }</Button>
						</p>
					</div>
				) }
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Presets', 'leapfrog-framework' ) }</h2>
				{ ! presets && <Spinner /> }
				{ presets && (
					<table className="lf-table">
						<tbody>
							{ presets.presets.map( ( preset ) => (
								<tr key={ preset.id }>
									<td>
										<strong>{ preset.name }</strong>
										<br />
										<Contents contents={ preset.contents } />
									</td>
									<td className="lf-nowrap lf-align-right">
										<Button variant="secondary" onClick={ () => applyPreset( preset ) } isBusy={ busy === preset.id } disabled={ !! busy }>{ __( 'Apply', 'leapfrog-framework' ) }</Button>
										{ ! preset.builtIn && (
											<Button variant="tertiary" isDestructive onClick={ () => deletePreset( preset ) } disabled={ !! busy }>{ __( 'Delete', 'leapfrog-framework' ) }</Button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
				<div className="lf-preset-save">
					<TextControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Save the current design system as a preset', 'leapfrog-framework' ) } value={ name } placeholder={ __( 'Preset name', 'leapfrog-framework' ) } onChange={ setName } />
					<Button variant="secondary" onClick={ savePreset } isBusy={ busy === 'save' } disabled={ !! busy || ! name.trim() }>{ __( 'Save preset', 'leapfrog-framework' ) }</Button>
				</div>
			</div>
		</section>
	);
}
