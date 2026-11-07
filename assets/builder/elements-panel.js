/**
 * Leapfrog Framework: a small toolbar above Bricks' elements panel.
 *
 * Shows the elements in 1, 2, 3 or 4 columns (remembered), and opens or closes every
 * category at once. It only changes how the panel looks; it clicks Bricks' own category
 * titles to open and close them.
 */
( () => {
	'use strict';

	const config = window.leapfrogBuilderHelpers || { i18n: {} };
	const t = ( key, ...args ) => {
		let text = config.i18n?.[ key ] || key;
		args.forEach( ( a ) => ( text = text.replace( /%[sd]/, String( a ) ) ) );
		return text;
	};
	const STORE = 'leapfrogElementsColumns';
	const LIST = '#bricks-panel-elements-categories';
	let columns = 2;

	try {
		columns = Math.min( 4, Math.max( 1, Number( window.localStorage.getItem( STORE ) ) || 2 ) );
	} catch ( e ) {}

	const setColumns = ( n ) => {
		columns = n;
		try {
			window.localStorage.setItem( STORE, String( n ) );
		} catch ( e ) {}
		document.body.classList.remove( 'lf-elements-cols-1', 'lf-elements-cols-3', 'lf-elements-cols-4' );
		if ( n !== 2 ) {
			document.body.classList.add( `lf-elements-cols-${ n }` );
		}
		document.querySelectorAll( '.lf-elements-toolbar [data-cols]' ).forEach( ( b ) => {
			const active = Number( b.dataset.cols ) === n;
			b.classList.toggle( 'active', active );
			b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	};

	const titles = () => [ ...document.querySelectorAll( `${ LIST } > li.category > .category-title` ) ];
	const anyOpen = () => titles().some( ( title ) => title.classList.contains( 'expand' ) );

	const updateToggle = () => {
		const button = document.querySelector( '.lf-elements-toolbar .lf-elements-toggle' );
		if ( ! button ) {
			return;
		}
		const open = anyOpen();
		if ( button.classList.contains( 'is-open' ) !== open || ! button.firstChild ) {
			button.innerHTML = open ? closeIcon : openIcon;
		}
		button.classList.toggle( 'is-open', open );
		const label = open ? t( 'collapseAll' ) : t( 'expandAll' );
		button.setAttribute( 'data-balloon', label );
		button.setAttribute( 'aria-label', label );
	};

	const toggleAll = () => {
		const close = anyOpen();
		titles().forEach( ( title ) => {
			if ( title.classList.contains( 'expand' ) === close ) {
				title.click();
			}
		} );
		window.setTimeout( updateToggle, 50 );
	};

	// Icons: n bars for n columns, and two chevrons for open/close all.
	const barsIcon = ( n ) => {
		const gap = 2;
		const width = ( 20 - gap * ( n - 1 ) ) / n;
		let rects = '';
		for ( let i = 0; i < n; i++ ) {
			rects += n === 1
				? `<rect x="2" y="${ 3 + i * 7 }" width="20" height="5" rx="1"/><rect x="2" y="10" width="20" height="5" rx="1"/><rect x="2" y="17" width="20" height="4" rx="1"/>`
				: `<rect x="${ 2 + i * ( width + gap ) }" y="4" width="${ width }" height="16" rx="1"/>`;
		}
		return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">${ rects }</svg>`;
	};
	// Arrows apart = open all; arrows together = close all.
	const openIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8l5-5 5 5M7 16l5 5 5-5"/></svg>';
	const closeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4l5 5 5-5M7 20l5-5 5 5"/></svg>';

	const build = ( list ) => {
		const bar = document.createElement( 'div' );
		bar.className = 'lf-elements-toolbar';
		bar.setAttribute( 'role', 'toolbar' );
		bar.setAttribute( 'aria-label', t( 'elementsToolbar' ) );
		[ 1, 2, 3, 4 ].forEach( ( n ) => {
			const b = document.createElement( 'button' );
			b.type = 'button';
			b.dataset.cols = String( n );
			const label = n === 1 ? t( 'oneColumn' ) : t( 'columns', n );
			b.setAttribute( 'data-balloon', label );
			b.setAttribute( 'data-balloon-pos', 'bottom' );
			b.setAttribute( 'aria-label', label );
			b.innerHTML = barsIcon( n );
			b.addEventListener( 'click', () => setColumns( n ) );
			bar.append( b );
		} );
		const toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'lf-elements-toggle';
		toggle.setAttribute( 'data-balloon-pos', 'bottom-right' );
		toggle.addEventListener( 'click', toggleAll );
		bar.append( toggle );
		list.before( bar );
		setColumns( columns );
		updateToggle();
	};

	const check = () => {
		const list = document.querySelector( LIST );
		if ( list && ! list.previousElementSibling?.classList.contains( 'lf-elements-toolbar' ) ) {
			list.parentElement.querySelectorAll( ':scope > .lf-elements-toolbar' ).forEach( ( old ) => old.remove() );
			build( list );
		} else if ( list ) {
			updateToggle();
		}
	};

	const start = () => {
		setColumns( columns );
		let scheduled = false;
		new MutationObserver( () => {
			if ( ! scheduled ) {
				scheduled = true;
				window.requestAnimationFrame( () => {
					scheduled = false;
					check();
				} );
			}
		} ).observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
		check();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
