/**
 * One style setting: a value box, a picker of Leapfrog colours or variables that
 * suit the setting, a colour well for colours, and Clear. Empty = not set, so the
 * header's own Bricks styling shows.
 */
import { useMemo, useState } from '@wordpress/element';
import { Button, Popover, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

// Which variables suit each kind of setting.
const SIZE_CATEGORIES = [ 'Space scale', 'Spacing', 'Layout and shape', 'Components', 'Type scale' ];
const VARIABLE_FILTERS = {
	size: ( v ) => SIZE_CATEGORIES.includes( v.category ) && ! /radius|shadow|duration|ease|^z-|opacity|transition|ring|font|weight|color|bg$/.test( v.name ),
	'font-size': ( v ) => v.category === 'Type scale' || ( v.category === 'Typography' && /size|^h\d$/.test( v.name ) ) || /font-size/.test( v.name ),
	weight: ( v ) => /weight/.test( v.name ),
	radius: ( v ) => /radius/.test( v.name ),
	shadow: ( v ) => /shadow/.test( v.name ),
	duration: ( v ) => /duration/.test( v.name ),
};

export function swatchFor( value, tokens ) {
	const v = ( value || '' ).trim();
	if ( /^#[0-9a-f]{3,8}$/i.test( v ) || /^(rgb|hsl)a?\(/i.test( v ) || v === 'transparent' ) {
		return v;
	}
	// var(--a, var(--b, #hex)): the first name with a known colour, else the plain fallback.
	for ( const [ , name ] of v.matchAll( /--([\w-]+)/g ) ) {
		const token = tokens.colors.find( ( c ) => c.name === name );
		if ( token && /^#|^rgb|^hsl/i.test( token.swatch ) ) {
			return token.swatch;
		}
	}
	const fallback = v.match( /#[0-9a-f]{3,8}\b|(rgb|hsl)a?\([^)]*\)/i );
	return fallback ? fallback[ 0 ] : '';
}

export default function StyleField( { id, field, value, tokens, onChange, onFocus } ) {
	const [ open, setOpen ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ anchor, setAnchor ] = useState( null );
	const isColor = field.kind === 'color';

	const options = useMemo( () => {
		if ( field.choices ) {
			return [];
		}
		const q = search.trim().toLowerCase();
		const list = isColor
			? tokens.colors.map( ( c ) => ( { name: c.name, value: c.value, group: c.palette, swatch: c.swatch } ) )
			: tokens.variables.filter( VARIABLE_FILTERS[ field.kind ] || VARIABLE_FILTERS.size ).map( ( v ) => ( { name: v.name, value: v.value, group: v.category } ) );
		return q ? list.filter( ( o ) => o.name.includes( q ) ) : list;
	}, [ tokens, field, search ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( field.choices ) {
		return (
			<div className="lfh-field" onFocus={ onFocus }>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					value={ value }
					options={ [
						...( '' in field.choices ? [] : [ { label: __( 'Not set', 'leapfrog-framework' ), value: '' } ] ),
						...Object.entries( field.choices ).map( ( [ v, [ label ] ] ) => ( { label, value: v } ) ),
					] }
					onChange={ onChange }
				/>
			</div>
		);
	}

	const swatch = isColor ? swatchFor( value, tokens ) : '';

	return (
		<div className={ 'lfh-field' + ( value ? ' is-set' : '' ) } onFocus={ onFocus }>
			<label className="lfh-field__label" htmlFor={ id }>
				<span>{ field.label }</span>
				{ value && (
					<button type="button" className="lfh-field__reset" onClick={ () => onChange( '' ) } title={ __( 'Clear: Bricks decides again', 'leapfrog-framework' ) }>
						{ __( 'Clear', 'leapfrog-framework' ) }
					</button>
				) }
			</label>
			<div className="lfh-field__row">
				{ isColor && (
					<span className="lfh-swatch" style={ swatch ? { background: swatch } : undefined }>
						<input
							type="color"
							aria-label={ sprintf( __( 'Pick a colour: %s', 'leapfrog-framework' ), field.label ) }
							value={ /^#[0-9a-f]{6}$/i.test( swatch ) ? swatch : '#000000' }
							onChange={ ( e ) => onChange( e.target.value ) }
						/>
					</span>
				) }
				<input
					id={ id }
					className="lfh-field__input"
					type="text"
					value={ value }
					placeholder={ field.suggest ? sprintf( __( 'Not set (try %s)', 'leapfrog-framework' ), field.suggest ) : __( 'Not set', 'leapfrog-framework' ) }
					spellCheck="false"
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
				<Button
					ref={ setAnchor }
					className="lfh-field__pick"
					icon="list-view"
					label={ isColor ? __( 'Leapfrog colours', 'leapfrog-framework' ) : __( 'Leapfrog variables', 'leapfrog-framework' ) }
					onClick={ () => setOpen( ! open ) }
					aria-expanded={ open }
				/>
			</div>
			{ open && (
				<Popover anchor={ anchor } placement="bottom-end" onClose={ () => setOpen( false ) } className="lfh-picker">
					<div className="lfh-picker__inner">
						<input
							className="lfh-picker__search"
							type="search"
							placeholder={ __( 'Search', 'leapfrog-framework' ) }
							value={ search }
							onChange={ ( e ) => setSearch( e.target.value ) }
							autoFocus // eslint-disable-line jsx-a11y/no-autofocus
						/>
						<ul className="lfh-picker__list" role="listbox" aria-label={ field.label }>
							{ options.length === 0 && <li className="lfh-picker__empty">{ __( 'Nothing matches.', 'leapfrog-framework' ) }</li> }
							{ options.map( ( o, i ) => (
								<li key={ o.value + i }>
									{ ( i === 0 || options[ i - 1 ].group !== o.group ) && o.group && <span className="lfh-picker__group">{ o.group }</span> }
									<button
										type="button"
										role="option"
										aria-selected={ value === o.value }
										className={ 'lfh-picker__option' + ( value === o.value ? ' is-current' : '' ) }
										onClick={ () => {
											onChange( o.value );
											setOpen( false );
										} }
									>
										{ isColor && <span className="lfh-swatch lfh-swatch--small" style={ o.swatch ? { background: o.swatch } : undefined } /> }
										<span>{ o.name }</span>
									</button>
								</li>
							) ) }
						</ul>
					</div>
				</Popover>
			) }
		</div>
	);
}
