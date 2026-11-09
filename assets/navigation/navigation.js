/**
 * Leapfrog Navigation Builder: styles the site's Leapfrog navigation (menu bar,
 * button link, dropdowns, mega menus, mobile menu) with a live preview, and
 * creates the starter header. The navigation's content is edited in Bricks;
 * header rows, sticky and scrolling are BricksExtras Header Row settings in Bricks.
 */
import { createRoot, useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal, Notice, PanelBody, SelectControl, Spinner, TabPanel, TextControl, ToggleControl } from '@wordpress/components';

import StyleField from './StyleField';
import './navigation.css';

const config = window.leapfrogNavigationBuilder || {};
const api = ( path, data ) => apiFetch( data ? { path: config.restPath + path, method: 'POST', data } : { path: config.restPath + path } );

const iconFor = ( icon ) => {
	if ( /laptop/.test( icon ) ) {
		return 'laptop';
	}
	if ( /tablet/.test( icon ) ) {
		return 'tablet';
	}
	if ( /phone|mobile/.test( icon ) ) {
		return 'smartphone';
	}
	return 'desktop';
};

// The style settings' CSS: the same as Design::styles_css in PHP, from the same definitions.
function selectorsFor( list, prefix ) {
	const out = [];
	list.forEach( ( s ) => {
		if ( s.startsWith( '^scrolled ' ) ) {
			const inner = s.slice( 10 );
			out.push( 'html body #brx-header.scrolling .brxe-nav-nested.lfn ' + inner, 'html body #brx-header.x-header_sticky-active .brxe-nav-nested.lfn ' + inner );
		} else if ( s.startsWith( '&' ) ) {
			out.push( prefix + s.slice( 1 ) );
		} else {
			out.push( prefix + ' ' + s );
		}
	} );
	return out;
}

export function buildCss( design, fields, prefix, mobileMax ) {
	const out = { '': '', desktop: '', mobile: '' };
	Object.entries( design.styles || {} ).forEach( ( [ key, value ] ) => {
		const field = fields[ key ];
		if ( ! field || value === '' || value === undefined ) {
			return;
		}
		const rules = [];
		if ( field.choices ) {
			const choice = field.choices[ value ] || [ '', '' ];
			rules.push( [ field.selectors, choice[ 1 ] || '' ] );
			( choice[ 2 ] || [] ).forEach( ( extra ) => rules.push( extra ) );
		} else {
			rules.push( [ field.selectors, field.property + ':' + ( field.format || '{v}' ).replace( '{v}', value ) ] );
		}
		rules.forEach( ( [ selectors, declarations ] ) => {
			if ( declarations ) {
				out[ field.media || '' ] += selectorsFor( selectors, prefix ).join( ',' ) + '{' + declarations + '}';
			}
		} );
	} );
	return out[ '' ] + ( out.desktop ? '@media (min-width:' + ( mobileMax + 1 ) + 'px){' + out.desktop + '}' : '' ) + ( out.mobile ? '@media (max-width:' + mobileMax + 'px){' + out.mobile + '}' : '' );
}

// What a setting is about decides what the preview shows while it's being set.
const stateForField = ( key ) => {
	if ( key === 'link-scrolled-color' ) {
		return { view: 'scrolled', open: null, hover: null };
	}
	if ( key.startsWith( 'link-hover' ) ) {
		return { open: null, hover: 'link' };
	}
	if ( key.startsWith( 'btn-hover' ) ) {
		return { open: null, hover: 'button' };
	}
	if ( key.startsWith( 'dd-link-hover' ) ) {
		return { open: 'dropdown', hover: 'dropdown-link' };
	}
	if ( key.startsWith( 'dd-' ) ) {
		return { open: 'dropdown', hover: null };
	}
	if ( key === 'row-hover-bg' ) {
		return { open: 'mega', hover: 'row' };
	}
	if ( key.startsWith( 'mega-' ) || key.startsWith( 'row-' ) || key.startsWith( 'cta-' ) ) {
		return { open: 'mega', hover: null };
	}
	if ( key.startsWith( 'm-sub' ) || key.startsWith( 'm-back' ) ) {
		return { mobileOpen: true, subOpen: true, open: null, hover: null };
	}
	if ( key.startsWith( 'm-' ) ) {
		return { mobileOpen: true, subOpen: false, open: null, hover: null };
	}
	return { open: null, hover: null };
};

const MENU_GROUPS = [ 'Menu bar', 'Button link', 'Dropdowns', 'Mega menus', 'Mega menu rows', 'Mega menu call to action' ];
const MOBILE_GROUPS = [ 'Mobile menu' ];

function Preview( { url, breakpoint, onFrame, reloadKey } ) {
	const stage = useRef( null );
	const [ size, setSize ] = useState( { width: 1000, height: 700 } );

	useEffect( () => {
		const node = stage.current;
		if ( ! node ) {
			return undefined;
		}
		const observer = new window.ResizeObserver( ( [ entry ] ) => setSize( { width: entry.contentRect.width, height: entry.contentRect.height } ) );
		observer.observe( node );
		return () => observer.disconnect();
	}, [] );

	const { width, height } = breakpoint;
	const scale = Math.min( 1, ( size.width - 32 ) / width, ( size.height - 40 ) / height );

	return (
		<div className="lfh-stage" ref={ stage }>
			<div className="lfh-frame" style={ { width: width * scale, height: height * scale } }>
				{ url && <iframe key={ reloadKey } ref={ onFrame } title={ __( 'Navigation preview', 'leapfrog-framework' ) } src={ url } style={ { width, height, transform: `scale(${ scale })` } } /> }
			</div>
			<span className="lfh-stage__size">
				{ breakpoint.label } · { width } × { height }{ scale < 1 ? ' · ' + Math.round( scale * 100 ) + '%' : '' }
			</span>
		</div>
	);
}

function StarterModal( { onClose, onCreate, busy } ) {
	const [ name, setName ] = useState( __( 'Main header', 'leapfrog-framework' ) );
	return (
		<Modal title={ __( 'New header with the starter navigation', 'leapfrog-framework' ) } onRequestClose={ onClose } className="lfh-modal">
			<p>{ __( 'Creates a Bricks header template: a BricksExtras Header Row with the logo and a navigation with two links, a dropdown, a mega menu and a button link. Edit its content in Bricks; style it here.', 'leapfrog-framework' ) }</p>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Template name', 'leapfrog-framework' ) }
				value={ name }
				onChange={ setName }
				autoFocus // eslint-disable-line jsx-a11y/no-autofocus
			/>
			<div className="lfh-modal__actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'leapfrog-framework' ) }</Button>
				<Button variant="primary" onClick={ () => onCreate( name ) } disabled={ busy || ! name.trim() } isBusy={ busy }>
					{ __( 'Create header', 'leapfrog-framework' ) }
				</Button>
			</div>
		</Modal>
	);
}

function MobileBehaviour( { mobile, breakpoints, onChange } ) {
	const set = ( patch ) => onChange( { ...mobile, ...patch } );
	return (
		<PanelBody title={ __( 'How the mobile menu works', 'leapfrog-framework' ) }>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Mobile menu from', 'leapfrog-framework' ) }
				help={ __( 'At this Bricks breakpoint and smaller, the menu bar becomes the menu button and panel.', 'leapfrog-framework' ) }
				value={ mobile.breakpoint }
				options={ breakpoints.filter( ( b ) => ! b.base ).map( ( b ) => ( { label: sprintf( '%1$s (%2$dpx and smaller)', b.label, b.bricksWidth ), value: b.key } ) ) }
				onChange={ ( breakpoint ) => set( { breakpoint } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'The panel comes in', 'leapfrog-framework' ) }
				value={ mobile.from }
				options={ [
					{ label: __( 'From the right', 'leapfrog-framework' ), value: 'right' },
					{ label: __( 'From the left', 'leapfrog-framework' ), value: 'left' },
					{ label: __( 'From the top', 'leapfrog-framework' ), value: 'top' },
					{ label: __( 'Over the whole screen (fades in)', 'leapfrog-framework' ), value: 'full' },
				] }
				onChange={ ( from ) => set( { from } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Sub-menus slide in, with a Back button', 'leapfrog-framework' ) }
				help={ __( 'Off: sub-menus open underneath their link.', 'leapfrog-framework' ) }
				checked={ mobile.drill }
				onChange={ ( drill ) => set( { drill } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Button link at the bottom of the panel', 'leapfrog-framework' ) }
				checked={ mobile.buttonBottom }
				onChange={ ( buttonBottom ) => set( { buttonBottom } ) }
			/>
		</PanelBody>
	);
}

function App() {
	const [ start, setStart ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ design, setDesign ] = useState( null );
	const [ unsaved, setUnsaved ] = useState( false );
	const [ headerId, setHeaderId ] = useState( 0 );
	const [ device, setDevice ] = useState( '' );
	const [ previewState, setPreviewState ] = useState( { view: 'live', hover: null, open: null, mobileOpen: false, subOpen: false } );
	const [ pageUrl, setPageUrl ] = useState( '' );
	const [ reloadKey, setReloadKey ] = useState( 0 );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ starterOpen, setStarterOpen ] = useState( false );
	const frame = useRef( null );
	const draftTimer = useRef( null );

	useEffect( () => {
		api( '/navigation' )
			.then( ( data ) => {
				setStart( data );
				setDesign( data.design );
				setUnsaved( data.unsaved );
				setPageUrl( data.pages[ 0 ]?.url || '' );
				setDevice( ( data.breakpoints.find( ( b ) => b.base ) || data.breakpoints[ 0 ] ).key );
				const withNav = data.headers.find( ( h ) => h.navigation && h.status === 'publish' ) || data.headers.find( ( h ) => h.navigation );
				setHeaderId( withNav ? withNav.id : 0 );
			} )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	}, [] );

	const send = useCallback( ( message ) => {
		const win = frame.current?.contentWindow;
		if ( win ) {
			win.postMessage( message, window.location.origin );
		}
	}, [] );

	const breakpoint = start?.breakpoints.find( ( b ) => b.key === device ) || start?.breakpoints[ 0 ];
	const mobileMax = useMemo( () => {
		const bp = start?.breakpoints.find( ( b ) => b.key === design?.mobile.breakpoint );
		return bp ? bp.bricksWidth : 991;
	}, [ start, design ] );
	const isMobileView = !! breakpoint && breakpoint.width <= mobileMax;
	const css = useMemo( () => ( design && start ? buildCss( design, start.fields, start.prefix, mobileMax ) : '' ), [ design, start, mobileMax ] );
	const frameState = {
		...previewState,
		open: isMobileView ? null : previewState.open,
		hover: isMobileView && [ 'dropdown-link', 'row' ].includes( previewState.hover ) ? null : previewState.hover,
		mobileOpen: isMobileView && previewState.mobileOpen,
		subOpen: isMobileView && previewState.mobileOpen && previewState.subOpen,
	};

	useEffect( () => {
		const onMessage = ( event ) => {
			if ( event.origin === window.location.origin && event.data?.type === 'lfn:ready' ) {
				send( { type: 'lfn:css', css } );
				send( { type: 'lfn:state', state: frameState } );
			}
		};
		window.addEventListener( 'message', onMessage );
		return () => window.removeEventListener( 'message', onMessage );
	} );

	useEffect( () => {
		send( { type: 'lfn:css', css } );
	}, [ css, send ] );

	useEffect( () => {
		send( { type: 'lfn:state', state: frameState } );
	}, [ previewState, device, mobileMax ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const change = ( next ) => {
		setDesign( next );
		setUnsaved( true );
		clearTimeout( draftTimer.current );
		draftTimer.current = setTimeout( () => {
			api( '/navigation/draft', { design: next } )
				.then( ( result ) => {
					if ( result.reload ) {
						setReloadKey( ( k ) => k + 1 );
					}
				} )
				.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		}, 400 );
	};

	const setStyle = ( key, value ) => change( { ...design, styles: { ...design.styles, [ key ]: value } } );

	const save = () => {
		clearTimeout( draftTimer.current );
		setBusy( 'save' );
		setNotice( null );
		api( '/navigation/save', { design } )
			.then( () => {
				setUnsaved( false );
				setNotice( { status: 'success', text: __( 'Saved. Every Leapfrog navigation on the site shows it now.', 'leapfrog-framework' ) } );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	const discard = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Go back to the saved style? Your unsaved changes are lost.', 'leapfrog-framework' ) ) ) {
			return;
		}
		clearTimeout( draftTimer.current );
		api( '/navigation/discard', {} ).then( ( data ) => {
			setDesign( data.design );
			setUnsaved( false );
			setReloadKey( ( k ) => k + 1 );
		} );
	};

	const createStarter = ( name ) => {
		setBusy( 'starter' );
		api( '/navigation/starter', { name } )
			.then( ( data ) => {
				setStart( ( s ) => ( { ...s, headers: data.headers } ) );
				setHeaderId( data.id );
				setReloadKey( ( k ) => k + 1 );
				setStarterOpen( false );
				setNotice( {
					status: 'success',
					text: data.wholeSite
						? sprintf( __( '“%s” is ready and shows on the whole site. Edit its links and content in Bricks.', 'leapfrog-framework' ), name )
						: sprintf( __( '“%s” is ready. Another header already has display conditions, so choose where this one shows in Bricks (template settings > Conditions).', 'leapfrog-framework' ), name ),
					editUrl: data.editUrl,
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	const fillTokens = ( groups ) => {
		const styles = { ...design.styles };
		Object.entries( start.fields ).forEach( ( [ key, field ] ) => {
			if ( field.suggest && ! styles[ key ] && groups.includes( field.group ) ) {
				styles[ key ] = field.suggest;
			}
		} );
		change( { ...design, styles } );
	};

	const previewUrl = useMemo( () => {
		if ( ! pageUrl || ! start ) {
			return '';
		}
		const url = new URL( pageUrl );
		url.searchParams.set( 'lfn_preview', '1' );
		url.searchParams.set( 'lfn_nonce', start.nonce );
		if ( headerId ) {
			url.searchParams.set( 'lfn_header', String( headerId ) );
		}
		url.searchParams.set( 'lfn_v', String( reloadKey ) );
		return url.toString();
	}, [ pageUrl, start, headerId, reloadKey ] );

	if ( error ) {
		return <div className="lfh-app lfh-app--message"><Notice status="error" isDismissible={ false }>{ error }</Notice></div>;
	}
	if ( ! start || ! design ) {
		return <div className="lfh-app lfh-app--message"><Spinner /></div>;
	}

	const navHeaders = start.headers.filter( ( h ) => h.navigation );
	const canBuild = start.problems.length === 0;
	const toMobile = () => {
		if ( ! isMobileView ) {
			const mobileBp = start.breakpoints.find( ( b ) => b.width <= mobileMax );
			if ( mobileBp ) {
				setDevice( mobileBp.key );
			}
		}
	};

	const groupsFor = ( names ) =>
		names
			.map( ( name ) => ( { name, fields: Object.entries( start.fields ).filter( ( [ , f ] ) => f.group === name ) } ) )
			.filter( ( g ) => g.fields.length );

	const renderGroups = ( names ) => (
		<>
			<div className="lfh-intro">
				<p>{ __( 'Empty settings leave the navigation as Bricks shows it; anything set here wins.', 'leapfrog-framework' ) }</p>
				<Button variant="secondary" size="compact" onClick={ () => fillTokens( names ) }>
					{ __( 'Fill the empty ones with Leapfrog’s tokens', 'leapfrog-framework' ) }
				</Button>
			</div>
			{ groupsFor( names ).map( ( group, index ) => (
				<PanelBody key={ group.name } title={ group.name } initialOpen={ index === 0 }>
					{ group.fields.map( ( [ key, field ] ) => (
						<StyleField
							key={ key }
							id={ 'lfn-style-' + key }
							field={ field }
							value={ design.styles[ key ] ?? '' }
							tokens={ start.tokens }
							onFocus={ () => {
								setPreviewState( ( s ) => ( { ...s, ...stateForField( key ) } ) );
								if ( key.startsWith( 'm-' ) ) {
									toMobile();
								}
							} }
							onChange={ ( value ) => setStyle( key, value ) }
						/>
					) ) }
				</PanelBody>
			) ) }
		</>
	);

	const views = [
		{ id: 'live', label: __( 'Scroll to try', 'leapfrog-framework' ) },
		{ id: 'top', label: __( 'At the top', 'leapfrog-framework' ) },
		{ id: 'scrolled', label: __( 'Scrolled', 'leapfrog-framework' ) },
	];
	const toggleState = ( patch ) => setPreviewState( { ...previewState, hover: null, ...patch } );

	return (
		<div className="lfh-app">
			<header className="lfh-bar">
				<div className="lfh-bar__brand">
					{ config.logoUrl && <img src={ config.logoUrl } alt="" /> }
					<span className="lfh-bar__title">{ __( 'Navigation Builder', 'leapfrog-framework' ) }</span>
				</div>
				{ navHeaders.length > 1 && (
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						hideLabelFromVision
						label={ __( 'Header to preview', 'leapfrog-framework' ) }
						value={ String( headerId ) }
						options={ navHeaders.map( ( h ) => ( { label: h.title + ( h.status !== 'publish' ? ' (' + h.status + ')' : '' ), value: String( h.id ) } ) ) }
						onChange={ ( id ) => setHeaderId( Number( id ) ) }
					/>
				) }
				<Button variant="secondary" onClick={ () => setStarterOpen( true ) } disabled={ ! canBuild || !! busy }>
					{ __( 'New header…', 'leapfrog-framework' ) }
				</Button>
				{ navHeaders.length > 0 && (
					<>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							hideLabelFromVision
							label={ __( 'Page to preview on', 'leapfrog-framework' ) }
							value={ pageUrl }
							options={ start.pages.map( ( p ) => ( { label: p.title, value: p.url } ) ) }
							onChange={ setPageUrl }
						/>
						<div className="lfh-toggles" role="group" aria-label={ __( 'Bricks breakpoints', 'leapfrog-framework' ) }>
							{ start.breakpoints.map( ( b ) => (
								<Button
									key={ b.key }
									icon={ iconFor( b.icon ) }
									className={ /landscape/.test( b.icon ) ? 'is-landscape' : '' }
									label={ b.base ? b.label : sprintf( '%1$s (%2$dpx)', b.label, b.bricksWidth ) }
									showTooltip
									isPressed={ device === b.key }
									onClick={ () => setDevice( b.key ) }
								/>
							) ) }
						</div>
					</>
				) }
				<span className="lfh-bar__spacer" />
				<span className="lfh-bar__status" aria-live="polite">
					{ busy === 'save' && __( 'Saving…', 'leapfrog-framework' ) }
					{ busy === 'starter' && __( 'Creating…', 'leapfrog-framework' ) }
					{ ! busy && ( unsaved ? __( 'Unsaved changes (kept as a draft)', 'leapfrog-framework' ) : __( 'Saved', 'leapfrog-framework' ) ) }
				</span>
				{ unsaved && <Button variant="tertiary" onClick={ discard } disabled={ !! busy }>{ __( 'Discard', 'leapfrog-framework' ) }</Button> }
				<Button variant="primary" onClick={ save } disabled={ !! busy || ! unsaved }>{ __( 'Save', 'leapfrog-framework' ) }</Button>
			</header>

			{ navHeaders.length > 0 && (
				<div className="lfh-states">
					<div className="lfh-toggles" role="group" aria-label={ __( 'Page', 'leapfrog-framework' ) }>
						{ views.map( ( v ) => (
							<Button key={ v.id } size="compact" isPressed={ previewState.view === v.id } onClick={ () => toggleState( { view: v.id } ) }>{ v.label }</Button>
						) ) }
					</div>
					{ ! isMobileView && (
						<div className="lfh-toggles" role="group" aria-label={ __( 'Show open', 'leapfrog-framework' ) }>
							<Button size="compact" isPressed={ ! previewState.open } onClick={ () => toggleState( { open: null } ) }>{ __( 'All closed', 'leapfrog-framework' ) }</Button>
							<Button size="compact" isPressed={ previewState.open === 'dropdown' } onClick={ () => toggleState( { open: 'dropdown' } ) }>{ __( 'Dropdown open', 'leapfrog-framework' ) }</Button>
							<Button size="compact" isPressed={ previewState.open === 'mega' } onClick={ () => toggleState( { open: 'mega' } ) }>{ __( 'Mega menu open', 'leapfrog-framework' ) }</Button>
						</div>
					) }
					{ isMobileView && (
						<div className="lfh-toggles" role="group" aria-label={ __( 'Mobile menu', 'leapfrog-framework' ) }>
							<Button size="compact" isPressed={ ! previewState.mobileOpen } onClick={ () => toggleState( { mobileOpen: false, subOpen: false } ) }>{ __( 'Menu closed', 'leapfrog-framework' ) }</Button>
							<Button size="compact" isPressed={ previewState.mobileOpen && ! previewState.subOpen } onClick={ () => toggleState( { mobileOpen: true, subOpen: false } ) }>{ __( 'Menu open', 'leapfrog-framework' ) }</Button>
							<Button size="compact" isPressed={ previewState.mobileOpen && previewState.subOpen } onClick={ () => toggleState( { mobileOpen: true, subOpen: true } ) }>{ __( 'Sub-menu open', 'leapfrog-framework' ) }</Button>
						</div>
					) }
				</div>
			) }

			{ ( start.problems.length > 0 || notice || ( navHeaders.length > 0 && ! start.displayPage ) ) && (
				<div className="lfh-notices">
					{ start.problems.length > 0 && <Notice status="warning" isDismissible={ false }>{ start.problems.join( ' ' ) }</Notice> }
					{ notice && (
						<Notice status={ notice.status } onRemove={ () => setNotice( null ) } actions={ notice.editUrl ? [ { label: __( 'Edit in Bricks', 'leapfrog-framework' ), url: notice.editUrl } ] : [] }>
							{ notice.text }
						</Notice>
					) }
					{ navHeaders.length > 0 && ! start.displayPage && (
						<Notice status="info" isDismissible>
							{ __( 'Tip: make a long page at /header-display/ (a hero, then plenty of content). The builder opens on it, so you can scroll and watch the navigation.', 'leapfrog-framework' ) }
						</Notice>
					) }
				</div>
			) }

			{ navHeaders.length === 0 && (
				<div className="lfh-empty">
					<h2>{ __( 'Start with the starter header', 'leapfrog-framework' ) }</h2>
					<p>{ __( 'The Navigation Builder styles Leapfrog navigations. Create a header with one: a BricksExtras Header Row with the logo and a navigation with two links, a dropdown, a mega menu and a button link. You edit its content in Bricks and style it here.', 'leapfrog-framework' ) }</p>
					<Button variant="primary" onClick={ () => setStarterOpen( true ) } disabled={ ! canBuild }>{ __( 'New header…', 'leapfrog-framework' ) }</Button>
				</div>
			) }

			{ navHeaders.length > 0 && breakpoint && (
				<div className="lfh-body">
					<aside className="lfh-panel">
						<TabPanel
							className="lfh-tabs"
							onSelect={ ( name ) => {
								if ( name === 'mobile' ) {
									toMobile();
									setPreviewState( ( s ) => ( { ...s, mobileOpen: true, open: null, hover: null } ) );
								} else if ( isMobileView ) {
									setDevice( ( start.breakpoints.find( ( b ) => b.base ) || start.breakpoints[ 0 ] ).key );
								}
							} }
							tabs={ [
								{ name: 'menu', title: __( 'Menu bar', 'leapfrog-framework' ) },
								{ name: 'mobile', title: __( 'Mobile menu', 'leapfrog-framework' ) },
							] }
						>
							{ ( t ) =>
								t.name === 'mobile' ? (
									<>
										<MobileBehaviour mobile={ design.mobile } breakpoints={ start.breakpoints } onChange={ ( mobile ) => change( { ...design, mobile } ) } />
										{ renderGroups( MOBILE_GROUPS ) }
									</>
								) : (
									renderGroups( MENU_GROUPS )
								)
							}
						</TabPanel>
					</aside>
					<Preview url={ previewUrl } breakpoint={ breakpoint } reloadKey={ reloadKey } onFrame={ ( node ) => ( frame.current = node ) } />
				</div>
			) }

			{ starterOpen && <StarterModal busy={ busy === 'starter' } onClose={ () => setStarterOpen( false ) } onCreate={ createStarter } /> }
		</div>
	);
}

const root = document.getElementById( 'leapfrog-navigation-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
