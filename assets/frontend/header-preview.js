/**
 * Header Builder preview (loaded only in the builder's preview frame).
 * Takes messages from the builder on the same site:
 *   { type: 'lfh:css', css }   the design's CSS, replaced at once
 *   { type: 'lfh:state', state: { view: 'live'|'top'|'scrolled', hover, mobileOpen } }
 * In 'live' view the page scrolls normally and Bricks' own sticky script runs.
 * Links don't navigate, so a click never leaves the page being previewed.
 */
( function () {
	'use strict';

	var header = function () {
		return document.getElementById( 'brx-header' );
	};
	var view = 'live';

	function setCss( css ) {
		var saved = document.getElementById( 'leapfrog-header-inline-css' );
		if ( saved ) {
			saved.textContent = '';
		}
		var live = document.getElementById( 'lfh-live-css' );
		if ( ! live ) {
			live = document.createElement( 'style' );
			live.id = 'lfh-live-css';
			document.head.appendChild( live );
		}
		live.textContent = css || '';
	}

	// Bricks adds .scrolling on scroll; in a pinned view keep the class as pinned.
	function applyView() {
		var el = header();
		if ( ! el ) {
			return;
		}
		if ( view === 'top' ) {
			el.classList.remove( 'scrolling', 'slide-up' );
		} else if ( view === 'scrolled' ) {
			el.classList.add( 'scrolling' );
			el.classList.remove( 'slide-up' );
		}
	}

	function setState( state ) {
		var el = header();
		if ( ! el || ! state ) {
			return;
		}
		view = state.view || 'live';
		if ( view === 'top' ) {
			window.scrollTo( 0, 0 );
		}
		if ( view === 'live' ) {
			el.classList.toggle( 'scrolling', window.scrollY > 0 );
		}
		applyView();

		document.querySelectorAll( '.lfh-force-hover' ).forEach( function ( node ) {
			node.classList.remove( 'lfh-force-hover' );
		} );
		var target = null;
		if ( state.hover === 'link' ) {
			target = el.querySelector( '.brx-nav-nested-items > li > a:not([aria-current="page"]), .bricks-nav-menu > li:not(.current-menu-item) > a' ) || el.querySelector( '.brx-nav-nested-items > li > a, .bricks-nav-menu > li > a' );
		} else if ( state.hover === 'button' ) {
			target = el.querySelector( '.brxe-button' );
		}
		if ( target ) {
			target.classList.add( 'lfh-force-hover' );
		}

		el.querySelectorAll( '.brxe-nav-nested' ).forEach( function ( nav ) {
			nav.classList.toggle( 'brx-open', !! state.mobileOpen );
		} );
		document.body.classList.toggle( 'no-scroll', !! state.mobileOpen );
	}

	window.addEventListener( 'scroll', applyView, { passive: true } );

	window.addEventListener( 'message', function ( event ) {
		if ( event.origin !== window.location.origin || ! event.data || typeof event.data !== 'object' ) {
			return;
		}
		if ( event.data.type === 'lfh:css' ) {
			setCss( event.data.css );
		} else if ( event.data.type === 'lfh:state' ) {
			setState( event.data.state );
		}
	} );

	document.addEventListener(
		'click',
		function ( event ) {
			if ( event.target.closest && event.target.closest( 'a[href]' ) ) {
				event.preventDefault();
			}
		},
		true
	);
	document.addEventListener(
		'submit',
		function ( event ) {
			event.preventDefault();
		},
		true
	);

	// Short pages get room below, so the header's scrolling can always be tried.
	function ensureScroll() {
		var room = window.innerHeight * 2 - document.documentElement.scrollHeight;
		if ( room > 0 && ! document.getElementById( 'lfh-scroll-room' ) ) {
			var spacer = document.createElement( 'div' );
			spacer.id = 'lfh-scroll-room';
			spacer.setAttribute( 'aria-hidden', 'true' );
			spacer.style.cssText = 'height:' + room + 'px;display:flex;align-items:flex-start;justify-content:center;padding-top:48px;color:#8c8f94;font:13px/1.4 system-ui,sans-serif;background:repeating-linear-gradient(180deg,transparent 0 119px,rgb(0 0 0 / 0.06) 119px 120px)';
			spacer.textContent = 'Space added by the Header Builder preview, so the page can scroll';
			document.body.appendChild( spacer );
		}
	}

	function ready() {
		ensureScroll();
		if ( window.parent && window.parent !== window ) {
			window.parent.postMessage( { type: 'lfh:ready' }, window.location.origin );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
