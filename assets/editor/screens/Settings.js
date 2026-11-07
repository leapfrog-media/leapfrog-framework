import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';

/**
 * Element settings: switches for the defaults, extra CSS controls and builder helpers
 * Leapfrog adds to Bricks elements. Saved on their own, separate from the Style Manager.
 */
function ElementSettingsPanel( { restPath } ) {
	const [ data, setData ] = useState( null );
	const [ values, setValues ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: `${ restPath }/element-settings` } )
			.then( ( res ) => {
				setData( res );
				setValues( res.settings );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	if ( ! data || ! values ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const dirty = JSON.stringify( values ) !== JSON.stringify( data.settings );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/element-settings/save`, method: 'POST', data: { settings: values } } )
			.then( ( res ) => {
				setData( ( d ) => ( { ...d, settings: res.settings } ) );
				setValues( res.settings );
				const ts = res.themeStyle || {};
				setNotice( {
					status: ts.status === 'error' ? 'warning' : 'success',
					text: ts.status === 'error'
						? sprintf( /* translators: %s error */ __( 'Saved, but the theme style could not be updated: %s', 'leapfrog-framework' ), ts.message )
						: __( 'Saved. Reload the Bricks builder to see the changes.', 'leapfrog-framework' ),
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="lf-panel">
			<h2 className="lf-panel__title">{ __( 'Element settings', 'leapfrog-framework' ) }</h2>
			<p className="lf-muted">{ __( 'Defaults and controls Leapfrog adds to Bricks elements. The extra CSS controls only output their CSS while Leapfrog is active; their values stay saved either way.', 'leapfrog-framework' ) }</p>
			<div className="lf-toggles">
				{ data.groups.map( ( g ) => (
					<ToggleControl
						key={ g.key }
						__nextHasNoMarginBottom
						label={ g.label }
						help={ g.description }
						checked={ !! values[ g.key ] }
						onChange={ ( v ) => setValues( { ...values, [ g.key ]: v } ) }
					/>
				) ) }
			</div>
			<div className="lf-panel__actions">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving || ! dirty }>
					{ __( 'Save element settings', 'leapfrog-framework' ) }
				</Button>
				{ notice && <span className={ `lf-savebar__message is-${ notice.status }` }>{ notice.text }</span> }
			</div>
		</div>
	);
}

/**
 * Bricks Style Manager basics: screen widths for fluid scales and the default light/dark mode.
 */
export default function Settings( { restPath, onDirtyChange } ) {
	const [ sm, setSm ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: `${ restPath }/scales` } )
			.then( ( res ) => {
				setSm( res.styleManager );
				setSaved( res.styleManager );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	const dirty = !! sm && !! saved && JSON.stringify( sm ) !== JSON.stringify( saved );
	useEffect( () => onDirtyChange?.( dirty ), [ dirty, onDirtyChange ] );

	if ( ! sm ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/style-manager/save`, method: 'POST', data: sm } )
			.then( ( res ) => {
				setSm( res.styleManager );
				setSaved( res.styleManager );
				setNotice( {
					status: 'success',
					text: res.summary
						? sprintf( /* translators: %d number of variables */ __( 'Saved. %d scale values were recalculated in Bricks.', 'leapfrog-framework' ), res.summary.updated )
						: __( 'Saved to Bricks’ Style Manager.', 'leapfrog-framework' ),
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setSaving( false ) );
	};

	return (
		<section className="lf-screen">
			<h1 className="lf-screen__title">{ __( 'Settings', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">{ __( 'These are Bricks’ own Style Manager settings. Changing the screen widths recalculates the type and space scales.', 'leapfrog-framework' ) }</p>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Fluid scales', 'leapfrog-framework' ) }</h2>
				<div className="lf-grid">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min="240"
						max="3840"
						label={ __( 'Smallest screen (px)', 'leapfrog-framework' ) }
						help={ __( 'Below this width, sizes stop shrinking.', 'leapfrog-framework' ) }
						value={ sm.minScreenWidth }
						onChange={ ( v ) => setSm( { ...sm, minScreenWidth: Number( v ) } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min="240"
						max="3840"
						label={ __( 'Largest screen (px)', 'leapfrog-framework' ) }
						help={ __( 'Above this width, sizes stop growing.', 'leapfrog-framework' ) }
						value={ sm.maxScreenWidth }
						onChange={ ( v ) => setSm( { ...sm, maxScreenWidth: Number( v ) } ) }
					/>
				</div>
				<p className="lf-muted">
					{ sprintf(
						/* translators: %s px value */
						__( 'Root font size: 1rem = %spx (set by Bricks). Scale values are written in rem, so visitors who enlarge text in their browser get larger text.', 'leapfrog-framework' ),
						sm.htmlFontSizePx
					) }
				</p>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Colour mode', 'leapfrog-framework' ) }</h2>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Default mode for visitors', 'leapfrog-framework' ) }
					help={ __( 'Auto follows the visitor’s device setting.', 'leapfrog-framework' ) }
					value={ sm.defaultMode }
					options={ [
						{ value: 'light', label: __( 'Light', 'leapfrog-framework' ) },
						{ value: 'dark', label: __( 'Dark', 'leapfrog-framework' ) },
						{ value: 'auto', label: __( 'Auto', 'leapfrog-framework' ) },
					] }
					onChange={ ( v ) => setSm( { ...sm, defaultMode: v } ) }
				/>
			</div>

			<ElementSettingsPanel restPath={ restPath } />

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
