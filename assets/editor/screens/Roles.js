import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, SelectControl, Spinner, ToggleControl } from '@wordpress/components';

const GROUPS = [
	{ id: 'text', label: __( 'Text', 'leapfrog-framework' ) },
	{ id: 'surface', label: __( 'Surfaces', 'leapfrog-framework' ) },
	{ id: 'border', label: __( 'Borders and focus', 'leapfrog-framework' ) },
	{ id: 'on', label: __( 'Text on colours', 'leapfrog-framework' ) },
	{ id: 'status', label: __( 'Status backgrounds', 'leapfrog-framework' ) },
];

function ratioTone( ratio, large = false ) {
	if ( ratio >= ( large ? 3 : 4.5 ) ) {
		return 'good';
	}
	return ratio >= 3 ? 'warn' : 'bad';
}

function Choice( { label, value, options, hex, onChange } ) {
	return (
		<div className="lf-choice">
			<span className="lf-choice__chip" style={ { background: hex } } aria-hidden="true" />
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				hideLabelFromVision
				value={ value }
				options={ options }
				onChange={ onChange }
			/>
		</div>
	);
}

/**
 * A light and a dark card drawn with the resolved role colours.
 */
function Preview( { roles, mode } ) {
	const c = useMemo( () => {
		const map = {};
		roles.forEach( ( r ) => ( map[ r.key ] = mode === 'dark' ? r.darkHex : r.lightHex ) );
		return map;
	}, [ roles, mode ] );

	return (
		<div className="lf-role-preview" style={ { background: c.canvas, color: c.text, borderColor: c.border } }>
			<span className="lf-role-preview__badge" style={ { color: c[ 'text-muted' ] } }>
				{ mode === 'dark' ? __( 'Dark section', 'leapfrog-framework' ) : __( 'Light section', 'leapfrog-framework' ) }
			</span>
			<h3 style={ { color: c.heading } }>{ __( 'Leap ahead with clarity', 'leapfrog-framework' ) }</h3>
			<p>
				{ __( 'Body text sits comfortably on the page background, with', 'leapfrog-framework' ) }{ ' ' }
				<a href="#preview" onClick={ ( e ) => e.preventDefault() } style={ { color: c.link } }>{ __( 'a link', 'leapfrog-framework' ) }</a>.
			</p>
			<p style={ { color: c[ 'text-muted' ] } }>{ __( 'Muted text for captions and notes.', 'leapfrog-framework' ) }</p>
			<div className="lf-role-preview__card" style={ { background: c.surface, borderColor: c.border } }>
				<strong style={ { color: c.heading } }>{ __( 'A card on a surface', 'leapfrog-framework' ) }</strong>
				<span style={ { color: c[ 'text-muted' ] } }>{ __( 'Surfaces sit on the page background.', 'leapfrog-framework' ) }</span>
			</div>
			<div className="lf-role-preview__muted" style={ { background: c[ 'surface-muted' ] } }>{ __( 'Muted surface', 'leapfrog-framework' ) }</div>
			<div className="lf-role-preview__status" style={ { background: c[ 'success-surface' ] } }>{ __( 'Saved successfully', 'leapfrog-framework' ) }</div>
		</div>
	);
}

export default function Roles( { restPath, onDirtyChange } ) {
	const [ data, setData ] = useState( null );
	const [ map, setMap ] = useState( null );
	const [ pageStyles, setPageStyles ] = useState( true );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const timer = useRef();

	const apply = ( res ) => {
		setData( res );
		setPageStyles( res.pageStyles );
		const next = {};
		res.roles.forEach( ( r ) => ( next[ r.key ] = { light: r.light, dark: r.dark } ) );
		setMap( next );
	};

	useEffect( () => {
		apiFetch( { path: `${ restPath }/roles` } ).then( apply ).catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	useEffect( () => {
		onDirtyChange?.( dirty );
	}, [ dirty, onDirtyChange ] );

	useEffect( () => {
		if ( ! dirty || ! map ) {
			return;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			apiFetch( { path: `${ restPath }/roles/preview`, method: 'POST', data: { map } } )
				.then( ( res ) => setData( ( d ) => ( { ...d, roles: res.roles, contrast: res.contrast } ) ) )
				.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		}, 200 );
		return () => clearTimeout( timer.current );
	}, [ map, dirty, restPath ] );

	if ( ! data || ! map ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const tokenOptions = data.tokens.map( ( t ) => ( { value: t.name, label: `--${ t.name }` } ) );
	const set = ( key, mode, value ) => {
		setMap( { ...map, [ key ]: { ...map[ key ], [ mode ]: value } } );
		setDirty( true );
		setNotice( null );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/roles/save`, method: 'POST', data: { map, pageStyles, revision: data.revision } } )
			.then( ( res ) => {
				apply( res );
				setDirty( false );
				const s = res.summary;
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: 1: added, 2: updated, 3: unchanged */
						__( 'Saved to the Bricks palette "Leapfrog roles" (%1$d added, %2$d updated, %3$d unchanged) and the section themes.', 'leapfrog-framework' ),
						s.created,
						s.updated,
						s.unchanged
					),
				} );
			} )
			.catch( ( e ) => {
				if ( e.data?.revision ) {
					setData( ( d ) => ( { ...d, revision: e.data.revision } ) );
				}
				setNotice( { status: 'error', text: e.message } );
			} )
			.finally( () => setSaving( false ) );
	};

	const labelOf = ( key ) => data.roles.find( ( r ) => r.key === key )?.label || key;

	return (
		<section className="lf-screen lf-screen--scales">
			<h1 className="lf-screen__title">{ __( 'Roles and themes', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">
				{ __( 'Roles give each colour a job. Use them in Bricks instead of palette colours, and dark mode and section themes come for free. Choose one palette colour for light and one for dark.', 'leapfrog-framework' ) }
			</p>

			<div className="lf-panel lf-previews">
				<Preview roles={ data.roles } mode="light" />
				<Preview roles={ data.roles } mode="dark" />
			</div>

			<div className="lf-panel">
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Colour the page with the roles', 'leapfrog-framework' ) }
					help={ __( 'Page text, headings, links, focus rings and background use the roles. Styles you set in Bricks still win.', 'leapfrog-framework' ) }
					checked={ pageStyles }
					onChange={ ( v ) => {
						setPageStyles( v );
						setDirty( true );
					} }
				/>
			</div>

			{ GROUPS.map( ( group ) => (
				<div className="lf-panel" key={ group.id }>
					<h2 className="lf-panel__title">{ group.label }</h2>
					<table className="lf-table lf-roles">
						<thead>
							<tr>
								<th scope="col">{ __( 'Role', 'leapfrog-framework' ) }</th>
								<th scope="col">{ __( 'Light', 'leapfrog-framework' ) }</th>
								<th scope="col">{ __( 'Dark', 'leapfrog-framework' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ data.roles.filter( ( r ) => r.group === group.id ).map( ( r ) => {
								const options = r.group === 'on' ? [ { value: 'auto', label: __( 'Automatic (white or black)', 'leapfrog-framework' ) }, ...tokenOptions ] : tokenOptions;
								return (
									<tr key={ r.key }>
										<td>
											<strong>{ r.label }</strong>
											<br />
											<code>{ r.variable }</code>
										</td>
										<td>
											<Choice label={ sprintf( /* translators: %s role */ __( '%s in light mode', 'leapfrog-framework' ), r.label ) } value={ map[ r.key ].light } options={ options } hex={ r.lightHex } onChange={ ( v ) => set( r.key, 'light', v ) } />
										</td>
										<td>
											<Choice label={ sprintf( /* translators: %s role */ __( '%s in dark mode', 'leapfrog-framework' ), r.label ) } value={ map[ r.key ].dark } options={ options } hex={ r.darkHex } onChange={ ( v ) => set( r.key, 'dark', v ) } />
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
			) ) }

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Automatic roles', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Live mixes of the roles above, so they follow every theme.', 'leapfrog-framework' ) }</p>
				<ul className="lf-plain-list">
					{ data.transparent.map( ( t ) => (
						<li key={ t.key }><code>{ t.variable }</code> { t.label }: <code>{ t.value }</code></li>
					) ) }
				</ul>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Contrast', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Text roles on surfaces. AA needs 4.5:1 for body text and 3:1 for large text.', 'leapfrog-framework' ) }</p>
				<table className="lf-table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Text', 'leapfrog-framework' ) }</th>
							<th scope="col">{ __( 'On', 'leapfrog-framework' ) }</th>
							<th scope="col">{ __( 'Light mode', 'leapfrog-framework' ) }</th>
							<th scope="col">{ __( 'Dark mode', 'leapfrog-framework' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ data.contrast.map( ( c ) => (
							<tr key={ c.text + c.surface }>
								<td>{ labelOf( c.text ) }</td>
								<td>{ labelOf( c.surface ) }</td>
								<td><span className={ `lf-contrast is-${ ratioTone( c.light, c.text === 'heading' ) }` }>{ c.light.toFixed( 2 ) }</span></td>
								<td><span className={ `lf-contrast is-${ ratioTone( c.dark, c.text === 'heading' ) }` }>{ c.dark.toFixed( 2 ) }</span></td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="lf-savebar" role="status" aria-live="polite">
				{ notice ? (
					<span className={ `lf-savebar__message is-${ notice.status }` }>{ notice.text }</span>
				) : (
					<span className="lf-muted">{ dirty ? __( 'Unsaved changes', 'leapfrog-framework' ) : __( 'All changes saved', 'leapfrog-framework' ) }</span>
				) }
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving || ! dirty }>
					{ __( 'Save to Bricks', 'leapfrog-framework' ) }
				</Button>
			</div>
		</section>
	);
}
