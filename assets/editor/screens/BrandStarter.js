import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';

const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;
const hex = ( v ) => {
	const m = String( v || '' ).trim().match( HEX );
	if ( ! m ) {
		return '';
	}
	const h = m[ 1 ].length === 3 ? m[ 1 ].split( '' ).map( ( c ) => c + c ).join( '' ) : m[ 1 ];
	return '#' + h.toLowerCase();
};

function ColourInput( { label, value, optional, onChange, help } ) {
	const [ text, setText ] = useState( value );
	useEffect( () => setText( value ), [ value ] );
	return (
		<div className="lf-starter-colour">
			<span className="lf-field__label">{ label }</span>
			<div className="lf-hex">
				<input type="color" aria-label={ label } value={ hex( value ) || '#cccccc' } onChange={ ( e ) => onChange( e.target.value ) } />
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ label }
					hideLabelFromVision
					value={ text }
					placeholder={ optional ? __( 'None', 'leapfrog-framework' ) : '#000000' }
					onChange={ setText }
					onBlur={ () => {
						const h = hex( text );
						if ( h || ( optional && ! text.trim() ) ) {
							onChange( h );
						} else {
							setText( value );
						}
					} }
				/>
				{ optional && value && <Button variant="link" onClick={ () => onChange( '' ) }>{ __( 'None', 'leapfrog-framework' ) }</Button> }
			</div>
			{ help && <span className="lf-hint">{ help }</span> }
		</div>
	);
}

/**
 * A light and a dark sample built from the preview data.
 */
function Sample( { preview, mode } ) {
	const role = useMemo( () => {
		const map = {};
		( preview?.roles || [] ).forEach( ( r ) => ( map[ r.key ] = mode === 'dark' ? r.darkHex : r.lightHex ) );
		return map;
	}, [ preview, mode ] );
	const colour = useMemo( () => {
		const map = {};
		( preview?.generated || [] ).forEach( ( g ) => ( map[ g.key ] = mode === 'dark' && g.dark ? g.dark : g.base ) );
		return map;
	}, [ preview, mode ] );
	if ( ! preview ) {
		return null;
	}
	const t = preview.typography;
	const size = ( step ) => ( t.sizes[ step ] ? `${ t.sizes[ step ] }px` : undefined );
	const radius = preview.radius[ 'radius-l' ];
	const btnRadius = preview.btnRadius === 'var(--radius-full)' ? '9999px' : preview.radius[ 'radius-m' ];
	const btn = ( key, onKey ) => ( { background: colour[ key ], color: role[ onKey ] || '#fff', borderRadius: btnRadius, fontFamily: t.fonts.body } );

	return (
		<div className="lf-starter-sample" style={ { background: role.canvas, color: role.text, fontFamily: t.fonts.body } }>
			<span className="lf-starter-sample__mode" style={ { color: role[ 'text-muted' ] } }>{ mode === 'dark' ? __( 'Dark', 'leapfrog-framework' ) : __( 'Light', 'leapfrog-framework' ) }</span>
			<h2 style={ { color: role.heading, fontFamily: t.fonts.heading, fontSize: size( t.headings.h1 ) } }>{ __( 'Finance, made simple', 'leapfrog-framework' ) }</h2>
			<p style={ { fontSize: size( t.bodyStep ) } }>
				{ __( 'Body text in your chosen font and size, with', 'leapfrog-framework' ) } <span style={ { color: role.link } }>{ __( 'a link', 'leapfrog-framework' ) }</span>.
			</p>
			<div className="lf-starter-sample__buttons">
				<span style={ btn( 'primary', 'on-primary' ) }>{ __( 'Primary', 'leapfrog-framework' ) }</span>
				{ colour.secondary && <span style={ btn( 'secondary', 'on-secondary' ) }>{ __( 'Secondary', 'leapfrog-framework' ) }</span> }
				{ colour.accent && <span style={ btn( 'accent', 'on-accent' ) }>{ __( 'Accent', 'leapfrog-framework' ) }</span> }
			</div>
			<div className="lf-starter-sample__card" style={ { background: role.surface, borderColor: role.border, borderRadius: radius } }>
				<strong style={ { color: role.heading, fontFamily: t.fonts.heading, fontSize: size( t.headings.h3 ) } }>{ __( 'A card', 'leapfrog-framework' ) }</strong>
				<span style={ { color: role[ 'text-muted' ] } }>{ __( 'Surfaces, borders and corners from your choices.', 'leapfrog-framework' ) }</span>
			</div>
		</div>
	);
}

/** Loads Google Fonts in this admin page only, so the preview shows them. The site itself uses Bricks' fonts. */
function usePreviewFonts( fonts ) {
	useEffect( () => {
		const families = [ fonts.heading, fonts.body ].map( ( f ) => String( f || '' ).trim() ).filter( ( f ) => /^[A-Za-z0-9 ]{2,40}$/.test( f ) );
		const id = 'lf-starter-fonts';
		document.getElementById( id )?.remove();
		if ( ! families.length ) {
			return;
		}
		const link = document.createElement( 'link' );
		link.id = id;
		link.rel = 'stylesheet';
		link.href = 'https://fonts.googleapis.com/css2?' + [ ...new Set( families ) ].map( ( f ) => 'family=' + encodeURIComponent( f ).replace( /%20/g, '+' ) + ':wght@400;600;700' ).join( '&' ) + '&display=swap';
		document.head.append( link );
	}, [ fonts.heading, fonts.body ] );
}

export default function BrandStarter( { restPath, onDirtyChange } ) {
	const [ data, setData ] = useState( null );
	const [ inputs, setInputs ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ applying, setApplying ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const timer = useRef();

	useEffect( () => {
		apiFetch( { path: `${ restPath }/starter` } )
			.then( ( res ) => {
				setData( res );
				setInputs( res.inputs );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	useEffect( () => onDirtyChange?.( dirty ), [ dirty, onDirtyChange ] );

	useEffect( () => {
		if ( ! inputs ) {
			return;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			apiFetch( { path: `${ restPath }/starter/preview`, method: 'POST', data: { inputs } } )
				.then( setPreview )
				.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		}, 300 );
		return () => clearTimeout( timer.current );
	}, [ inputs, restPath ] );

	usePreviewFonts( inputs?.fonts || {} );

	if ( ! data || ! inputs ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const set = ( path, value ) => {
		const next = { ...inputs };
		if ( path.length === 2 ) {
			next[ path[ 0 ] ] = { ...next[ path[ 0 ] ], [ path[ 1 ] ]: value };
		} else {
			next[ path[ 0 ] ] = value;
		}
		setInputs( next );
		setDirty( true );
		setNotice( null );
	};

	const apply = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Generate the design system from these choices? Colours, type, spacing, corners and dark backgrounds are replaced and written to Bricks. The current version is kept under Review and save, where you can restore it.', 'leapfrog-framework' ) ) ) {
			return;
		}
		setApplying( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/starter/apply`, method: 'POST', data: { inputs, revision: data.revision } } )
			.then( ( res ) => {
				setData( ( d ) => ( { ...d, revision: res.revision } ) );
				setInputs( res.inputs );
				setDirty( false );
				const t = res.totals;
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: 1: seconds, 2: updated, 3: added, 4: unchanged */
						__( 'Design system generated and saved to Bricks in %1$s seconds: %2$d updated, %3$d added, %4$d unchanged.', 'leapfrog-framework' ),
						res.seconds,
						t.updated,
						t.created,
						t.unchanged
					),
				} );
			} )
			.catch( ( e ) => {
				if ( e.data?.revision ) {
					setData( ( d ) => ( { ...d, revision: e.data.revision } ) );
				}
				setNotice( { status: 'error', text: e.message } );
			} )
			.finally( () => setApplying( false ) );
	};

	const fontList = 'lf-bricks-fonts';
	const contrast = preview?.contrast?.find( ( c ) => c.text === 'text' && c.surface === 'canvas' );

	return (
		<section className="lf-screen lf-screen--scales">
			<h1 className="lf-screen__title">{ __( 'Brand starter', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">{ __( 'Enter your brand basics and Leapfrog generates the whole design system: palette with shades and dark values, colour roles, type and space scales, corners and buttons. Fine-tune anything afterwards on the other screens.', 'leapfrog-framework' ) }</p>

			<div className="lf-starter">
				<div className="lf-starter__form">
					<div className="lf-panel">
						<h2 className="lf-panel__title">{ __( 'Colours', 'leapfrog-framework' ) }</h2>
						<div className="lf-grid">
							<ColourInput label={ __( 'Primary', 'leapfrog-framework' ) } value={ inputs.colors.primary } onChange={ ( v ) => v && set( [ 'colors', 'primary' ], v ) } help={ __( 'Your main brand colour.', 'leapfrog-framework' ) } />
							<ColourInput label={ __( 'Secondary', 'leapfrog-framework' ) } value={ inputs.colors.secondary } optional onChange={ ( v ) => set( [ 'colors', 'secondary' ], v ) } />
							<ColourInput label={ __( 'Accent', 'leapfrog-framework' ) } value={ inputs.colors.accent } optional onChange={ ( v ) => set( [ 'colors', 'accent' ], v ) } help={ __( 'For highlights and calls to action.', 'leapfrog-framework' ) } />
							<ColourInput label={ __( 'Neutral', 'leapfrog-framework' ) } value={ inputs.colors.neutral } optional onChange={ ( v ) => set( [ 'colors', 'neutral' ], v ) } help={ __( 'Leave empty for a grey tinted with your primary colour.', 'leapfrog-framework' ) } />
						</div>
					</div>

					<div className="lf-panel">
						<h2 className="lf-panel__title">{ __( 'Fonts and type', 'leapfrog-framework' ) }</h2>
						<datalist id={ fontList }>{ data.fonts.map( ( f ) => <option key={ f } value={ f } /> ) }</datalist>
						<div className="lf-grid">
							<TextControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Heading font', 'leapfrog-framework' ) } list={ fontList } value={ inputs.fonts.heading } placeholder="System font" onChange={ ( v ) => set( [ 'fonts', 'heading' ], v ) } />
							<TextControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Body font', 'leapfrog-framework' ) } list={ fontList } value={ inputs.fonts.body } placeholder="System font" onChange={ ( v ) => set( [ 'fonts', 'body' ], v ) } />
							<TextControl __nextHasNoMarginBottom __next40pxDefaultSize type="number" min="10" max="30" label={ __( 'Body text on small screens (px)', 'leapfrog-framework' ) } value={ inputs.text.minSize } onChange={ ( v ) => set( [ 'text', 'minSize' ], Number( v ) ) } />
							<TextControl __nextHasNoMarginBottom __next40pxDefaultSize type="number" min="10" max="30" label={ __( 'Body text on large screens (px)', 'leapfrog-framework' ) } value={ inputs.text.maxSize } onChange={ ( v ) => set( [ 'text', 'maxSize' ], Number( v ) ) } />
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Heading contrast', 'leapfrog-framework' ) }
								value={ inputs.text.contrast }
								options={ [
									{ value: 'calm', label: __( 'Calm (headings close to body size)', 'leapfrog-framework' ) },
									{ value: 'balanced', label: __( 'Balanced', 'leapfrog-framework' ) },
									{ value: 'bold', label: __( 'Bold (large headings)', 'leapfrog-framework' ) },
								] }
								onChange={ ( v ) => set( [ 'text', 'contrast' ], v ) }
							/>
						</div>
						<p className="lf-hint">{ __( 'Add the font files in Bricks > Custom Fonts so the site loads them; names added there are suggested above.', 'leapfrog-framework' ) }</p>
					</div>

					<div className="lf-panel">
						<h2 className="lf-panel__title">{ __( 'Space, shape and dark backgrounds', 'leapfrog-framework' ) }</h2>
						<div className="lf-grid">
							<SelectControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Spacing', 'leapfrog-framework' ) } value={ inputs.space } options={ [ { value: 'compact', label: __( 'Compact', 'leapfrog-framework' ) }, { value: 'comfortable', label: __( 'Comfortable', 'leapfrog-framework' ) }, { value: 'spacious', label: __( 'Spacious', 'leapfrog-framework' ) } ] } onChange={ ( v ) => set( [ 'space' ], v ) } />
							<SelectControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Corners', 'leapfrog-framework' ) } value={ inputs.corners } options={ [ { value: 'sharp', label: __( 'Sharp', 'leapfrog-framework' ) }, { value: 'soft', label: __( 'Soft', 'leapfrog-framework' ) }, { value: 'round', label: __( 'Round', 'leapfrog-framework' ) } ] } onChange={ ( v ) => set( [ 'corners' ], v ) } />
							<SelectControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Buttons', 'leapfrog-framework' ) } value={ inputs.buttons } options={ [ { value: 'rounded', label: __( 'Rounded corners', 'leapfrog-framework' ) }, { value: 'pill', label: __( 'Pill', 'leapfrog-framework' ) } ] } onChange={ ( v ) => set( [ 'buttons' ], v ) } />
							<SelectControl __nextHasNoMarginBottom __next40pxDefaultSize label={ __( 'Dark backgrounds', 'leapfrog-framework' ) } value={ inputs.darkBackgrounds } options={ [ { value: 'neutral', label: __( 'Neutral grey', 'leapfrog-framework' ) }, { value: 'brand', label: __( 'Brand colour (deep primary)', 'leapfrog-framework' ) } ] } onChange={ ( v ) => set( [ 'darkBackgrounds' ], v ) } />
						</div>
					</div>
				</div>

				<div className="lf-starter__preview" aria-live="polite">
					{ ! preview && <Spinner /> }
					<Sample preview={ preview } mode="light" />
					<Sample preview={ preview } mode="dark" />
					{ contrast && (
						<p className="lf-hint">
							{ sprintf( /* translators: 1: light ratio, 2: dark ratio */ __( 'Body text contrast: %1$s:1 light, %2$s:1 dark.', 'leapfrog-framework' ), contrast.light.toFixed( 1 ), contrast.dark.toFixed( 1 ) ) }
						</p>
					) }
				</div>
			</div>

			<div className="lf-savebar" role="status" aria-live="polite">
				{ notice ? (
					<span className={ `lf-savebar__message is-${ notice.status }` }>{ notice.text }</span>
				) : (
					<span className="lf-muted">{ dirty ? __( 'Preview only, nothing saved yet', 'leapfrog-framework' ) : __( 'Showing the current design system', 'leapfrog-framework' ) }</span>
				) }
				<Button variant="primary" onClick={ apply } isBusy={ applying } disabled={ applying }>
					{ __( 'Generate design system', 'leapfrog-framework' ) }
				</Button>
			</div>
		</section>
	);
}
