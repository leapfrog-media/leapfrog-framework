import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner, TextControl, ToggleControl } from '@wordpress/components';

const GROUPS = [
	{ id: 'brand', label: __( 'Brand', 'leapfrog-framework' ) },
	{ id: 'neutral', label: __( 'Neutral', 'leapfrog-framework' ) },
	{ id: 'status', label: __( 'Status', 'leapfrog-framework' ) },
	{ id: 'base', label: __( 'White and black', 'leapfrog-framework' ) },
	{ id: 'custom', label: __( 'Custom', 'leapfrog-framework' ) },
];

const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

function normaliseHex( value ) {
	const match = String( value || '' ).trim().match( HEX );
	if ( ! match ) {
		return '';
	}
	let hex = match[ 1 ].toLowerCase();
	if ( hex.length === 3 ) {
		hex = hex.split( '' ).map( ( c ) => c + c ).join( '' );
	}
	return '#' + hex;
}

function toKey( label ) {
	return String( label || '' )
		.toLowerCase()
		.replace( /[^a-z]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 32 );
}

function rating( ratio ) {
	if ( ratio >= 7 ) {
		return { label: 'AAA', tone: 'good' };
	}
	if ( ratio >= 4.5 ) {
		return { label: 'AA', tone: 'good' };
	}
	if ( ratio >= 3 ) {
		return { label: __( 'Large text', 'leapfrog-framework' ), tone: 'warn' };
	}
	return { label: __( 'Fail', 'leapfrog-framework' ), tone: 'bad' };
}

function Contrast( { label, ratio } ) {
	if ( ratio === null || ratio === undefined ) {
		return null;
	}
	const r = rating( ratio );
	return (
		<span className={ `lf-contrast is-${ r.tone }` }>
			{ label } <strong>{ ratio.toFixed( 2 ) }</strong> { r.label }
		</span>
	);
}

/**
 * One editable hex value: native colour picker + text field.
 */
function HexField( { label, value, onChange, placeholder } ) {
	const [ text, setText ] = useState( value );
	useEffect( () => setText( value ), [ value ] );

	return (
		<div className="lf-hex">
			<input
				type="color"
				aria-label={ label }
				value={ normaliseHex( value ) || normaliseHex( placeholder ) || '#000000' }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				hideLabelFromVision
				value={ text }
				placeholder={ placeholder }
				onChange={ setText }
				onBlur={ () => {
					const hex = normaliseHex( text );
					if ( hex ) {
						onChange( hex );
					} else {
						setText( value );
					}
				} }
			/>
		</div>
	);
}

function ColourRow( { item, gen, onChange, onRemove } ) {
	const set = ( patch ) => onChange( { ...item, ...patch } );
	const isBase = item.group === 'base';
	const isNeutral = item.key === 'neutral';

	return (
		<article className="lf-colour" aria-labelledby={ `lf-colour-${ item.key }` }>
			<header className="lf-colour__head">
				<span className="lf-colour__chip" style={ { background: gen?.base } } aria-hidden="true" />
				<div className="lf-colour__name">
					<h3 id={ `lf-colour-${ item.key }` }>{ item.label }</h3>
					<code>--{ item.key }</code>
				</div>
				{ gen && (
					<div className="lf-colour__contrast">
						<Contrast label={ __( 'on white', 'leapfrog-framework' ) } ratio={ gen.contrast.white } />
						<Contrast label={ __( 'on black', 'leapfrog-framework' ) } ratio={ gen.contrast.black } />
					</div>
				) }
				{ ! gen?.locked && (
					<Button variant="tertiary" isDestructive onClick={ onRemove }>
						{ __( 'Remove', 'leapfrog-framework' ) }
					</Button>
				) }
			</header>

			<div className="lf-colour__controls">
				<div className="lf-field">
					<span className="lf-field__label">{ __( 'Colour', 'leapfrog-framework' ) }</span>
					<HexField
						label={ sprintf( /* translators: %s colour name */ __( '%s colour', 'leapfrog-framework' ), item.label ) }
						value={ item.value }
						placeholder={ isNeutral ? gen?.base : '' }
						onChange={ ( value ) => set( { value } ) }
					/>
					{ isNeutral && (
						<span className="lf-hint">
							{ item.value
								? <Button variant="link" onClick={ () => set( { value: '' } ) }>{ __( 'Use automatic grey', 'leapfrog-framework' ) }</Button>
								: __( 'Automatic: a grey tinted with your primary colour.', 'leapfrog-framework' ) }
						</span>
					) }
				</div>

				{ ! isBase && (
					<div className="lf-field">
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Dark mode value', 'leapfrog-framework' ) }
							checked={ item.darkMode }
							onChange={ ( darkMode ) => set( { darkMode } ) }
						/>
						{ item.darkMode && (
							<>
								<HexField
									label={ sprintf( /* translators: %s colour name */ __( '%s dark mode colour', 'leapfrog-framework' ), item.label ) }
									value={ item.dark }
									placeholder={ gen?.dark || '' }
									onChange={ ( dark ) => set( { dark } ) }
								/>
								<span className="lf-hint">
									{ item.dark
										? <Button variant="link" onClick={ () => set( { dark: '' } ) }>{ __( 'Use automatic', 'leapfrog-framework' ) }</Button>
										: __( 'Automatic: lighter, for dark backgrounds.', 'leapfrog-framework' ) }
								</span>
								{ gen && <Contrast label={ __( 'on black', 'leapfrog-framework' ) } ratio={ gen.contrast.dark } /> }
							</>
						) }
					</div>
				) }

				<div className="lf-field lf-field--toggles">
					{ ! isBase && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Shades 10–90', 'leapfrog-framework' ) }
							checked={ item.shades }
							onChange={ ( shades ) => set( { shades } ) }
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Transparency a10–a90', 'leapfrog-framework' ) }
						checked={ item.alpha }
						onChange={ ( alpha ) => set( { alpha } ) }
					/>
				</div>
			</div>

			{ gen && (
				<div className="lf-swatches">
					<div className="lf-swatch-row" aria-label={ __( 'Base and dark mode', 'leapfrog-framework' ) }>
						<Swatch color={ gen.base } label={ __( 'base', 'leapfrog-framework' ) } title={ gen.base } />
						{ gen.dark && <Swatch color={ gen.dark } label={ __( 'dark', 'leapfrog-framework' ) } title={ gen.dark } onDark /> }
					</div>
					{ Object.keys( gen.shades ).length > 0 && (
						<div className="lf-swatch-row">
							{ Object.entries( gen.shades ).map( ( [ step, hex ] ) => (
								<Swatch key={ step } color={ hex } label={ step } title={ `--${ item.key }-${ step }: ${ hex }` } />
							) ) }
						</div>
					) }
					{ Object.keys( gen.alpha ).length > 0 && (
						<div className="lf-swatch-row">
							{ Object.keys( gen.alpha ).map( ( step ) => (
								<Swatch
									key={ step }
									color={ `color-mix(in oklch, ${ gen.base } ${ step }%, transparent)` }
									label={ `a${ step }` }
									title={ `--${ item.key }-a${ step }` }
									checker
								/>
							) ) }
						</div>
					) }
				</div>
			) }
		</article>
	);
}

function Swatch( { color, label, title, checker, onDark } ) {
	return (
		<div className={ 'lf-swatch' + ( checker ? ' is-checker' : '' ) + ( onDark ? ' is-on-dark' : '' ) } title={ title }>
			<span className="lf-swatch__fill" style={ checker ? { '--lf-swatch-color': color } : { background: color } } />
			<span className="lf-swatch__label">{ label }</span>
		</div>
	);
}

function AddColour( { existing, onAdd } ) {
	const [ label, setLabel ] = useState( '' );
	const [ value, setValue ] = useState( '#14b8a6' );
	const key = toKey( label );
	const taken = existing.includes( key );
	const valid = key && /^[a-z]+(-[a-z]+)*$/.test( key ) && ! taken;

	return (
		<form
			className="lf-add"
			onSubmit={ ( e ) => {
				e.preventDefault();
				if ( valid ) {
					onAdd( { key, label: label.trim(), group: 'custom', value, dark: '', darkMode: true, shades: true, alpha: false } );
					setLabel( '' );
				}
			} }
		>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'New colour name', 'leapfrog-framework' ) }
				value={ label }
				onChange={ setLabel }
				help={
					key
						? ( taken ? __( 'That name is already used.', 'leapfrog-framework' ) : sprintf( /* translators: %s variable */ __( 'Variable: --%s', 'leapfrog-framework' ), key ) )
						: __( 'Letters only, e.g. "Brand pink".', 'leapfrog-framework' )
				}
			/>
			<HexField label={ __( 'New colour value', 'leapfrog-framework' ) } value={ value } onChange={ setValue } />
			<Button variant="secondary" type="submit" disabled={ ! valid }>
				{ __( 'Add colour', 'leapfrog-framework' ) }
			</Button>
		</form>
	);
}

export default function Colours( { restPath, onDirtyChange } ) {
	const [ items, setItems ] = useState( null );
	const [ revision, setRevision ] = useState( 0 );
	const [ generated, setGenerated ] = useState( [] );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const timer = useRef();

	useEffect( () => {
		apiFetch( { path: `${ restPath }/settings` } )
			.then( ( settings ) => {
				setItems( settings.colors.items );
				setRevision( settings.revision );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	// Live preview: regenerate shortly after each change.
	useEffect( () => {
		if ( ! items ) {
			return;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			apiFetch( { path: `${ restPath }/colours/preview`, method: 'POST', data: { items } } )
				.then( ( res ) => setGenerated( res.generated ) )
				.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		}, 200 );
		return () => clearTimeout( timer.current );
	}, [ items, restPath ] );

	useEffect( () => {
		onDirtyChange?.( dirty );
		const warn = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		if ( dirty ) {
			window.addEventListener( 'beforeunload', warn );
		}
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty, onDirtyChange ] );

	const byKey = useMemo( () => {
		const map = {};
		generated.forEach( ( g ) => ( map[ g.key ] = g ) );
		return map;
	}, [ generated ] );

	const update = ( next ) => {
		setItems( next );
		setDirty( true );
		setNotice( null );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/colours/save`, method: 'POST', data: { items, revision } } )
			.then( ( res ) => {
				setItems( res.settings.colors.items );
				setRevision( res.settings.revision );
				setGenerated( res.generated );
				setDirty( false );
				const s = res.summary;
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: 1: created, 2: updated, 3: removed, 4: unchanged */
						__( 'Saved to the Bricks palette "Leapfrog": %1$d added, %2$d updated, %3$d removed, %4$d unchanged.', 'leapfrog-framework' ),
						s.created,
						s.updated,
						s.deleted,
						s.unchanged
					),
				} );
			} )
			.catch( ( e ) => {
				if ( e.data?.settings ) {
					setRevision( e.data.settings.revision );
					setDirty( false );
				}
				setNotice( { status: 'error', text: e.message } );
			} )
			.finally( () => setSaving( false ) );
	};

	if ( ! items ) {
		return notice ? <Notice status={ notice.status } isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	return (
		<section className="lf-screen lf-screen--colours">
			<h1 className="lf-screen__title">{ __( 'Colours', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">
				{ __( 'Shades are fixed values, recalculated when you change a colour. Transparency steps are live, so they follow dark mode. Everything is saved as the Bricks palette "Leapfrog".', 'leapfrog-framework' ) }
			</p>

			{ GROUPS.map( ( group ) => {
				const groupItems = items.filter( ( i ) => i.group === group.id );
				if ( ! groupItems.length && group.id !== 'custom' ) {
					return null;
				}
				return (
					<div className="lf-group" key={ group.id }>
						<h2 className="lf-group__title">{ group.label }</h2>
						{ groupItems.map( ( item ) => (
							<ColourRow
								key={ item.key }
								item={ item }
								gen={ byKey[ item.key ] }
								onChange={ ( next ) => update( items.map( ( i ) => ( i.key === item.key ? next : i ) ) ) }
								onRemove={ () => update( items.filter( ( i ) => i.key !== item.key ) ) }
							/>
						) ) }
						{ group.id === 'custom' && (
							<AddColour existing={ items.map( ( i ) => i.key ) } onAdd={ ( item ) => update( [ ...items, item ] ) } />
						) }
					</div>
				);
			} ) }

			<div className="lf-savebar" role="status" aria-live="polite">
				{ notice ? (
					<span className={ `lf-savebar__message is-${ notice.status }` }>{ notice.text }</span>
				) : (
					<span className="lf-muted">
						{ dirty ? __( 'Unsaved changes', 'leapfrog-framework' ) : __( 'All changes saved', 'leapfrog-framework' ) }
					</span>
				) }
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving || ! dirty }>
					{ __( 'Save to Bricks', 'leapfrog-framework' ) }
				</Button>
			</div>
		</section>
	);
}
