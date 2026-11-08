/**
 * Leapfrog Header Builder: styles and scrolling for headers built in Bricks.
 * Settings on the left; on the right, a page of the site with the header and its
 * unsaved design. Style changes reach the preview at once; scrolling changes
 * reload it. The header's content is built in Bricks and never changed here.
 */
import { createRoot, useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, PanelBody, SelectControl, Spinner, TabPanel, ToggleControl, RangeControl } from '@wordpress/components';

import StyleField from './StyleField';
import './header.css';

const config = window.leapfrogHeader || {};
const api = ( path, data ) => apiFetch( data ? { path: config.restPath + path, method: 'POST', data } : { path: config.restPath + path } );

// Bricks' breakpoint icons as WordPress icons.
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

// The same CSS the site gets (Design::css in PHP), built from the same settings.
export function buildCss( design, fields, prefix ) {
	let css = '';
	Object.entries( design.styles || {} ).forEach( ( [ key, value ] ) => {
		const field = fields[ key ];
		if ( ! field || value === '' || value === undefined ) {
			return;
		}
		const declarations = field.choices ? field.choices[ value ]?.[ 1 ] || '' : field.property + ':' + ( field.format || '{v}' ).replace( '{v}', value );
		if ( ! declarations ) {
			return;
		}
		const selectors = field.selectors.map( ( s ) => ( s.startsWith( '&' ) ? prefix + s.slice( 1 ) : prefix + ' ' + s ) );
		css += selectors.join( ',' ) + '{' + declarations + '}';
	} );
	if ( design.scroll.overlay && design.scroll.overlayOffset ) {
		css += 'html body #brx-content{padding-top:' + design.scroll.overlayOffset + '}';
	}
	return css;
}

// Settings that show best in a particular state: focusing one switches the preview to it.
const viewForField = ( key ) => {
	if ( key.includes( 'scrolled' ) || key.endsWith( ':when-scrolled' ) ) {
		return { view: 'scrolled', hover: null };
	}
	if ( key.startsWith( 'hero-' ) ) {
		return { view: 'top', hover: null };
	}
	if ( key.startsWith( 'link-hover' ) ) {
		return { hover: 'link' };
	}
	if ( key.startsWith( 'btn-hover' ) ) {
		return { hover: 'button' };
	}
	return { hover: null };
};

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

	// The breakpoint's width on a typical screen of that shape, scaled down to fit, so scrolling behaves as it will there.
	const { width, height } = breakpoint;
	const scale = Math.min( 1, ( size.width - 32 ) / width, ( size.height - 40 ) / height );

	return (
		<div className="lfh-stage" ref={ stage }>
			<div className="lfh-frame" style={ { width: width * scale, height: height * scale } }>
				{ url && <iframe key={ reloadKey } ref={ onFrame } title={ __( 'Header preview', 'leapfrog-framework' ) } src={ url } style={ { width, height, transform: `scale(${ scale })` } } /> }
			</div>
			<span className="lfh-stage__size">
				{ breakpoint.label } · { width } × { height }{ scale < 1 ? ' · ' + Math.round( scale * 100 ) + '%' : '' }
			</span>
		</div>
	);
}

function ScrollPanel( { scroll, onChange } ) {
	const set = ( patch ) => onChange( { ...scroll, ...patch } );
	return (
		<>
			<PanelBody title={ __( 'Scrolling', 'leapfrog-framework' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Stay at the top while scrolling (sticky)', 'leapfrog-framework' ) }
					checked={ scroll.sticky }
					onChange={ ( sticky ) => set( { sticky } ) }
				/>
				{ scroll.sticky && (
					<>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Hide while scrolling down, show on scrolling up', 'leapfrog-framework' ) }
							checked={ scroll.hideOnScroll }
							onChange={ ( hideOnScroll ) => set( { hideOnScroll } ) }
						/>
						{ scroll.hideOnScroll && (
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Start hiding after (pixels)', 'leapfrog-framework' ) }
								min={ 50 }
								max={ 1000 }
								step={ 50 }
								value={ scroll.hideAfter }
								onChange={ ( hideAfter ) => set( { hideAfter } ) }
							/>
						) }
					</>
				) }
			</PanelBody>
			<PanelBody title={ __( 'Over the hero', 'leapfrog-framework' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Sit over the top of the page', 'leapfrog-framework' ) }
					help={ __( 'The first section (a hero) shows behind the header, which turns solid once the page scrolls. Style it under Styles > Over the hero.', 'leapfrog-framework' ) }
					checked={ scroll.overlay }
					onChange={ ( overlay ) => set( { overlay } ) }
				/>
				{ scroll.overlay && (
					<StyleField
						id="lfh-overlay-offset"
						field={ { label: __( 'Space under the header', 'leapfrog-framework' ), kind: 'size', suggest: '0px' } }
						value={ scroll.overlayOffset }
						tokens={ window.lfhTokens }
						onChange={ ( overlayOffset ) => set( { overlayOffset } ) }
					/>
				) }
				{ scroll.overlay && <p className="lfh-muted">{ __( 'How far the page content starts below the top. Leave empty (or 0px) for pages that begin with a hero; use the header height for pages without one.', 'leapfrog-framework' ) }</p> }
			</PanelBody>
		</>
	);
}

function App() {
	const [ start, setStart ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ header, setHeader ] = useState( null ); // { id, title, rows, parts, fields, prefix, editUrl, styled, live }
	const [ design, setDesign ] = useState( null );
	const [ unsaved, setUnsaved ] = useState( false );
	const [ device, setDevice ] = useState( '' ); // A Bricks breakpoint key.
	const [ previewState, setPreviewState ] = useState( { view: 'live', hover: null, mobileOpen: false } );
	const [ pageUrl, setPageUrl ] = useState( '' );
	const [ reloadKey, setReloadKey ] = useState( 0 );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const frame = useRef( null );
	const draftTimer = useRef( null );

	useEffect( () => {
		api( '/header' )
			.then( ( data ) => {
				window.lfhTokens = data.tokens;
				setStart( data );
				setPageUrl( data.pages[ 0 ]?.url || '' );
				setDevice( ( data.breakpoints.find( ( b ) => b.base ) || data.breakpoints[ 0 ] ).key );
				if ( data.headers[ 0 ] ) {
					load( data.headers[ 0 ].id );
				}
			} )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const load = ( id ) => {
		setBusy( 'load' );
		api( '/header/design?id=' + id )
			.then( ( data ) => {
				setHeader( data );
				setDesign( data.design );
				setUnsaved( data.unsaved );
				setReloadKey( ( k ) => k + 1 );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	const send = useCallback( ( message ) => {
		const win = frame.current?.contentWindow;
		if ( win ) {
			win.postMessage( message, window.location.origin );
		}
	}, [] );

	const css = useMemo( () => ( design && header ? buildCss( design, header.fields, header.prefix ) : '' ), [ design, header ] );
	const breakpoint = start?.breakpoints.find( ( b ) => b.key === device ) || start?.breakpoints[ 0 ];
	const isBase = !! breakpoint?.base;
	const frameState = { ...previewState, mobileOpen: previewState.mobileOpen && ! isBase };

	useEffect( () => {
		const onMessage = ( event ) => {
			if ( event.origin === window.location.origin && event.data?.type === 'lfh:ready' ) {
				send( { type: 'lfh:css', css } );
				send( { type: 'lfh:state', state: frameState } );
			}
		};
		window.addEventListener( 'message', onMessage );
		return () => window.removeEventListener( 'message', onMessage );
	} );

	useEffect( () => {
		send( { type: 'lfh:css', css } );
	}, [ css, send ] );

	useEffect( () => {
		send( { type: 'lfh:state', state: frameState } );
	}, [ previewState, device ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Every change: the preview gets the CSS at once; the draft is stored shortly after.
	const change = ( next ) => {
		setDesign( next );
		setUnsaved( true );
		clearTimeout( draftTimer.current );
		draftTimer.current = setTimeout( () => {
			api( '/header/draft', { design: next } )
				.then( ( result ) => {
					if ( result.scrollChanged ) {
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
		api( '/header/save', { design } )
			.then( ( data ) => {
				setHeader( data );
				setUnsaved( false );
				setNotice( {
					status: 'success',
					text: data.live
						? __( 'Saved. The site’s header shows it now.', 'leapfrog-framework' )
						: __( 'Saved. This header template isn’t published in Bricks yet, so the site doesn’t show it.', 'leapfrog-framework' ),
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	const discard = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Go back to the saved styles? Your unsaved changes are lost.', 'leapfrog-framework' ) ) ) {
			return;
		}
		clearTimeout( draftTimer.current );
		api( '/header/discard', { id: header.id } ).then( ( data ) => {
			setHeader( data );
			setDesign( data.design );
			setUnsaved( false );
			setReloadKey( ( k ) => k + 1 );
		} );
	};

	const fillTokens = () => {
		const styles = { ...design.styles };
		Object.entries( header.fields ).forEach( ( [ key, field ] ) => {
			if ( field.suggest && ! styles[ key ] && ( ! key.startsWith( 'hero-' ) || design.scroll.overlay ) ) {
				styles[ key ] = field.suggest;
			}
		} );
		change( { ...design, styles } );
	};

	const previewUrl = useMemo( () => {
		if ( ! pageUrl || ! header || ! start ) {
			return '';
		}
		const url = new URL( pageUrl );
		url.searchParams.set( 'lfh_preview', String( header.id ) );
		url.searchParams.set( 'lfh_nonce', start.nonce );
		url.searchParams.set( 'lfh_v', String( reloadKey ) );
		return url.toString();
	}, [ pageUrl, header, start, reloadKey ] );

	const groups = useMemo( () => {
		if ( ! header || ! design ) {
			return [];
		}
		const parts = header.parts;
		const show = {
			Logo: parts.logo > 0,
			'Menu links': parts.nav > 0,
			Buttons: parts.button > 0,
			'Mobile menu': parts.nav > 0 || parts.toggle > 0,
			'Over the hero': design.scroll.overlay,
		};
		const out = [];
		Object.entries( header.fields ).forEach( ( [ key, field ] ) => {
			if ( show[ field.group ] === false ) {
				return;
			}
			let group = out.find( ( g ) => g.name === field.group );
			if ( ! group ) {
				group = { name: field.group, fields: [] };
				out.push( group );
			}
			group.fields.push( [ key, field ] );
		} );
		return out;
	}, [ header, design ] );

	if ( error ) {
		return <div className="lfh-app lfh-app--message"><Notice status="error" isDismissible={ false }>{ error }</Notice></div>;
	}
	if ( ! start ) {
		return <div className="lfh-app lfh-app--message"><Spinner /></div>;
	}

	const views = [
		{ id: 'live', label: __( 'Scroll to try', 'leapfrog-framework' ) },
		{ id: 'top', label: __( 'At the top', 'leapfrog-framework' ) },
		{ id: 'scrolled', label: __( 'Scrolled', 'leapfrog-framework' ) },
	];

	return (
		<div className="lfh-app">
			<header className="lfh-bar">
				<div className="lfh-bar__brand">
					{ config.logoUrl && <img src={ config.logoUrl } alt="" /> }
					<span className="lfh-bar__title">{ __( 'Header Builder', 'leapfrog-framework' ) }</span>
				</div>
				{ start.headers.length > 0 && (
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						hideLabelFromVision
						label={ __( 'Bricks header template', 'leapfrog-framework' ) }
						value={ header ? String( header.id ) : '' }
						options={ start.headers.map( ( h ) => ( { label: h.title + ( h.status !== 'publish' ? ' (' + h.status + ')' : '' ), value: String( h.id ) } ) ) }
						onChange={ ( id ) => load( Number( id ) ) }
					/>
				) }
				{ header && (
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
						<div className="lfh-toggles" role="group" aria-label={ __( 'Show the header', 'leapfrog-framework' ) }>
							{ views.map( ( v ) => (
								<Button key={ v.id } isPressed={ previewState.view === v.id } onClick={ () => setPreviewState( { ...previewState, view: v.id, hover: null } ) }>
									{ v.label }
								</Button>
							) ) }
							{ ! isBase && (
								<Button isPressed={ previewState.mobileOpen } onClick={ () => setPreviewState( { ...previewState, mobileOpen: ! previewState.mobileOpen } ) }>
									{ __( 'Menu open', 'leapfrog-framework' ) }
								</Button>
							) }
						</div>
						<span className="lfh-bar__spacer" />
						<span className="lfh-bar__status" aria-live="polite">
							{ busy === 'save' && __( 'Saving…', 'leapfrog-framework' ) }
							{ busy === 'load' && __( 'Loading…', 'leapfrog-framework' ) }
							{ ! busy && ( unsaved ? __( 'Unsaved changes (kept as a draft)', 'leapfrog-framework' ) : __( 'Saved', 'leapfrog-framework' ) ) }
						</span>
						{ unsaved && (
							<Button variant="tertiary" onClick={ discard } disabled={ !! busy }>{ __( 'Discard', 'leapfrog-framework' ) }</Button>
						) }
						<Button variant="tertiary" href={ header.editUrl } target="_blank">{ __( 'Edit content in Bricks', 'leapfrog-framework' ) }</Button>
						<Button variant="primary" onClick={ save } disabled={ !! busy || ! unsaved }>
							{ __( 'Save', 'leapfrog-framework' ) }
						</Button>
					</>
				) }
			</header>

			{ ( start.problems.length > 0 || notice || ( start.headers.length > 0 && ! start.displayPage ) ) && (
				<div className="lfh-notices">
					{ start.problems.length > 0 && <Notice status="warning" isDismissible={ false }>{ start.problems.join( ' ' ) }</Notice> }
					{ notice && <Notice status={ notice.status } onRemove={ () => setNotice( null ) }>{ notice.text }</Notice> }
					{ start.headers.length > 0 && ! start.displayPage && (
						<Notice status="info" isDismissible>
							{ __( 'Tip: make a long page at /header-display/ (a hero, then plenty of content). The builder opens on it, so you can scroll and watch the header change.', 'leapfrog-framework' ) }
						</Notice>
					) }
				</div>
			) }

			{ start.headers.length === 0 && (
				<div className="lfh-empty">
					<h2>{ __( 'No header templates yet', 'leapfrog-framework' ) }</h2>
					<p>{ __( 'Build the header in Bricks first: Bricks > Templates > Add New, choose the Header type, and add its rows, logo, menu and buttons. Then come back here to style it and set how it scrolls.', 'leapfrog-framework' ) }</p>
					<Button variant="primary" href={ start.newHeaderUrl }>{ __( 'Go to Bricks templates', 'leapfrog-framework' ) }</Button>
				</div>
			) }

			{ header && design && (
				<div className="lfh-body">
					<aside className="lfh-panel">
						<TabPanel
							className="lfh-tabs"
							tabs={ [
								{ name: 'styles', title: __( 'Styles', 'leapfrog-framework' ) },
								{ name: 'scroll', title: __( 'Scrolling', 'leapfrog-framework' ) },
							] }
						>
							{ ( tab ) =>
								tab.name === 'scroll' ? (
									<ScrollPanel scroll={ design.scroll } onChange={ ( scroll ) => change( { ...design, scroll } ) } />
								) : (
									<>
										<div className="lfh-intro">
											<p>{ __( 'Empty settings leave the header as styled in Bricks; anything set here wins.', 'leapfrog-framework' ) }</p>
											<Button variant="secondary" size="compact" onClick={ fillTokens }>
												{ __( 'Fill the empty ones with Leapfrog’s header tokens', 'leapfrog-framework' ) }
											</Button>
										</div>
										{ groups.map( ( group, index ) => (
											<PanelBody key={ group.name } title={ group.name } initialOpen={ index === 0 }>
												{ group.fields.map( ( [ key, field ] ) => (
													<StyleField
														key={ key }
														id={ 'lfh-style-' + key.replace( /[^a-z0-9-]/gi, '-' ) }
														field={ field }
														value={ design.styles[ key ] ?? '' }
														tokens={ start.tokens }
														onFocus={ () => setPreviewState( ( s ) => ( { ...s, ...viewForField( key ) } ) ) }
														onChange={ ( value ) => setStyle( key, value ) }
													/>
												) ) }
											</PanelBody>
										) ) }
										{ header.rows.length === 0 && <p className="lfh-intro">{ sprintf( __( '“%s” has no rows yet. Add them in Bricks.', 'leapfrog-framework' ), header.title ) }</p> }
									</>
								)
							}
						</TabPanel>
					</aside>
					{ breakpoint && <Preview url={ previewUrl } breakpoint={ breakpoint } reloadKey={ reloadKey } onFrame={ ( node ) => ( frame.current = node ) } /> }
				</div>
			) }
		</div>
	);
}

const root = document.getElementById( 'leapfrog-header-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
