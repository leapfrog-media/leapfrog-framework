/**
 * Navigation Builder preview (loaded only in the builder's preview frame).
 * Messages from the builder on the same site:
 *   { type: 'lfn:css', css }  the style settings' CSS, replaced at once
 *   { type: 'lfn:state', state: { view, hover, open, mobileOpen, subOpen } }
 *     view: 'live' (scroll the page) | 'top' | 'scrolled'
 *     hover: null | 'link' | 'button' | 'dropdown-link' | 'row'
 *     open: null | 'dropdown' | 'mega'
 * Links don't navigate, and short pages get room so the page can scroll.
 */
( function () {
	'use strict';

	var header = function () {
		return document.getElementById( 'brx-header' );
	};
	var nav = function () {
		return document.querySelector( '#brx-header .brxe-nav-nested.lfn' );
	};
	var view = 'live';

	function setCss( css ) {
		var style = document.getElementById( 'leapfrog-navigation-styles-inline-css' ) || document.getElementById( 'lfn-live-css' );
		if ( ! style ) {
			style = document.createElement( 'style' );
			style.id = 'lfn-live-css';
			document.head.appendChild( style );
		}
		style.textContent = css || '';
	}

	function applyView() {
		var el = header();
		if ( ! el ) {
			return;
		}
		if ( view === 'top' ) {
			el.classList.remove( 'scrolling', 'slide-up', 'x-header_sticky-active' );
		} else if ( view === 'scrolled' ) {
			el.classList.add( 'scrolling' );
			el.classList.remove( 'slide-up' );
		}
	}

	function setOpen( dropdown, open ) {
		dropdown.classList.toggle( 'open', open );
		dropdown.classList.toggle( 'active', open );
		var button = dropdown.querySelector( ':scope > .brx-submenu-toggle > button' );
		if ( button ) {
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function setState( state ) {
		var el = nav();
		if ( ! el || ! state ) {
			return;
		}
		view = state.view || 'live';
		if ( view === 'top' ) {
			window.scrollTo( 0, 0 );
		}
		applyView();

		document.querySelectorAll( '.lfn-force-hover' ).forEach( function ( node ) {
			node.classList.remove( 'lfn-force-hover' );
		} );

		var dropdowns = el.querySelectorAll( '.lfn-items > .brxe-dropdown' );
		dropdowns.forEach( function ( d ) {
			setOpen( d, false );
		} );
		var plain = el.querySelector( '.lfn-items > .brxe-dropdown:not(.brx-has-megamenu)' );
		var mega = el.querySelector( '.lfn-items > .brx-has-megamenu' );
		var target = null;
		if ( state.open === 'dropdown' || state.hover === 'dropdown-link' ) {
			target = plain;
		} else if ( state.open === 'mega' || state.hover === 'row' ) {
			target = mega;
		} else if ( state.subOpen ) {
			target = plain || mega;
		}
		if ( target ) {
			setOpen( target, true );
		}

		var hovered = null;
		if ( state.hover === 'link' ) {
			hovered = el.querySelector( '.lfn-items > li > a:not(.lfn-btn):not([aria-current="page"])' ) || el.querySelector( '.lfn-items > li > a:not(.lfn-btn)' );
		} else if ( state.hover === 'button' ) {
			hovered = el.querySelector( '.lfn-items > li > a.lfn-btn' );
		} else if ( state.hover === 'dropdown-link' && plain ) {
			hovered = plain.querySelector( '.brx-dropdown-content > li > a' );
		} else if ( state.hover === 'row' && mega ) {
			hovered = mega.querySelector( '.lfn-mega-row' );
		}
		if ( hovered ) {
			hovered.classList.add( 'lfn-force-hover' );
		}

		el.classList.toggle( 'brx-open', !! state.mobileOpen );
	}

	window.addEventListener( 'scroll', applyView, { passive: true } );

	window.addEventListener( 'message', function ( event ) {
		if ( event.origin !== window.location.origin || ! event.data || typeof event.data !== 'object' ) {
			return;
		}
		if ( event.data.type === 'lfn:css' ) {
			setCss( event.data.css );
		} else if ( event.data.type === 'lfn:state' ) {
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
		if ( room > 0 && ! document.getElementById( 'lfn-scroll-room' ) ) {
			var spacer = document.createElement( 'div' );
			spacer.id = 'lfn-scroll-room';
			spacer.setAttribute( 'aria-hidden', 'true' );
			spacer.style.cssText = 'height:' + room + 'px;display:flex;align-items:flex-start;justify-content:center;padding-top:48px;color:#8c8f94;font:13px/1.4 system-ui,sans-serif;background:repeating-linear-gradient(180deg,transparent 0 119px,rgb(0 0 0 / 0.06) 119px 120px)';
			spacer.textContent = 'Space added by the Navigation Builder preview, so the page can scroll';
			document.body.appendChild( spacer );
		}
	}

	function ready() {
		ensureScroll();
		if ( window.parent && window.parent !== window ) {
			window.parent.postMessage( { type: 'lfn:ready' }, window.location.origin );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
