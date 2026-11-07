/**
 * Leapfrog Framework: grid guides on the Bricks builder canvas.
 *
 * A toolbar button (and Ctrl+Shift+G) shows 12 column guides that follow the site's
 * layout: inside --width-content, with --gutter at the edges and --gap between
 * columns. When the selected element is a grid, its own tracks are outlined too.
 * Guides are drawn only in the builder canvas, never on the site.
 */
( () => {
	'use strict';

	const config = window.leapfrogBuilderHelpers || { i18n: {} };
	const t = ( key ) => config.i18n?.[ key ] || key;
	const STORE = 'leapfrogGridGuides';
	const COLUMNS = Number( config.guideColumns ) || 12;
	let on = false;
	let timer = null;

	try {
		on = window.localStorage.getItem( STORE ) === '1';
	} catch ( e ) {}

	const props = () => document.querySelector( '.brx-body' )?.__vue_app__?.config?.globalProperties;
	const canvas = () => {
		try {
			return document.querySelector( '#bricks-builder-iframe' )?.contentDocument || null;
		} catch ( e ) {
			return null;
		}
	};

	const remove = ( doc ) => {
		doc?.getElementById( 'lf-grid-guides' )?.remove();
		doc?.getElementById( 'lf-grid-tracks' )?.remove();
	};

	const drawColumns = ( doc ) => {
		if ( doc.getElementById( 'lf-grid-guides' ) ) {
			return;
		}
		const wrap = doc.createElement( 'div' );
		wrap.id = 'lf-grid-guides';
		wrap.setAttribute( 'aria-hidden', 'true' );
		wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483646;pointer-events:none;display:flex;justify-content:center;';
		const inner = doc.createElement( 'div' );
		inner.style.cssText = `inline-size:min(100% - 2 * var(--gutter, 24px), var(--width-content, 1280px));block-size:100%;display:grid;grid-template-columns:repeat(${ COLUMNS }, minmax(0, 1fr));column-gap:var(--gap, 24px);`;
		for ( let i = 0; i < COLUMNS; i++ ) {
			const col = doc.createElement( 'div' );
			col.style.cssText = 'background:rgba(161,84,161,0.07);border-inline:1px solid rgba(161,84,161,0.28);';
			inner.append( col );
		}
		wrap.append( inner );
		doc.body.append( wrap );
	};

	// Outline the tracks of the selected element when it is a grid.
	const drawTracks = ( doc ) => {
		const p = props();
		const active = p?.$_state?.activeElement;
		let node = null;
		if ( active?.id ) {
			node = ( typeof p.$_getElementNode === 'function' && p.$_getElementNode( active, 'iframe' ) ) || doc.getElementById( `brxe-${ active.id }` );
		}
		const cs = node ? doc.defaultView.getComputedStyle( node ) : null;
		let layer = doc.getElementById( 'lf-grid-tracks' );
		if ( ! cs || ! /grid/.test( cs.display ) ) {
			layer?.remove();
			return;
		}
		if ( ! layer ) {
			layer = doc.createElement( 'div' );
			layer.id = 'lf-grid-tracks';
			layer.setAttribute( 'aria-hidden', 'true' );
			doc.body.append( layer );
		}
		const r = node.getBoundingClientRect();
		const num = ( v ) => parseFloat( v ) || 0;
		const left = r.left + num( cs.borderLeftWidth ) + num( cs.paddingLeft );
		const top = r.top + num( cs.borderTopWidth ) + num( cs.paddingTop );
		const width = r.width - num( cs.borderLeftWidth ) - num( cs.borderRightWidth ) - num( cs.paddingLeft ) - num( cs.paddingRight );
		const height = r.height - num( cs.borderTopWidth ) - num( cs.borderBottomWidth ) - num( cs.paddingTop ) - num( cs.paddingBottom );
		const cols = cs.gridTemplateColumns.split( ' ' ).filter( Boolean );
		const rows = cs.gridTemplateRows.split( ' ' ).filter( Boolean );
		const key = [ left, top, width, height, cs.gridTemplateColumns, cs.gridTemplateRows, cs.columnGap, cs.rowGap ].join( '|' );
		if ( layer.dataset.key === key ) {
			return;
		}
		layer.dataset.key = key;
		layer.style.cssText = `position:fixed;left:${ left }px;top:${ top }px;width:${ width }px;height:${ height }px;z-index:2147483647;pointer-events:none;display:grid;grid-template-columns:${ cs.gridTemplateColumns };grid-template-rows:${ cs.gridTemplateRows };column-gap:${ cs.columnGap };row-gap:${ cs.rowGap };`;
		layer.replaceChildren();
		for ( let i = 0; i < Math.max( 1, cols.length ) * Math.max( 1, rows.length ); i++ ) {
			const cell = doc.createElement( 'div' );
			cell.style.cssText = 'outline:1px dashed rgba(161,84,161,0.85);outline-offset:-1px;';
			layer.append( cell );
		}
	};

	const tick = () => {
		const doc = canvas();
		if ( ! doc?.body ) {
			return;
		}
		if ( ! on ) {
			remove( doc );
			return;
		}
		drawColumns( doc );
		drawTracks( doc );
	};

	const button = () => document.getElementById( 'lf-grid-guides-toggle' );

	const set = ( value ) => {
		on = value;
		try {
			window.localStorage.setItem( STORE, on ? '1' : '0' );
		} catch ( e ) {}
		button()?.classList.toggle( 'active', on );
		window.clearInterval( timer );
		timer = on ? window.setInterval( tick, 200 ) : null;
		tick();
	};

	const addButton = () => {
		if ( button() ) {
			return;
		}
		const undo = document.querySelector( '#bricks-toolbar-top li.undo, #bricks-toolbar li.undo, .bricks-toolbar li.undo' );
		if ( ! undo ) {
			return;
		}
		const li = document.createElement( 'li' );
		li.id = 'lf-grid-guides-toggle';
		li.className = on ? 'active' : '';
		li.tabIndex = 0;
		li.setAttribute( 'data-balloon', t( 'guides' ) );
		li.setAttribute( 'data-balloon-pos', 'bottom' );
		li.setAttribute( 'role', 'button' );
		li.setAttribute( 'aria-label', t( 'guides' ) );
		li.innerHTML = '<span class="bricks-svg-wrapper"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="bricks-svg" aria-hidden="true"><path d="M1.5 3v18M8.5 3v18M15.5 3v18M22.5 3v18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>';
		li.addEventListener( 'click', () => set( ! on ) );
		li.addEventListener( 'keydown', ( e ) => ( e.key === 'Enter' || e.key === ' ' ) && ( e.preventDefault(), set( ! on ) ) );
		undo.before( li );
	};

	const onKey = ( e ) => {
		if ( ( e.ctrlKey || e.metaKey ) && e.shiftKey && ! e.altKey && e.code === 'KeyG' ) {
			e.preventDefault();
			set( ! on );
		}
	};

	const start = () => {
		document.addEventListener( 'keydown', onKey, true );
		let hooked = null;
		// The toolbar and the canvas can be re-rendered; keep the button and the key in place.
		window.setInterval( () => {
			addButton();
			const doc = canvas();
			if ( doc && doc !== hooked ) {
				hooked = doc;
				doc.addEventListener( 'keydown', onKey, true );
			}
		}, 1000 );
		addButton();
		set( on );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
