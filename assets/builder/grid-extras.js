/**
 * Leapfrog Framework: extras for Bricks' Visual grid builder.
 *
 * Adds one row to Bricks' own grid builder pop-up: Leapfrog grid layouts and gaps,
 * bento layouts and "Stack on mobile". Every change goes through Bricks' own
 * $_updateSetting (so it lands on the breakpoint shown in the pop-up, on the active
 * class if one is selected, and in Bricks' undo history), then the pop-up is reopened
 * so Bricks redraws it from the new settings.
 */
( () => {
	'use strict';

	const config = window.leapfrogGridExtras || { layouts: [], gaps: [], i18n: {} };
	const t = ( key, ...args ) => {
		let text = config.i18n?.[ key ] || key;
		args.forEach( ( a ) => ( text = text.replace( /%[sd]/, String( a ) ) ) );
		return text;
	};
	const POPUP = 'visual-grid-builder';
	let message = '';

	// Bento layouts: columns, rows and each child's [column, columns spanned, row, rows spanned].
	const BENTO = {
		3: { cols: 'repeat(3, minmax(0, 1fr))', rows: 'repeat(2, minmax(200px, auto))', tiles: [ [ 1, 2, 1, 2 ], [ 3, 1, 1, 1 ], [ 3, 1, 2, 1 ] ] },
		4: { cols: 'repeat(4, minmax(0, 1fr))', rows: 'repeat(2, minmax(200px, auto))', tiles: [ [ 1, 2, 1, 2 ], [ 3, 2, 1, 1 ], [ 3, 1, 2, 1 ], [ 4, 1, 2, 1 ] ] },
		5: { cols: 'repeat(4, minmax(0, 1fr))', rows: 'repeat(2, minmax(200px, auto))', tiles: [ [ 1, 2, 1, 2 ], [ 3, 1, 1, 1 ], [ 4, 1, 1, 1 ], [ 3, 1, 2, 1 ], [ 4, 1, 2, 1 ] ] },
		6: { cols: 'repeat(3, minmax(0, 1fr))', rows: 'repeat(3, minmax(200px, auto))', tiles: [ [ 1, 2, 1, 2 ], [ 3, 1, 1, 1 ], [ 3, 1, 2, 1 ], [ 1, 1, 3, 1 ], [ 2, 1, 3, 1 ], [ 3, 1, 3, 1 ] ] },
	};

	// Bricks' grid builder keeps placements it can't save as element settings in a marked
	// section of the grid's custom CSS. Leapfrog reads and writes that section the same way.
	const START = '/** VISUAL GRID BUILDER - START **/';
	const END = '/** VISUAL GRID BUILDER - END **/';

	const props = () => document.querySelector( '.brx-body' )?.__vue_app__?.config?.globalProperties;
	const state = () => props()?.$_state;

	const findElement = ( id ) => {
		const s = state();
		for ( const area of [ 'content', 'header', 'footer' ] ) {
			const found = Array.isArray( s?.[ area ] ) ? s[ area ].find( ( e ) => e?.id === id ) : null;
			if ( found ) {
				return found;
			}
		}
		return null;
	};

	// Same target as Bricks' grid builder: the selected class, else the active element.
	const targetId = () => state()?.activeClass?.id || state()?.activeElement?.id;

	const children = () => {
		const parent = findElement( state()?.activeElement?.id );
		return ( parent?.children || [] ).map( findElement ).filter( Boolean );
	};

	const isLoop = ( el ) => !! ( el?.settings && ! Array.isArray( el.settings ) && el.settings.hasLoop );

	const write = ( id, key, value ) => props()?.$_updateSetting( id, key, value );

	const currentBreakpoint = () => {
		const s = state();
		return s?.breakpoints?.find( ( b ) => b.key === s.breakpointActive );
	};

	// Bricks reads its settings when the pop-up opens, so close and reopen it to redraw.
	const reopen = ( note ) => {
		const s = state();
		message = note || '';
		s.popup = false;
		window.setTimeout( () => {
			s.popup = POPUP;
		}, 60 );
	};

	const applyLayout = ( value, label ) => {
		write( targetId(), '_gridTemplateColumns', value );
		reopen( label );
	};

	const applyGap = ( value, label ) => {
		write( targetId(), '_gridGap', value );
		reopen( label );
	};

	const bentoProblem = () => {
		const kids = children();
		return ! kids.length || kids.some( isLoop ) ? t( 'needsElement' ) : '';
	};

	// Bricks saves a child's placement as its own settings only when it is a layout element
	// (container, block, div) and no class is selected; otherwise it goes in the grid's custom CSS.
	const usesSettings = ( kid ) => {
		const p = props();
		const nestable = typeof p?.$_isNestable === 'function' ? p.$_isNestable( kid ) : [ 'container', 'block', 'div', 'section' ].includes( kid.name );
		const componentRoot = typeof p?.$_isComponentRoot === 'function' && p.$_isComponentRoot( kid );
		return ! state()?.activeClass && nestable && ! componentRoot;
	};

	const activeSelector = () => {
		const s = state();
		return s.activeClass ? `.${ s.activeClass.name }` : `#brxe-${ s.activeElement?.id }`;
	};

	const cssKey = () => ( currentBreakpoint()?.base ? '_cssCustom' : `_cssCustom:${ state().breakpointActive }` );

	const customCss = ( key = cssKey() ) => {
		const s = state();
		const settings = s.activeClass?.settings || s.activeElement?.settings;
		return settings && ! Array.isArray( settings ) && typeof settings[ key ] === 'string' ? settings[ key ] : '';
	};

	const section = ( css ) => {
		const a = css.indexOf( START );
		const b = css.indexOf( END );
		return a !== -1 && b > a ? css.slice( a + START.length, b ).trim() : '';
	};

	// Adds, replaces or (props null) removes one child's block, in Bricks' own format.
	const setBlock = ( text, kid, n, rules ) => {
		const label = `/** ${ kid.id } - ${ n } **/`;
		let at = text.indexOf( label );
		if ( at !== -1 ) {
			const open = text.indexOf( '{', at );
			let depth = 1;
			let close = open + 1;
			while ( depth > 0 && close < text.length ) {
				depth += text[ close ] === '{' ? 1 : text[ close ] === '}' ? -1 : 0;
				close++;
			}
			text = ( text.slice( 0, at ) + text.slice( close ).replace( /^\s*\n/, '' ) ).trim();
			at = -1;
		}
		if ( ! rules ) {
			return text;
		}
		const body = Object.entries( rules ).map( ( [ k, v ] ) => `  ${ k }: ${ v };\n` ).join( '' );
		return `${ text ? text + '\n' : '' }${ label }\n${ activeSelector() } :nth-child(${ n }) {\n${ body }}\n`.trim();
	};

	const withSection = ( css, text ) => {
		let out = css;
		if ( out.indexOf( START ) === -1 || out.indexOf( END ) === -1 ) {
			out = ( out.trim() ? out.trimEnd() + '\n\n' : '' ) + START + '\n\n' + END;
		}
		const a = out.indexOf( START ) + START.length;
		const b = out.indexOf( END );
		if ( ! text.trim() ) {
			// Nothing left for the grid builder: drop the markers, as Bricks does.
			return ( out.slice( 0, out.indexOf( START ) ).trimEnd() + '\n\n' + out.slice( b + END.length ).trimStart() ).trim();
		}
		return out.slice( 0, a ) + '\n\n' + text.trim() + '\n\n' + out.slice( b );
	};

	const applyBento = ( count ) => {
		const pattern = BENTO[ count ];
		if ( ! pattern || bentoProblem() ) {
			return;
		}
		const id = targetId();
		const before = customCss();
		let text = section( before );
		write( id, '_gridTemplateColumns', pattern.cols );
		write( id, '_gridTemplateRows', pattern.rows );
		children().forEach( ( kid, i ) => {
			const tile = pattern.tiles[ i ];
			const column = tile ? `${ tile[ 0 ] } / ${ tile[ 0 ] + tile[ 1 ] }` : '';
			const row = tile ? `${ tile[ 2 ] } / ${ tile[ 2 ] + tile[ 3 ] }` : '';
			if ( usesSettings( kid ) ) {
				text = setBlock( text, kid, i + 1, null );
				write( kid.id, '_gridItemColumnSpan', column );
				write( kid.id, '_gridItemRowSpan', row );
			} else {
				text = setBlock( text, kid, i + 1, tile ? { 'grid-column': column, 'grid-row': row } : null );
			}
		} );
		const after = withSection( before, text );
		if ( after !== before ) {
			write( id, '_cssCustom', after );
		}
		reopen( t( 'bentoTiles', count ) );
	};

	const hasPlacement = ( el ) => el?.settings && ! Array.isArray( el.settings ) && Object.keys( el.settings ).some( ( k ) => /^_gridItem(Column|Row)Span/.test( k ) );

	const stackOnMobile = () => {
		const s = state();
		const breakpoints = [ ...( s?.breakpoints || [] ) ];
		const smallest = breakpoints.sort( ( a, b ) => Number( a.width ) - Number( b.width ) )[ 0 ];
		if ( ! smallest ) {
			return;
		}
		const id = targetId();
		const kids = children();
		// Children placed through the grid's custom CSS, at any breakpoint.
		const cssPlaced = new Set();
		const settings = s.activeClass?.settings || s.activeElement?.settings || {};
		Object.keys( Array.isArray( settings ) ? {} : settings ).filter( ( k ) => /^_cssCustom/.test( k ) ).forEach( ( k ) => {
			kids.forEach( ( kid, i ) => section( String( settings[ k ] ) ).includes( `/** ${ kid.id } - ${ i + 1 } **/` ) && cssPlaced.add( kid.id ) );
		} );

		const previous = s.breakpointActive;
		// $_updateSetting writes to the active breakpoint; switch and switch back in the same tick.
		s.breakpointActive = smallest.key;
		write( id, '_gridTemplateColumns', 'var(--grid-1)' );
		write( id, '_gridTemplateRows', 'none' );
		const before = customCss();
		let text = section( before );
		kids.forEach( ( kid, i ) => {
			if ( isLoop( kid ) ) {
				return;
			}
			if ( cssPlaced.has( kid.id ) ) {
				text = setBlock( text, kid, i + 1, { 'grid-column': 'auto', 'grid-row': 'auto' } );
			}
			if ( hasPlacement( kid ) ) {
				write( kid.id, '_gridItemColumnSpan', 'auto' );
				write( kid.id, '_gridItemRowSpan', 'auto' );
			}
		} );
		const after = withSection( before, text );
		if ( after !== before ) {
			write( id, '_cssCustom', after );
		}
		s.breakpointActive = previous;
		reopen( kids.some( isLoop ) ? t( 'stackLoop' ) : `${ t( 'stack' ) }: ${ smallest.label }` );
	};

	const select = ( placeholder, options, onPick, extra = {} ) => {
		const el = document.createElement( 'select' );
		el.className = 'lf-grid-extras__select';
		el.setAttribute( 'aria-label', placeholder );
		el.append( new Option( placeholder, '' ) );
		options.forEach( ( o ) => el.append( new Option( o.label, o.value ) ) );
		Object.assign( el, extra );
		el.addEventListener( 'change', () => {
			const option = el.selectedOptions[ 0 ];
			if ( el.value ) {
				onPick( el.value, option.textContent );
			}
			el.value = '';
		} );
		return el;
	};

	const build = ( popup ) => {
		const row = document.createElement( 'div' );
		row.className = 'lf-grid-extras';

		const title = document.createElement( 'span' );
		title.className = 'lf-grid-extras__title';
		title.textContent = t( 'title' );
		row.append( title );

		row.append( select( t( 'layout' ), config.layouts, applyLayout ) );
		row.append( select( t( 'gap' ), config.gaps, applyGap ) );

		const problem = bentoProblem();
		const bento = select( t( 'bento' ), Object.keys( BENTO ).map( ( n ) => ( { value: n, label: t( 'bentoTiles', n ) } ) ), ( v ) => applyBento( Number( v ) ) );
		if ( problem ) {
			bento.disabled = true;
			bento.title = problem;
		}
		row.append( bento );

		const stack = document.createElement( 'button' );
		stack.type = 'button';
		stack.className = 'lf-grid-extras__button';
		stack.textContent = t( 'stack' );
		stack.title = t( 'stackHelp' );
		stack.addEventListener( 'click', stackOnMobile );
		row.append( stack );

		const where = document.createElement( 'span' );
		where.className = 'lf-grid-extras__where';
		row.append( where );

		const note = document.createElement( 'span' );
		note.className = 'lf-grid-extras__note';
		note.setAttribute( 'role', 'status' );
		if ( message ) {
			note.textContent = `${ t( 'done' ) }: ${ message }`;
			message = '';
			window.setTimeout( () => ( note.textContent = '' ), 4000 );
		}
		row.append( note );

		const anchor = popup.querySelector( '.sub-header' ) || popup.querySelector( 'header' );
		anchor?.after( row );
		updateWhere( popup );
	};

	const updateWhere = ( popup ) => {
		const where = popup.querySelector( '.lf-grid-extras__where' );
		const bp = currentBreakpoint();
		if ( where && bp ) {
			where.textContent = t( 'breakpointAt', bp.label );
		}
	};

	const check = () => {
		const popup = document.querySelector( `#bricks-popup.${ POPUP }` );
		if ( ! popup ) {
			return;
		}
		if ( ! popup.querySelector( '.lf-grid-extras' ) ) {
			build( popup );
		} else {
			updateWhere( popup );
		}
	};

	const start = () => {
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
		// Breakpoint buttons only change a class; refresh the "Applies to" label after clicks.
		document.addEventListener( 'click', () => window.setTimeout( check, 0 ), true );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
