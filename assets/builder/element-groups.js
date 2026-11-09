/**
 * Leapfrog Framework: a show/hide switch on each group in Bricks' elements panel.
 *
 * Switching a group off shrinks it to its title row, dims it and moves it to the
 * bottom of the panel, where its switch turns it back on. The choice is remembered
 * in the browser and applies whatever is being edited (pages, posts, templates).
 * Search results and pinned elements are never hidden.
 *
 * Bricks re-renders the panel often, so hidden groups are applied by a style sheet
 * keyed on Bricks' own category-<name> classes rather than by changing its markup.
 */
( () => {
	'use strict';

	const config = window.leapfrogBuilderHelpers || { i18n: {} };
	const t = ( key ) => config.i18n?.[ key ] || key;
	const STORE = 'leapfrogHiddenElementGroups';
	const LIST = '#bricks-panel-elements-categories:not([data-tab="components"])';
	const SKIP = [ 'searchedElements', 'pinned' ];
	let hidden = [];

	try {
		const saved = JSON.parse( window.localStorage.getItem( STORE ) || '[]' );
		hidden = Array.isArray( saved ) ? saved.filter( ( key ) => typeof key === 'string' ) : [];
	} catch ( e ) {}

	const keyOf = ( li ) => {
		const cls = [ ...li.classList ].find( ( c ) => c.startsWith( 'category-' ) && c !== 'category-title' );
		return cls ? cls.slice( 9 ) : '';
	};

	const writeStyle = () => {
		let style = document.getElementById( 'lf-element-groups-hidden' );
		if ( ! style ) {
			style = document.createElement( 'style' );
			style.id = 'lf-element-groups-hidden';
			document.head.append( style );
		}
		style.textContent = hidden
			.map( ( key ) => {
				const li = `${ LIST } > li.category-${ window.CSS.escape( key ) }`;
				return `${ li }{order:1}${ li } > :not(.category-title){display:none!important}${ li } > .category-title > :not(.lf-group-switch){opacity:.45}`;
			} )
			.join( '' );
	};

	const syncSwitch = ( button, key ) => {
		const on = ! hidden.includes( key );
		button.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		const label = on ? t( 'hideGroup' ) : t( 'showGroup' );
		button.setAttribute( 'data-balloon', label );
		button.setAttribute( 'aria-label', label );
	};

	const toggle = ( key ) => {
		hidden = hidden.includes( key ) ? hidden.filter( ( k ) => k !== key ) : [ ...hidden, key ];
		try {
			window.localStorage.setItem( STORE, JSON.stringify( hidden ) );
		} catch ( e ) {}
		writeStyle();
		check();
	};

	const check = () => {
		document.querySelectorAll( `${ LIST } > li.category` ).forEach( ( li ) => {
			const key = keyOf( li );
			const title = li.querySelector( ':scope > .category-title' );
			if ( ! key || ! title || SKIP.includes( key ) ) {
				return;
			}
			let button = title.querySelector( ':scope > .lf-group-switch' );
			if ( ! button || button.dataset.key !== key ) {
				button?.remove();
				button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'lf-group-switch';
				button.dataset.key = key;
				button.setAttribute( 'role', 'switch' );
				button.setAttribute( 'data-balloon-pos', 'left' );
				// Don't let the click reach Bricks' title, which opens and closes the group.
				button.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					e.stopPropagation();
					toggle( button.dataset.key );
				} );
				button.addEventListener( 'mousedown', ( e ) => e.stopPropagation() );
				// Just before Bricks' arrow.
				title.insertBefore( button, title.lastElementChild );
			}
			syncSwitch( button, key );
		} );
	};

	const start = () => {
		writeStyle();
		let scheduled = false;
		new MutationObserver( () => {
			if ( ! scheduled ) {
				scheduled = true;
				window.requestAnimationFrame( () => {
					scheduled = false;
					check();
				} );
			}
		} ).observe( document.body, { childList: true, subtree: true } );
		check();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
