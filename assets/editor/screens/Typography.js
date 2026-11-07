import { __, sprintf } from '@wordpress/i18n';
import { Spinner, Notice, TextControl, SelectControl } from '@wordpress/components';

import useScales from './useScales';
import { ScaleEditor, ScaleNotices, SaveBar, StepSelect, px } from './ScaleParts';

const HEADINGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];
const SIZES = [
	{ key: 'body', label: __( 'Body text', 'leapfrog-framework' ), variable: '--body-size' },
	{ key: 'lead', label: __( 'Lead / intro text', 'leapfrog-framework' ), variable: '--lead-size' },
	{ key: 'small', label: __( 'Small text', 'leapfrog-framework' ), variable: '--small-size' },
	{ key: 'caption', label: __( 'Captions', 'leapfrog-framework' ), variable: '--caption-size' },
	{ key: 'label', label: __( 'Labels', 'leapfrog-framework' ), variable: '--label-size' },
	{ key: 'quote', label: __( 'Quotes', 'leapfrog-framework' ), variable: '--quote-size' },
];
const FONTS = [
	{ key: 'body', label: __( 'Body font', 'leapfrog-framework' ) },
	{ key: 'heading', label: __( 'Heading font', 'leapfrog-framework' ) },
	{ key: 'display', label: __( 'Display font (hero titles)', 'leapfrog-framework' ) },
	{ key: 'mono', label: __( 'Code font', 'leapfrog-framework' ) },
];
const LINE_HEIGHTS = [
	{ key: 'body', label: __( 'Body line height', 'leapfrog-framework' ) },
	{ key: 'heading', label: __( 'Heading line height', 'leapfrog-framework' ) },
	{ key: 'display', label: __( 'Display line height', 'leapfrog-framework' ) },
];
const WEIGHTS = [
	{ key: 'regular', label: __( 'Regular', 'leapfrog-framework' ) },
	{ key: 'medium', label: __( 'Medium', 'leapfrog-framework' ) },
	{ key: 'semibold', label: __( 'Semibold', 'leapfrog-framework' ) },
	{ key: 'bold', label: __( 'Bold', 'leapfrog-framework' ) },
];
const ROLE_WEIGHTS = [
	{ key: 'heading', label: __( 'Heading weight', 'leapfrog-framework' ) },
	{ key: 'label', label: __( 'Label weight', 'leapfrog-framework' ) },
];
const LETTER_SPACING = [
	{ key: 'body', label: __( 'Body letter spacing', 'leapfrog-framework' ) },
	{ key: 'heading', label: __( 'Heading letter spacing', 'leapfrog-framework' ) },
];

export default function Typography( { restPath, onDirtyChange } ) {
	const state = useScales( restPath, onDirtyChange );
	const { data, typography: t, preview, setTypography, notice } = state;

	if ( ! data || ! t ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const set = ( patch ) => setTypography( { ...t, ...patch } );
	const setMap = ( map, key, value ) => set( { [ map ]: { ...t[ map ], [ key ]: value } } );
	const rows = preview?.typography || [];
	const sm = data.styleManager;

	return (
		<section className="lf-screen lf-screen--scales">
			<h1 className="lf-screen__title">{ __( 'Typography', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">
				{ sprintf(
					/* translators: 1: smallest screen px, 2: largest screen px */
					__( 'A fluid type scale: each step grows smoothly from the smallest screen (%1$dpx) to the largest (%2$dpx). Bricks does the maths, so this matches Bricks’ Style Manager exactly.', 'leapfrog-framework' ),
					sm.minScreenWidth,
					sm.maxScreenWidth
				) }
			</p>

			<ScaleNotices data={ data } kind="typography" />

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Type scale', 'leapfrog-framework' ) }</h2>
				<ScaleEditor scale={ t } onChange={ setTypography } steps={ data.steps } ratios={ data.ratios } unitLabel={ __( 'Text size', 'leapfrog-framework' ) } />

				<table className="lf-table lf-specimens">
					<thead>
						<tr>
							<th scope="col">{ __( 'Variable', 'leapfrog-framework' ) }</th>
							<th scope="col">{ __( 'Size', 'leapfrog-framework' ) }</th>
							<th scope="col">{ sprintf( /* translators: %d px */ __( 'At %dpx', 'leapfrog-framework' ), sm.minScreenWidth ) }</th>
							<th scope="col">{ sprintf( /* translators: %d px */ __( 'At %dpx', 'leapfrog-framework' ), sm.maxScreenWidth ) }</th>
						</tr>
					</thead>
					<tbody>
						{ [ ...rows ].reverse().map( ( row ) => (
							<tr key={ row.name } className={ row.step === 'm' ? 'is-base' : '' }>
								<td><code>--{ row.name }</code></td>
								<td className="lf-nowrap">
									{ px( row.minPx ) } → { px( row.maxPx ) }
									{ row.reversed && <span className="lf-flag" title={ __( 'Smaller on large screens than on small ones, because the two ratios cross here.', 'leapfrog-framework' ) }>{ __( 'shrinks', 'leapfrog-framework' ) }</span> }
								</td>
								<td><span className="lf-specimen" style={ { fontSize: `${ row.minPx }px`, fontFamily: t.fonts.heading } }>{ __( 'Leap ahead', 'leapfrog-framework' ) }</span></td>
								<td><span className="lf-specimen" style={ { fontSize: `${ row.maxPx }px`, fontFamily: t.fonts.heading } }>{ __( 'Leap ahead', 'leapfrog-framework' ) }</span></td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Headings and text sizes', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Each role is a variable that points at a step, so changing the scale updates them all.', 'leapfrog-framework' ) }</p>
				<div className="lf-grid">
					{ HEADINGS.map( ( h ) => (
						<StepSelect key={ h } label={ `${ h.toUpperCase() } · --${ h }` } value={ t.headings[ h ] } steps={ t.steps } onChange={ ( v ) => setMap( 'headings', h, v ) } />
					) ) }
					{ SIZES.map( ( s ) => (
						<StepSelect key={ s.key } label={ `${ s.label } · ${ s.variable }` } value={ t.sizes[ s.key ] } steps={ t.steps } onChange={ ( v ) => setMap( 'sizes', s.key, v ) } />
					) ) }
				</div>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Fonts and line heights', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Font stacks as CSS, e.g. "Inter", system-ui, sans-serif. Add the font files themselves in Bricks > Custom Fonts.', 'leapfrog-framework' ) }</p>
				<div className="lf-grid lf-grid--wide">
					{ FONTS.map( ( f ) => (
						<TextControl
							key={ f.key }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ `${ f.label } · --font-${ f.key }` }
							value={ t.fonts[ f.key ] }
							onChange={ ( v ) => setMap( 'fonts', f.key, v ) }
						/>
					) ) }
					{ LINE_HEIGHTS.map( ( l ) => (
						<TextControl
							key={ l.key }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="number"
							step="0.05"
							min="0.8"
							max="2.5"
							label={ `${ l.label } · --line-height-${ l.key }` }
							value={ t.lineHeights[ l.key ] }
							onChange={ ( v ) => setMap( 'lineHeights', l.key, Number( v ) ) }
						/>
					) ) }
				</div>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Weights and letter spacing', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Named weights (100 to 900) for use anywhere, and which of them headings and labels use. Variable fonts accept any number in between.', 'leapfrog-framework' ) }</p>
				<div className="lf-grid">
					{ WEIGHTS.map( ( w ) => (
						<TextControl
							key={ w.key }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="number"
							step="50"
							min="100"
							max="900"
							label={ `${ w.label } · --font-weight-${ w.key }` }
							value={ t.weights[ w.key ] }
							onChange={ ( v ) => setMap( 'weights', w.key, Number( v ) ) }
						/>
					) ) }
					{ ROLE_WEIGHTS.map( ( r ) => (
						<SelectControl
							key={ r.key }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ `${ r.label } · --${ r.key }-weight` }
							value={ t.roleWeights[ r.key ] }
							options={ WEIGHTS.map( ( w ) => ( { value: w.key, label: `${ w.label } (${ t.weights[ w.key ] })` } ) ) }
							onChange={ ( v ) => setMap( 'roleWeights', r.key, v ) }
						/>
					) ) }
					{ LETTER_SPACING.map( ( l ) => (
						<TextControl
							key={ l.key }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ `${ l.label } · --${ l.key }-letter-spacing` }
							help={ __( 'For example 0em, -0.01em or 0.02em.', 'leapfrog-framework' ) }
							value={ t.letterSpacing[ l.key ] }
							onChange={ ( v ) => setMap( 'letterSpacing', l.key, v ) }
						/>
					) ) }
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Code size · --code-size', 'leapfrog-framework' ) }
						help={ __( 'Relative to the surrounding text, for example 0.9em.', 'leapfrog-framework' ) }
						value={ t.codeSize }
						onChange={ ( v ) => set( { codeSize: v } ) }
					/>
				</div>
			</div>

			<SaveBar state={ state } />
		</section>
	);
}
