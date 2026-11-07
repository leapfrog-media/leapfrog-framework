/**
 * Leapfrog Framework: Theme control in the Bricks builder.
 *
 * Mirrors each Section/Container/Block "Theme" setting onto the canvas as
 * data-lf-theme (so Leapfrog's theme CSS previews it live) and shows a badge in
 * the Structure panel. Ported from CoreBridge's bricks-theme-mode.js.
 */
( () => {
	'use strict';

	const settingKey = 'lfTheme';
	const legacyKeys = [ 'corebridgeThemeMode', 'plinthColourMode' ];
	const targets = new Set( [ 'section', 'container', 'block' ] );
	const modes = new Set( [ 'light', 'dark', 'inverted' ] );
	const icons = {
		light: '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="3.25" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.05 3.05l1.4 1.4M11.55 11.55l1.4 1.4M12.95 3.05l-1.4 1.4M4.45 11.55l-1.4 1.4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
		dark: '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 2a6 6 0 0 0 0 12Z" fill="currentColor"/></svg>',
		inverted: '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M4 5.5h7m0 0L9.25 3.75M11 5.5 9.25 7.25M12 10.5H5m0 0 1.75-1.75M5 10.5l1.75 1.75" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	};
	let scheduled = false;

	const state = () => document.querySelector( '.brx-body' )?.__vue_app__?.config?.globalProperties?.$_state;

	const modeOf = ( element ) => {
		const settings = element?.settings || {};
		let value = settings[ settingKey ];
		if ( ! value ) {
			value = legacyKeys.map( ( key ) => settings[ key ] ).find( Boolean );
		}
		const mode = String( value || '' ).trim().toLowerCase();
		return modes.has( mode ) ? mode : '';
	};

	const elements = ( s ) => {
		const list = [];
		for ( const area of [ 'content', 'header', 'footer' ] ) {
			if ( Array.isArray( s?.[ area ] ) ) {
				list.push( ...s[ area ] );
			}
		}
		for ( const component of s?.components || [] ) {
			if ( Array.isArray( component?.elements ) ) {
				list.push( ...component.elements );
			}
		}
		return list;
	};

	const escape = ( value ) => ( window.CSS?.escape ? window.CSS.escape( value ) : String( value ).replace( /["\\]/g, '\\$&' ) );

	const canvases = () => Array.from( document.querySelectorAll( 'iframe' ) )
		.map( ( frame ) => {
			try {
				return frame.contentDocument;
			} catch ( e ) {
				return null;
			}
		} )
		.filter( ( doc ) => doc?.body );

	const applyCanvas = ( id, mode ) => {
		const e = escape( id );
		for ( const doc of canvases() ) {
			for ( const node of doc.querySelectorAll( `#brxe-${ e }, .brxe-${ e }, [data-id="${ e }"], [data-element-id="${ e }"]` ) ) {
				if ( mode && node.dataset.lfTheme !== mode ) {
					node.dataset.lfTheme = mode;
				} else if ( ! mode && node.dataset.lfTheme ) {
					delete node.dataset.lfTheme;
				}
			}
		}
	};

	const applyBadge = ( id, mode ) => {
		const title = document.querySelector( `.bricks-draggable-item.element[data-id="${ escape( id ) }"]` )?.querySelector( ':scope > .structure-item > .title' );
		if ( ! title ) {
			return;
		}
		let badge = title.querySelector( ':scope > .lf-theme-badge' );
		if ( ! mode ) {
			badge?.remove();
			return;
		}
		if ( ! badge ) {
			badge = document.createElement( 'span' );
			badge.className = 'lf-theme-badge';
			badge.setAttribute( 'role', 'img' );
			title.append( badge );
		}
		if ( badge.dataset.mode !== mode ) {
			badge.dataset.mode = mode;
			badge.innerHTML = icons[ mode ];
			badge.setAttribute( 'aria-label', `${ mode } theme` );
			badge.title = `Leapfrog: ${ mode } theme`;
		}
	};

	const sync = () => {
		scheduled = false;
		const s = state();
		if ( ! s ) {
			return;
		}
		const active = new Set();
		for ( const element of elements( s ) ) {
			const id = String( element?.id || '' ).trim();
			if ( ! id || ! targets.has( String( element.name || '' ) ) ) {
				continue;
			}
			active.add( id );
			const mode = modeOf( element );
			applyCanvas( id, mode );
			applyBadge( id, mode );
		}
		document.querySelectorAll( '.bricks-draggable-item.element[data-id] .lf-theme-badge' ).forEach( ( badge ) => {
			if ( ! active.has( badge.closest( '.bricks-draggable-item.element[data-id]' )?.dataset.id || '' ) ) {
				badge.remove();
			}
		} );
	};

	// A timer rather than an animation frame: Bricks may keep the builder in a throttled tab while loading.
	const schedule = () => {
		if ( ! scheduled ) {
			scheduled = true;
			window.setTimeout( sync, 0 );
		}
	};

	const start = () => {
		document.addEventListener( 'input', schedule, true );
		document.addEventListener( 'change', schedule, true );
		document.addEventListener( 'click', schedule, true );
		new MutationObserver( schedule ).observe( document.body, { childList: true, subtree: true } );
		// The builder state can arrive late on big pages; a light heartbeat keeps the canvas in step.
		window.setInterval( schedule, 900 );
		schedule();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
