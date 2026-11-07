/**
 * Leapfrog Framework: BEM class generator for the Bricks builder.
 *
 * Right-click an element (Structure panel or canvas) and choose "Add BEM classes…".
 * A pop-up proposes a block name from the element's label and an element name for
 * every element inside it (card, card__image, card__title…). Leapfrog then creates the
 * global classes (reusing any with the same name) and adds them to the elements, in
 * one step that Bricks' undo reverses.
 */
( () => {
	'use strict';

	const config = window.leapfrogBuilderHelpers || { i18n: {} };
	const t = ( key, ...args ) => {
		let text = config.i18n?.[ key ] || key;
		args.forEach( ( a ) => ( text = text.replace( /%[sd]/, String( a ) ) ) );
		return text;
	};

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

	const label = ( el ) => {
		const p = props();
		return el.label || ( typeof p?.$_getElementLabel === 'function' ? p.$_getElementLabel( el ) : '' ) || el.name;
	};

	// Usual BEM part for an element that still has its default Bricks label.
	const PARTS = {
		heading: 'title', 'text-basic': 'text', text: 'text', 'rich-text': 'text', 'post-content': 'content',
		image: 'image', video: 'video', icon: 'icon', svg: 'icon', button: 'button', 'text-link': 'link',
		list: 'list', 'icon-box': 'feature', counter: 'number', divider: 'divider', form: 'form',
		block: 'body', div: 'inner', container: 'inner',
	};
	const partFor = ( el ) => ( el.label ? slug( el.label ) : PARTS[ el.name ] || slug( label( el ) ) ) || slug( el.name );

	const slug = ( text ) => String( text || '' ).toLowerCase().normalize( 'NFKD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	const title = ( text ) => String( text || '' ).replace( /[-_]+/g, ' ' ).replace( /^\w/, ( c ) => c.toUpperCase() );
	const validClass = ( name ) => /^-?[_a-zA-Z][_a-zA-Z0-9-]*$/.test( name );

	const settingsOf = ( el ) => ( el.settings && ! Array.isArray( el.settings ) ? el.settings : {} );
	const classIds = ( el ) => {
		const ids = settingsOf( el )._cssGlobalClasses;
		return Array.isArray( ids ) ? ids : ids && typeof ids === 'object' ? Object.values( ids ) : [];
	};
	const className = ( id ) => state()?.globalClasses?.find( ( c ) => c.id === id )?.name || '';

	// Every element inside the root, depth first. Component instances are kept whole.
	const descendants = ( root, depth = 1, out = [] ) => {
		for ( const id of root.children || [] ) {
			const el = findElement( id );
			if ( ! el ) {
				continue;
			}
			out.push( { el, depth } );
			if ( ! el.cid ) {
				descendants( el, depth + 1, out );
			}
		}
		return out;
	};

	const uid = () => {
		const taken = new Set( ( state()?.globalClasses || [] ).map( ( c ) => c.id ) );
		let id = '';
		do {
			id = Math.random().toString( 36 ).slice( 2, 8 ).padEnd( 6, 'x' );
		} while ( taken.has( id ) || ! /^[a-z]/.test( id ) );
		return id;
	};

	// Settings that are styles (their control writes CSS), so they can move to a class.
	const isStyleKey = ( el, key ) => {
		if ( key === '_cssGlobalClasses' || key === '_cssCustom' || key.startsWith( '_cssCustom:' ) || key === '_cssId' ) {
			return false;
		}
		const base = key.split( ':' )[ 0 ];
		const controls = window.bricksData?.elements?.[ el.name ]?.controls || {};
		return !! controls[ base ]?.css;
	};

	const apply = ( root, rows, options ) => {
		const s = state();
		const now = Math.round( Date.now() / 1000 );
		const created = new Set();
		const classFor = ( name ) => {
			let cls = s.globalClasses.find( ( c ) => c.name === name );
			if ( ! cls ) {
				cls = { id: uid(), name, settings: {}, modified: now, user_id: window.bricksData?.loadData?.currentUserId };
				s.globalClasses.push( cls );
				cls = s.globalClasses.find( ( c ) => c.id === cls.id );
				created.add( cls.id );
			}
			return cls;
		};
		let count = 0;
		rows.filter( ( r ) => r.on ).forEach( ( r ) => {
			const el = r.el;
			if ( ! el.settings || Array.isArray( el.settings ) ) {
				el.settings = {};
			}
			const names = [ r.className ].concat( r.modifier ? [ r.modifier ] : [] );
			names.forEach( ( name, i ) => {
				const cls = classFor( name );
				const ids = classIds( el );
				if ( ! ids.includes( cls.id ) ) {
					el.settings._cssGlobalClasses = ids.concat( cls.id );
				}
				// Move styles only onto a class made just now, so a shared class is never changed.
				if ( options.moveStyles && i === 0 && created.has( cls.id ) ) {
					Object.keys( el.settings ).filter( ( k ) => isStyleKey( el, k ) ).forEach( ( k ) => {
						if ( ! ( k in cls.settings ) ) {
							cls.settings[ k ] = el.settings[ k ];
						}
						delete el.settings[ k ];
					} );
				}
			} );
			if ( options.renameLabels ) {
				el.label = title( r.part || r.className );
			}
			count++;
		} );
		const p = props();
		if ( typeof p?.$_showMessage === 'function' ) {
			p.$_showMessage( t( 'done', count, created.size ) );
		}
	};

	// ---------------------------------------------------------------- pop-up

	const close = () => document.getElementById( 'lf-bem' )?.remove();

	const open = ( root ) => {
		close();
		const block0 = slug( label( root ) ) || slug( root.name ) || 'block';
		const rows = [ { el: root, depth: 0, part: '', on: true, isRoot: true } ].concat(
			descendants( root ).map( ( { el, depth } ) => ( {
				el,
				depth,
				part: partFor( el ),
				// Elements that already have classes, or are component instances, start unticked.
				on: ! el.cid && classIds( el ).length === 0,
			} ) )
		);

		const overlay = document.createElement( 'div' );
		overlay.id = 'lf-bem';
		overlay.className = 'lf-bem';
		overlay.innerHTML = `
			<div class="lf-bem__dialog" role="dialog" aria-modal="true" aria-labelledby="lf-bem-title">
				<header class="lf-bem__header"><h2 id="lf-bem-title">${ t( 'title' ) }</h2><button type="button" class="lf-bem__close" aria-label="${ t( 'cancel' ) }">×</button></header>
				<div class="lf-bem__fields">
					<label>${ t( 'block' ) }<input type="text" class="lf-bem__block" spellcheck="false"></label>
					<label>${ t( 'modifier' ) }<input type="text" class="lf-bem__modifier" spellcheck="false" placeholder="${ t( 'modifierPlaceholder' ) }"></label>
				</div>
				<div class="lf-bem__list" role="list"></div>
				<div class="lf-bem__options">
					<label><input type="checkbox" class="lf-bem__rename"> ${ t( 'rename' ) }</label>
					<label><input type="checkbox" class="lf-bem__move"> ${ t( 'move' ) }</label>
				</div>
				<p class="lf-bem__error" role="alert"></p>
				<footer class="lf-bem__footer"><button type="button" class="lf-bem__cancel">${ t( 'cancel' ) }</button><button type="button" class="lf-bem__apply">${ t( 'apply' ) }</button></footer>
			</div>`;
		document.body.append( overlay );

		const $ = ( sel ) => overlay.querySelector( sel );
		const blockInput = $( '.lf-bem__block' );
		const modInput = $( '.lf-bem__modifier' );
		blockInput.value = block0;

		const nameFor = ( r ) => {
			const block = slug( blockInput.value ) || 'block';
			return r.isRoot ? block : `${ block }__${ slug( r.part ) || slug( r.el.name ) }`;
		};

		const list = $( '.lf-bem__list' );
		rows.forEach( ( r ) => {
			const row = document.createElement( 'div' );
			row.className = 'lf-bem__row';
			row.setAttribute( 'role', 'listitem' );
			row.style.setProperty( '--depth', r.depth );
			const existing = classIds( r.el ).map( className ).filter( Boolean );
			row.innerHTML = `
				<input type="checkbox" class="lf-bem__on" ${ r.on ? 'checked' : '' } ${ r.isRoot ? 'disabled' : '' } aria-label="${ t( 'include' ) }">
				<span class="lf-bem__label"></span>
				${ r.isRoot ? '<span class="lf-bem__part is-root"></span>' : '<input type="text" class="lf-bem__part" spellcheck="false">' }
				<code class="lf-bem__name"></code>
				<span class="lf-bem__existing"></span>`;
			row.querySelector( '.lf-bem__label' ).textContent = `${ label( r.el ) }${ r.el.cid ? ' · ' + t( 'component' ) : '' }`;
			row.querySelector( '.lf-bem__existing' ).textContent = existing.length ? existing.map( ( n ) => '.' + n ).join( ' ' ) : '';
			const part = row.querySelector( 'input.lf-bem__part' );
			if ( part ) {
				part.value = r.part;
				part.addEventListener( 'input', () => {
					r.part = part.value;
					refresh();
				} );
			}
			row.querySelector( '.lf-bem__on' ).addEventListener( 'change', ( e ) => {
				r.on = e.target.checked;
				refresh();
			} );
			r.row = row;
			list.append( row );
		} );

		const refresh = () => {
			rows.forEach( ( r ) => {
				r.className = nameFor( r );
				r.modifier = r.isRoot && slug( modInput.value ) ? `${ r.className }--${ slug( modInput.value ) }` : '';
				r.row.querySelector( '.lf-bem__name' ).textContent = '.' + r.className + ( r.modifier ? ' .' + r.modifier : '' );
				r.row.classList.toggle( 'is-off', ! r.on );
			} );
		};
		blockInput.addEventListener( 'input', refresh );
		modInput.addEventListener( 'input', refresh );
		refresh();

		const submit = () => {
			refresh();
			const bad = rows.find( ( r ) => r.on && ( ! validClass( r.className ) || ( r.modifier && ! validClass( r.modifier ) ) ) );
			if ( bad ) {
				$( '.lf-bem__error' ).textContent = t( 'invalid', bad.className );
				return;
			}
			apply( root, rows, { renameLabels: $( '.lf-bem__rename' ).checked, moveStyles: $( '.lf-bem__move' ).checked } );
			close();
		};

		$( '.lf-bem__apply' ).addEventListener( 'click', submit );
		$( '.lf-bem__cancel' ).addEventListener( 'click', close );
		$( '.lf-bem__close' ).addEventListener( 'click', close );
		overlay.addEventListener( 'mousedown', ( e ) => e.target === overlay && close() );
		overlay.addEventListener( 'keydown', ( e ) => {
			e.stopPropagation(); // Keep Bricks' shortcuts (delete, copy…) away from the pop-up.
			if ( e.key === 'Escape' ) {
				close();
			} else if ( e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type === 'text' ) {
				submit();
			}
		} );
		blockInput.focus();
		blockInput.select();
	};

	// ---------------------------------------------------------------- context menu

	// The right-click menu exists twice: in the builder (Structure panel) and inside the
	// canvas, which runs its own copy of Bricks' state. Each records the clicked element.
	const canvas = () => {
		try {
			return document.querySelector( '#bricks-builder-iframe' )?.contentDocument || null;
		} catch ( e ) {
			return null;
		}
	};
	const canvasState = ( doc ) => doc?.querySelector( '.brx-body' )?.__vue_app__?.config?.globalProperties?.$_state;

	const menuTarget = ( st ) => {
		const id = typeof st?.showContextMenu === 'string' ? st.showContextMenu : '';
		return findElement( id );
	};

	const addMenuItem = ( doc, st ) => {
		const ul = doc?.querySelector( '#bricks-builder-context-menu > ul' );
		if ( ! ul || ul.querySelector( '.lf-bem-item' ) ) {
			return;
		}
		const target = menuTarget( st );
		if ( ! target || ! ( target.children || [] ).length ) {
			return; // BEM classes name an element and what is inside it.
		}
		const li = doc.createElement( 'li' );
		li.className = 'lf-bem-item';
		li.innerHTML = '<span class="label"></span>';
		li.querySelector( '.label' ).textContent = t( 'menu' );
		li.addEventListener( 'click', ( e ) => {
			e.stopPropagation();
			const el = menuTarget( st );
			st.showContextMenu = false;
			if ( el ) {
				open( el );
			}
		} );
		ul.append( li );
	};

	const watch = ( doc, getState ) => {
		let scheduled = false;
		new MutationObserver( () => {
			if ( ! scheduled ) {
				scheduled = true;
				window.requestAnimationFrame( () => {
					scheduled = false;
					addMenuItem( doc, getState() );
				} );
			}
		} ).observe( doc.body, { childList: true, subtree: true } );
	};

	const start = () => {
		watch( document, state );
		// The canvas can be reloaded; watch each new canvas document once.
		let watched = null;
		window.setInterval( () => {
			const doc = canvas();
			if ( doc?.body && doc !== watched ) {
				watched = doc;
				watch( doc, () => canvasState( doc ) );
			}
		}, 1000 );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}

	window.leapfrogBem = { open: ( id ) => open( findElement( id ) ) };
} )();
