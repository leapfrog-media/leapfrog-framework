/**
 * Leapfrog Framework: show or hide the header and the footer on the Bricks builder canvas.
 *
 * Two toolbar buttons, one for the header and one for the footer, each working on its
 * own. Hiding only affects the canvas while editing: the site and the saved page are
 * unchanged. Each person's choice is remembered in their browser. While a header (or
 * footer) template is being edited, its own button isn't shown.
 */
( () => {
	'use strict';

	const config = window.leapfrogBuilderHelpers || { i18n: {} };
	const t = ( key ) => config.i18n?.[ key ] || key;
	const editing = config.editingType || '';

	const parts = [
		{
			id: 'header',
			selector: '#brx-header',
			store: 'leapfrogHideHeader',
			// A page outline with the top band filled.
			icon: '<rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="3" y="3" width="18" height="6" rx="2" fill="currentColor"/>',
		},
		{
			id: 'footer',
			selector: '#brx-footer',
			store: 'leapfrogHideFooter',
			icon: '<rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="3" y="15" width="18" height="6" rx="2" fill="currentColor"/>',
		},
	].filter( ( part ) => part.id !== editing );

	if ( ! parts.length ) {
		return;
	}

	const hidden = {};
	parts.forEach( ( part ) => {
		try {
			hidden[ part.id ] = window.localStorage.getItem( part.store ) === '1';
		} catch ( e ) {
			hidden[ part.id ] = false;
		}
	} );

	const canvas = () => {
		try {
			return document.querySelector( '#bricks-builder-iframe' )?.contentDocument || null;
		} catch ( e ) {
			return null;
		}
	};

	// One style element in the canvas; the classes on its <html> say what's hidden.
	const apply = () => {
		const doc = canvas();
		if ( ! doc?.documentElement || ! doc.head ) {
			return;
		}
		if ( ! doc.getElementById( 'lf-hide-chrome' ) ) {
			const style = doc.createElement( 'style' );
			style.id = 'lf-hide-chrome';
			style.textContent = parts.map( ( part ) => `html.lf-hide-${ part.id } ${ part.selector }{display:none!important}` ).join( '' );
			doc.head.append( style );
		}
		parts.forEach( ( part ) => doc.documentElement.classList.toggle( `lf-hide-${ part.id }`, !! hidden[ part.id ] ) );
	};

	const label = ( part ) => t( hidden[ part.id ] ? `show_${ part.id }` : `hide_${ part.id }` );

	const button = ( part ) => document.getElementById( `lf-toggle-${ part.id }` );

	const refresh = ( part ) => {
		const li = button( part );
		if ( ! li ) {
			return;
		}
		li.classList.toggle( 'is-hidden-part', !! hidden[ part.id ] );
		li.setAttribute( 'data-balloon', label( part ) );
		li.setAttribute( 'aria-label', label( part ) );
		li.setAttribute( 'aria-pressed', hidden[ part.id ] ? 'true' : 'false' );
	};

	const set = ( part, value ) => {
		hidden[ part.id ] = value;
		try {
			window.localStorage.setItem( part.store, value ? '1' : '0' );
		} catch ( e ) {}
		refresh( part );
		apply();
	};

	const addButtons = () => {
		// Beside the grid guides button when it's there, otherwise before Undo.
		const anchor = document.getElementById( 'lf-grid-guides-toggle' ) || document.querySelector( '#bricks-toolbar-top li.undo, #bricks-toolbar li.undo, .bricks-toolbar li.undo' );
		if ( ! anchor ) {
			return;
		}
		parts.forEach( ( part ) => {
			if ( button( part ) ) {
				return;
			}
			const li = document.createElement( 'li' );
			li.id = `lf-toggle-${ part.id }`;
			li.className = 'lf-chrome-toggle';
			li.tabIndex = 0;
			li.setAttribute( 'role', 'button' );
			li.setAttribute( 'data-balloon-pos', 'bottom' );
			li.innerHTML = `<span class="bricks-svg-wrapper"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="bricks-svg" aria-hidden="true">${ part.icon }<path class="lf-chrome-slash" d="M3 21 21 3" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></span>`;
			li.addEventListener( 'click', () => set( part, ! hidden[ part.id ] ) );
			li.addEventListener( 'keydown', ( e ) => ( e.key === 'Enter' || e.key === ' ' ) && ( e.preventDefault(), set( part, ! hidden[ part.id ] ) ) );
			// Header then footer, both just before the anchor.
			anchor.before( li );
			refresh( part );
		} );
	};

	let watched = null;

	const start = () => {
		addButtons();
		apply();
		// The toolbar can be re-rendered and the canvas reloaded (switching pages or templates).
		window.setInterval( () => {
			addButtons();
			const frame = document.querySelector( '#bricks-builder-iframe' );
			if ( frame && frame !== watched ) {
				watched = frame;
				frame.addEventListener( 'load', apply );
			}
			apply();
		}, 1000 );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
