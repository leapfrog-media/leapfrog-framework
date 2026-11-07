/**
 * Leapfrog Framework: contextual variable picker in the Bricks builder.
 *
 * Right-click a style field (or click Bricks' variable button) to see only the
 * variables that fit it: spacing for padding, colours for colour, and so on, with
 * the ones made for the selected element first. Hover previews a variable; Enter
 * or a click applies it. For colours, an opacity slider applies a live
 * color-mix() without creating a new variable. Ported from CoreBridge.
 */
( () => {
	'use strict';

	const data = window.leapfrogVariablePicker || {};
	const rules = window.LeapfrogVariableRules;
	if ( ! data.enabled || ! rules || ! Array.isArray( data.variables ) || ! data.variables.length ) {
		return;
	}

	const state = { input: null, original: '', previewed: false, context: null, showAll: false, query: '', activeIndex: 0, opacity: 100, popup: null };
	const categories = data.categories || {};

	// Scale steps sort by size (space-3xs … space-3xl), numbers numerically (primary-10 … primary-90).
	const STEPS = [ '4xs', '3xs', '2xs', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl' ];
	const stepOf = ( name ) => name.match( /^(.*)-(4xs|3xs|2xs|xs|s|m|l|xl|2xl|3xl|4xl|5xl|6xl)$/ );
	const byName = ( a, b ) => {
		const sa = stepOf( a );
		const sb = stepOf( b );
		if ( sa && sb && sa[ 1 ] === sb[ 1 ] ) {
			return STEPS.indexOf( sa[ 2 ] ) - STEPS.indexOf( sb[ 2 ] );
		}
		return a.localeCompare( b, undefined, { numeric: true } );
	};
	const variables = rules.dedupeVariables( data.variables ).sort( ( a, b ) => byName( a.name, b.name ) );

	const valueFor = ( variable ) => {
		const ref = `var(--${ variable.name })`;
		return state.context?.isColour && state.opacity < 100 ? `color-mix(in oklch, ${ ref } ${ state.opacity }%, transparent)` : ref;
	};

	function canvasDescriptor() {
		try {
			const frame = document.querySelector( '#bricks-builder-iframe' );
			const doc = frame && frame.contentDocument;
			const selectedId = document.querySelector( 'li.element.active[data-id]' )?.dataset.id;
			const active = doc && ( ( selectedId && doc.querySelector( `[data-id="${ selectedId }"]` ) ) || doc.querySelector( '.is-active-element[data-id]' ) );
			if ( ! active ) {
				return {};
			}
			const classes = Array.from( active.classList );
			const computed = frame.contentWindow.getComputedStyle( active );
			return {
				elementType: ( classes.find( ( c ) => c.startsWith( 'brxe-' ) ) || '' ).replace( /^brxe-/, '' ),
				elementTag: active.tagName.toLowerCase(),
				elementClasses: classes.join( ' ' ),
				display: computed.display,
				flexDirection: computed.flexDirection,
			};
		} catch ( e ) {
			return {};
		}
	}

	function describe( input ) {
		const control = input.closest( '.control, [class*="control-"]' );
		const inner = input.closest( '.control-inner' ) || control;
		const label = inner && inner.querySelector( 'label' );
		const handle = input.closest( '.handle[title], [class*="handle"][title]' );
		const composite = input.closest( '.variables-ui[data-control], [data-control="spacing"], [data-control="typography"], [data-control="border"]' );
		const keyed = input.closest( '[data-controlkey]' );
		return {
			controlId: keyed?.getAttribute( 'data-controlkey' ) || input.id || input.getAttribute( 'name' ) || '',
			property: handle?.getAttribute( 'title' ) || label?.getAttribute( 'title' ) || '',
			label: label?.getAttribute( 'title' ) || label?.textContent || inner?.textContent || input.getAttribute( 'aria-label' ) || input.getAttribute( 'placeholder' ) || '',
			controlType: composite?.dataset.control || input.parentElement?.dataset.control || '',
			...canvasDescriptor(),
		};
	}

	function rows() {
		const query = rules.normalise( state.query ).trim();
		return variables
			.map( ( variable ) => ( {
				variable,
				assessment: state.showAll ? { rank: 3, section: categories[ variable.category ] || variable.category || 'Other' } : rules.assess( variable, state.context ),
			} ) )
			.filter( ( r ) => r.assessment && ( ! query || rules.normalise( `${ r.variable.name } ${ r.variable.value }` ).includes( query ) ) )
			.sort( ( a, b ) => a.assessment.rank - b.assessment.rank || a.assessment.section.localeCompare( b.assessment.section ) || byName( a.variable.name, b.variable.name ) );
	}

	function controlInput( target ) {
		if ( ! ( target instanceof Element ) ) {
			return null;
		}
		const input = target.closest( 'input, textarea, [contenteditable="true"]' );
		if ( ! input || input.matches( '[type="checkbox"], [type="radio"], [type="file"], [type="range"], [disabled], [readonly]' ) ) {
			return null;
		}
		if ( input.closest( '.CodeMirror, .cm-editor, [class*="code-editor"], [role="dialog"], .lf-picker' ) ) {
			return null;
		}
		return input;
	}

	function inputNear( trigger ) {
		let node = trigger;
		for ( let depth = 0; node && depth < 5; depth += 1, node = node.parentElement ) {
			const inputs = node.querySelectorAll ? node.querySelectorAll( 'input:not([type="hidden"]), textarea, [contenteditable="true"]' ) : [];
			if ( inputs.length === 1 ) {
				return controlInput( inputs[ 0 ] );
			}
		}
		return null;
	}

	function variableTrigger( target ) {
		const trigger = target.closest && target.closest( 'button, [role="button"], .variable-picker-button' );
		if ( ! trigger || trigger.closest( '.lf-picker' ) ) {
			return null;
		}
		const signature = rules.normalise( `${ trigger.className || '' } ${ trigger.getAttribute( 'title' ) || '' } ${ trigger.getAttribute( 'aria-label' ) || '' } ${ trigger.getAttribute( 'data-balloon' ) || '' }` );
		return /(^|\s)(css\s*)?variable(s)?(\s|$)/.test( signature ) ? trigger : null;
	}

	function setValue( input, value ) {
		if ( ! input ) {
			return;
		}
		if ( input.isContentEditable ) {
			input.textContent = value;
			input.dispatchEvent( new InputEvent( 'input', { bubbles: true, inputType: 'insertText', data: value } ) );
			return;
		}
		const proto = input instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
		const setter = Object.getOwnPropertyDescriptor( proto, 'value' )?.set;
		if ( setter ) {
			setter.call( input, value );
		} else {
			input.value = value;
		}
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function restore() {
		if ( state.previewed && state.input ) {
			setValue( state.input, state.original );
			state.previewed = false;
		}
	}

	function preview( variable ) {
		if ( state.input ) {
			setValue( state.input, valueFor( variable ) );
			state.previewed = true;
		}
	}

	function commit( variable ) {
		if ( ! state.input ) {
			return;
		}
		const value = valueFor( variable );
		setValue( state.input, value );
		state.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		state.original = value;
		state.previewed = false;
		close();
	}

	function close() {
		restore();
		state.popup?.remove();
		state.popup = null;
		state.input = null;
		state.context = null;
	}

	function render() {
		if ( ! state.popup ) {
			return;
		}
		const list = state.popup.querySelector( '[data-lf-list]' );
		const items = rows();
		state.activeIndex = Math.max( 0, Math.min( state.activeIndex, items.length - 1 ) );
		list.replaceChildren();

		if ( ! items.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'lf-picker__empty';
			empty.textContent = 'No matching variables';
			list.append( empty );
			return;
		}

		let section = null;
		items.forEach( ( row, index ) => {
			if ( row.assessment.section !== section ) {
				section = row.assessment.section;
				const heading = document.createElement( 'div' );
				heading.className = 'lf-picker__section';
				heading.textContent = section;
				list.append( heading );
			}
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'lf-picker__item';
			button.dataset.index = String( index );
			button.setAttribute( 'role', 'option' );
			button.setAttribute( 'aria-selected', index === state.activeIndex ? 'true' : 'false' );
			button.innerHTML = '<span class="lf-picker__swatch" aria-hidden="true"></span><span class="lf-picker__name"></span><span class="lf-picker__value"></span>';
			if ( row.variable.type === 'colour' ) {
				// The builder shell doesn't have the site's variables, so the server sends a resolved swatch.
				button.children[ 0 ].style.background = row.variable.swatch || row.variable.value;
			} else {
				button.children[ 0 ].remove();
			}
			button.querySelector( '.lf-picker__name' ).textContent = `--${ row.variable.name }`;
			button.querySelector( '.lf-picker__value' ).textContent = row.variable.value;
			button.addEventListener( 'pointerenter', () => preview( row.variable ) );
			button.addEventListener( 'pointerleave', restore );
			button.addEventListener( 'focus', () => {
				state.activeIndex = index;
				preview( row.variable );
			} );
			button.addEventListener( 'blur', restore );
			button.addEventListener( 'click', () => commit( row.variable ) );
			list.append( button );
		} );
	}

	function place( popup, x, y ) {
		const margin = 10;
		const rect = popup.getBoundingClientRect();
		popup.style.left = `${ Math.max( margin, Math.min( x, window.innerWidth - rect.width - margin ) ) }px`;
		popup.style.top = `${ Math.max( margin, Math.min( y, window.innerHeight - rect.height - margin ) ) }px`;
	}

	function open( input, x, y ) {
		const context = rules.resolveControl( describe( input ) );
		if ( ! context ) {
			return false;
		}
		close();
		Object.assign( state, { input, context, original: input.isContentEditable ? input.textContent || '' : input.value || '', showAll: false, query: '', activeIndex: 0, opacity: 100 } );

		const popup = document.createElement( 'section' );
		popup.className = 'lf-picker';
		popup.setAttribute( 'role', 'dialog' );
		popup.setAttribute( 'aria-label', `${ context.label } variables` );
		popup.innerHTML = `
			<header class="lf-picker__header">
				<strong></strong>
				<button type="button" data-lf-close aria-label="Close">&times;</button>
			</header>
			<div class="lf-picker__toolbar">
				<input type="search" data-lf-search placeholder="Search" aria-label="Search variables">
				<button type="button" data-lf-all aria-pressed="false">All variables</button>
			</div>
			${ context.isColour ? '<label class="lf-picker__opacity"><span>Opacity</span><input type="range" min="5" max="100" step="5" value="100" data-lf-opacity><output data-lf-opacity-value>100%</output></label>' : '' }
			<div class="lf-picker__list" data-lf-list role="listbox"></div>`;
		popup.querySelector( 'strong' ).textContent = `${ context.label } · Leapfrog`;
		document.body.append( popup );
		state.popup = popup;

		popup.querySelector( '[data-lf-close]' ).addEventListener( 'click', close );
		popup.querySelector( '[data-lf-all]' ).addEventListener( 'click', ( e ) => {
			state.showAll = ! state.showAll;
			e.currentTarget.setAttribute( 'aria-pressed', state.showAll ? 'true' : 'false' );
			e.currentTarget.textContent = state.showAll ? 'Fitting only' : 'All variables';
			state.activeIndex = 0;
			render();
		} );
		popup.querySelector( '[data-lf-search]' ).addEventListener( 'input', ( e ) => {
			state.query = e.target.value;
			state.activeIndex = 0;
			render();
		} );
		popup.querySelector( '[data-lf-opacity]' )?.addEventListener( 'input', ( e ) => {
			state.opacity = Number( e.target.value );
			popup.querySelector( '[data-lf-opacity-value]' ).textContent = `${ state.opacity }%`;
		} );
		popup.addEventListener( 'keydown', onKeydown );
		render();
		place( popup, x, y );
		popup.querySelector( '[data-lf-search]' ).focus();
		return true;
	}

	function onKeydown( event ) {
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			close();
			return;
		}
		if ( ! [ 'ArrowDown', 'ArrowUp', 'Enter' ].includes( event.key ) || event.target.matches( '[data-lf-opacity]' ) ) {
			return;
		}
		const items = rows();
		if ( ! items.length ) {
			return;
		}
		event.preventDefault();
		if ( event.key === 'Enter' ) {
			commit( items[ state.activeIndex ].variable );
			return;
		}
		state.activeIndex = ( state.activeIndex + ( event.key === 'ArrowDown' ? 1 : -1 ) + items.length ) % items.length;
		render();
		state.popup?.querySelector( `[data-index="${ state.activeIndex }"]` )?.scrollIntoView( { block: 'nearest' } );
	}

	document.addEventListener( 'contextmenu', ( event ) => {
		const input = controlInput( event.target );
		if ( ! input || ! open( input, event.clientX, event.clientY ) ) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
	}, true );

	document.addEventListener( 'click', ( event ) => {
		if ( state.popup && ! state.popup.contains( event.target ) ) {
			close();
		}
	}, true );

	document.addEventListener( 'click', ( event ) => {
		const trigger = variableTrigger( event.target );
		const input = trigger && inputNear( trigger );
		if ( ! input ) {
			return;
		}
		const rect = trigger.getBoundingClientRect();
		if ( open( input, rect.right + 6, rect.top ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true );
} )();
