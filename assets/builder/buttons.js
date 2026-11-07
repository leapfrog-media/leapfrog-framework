/**
 * Leapfrog Framework: keep Bricks Button classes in step on the builder canvas,
 * so changing Style, Size, Outline or Circle previews Leapfrog's button styles live.
 * Mirrors Leapfrog\Framework\Bricks\Buttons::classes(). Ported from CoreBridge.
 */
( () => {
	'use strict';

	const styles = new Set( [ 'secondary', 'accent', 'neutral' ] );
	const managed = new Set( [ 'lf-btn', 'lf-btn--secondary', 'lf-btn--accent', 'lf-btn--neutral', 'lf-btn--outline', 'lf-btn--circle', 'lf-btn--sm', 'lf-btn--lg', 'sm', 'md', 'lg', 'xl' ] );
	let scheduled = false;

	const state = () => document.querySelector( '.brx-body' )?.__vue_app__?.config?.globalProperties?.$_state;

	const classesFor = ( settings = {} ) => {
		const out = [ 'lf-btn' ];
		const style = String( settings.style || 'primary' ).toLowerCase();
		if ( styles.has( style ) ) {
			out.push( `lf-btn--${ style }` );
		}
		const size = String( settings.size || 'md' );
		if ( size === 'sm' || size === 'lg' ) {
			out.push( `lf-btn--${ size }` );
		}
		if ( settings.outline ) {
			out.push( 'lf-btn--outline' );
		}
		if ( settings.circle ) {
			out.push( 'lf-btn--circle' );
		}
		return out;
	};

	const escape = ( value ) => ( window.CSS?.escape ? window.CSS.escape( value ) : String( value ).replace( /["\\]/g, '\\$&' ) );

	const sync = () => {
		scheduled = false;
		const s = state();
		if ( ! s ) {
			return;
		}
		const all = [];
		for ( const area of [ 'content', 'header', 'footer' ] ) {
			if ( Array.isArray( s[ area ] ) ) {
				all.push( ...s[ area ] );
			}
		}
		for ( const component of s.components || [] ) {
			all.push( ...( component?.elements || [] ) );
		}

		const frames = Array.from( document.querySelectorAll( 'iframe' ) )
			.map( ( f ) => {
				try {
					return f.contentDocument;
				} catch ( e ) {
					return null;
				}
			} )
			.filter( ( d ) => d?.body );

		for ( const element of all ) {
			if ( element?.name !== 'button' || ! element.id ) {
				continue;
			}
			const wanted = classesFor( element.settings || {} );
			const id = escape( element.id );
			for ( const doc of frames ) {
				for ( const node of doc.querySelectorAll( `#brxe-${ id }, [data-id="${ id }"]` ) ) {
					for ( const c of [ ...node.classList ] ) {
						if ( managed.has( c ) || c.startsWith( 'bricks-background-' ) || c.startsWith( 'bricks-color-' ) ) {
							node.classList.remove( c );
						}
					}
					node.classList.add( ...wanted );
				}
			}
		}
	};

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
		window.setInterval( schedule, 900 );
		schedule();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
