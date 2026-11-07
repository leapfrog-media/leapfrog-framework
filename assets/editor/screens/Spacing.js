import { __, sprintf } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';

import useScales from './useScales';
import { ScaleEditor, ScaleNotices, SaveBar, StepSelect, px } from './ScaleParts';

const ROLES = [
	{ key: 'gutter', label: __( 'Page gutter', 'leapfrog-framework' ), variable: '--gutter', help: __( 'Space at the left and right of the page.', 'leapfrog-framework' ) },
	{ key: 'gap', label: __( 'Gap', 'leapfrog-framework' ), variable: '--gap', help: __( 'Default gap in grids and rows.', 'leapfrog-framework' ) },
	{ key: 'flow', label: __( 'Flow', 'leapfrog-framework' ), variable: '--flow', help: __( 'Space between paragraphs and blocks of content.', 'leapfrog-framework' ) },
	{ key: 'section', label: __( 'Section space', 'leapfrog-framework' ), variable: '--section-space', help: __( 'Top and bottom padding of a section.', 'leapfrog-framework' ) },
	{ key: 'sectionCompact', label: __( 'Section space, compact', 'leapfrog-framework' ), variable: '--section-space-compact' },
	{ key: 'sectionSpacious', label: __( 'Section space, spacious', 'leapfrog-framework' ), variable: '--section-space-spacious' },
	{ key: 'stackGap', label: __( 'Stack gap', 'leapfrog-framework' ), variable: '--stack-gap', help: __( 'Gap between items stacked in a column.', 'leapfrog-framework' ) },
	{ key: 'clusterGap', label: __( 'Cluster gap', 'leapfrog-framework' ), variable: '--cluster-gap', help: __( 'Gap between items in a wrapping row, like tags or buttons.', 'leapfrog-framework' ) },
	{ key: 'listGap', label: __( 'List gap', 'leapfrog-framework' ), variable: '--list-gap', help: __( 'Space between list items.', 'leapfrog-framework' ) },
	{ key: 'inlineGap', label: __( 'Inline gap', 'leapfrog-framework' ), variable: '--inline-gap', help: __( 'Gap between an icon and its text.', 'leapfrog-framework' ) },
	{ key: 'panelPadding', label: __( 'Panel padding', 'leapfrog-framework' ), variable: '--panel-padding', help: __( 'Padding inside panels, sidebars and boxed areas.', 'leapfrog-framework' ) },
];

export default function Spacing( { restPath, onDirtyChange } ) {
	const state = useScales( restPath, onDirtyChange );
	const { data, spacing: s, preview, setSpacing, notice } = state;

	if ( ! data || ! s ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const rows = preview?.spacing || [];
	const sm = data.styleManager;
	const largest = Math.max( 1, ...rows.map( ( r ) => r.maxPx || 0 ) );
	const width = ( value ) => `${ Math.max( 2, ( value / largest ) * 100 ) }%`;

	return (
		<section className="lf-screen lf-screen--scales">
			<h1 className="lf-screen__title">{ __( 'Spacing', 'leapfrog-framework' ) }</h1>
			<p className="lf-muted">
				{ sprintf(
					/* translators: 1: smallest screen px, 2: largest screen px */
					__( 'A fluid space scale for padding, gaps and margins, from the smallest screen (%1$dpx) to the largest (%2$dpx).', 'leapfrog-framework' ),
					sm.minScreenWidth,
					sm.maxScreenWidth
				) }
			</p>

			<ScaleNotices data={ data } kind="spacing" />

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Space scale', 'leapfrog-framework' ) }</h2>
				<ScaleEditor scale={ s } onChange={ setSpacing } steps={ data.steps } ratios={ data.ratios } unitLabel={ __( 'Space', 'leapfrog-framework' ) } />

				<table className="lf-table lf-bars">
					<thead>
						<tr>
							<th scope="col">{ __( 'Variable', 'leapfrog-framework' ) }</th>
							<th scope="col">{ __( 'Size', 'leapfrog-framework' ) }</th>
							<th scope="col">{ sprintf( /* translators: 1: small px, 2: large px */ __( 'At %1$dpx and %2$dpx', 'leapfrog-framework' ), sm.minScreenWidth, sm.maxScreenWidth ) }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.name } className={ row.step === 'm' ? 'is-base' : '' }>
								<td><code>--{ row.name }</code></td>
								<td className="lf-nowrap">
									{ px( row.minPx ) } → { px( row.maxPx ) }
									{ row.reversed && <span className="lf-flag">{ __( 'shrinks', 'leapfrog-framework' ) }</span> }
								</td>
								<td className="lf-bar-cell">
									<span className="lf-bar is-min" style={ { width: width( row.minPx ) } } />
									<span className="lf-bar is-max" style={ { width: width( row.maxPx ) } } />
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="lf-panel">
				<h2 className="lf-panel__title">{ __( 'Layout spacing', 'leapfrog-framework' ) }</h2>
				<p className="lf-muted">{ __( 'Each role is a variable that points at a step. Use these in Bricks instead of raw steps where you can.', 'leapfrog-framework' ) }</p>
				<div className="lf-grid">
					{ ROLES.map( ( r ) => (
						<StepSelect
							key={ r.key }
							label={ `${ r.label } · ${ r.variable }` }
							help={ r.help }
							value={ s.roles[ r.key ] }
							steps={ s.steps }
							onChange={ ( v ) => setSpacing( { ...s, roles: { ...s.roles, [ r.key ]: v } } ) }
						/>
					) ) }
				</div>
			</div>

			<SaveBar state={ state } />
		</section>
	);
}
