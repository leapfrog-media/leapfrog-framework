import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';

/**
 * Small visual next to a value, where one helps.
 */
function Sample( { name, value } ) {
	if ( name.startsWith( 'radius-' ) ) {
		return <span className="lf-shape-sample" style={ { borderRadius: value } } aria-hidden="true" />;
	}
	if ( name.startsWith( 'shadow-' ) ) {
		return <span className="lf-shape-sample is-shadow" style={ { boxShadow: value.replace( /var\(--black\)/g, '#000' ) } } aria-hidden="true" />;
	}
	if ( name.startsWith( 'border-' ) ) {
		return <span className="lf-shape-sample is-border" style={ { borderWidth: value } } aria-hidden="true" />;
	}
	return null;
}

export default function Shape( { restPath, onDirtyChange } ) {
	const [ data, setData ] = useState( null );
	const [ values, setValues ] = useState( null );
	const [ corners, setCorners ] = useState( 'soft' );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const apply = ( res ) => {
		setData( res );
		setValues( res.values );
		setCorners( res.corners );
	};

	useEffect( () => {
		apiFetch( { path: `${ restPath }/shape` } ).then( apply ).catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	useEffect( () => {
		onDirtyChange?.( dirty );
	}, [ dirty, onDirtyChange ] );

	if ( ! data || ! values ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const change = ( patch, nextCorners = corners ) => {
		setValues( { ...values, ...patch } );
		setCorners( nextCorners );
		setDirty( true );
		setNotice( null );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/shape/save`, method: 'POST', data: { corners, values, revision: data.revision } } )
			.then( ( res ) => {
				apply( res );
				setDirty( false );
				const s = res.summary;
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: 1: added, 2: updated, 3: unchanged */
						__( 'Saved to Bricks: %1$d added, %2$d updated, %3$d unchanged.', 'leapfrog-framework' ),
						s.created,
						s.updated,
						s.unchanged
					) + ( s.skipped?.length ? ' ' + sprintf( /* translators: %s names */ __( 'Skipped (name used by another variable): %s', 'leapfrog-framework' ), s.skipped.join( ', ' ) ) : '' ),
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

	return (
		<section className="lf-screen lf-screen--scales">
			<h1 className="lf-screen__title">{ __( 'Layout and shape', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">{ __( 'Content widths, corners, borders, shadows, motion and layers. Each is a Bricks variable in the category "Layout and shape".', 'leapfrog-framework' ) }</p>

			{ data.groups.map( ( group ) => (
				<div className="lf-panel" key={ group.id }>
					<h2 className="lf-panel__title">{ group.label }</h2>
					{ group.id === 'corners' && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Corner style', 'leapfrog-framework' ) }
							value={ corners }
							options={ [
								{ value: 'sharp', label: __( 'Sharp', 'leapfrog-framework' ) },
								{ value: 'soft', label: __( 'Soft', 'leapfrog-framework' ) },
								{ value: 'round', label: __( 'Round', 'leapfrog-framework' ) },
								{ value: 'custom', label: __( 'Custom', 'leapfrog-framework' ), disabled: true },
							] }
							onChange={ ( v ) => change( data.presets[ v ] || {}, v ) }
							className="lf-corner-style"
						/>
					) }
					<div className="lf-grid lf-grid--wide">
						{ group.names.map( ( name ) => (
							<div className="lf-shape-field" key={ name }>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ `--${ name }` }
									value={ values[ name ] }
									placeholder={ data.defaults[ name ] }
									onChange={ ( v ) => change( { [ name ]: v }, name.startsWith( 'radius-' ) && name !== 'radius-full' ? 'custom' : corners ) }
								/>
								<Sample name={ name } value={ values[ name ] } />
							</div>
						) ) }
					</div>
				</div>
			) ) }

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
