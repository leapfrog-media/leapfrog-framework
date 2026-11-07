import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, SelectControl, TextControl } from '@wordpress/components';

function ratioKey( ratios, value ) {
	const key = String( Number( Number( value ).toFixed( 3 ) ) );
	return ratios[ key ] ? key : 'custom';
}

/**
 * A named ratio (Major third…) or a custom number.
 */
function RatioField( { label, value, ratios, onChange } ) {
	const [ custom, setCustom ] = useState( ratioKey( ratios, value ) === 'custom' );
	const options = [
		...Object.entries( ratios ).map( ( [ v, name ] ) => ( { value: v, label: `${ v } · ${ name }` } ) ),
		{ value: 'custom', label: __( 'Custom…', 'leapfrog-framework' ) },
	];

	return (
		<div className="lf-ratio">
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				value={ custom ? 'custom' : ratioKey( ratios, value ) }
				options={ options }
				onChange={ ( v ) => {
					setCustom( v === 'custom' );
					if ( v !== 'custom' ) {
						onChange( Number( v ) );
					}
				} }
			/>
			{ custom && (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="number"
					step="0.001"
					min="1"
					max="3"
					label={ sprintf( /* translators: %s field label */ __( '%s (custom)', 'leapfrog-framework' ), label ) }
					value={ value }
					onChange={ ( v ) => onChange( Number( v ) ) }
				/>
			) }
		</div>
	);
}

/**
 * Size and ratio controls shared by the type and space scales.
 */
export function ScaleEditor( { scale, onChange, steps, ratios, unitLabel } ) {
	const set = ( patch ) => onChange( { ...scale, ...patch } );
	const first = scale.steps[ 0 ];
	const last = scale.steps[ scale.steps.length - 1 ];
	const mIndex = steps.indexOf( 'm' );
	const range = ( from, to ) => steps.slice( steps.indexOf( from ), steps.indexOf( to ) + 1 );

	return (
		<div className="lf-scale-editor">
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				type="number"
				min="4"
				max="200"
				label={ sprintf( /* translators: %s e.g. "Text size" */ __( '%s at m, smallest screen (px)', 'leapfrog-framework' ), unitLabel ) }
				value={ scale.minSize }
				onChange={ ( v ) => set( { minSize: Number( v ) } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				type="number"
				min="4"
				max="200"
				label={ sprintf( /* translators: %s e.g. "Text size" */ __( '%s at m, largest screen (px)', 'leapfrog-framework' ), unitLabel ) }
				value={ scale.maxSize }
				onChange={ ( v ) => set( { maxSize: Number( v ) } ) }
			/>
			<RatioField label={ __( 'Ratio, smallest screen', 'leapfrog-framework' ) } value={ scale.minRatio } ratios={ ratios } onChange={ ( minRatio ) => set( { minRatio } ) } />
			<RatioField label={ __( 'Ratio, largest screen', 'leapfrog-framework' ) } value={ scale.maxRatio } ratios={ ratios } onChange={ ( maxRatio ) => set( { maxRatio } ) } />
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Smallest step', 'leapfrog-framework' ) }
				value={ first }
				options={ steps.slice( 0, mIndex + 1 ).map( ( s ) => ( { value: s, label: s } ) ) }
				onChange={ ( v ) => set( { steps: range( v, last ) } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Largest step', 'leapfrog-framework' ) }
				value={ last }
				options={ steps.slice( mIndex ).map( ( s ) => ( { value: s, label: s } ) ) }
				onChange={ ( v ) => set( { steps: range( first, v ) } ) }
			/>
		</div>
	);
}

export function StepSelect( { label, value, steps, onChange, help } ) {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			help={ help }
			value={ value }
			options={ steps.map( ( s ) => ( { value: s, label: s } ) ) }
			onChange={ onChange }
		/>
	);
}

export function SaveBar( { state } ) {
	const { notice, dirty, saving, save } = state;
	return (
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
	);
}

export function ScaleNotices( { data, kind } ) {
	return (
		<>
			{ data.manual[ kind ] && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'This scale uses manual values set in Bricks’ Style Manager. Leapfrog shows the automatic scale; saving here replaces the manual values.', 'leapfrog-framework' ) }
				</Notice>
			) }
			{ ! data.inBricks[ kind ] && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'Not in Bricks yet. Save to create the scale in Bricks’ Style Manager.', 'leapfrog-framework' ) }
				</Notice>
			) }
		</>
	);
}

export function px( n ) {
	return n === null || n === undefined ? '—' : `${ Number( n ).toFixed( n < 10 ? 1 : 0 ) }px`;
}
