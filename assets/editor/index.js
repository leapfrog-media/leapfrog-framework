/**
 * Leapfrog Framework editor.
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';

import BrandStarter from './screens/BrandStarter';
import BricksData from './screens/BricksData';
import ExportPresets from './screens/ExportPresets';
import Review from './screens/Review';
import Colours from './screens/Colours';
import Components from './screens/Components';
import Placeholder from './screens/Placeholder';
import Roles from './screens/Roles';
import Settings from './screens/Settings';
import Shape from './screens/Shape';
import Spacing from './screens/Spacing';
import Typography from './screens/Typography';
import './editor.css';

const config = window.leapfrogFramework || {};

// Screens from the spec, in order. `milestone` = when the screen is built.
const SCREENS = [
	{ id: 'starter', label: __( 'Brand starter', 'leapfrog-framework' ), milestone: 6 },
	{ id: 'colours', label: __( 'Colours', 'leapfrog-framework' ), milestone: 2 },
	{ id: 'typography', label: __( 'Typography', 'leapfrog-framework' ), milestone: 3 },
	{ id: 'spacing', label: __( 'Spacing', 'leapfrog-framework' ), milestone: 3 },
	{ id: 'shape', label: __( 'Layout and shape', 'leapfrog-framework' ), milestone: 4 },
	{ id: 'roles', label: __( 'Roles and themes', 'leapfrog-framework' ), milestone: 4 },
	{ id: 'components', label: __( 'Components', 'leapfrog-framework' ), milestone: 5 },
	{ id: 'review', label: __( 'Review and save', 'leapfrog-framework' ), milestone: 6 },
	{ id: 'export', label: __( 'Export and presets', 'leapfrog-framework' ), milestone: 6 },
	{ id: 'settings', label: __( 'Settings', 'leapfrog-framework' ), milestone: 3 },
	{ id: 'bricks', label: __( 'Bricks data', 'leapfrog-framework' ), milestone: 1 },
];

// Screens that exist so far; the rest show a placeholder.
const SCREEN_COMPONENTS = {
	starter: BrandStarter,
	colours: Colours,
	typography: Typography,
	spacing: Spacing,
	shape: Shape,
	roles: Roles,
	components: Components,
	review: Review,
	export: ExportPresets,
	settings: Settings,
};
const BUILT = [ ...Object.keys( SCREEN_COMPONENTS ), 'bricks' ];

function App() {
	const [ screen, setScreen ] = useState( 'starter' );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ dirty, setDirty ] = useState( false );

	const go = ( id ) => {
		if ( id === screen ) {
			return;
		}
		// eslint-disable-next-line no-alert
		if ( dirty && ! window.confirm( __( 'You have unsaved changes on this screen. Leave without saving?', 'leapfrog-framework' ) ) ) {
			return;
		}
		setDirty( false );
		setScreen( id );
	};

	useEffect( () => {
		apiFetch( { path: `${ config.restPath }/status` } )
			.then( setStatus )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	}, [] );

	const current = SCREENS.find( ( s ) => s.id === screen );

	return (
		<div className="lf-app">
			<header className="lf-header">
				<div className="lf-header__brand">
					<span className="lf-header__logo" aria-hidden="true">LF</span>
					<span className="lf-header__title">{ __( 'Leapfrog Framework', 'leapfrog-framework' ) }</span>
					<span className="lf-header__version">v{ config.version }</span>
				</div>
				<div className="lf-header__site">{ config.siteName }</div>
				<Button variant="tertiary" href={ config.dashboardUrl }>
					{ __( 'Back to WordPress', 'leapfrog-framework' ) }
				</Button>
			</header>

			<div className="lf-body">
				<nav className="lf-nav" aria-label={ __( 'Framework screens', 'leapfrog-framework' ) }>
					<ul>
						{ SCREENS.map( ( s ) => (
							<li key={ s.id }>
								<button
									type="button"
									className={ 'lf-nav__item' + ( s.id === screen ? ' is-active' : '' ) + ( BUILT.includes( s.id ) ? '' : ' is-later' ) }
									aria-current={ s.id === screen ? 'page' : undefined }
									onClick={ () => go( s.id ) }
								>
									{ s.label }
								</button>
							</li>
						) ) }
					</ul>
				</nav>

				<main className="lf-main">
					{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
					{ ! status && ! error && <Spinner /> }
					{ status && ! status.ready && (
						<Notice status="warning" isDismissible={ false }>
							{ status.problems.join( ' ' ) }
						</Notice>
					) }
					{ status && status.ready && screen === 'bricks' && <BricksData status={ status } restPath={ config.restPath } /> }
					{ status && status.ready && SCREEN_COMPONENTS[ screen ] && (
						( () => {
							const Screen = SCREEN_COMPONENTS[ screen ];
							return <Screen key={ screen } restPath={ config.restPath } onDirtyChange={ setDirty } />;
						} )()
					) }
					{ status && status.ready && ! BUILT.includes( screen ) && <Placeholder screen={ current } /> }
				</main>
			</div>
		</div>
	);
}

const root = document.getElementById( 'leapfrog-framework-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
