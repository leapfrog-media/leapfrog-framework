/**
 * Leapfrog navigation: the mobile menu.
 *
 * At and below the mobile breakpoint (window.leapfrogNavigation.mobileMax) the
 * menu panel slides in when Bricks' Toggle elements add .brx-open to the nav.
 * This script adds what Bricks doesn't:
 * - drill-down: an open sub-menu marks the nav .lfn-sub-open (CSS slides it in and
 *   shows Back); Back closes the open sub-menu and returns focus to its link
 * - the height of the panel's top bar (--lfn-bar-h), so sub-menus sit under it
 * - the page doesn't scroll behind the open menu; Escape steps back or closes
 * - leaving the mobile size closes everything
 * Desktop dropdowns and mega menus are Bricks' own.
 */
( function () {
	'use strict';

	var config = window.leapfrogNavigation || {};
	var mobileQuery = window.matchMedia( '(max-width: ' + ( config.mobileMax || 991 ) + 'px)' );

	function openDropdowns( nav ) {
		return Array.prototype.slice.call( nav.querySelectorAll( '.lfn-items > .brxe-dropdown.open' ) );
	}

	function closeDropdown( dropdown ) {
		dropdown.classList.remove( 'open', 'active' );
		var button = dropdown.querySelector( ':scope > .brx-submenu-toggle > button' );
		if ( button ) {
			button.setAttribute( 'aria-expanded', 'false' );
		}
	}

	function setup( nav ) {
		var bar = nav.querySelector( '.lfn-bar' );
		var back = nav.querySelector( '.lfn-back' );
		var close = nav.querySelector( '.lfn-close' );
		var burger = nav.querySelector( '.lfn-burger' );

		function measure() {
			if ( bar ) {
				nav.style.setProperty( '--lfn-bar-h', bar.offsetHeight + 'px' );
			}
		}

		function sync() {
			var mobile = mobileQuery.matches;
			var open = mobile && nav.classList.contains( 'brx-open' );
			nav.classList.toggle( 'lfn-sub-open', open && openDropdowns( nav ).length > 0 );
			document.documentElement.classList.toggle( 'lfn-lock', open );
			if ( open ) {
				measure();
			}
		}

		function closeMenu() {
			openDropdowns( nav ).forEach( closeDropdown );
			nav.classList.remove( 'brx-open' );
			[ burger, close ].forEach( function ( toggle ) {
				if ( toggle ) {
					toggle.setAttribute( 'aria-expanded', 'false' );
				}
			} );
			sync();
		}

		// Class changes on the nav (opened, closed) and on its dropdowns (sub-menu opened, closed).
		var observer = new MutationObserver( function ( records ) {
			var wasOpen = records.some( function ( r ) {
				return r.target === nav && r.oldValue && /\bbrx-open\b/.test( r.oldValue );
			} );
			if ( wasOpen && ! nav.classList.contains( 'brx-open' ) ) {
				// The menu was closed: close its sub-menus too, ready for next time.
				openDropdowns( nav ).forEach( closeDropdown );
				if ( burger && mobileQuery.matches ) {
					burger.focus();
				}
			} else if ( ! wasOpen && nav.classList.contains( 'brx-open' ) && records.some( function ( r ) {
				return r.target === nav;
			} ) && close && mobileQuery.matches ) {
				setTimeout( function () {
					close.focus();
				}, 50 );
			}
			sync();
		} );
		observer.observe( nav, { attributes: true, attributeFilter: [ 'class' ], attributeOldValue: true, subtree: true } );

		if ( back ) {
			back.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				var open = openDropdowns( nav );
				var last = open[ open.length - 1 ];
				if ( last ) {
					closeDropdown( last );
					var toggle = last.querySelector( ':scope > .brx-submenu-toggle > button' );
					if ( toggle ) {
						toggle.focus();
					}
				}
				sync();
			} );
		}

		// A sub-menu opened by keyboard: move focus to its first link.
		nav.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest && event.target.closest( '.lfn-items > .brxe-dropdown > .brx-submenu-toggle' );
			if ( ! toggle || ! mobileQuery.matches || event.detail !== 0 ) {
				return;
			}
			setTimeout( function () {
				var first = toggle.parentElement.querySelector( ':scope > .brx-dropdown-content a[href]' );
				if ( first && toggle.parentElement.classList.contains( 'open' ) ) {
					first.focus();
				}
			}, 50 );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Escape' || ! mobileQuery.matches || ! nav.classList.contains( 'brx-open' ) ) {
				return;
			}
			if ( nav.classList.contains( 'lfn-sub-open' ) && back ) {
				back.click();
			} else {
				closeMenu();
			}
		} );

		var onChange = function () {
			if ( ! mobileQuery.matches ) {
				closeMenu();
			}
		};
		if ( mobileQuery.addEventListener ) {
			mobileQuery.addEventListener( 'change', onChange );
		} else if ( mobileQuery.addListener ) {
			mobileQuery.addListener( onChange );
		}
		window.addEventListener( 'resize', function () {
			if ( nav.classList.contains( 'brx-open' ) ) {
				measure();
			}
		} );

		sync();
	}

	function init() {
		document.querySelectorAll( '#brx-header .brxe-nav-nested.lfn' ).forEach( setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
