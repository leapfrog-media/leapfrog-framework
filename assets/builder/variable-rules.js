/**
 * Leapfrog Framework: which variables suit which Bricks control.
 *
 * resolveControl() reads a control (padding-top, gap, background colour…) and the
 * selected element; assess() says whether a variable fits and how strongly:
 * rank 0 "Recommended" (made for this component), 1 "Semantic" (a role), 2 "Scale"
 * (a raw scale step or palette colour). The control-reading logic is CoreBridge's;
 * the variable rules are written for Leapfrog's names.
 */
( function ( root, factory ) {
	'use strict';
	const api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}
	if ( root ) {
		root.LeapfrogVariableRules = Object.freeze( api );
	}
} )( typeof window !== 'undefined' ? window : globalThis, function () {
	'use strict';

	const spacingPurposes = new Set( [ 'padding', 'margin', 'gap', 'inset', 'offset' ] );
	const colourPurposes = new Set( [ 'color', 'background-color', 'border-color' ] );
	const componentAliases = {
		button: [ 'button', 'control' ],
		field: [ 'field', 'form', 'control' ],
		form: [ 'form', 'field' ],
		card: [ 'card' ],
		header: [ 'header', 'navigation' ],
		navigation: [ 'navigation', 'header' ],
		section: [ 'section' ],
		container: [ 'container' ],
		content: [ 'content', 'prose' ],
		prose: [ 'prose', 'content' ],
	};

	function normalise( value ) {
		return String( value || '' ).replace( /([a-z])([A-Z])/g, '$1 $2' ).replace( /[_-]+/g, ' ' ).replace( /\s+/g, ' ' ).trim().toLowerCase();
	}

	function dedupeVariables( items ) {
		const unique = new Map();
		for ( const item of Array.isArray( items ) ? items : [] ) {
			if ( item && item.name && item.value !== undefined && ! unique.has( item.name ) ) {
				unique.set( item.name, item );
			}
		}
		return Array.from( unique.values() );
	}

	// [pattern, meta]. First match wins. purposes: what the variable is for; components: where it is made for.
	const rules = [
		// Layout roles.
		[ /^gutter$/, { tier: 'semantic', purposes: [ 'padding' ], axes: [ 'inline' ], components: [ 'section', 'container' ] } ],
		[ /^section-space(-compact|-spacious)?$/, { tier: 'semantic', purposes: [ 'padding', 'margin' ], axes: [ 'block' ], components: [ 'section' ] } ],
		[ /^gap$/, { tier: 'semantic', purposes: [ 'gap' ] } ],
		[ /^flow$/, { tier: 'semantic', purposes: [ 'gap', 'margin' ], axes: [ 'block' ], components: [ 'prose', 'content' ] } ],
		[ /^width-(content|wide|narrow)$/, { tier: 'semantic', purposes: [ 'width' ], components: [ 'container', 'section' ] } ],
		[ /^width-text$/, { tier: 'semantic', purposes: [ 'width' ], components: [ 'prose', 'content' ] } ],

		// Component tokens.
		[ /^btn-padding-block$/, { tier: 'semantic', purposes: [ 'padding' ], axes: [ 'block' ], components: [ 'button' ] } ],
		[ /^btn-padding-inline$/, { tier: 'semantic', purposes: [ 'padding' ], axes: [ 'inline' ], components: [ 'button' ] } ],
		[ /^btn-gap$/, { tier: 'semantic', purposes: [ 'gap' ], components: [ 'button' ] } ],
		[ /^btn-min-height$/, { tier: 'semantic', purposes: [ 'height' ], components: [ 'button' ] } ],
		[ /^btn-font$/, { tier: 'semantic', purposes: [ 'font-family' ], components: [ 'button' ] } ],
		[ /^btn-font-size(-sm|-lg)?$/, { tier: 'semantic', purposes: [ 'font-size' ], components: [ 'button' ] } ],
		[ /^btn-font-weight$/, { tier: 'semantic', purposes: [ 'font-weight' ], components: [ 'button' ] } ],
		[ /^btn-line-height$/, { tier: 'semantic', purposes: [ 'line-height' ], components: [ 'button' ] } ],
		[ /^btn-radius$/, { tier: 'semantic', purposes: [ 'radius' ], components: [ 'button' ] } ],
		[ /^btn-border-width$/, { tier: 'semantic', purposes: [ 'border-width' ], components: [ 'button' ] } ],
		[ /^field-padding-block$/, { tier: 'semantic', purposes: [ 'padding' ], axes: [ 'block' ], components: [ 'field' ] } ],
		[ /^field-padding-inline$/, { tier: 'semantic', purposes: [ 'padding' ], axes: [ 'inline' ], components: [ 'field' ] } ],
		[ /^field-gap$/, { tier: 'semantic', purposes: [ 'gap' ], components: [ 'field', 'form' ] } ],
		[ /^field-radius$/, { tier: 'semantic', purposes: [ 'radius' ], components: [ 'field' ] } ],
		[ /^field-border-width$/, { tier: 'semantic', purposes: [ 'border-width' ], components: [ 'field' ] } ],
		[ /^field-bg$/, { tier: 'semantic', purposes: [ 'background-color' ], components: [ 'field' ] } ],
		[ /^field-color$/, { tier: 'semantic', purposes: [ 'color' ], components: [ 'field' ] } ],
		[ /^field-(border|focus)-color$/, { tier: 'semantic', purposes: [ 'border-color' ], components: [ 'field' ] } ],
		[ /^card-padding$/, { tier: 'semantic', purposes: [ 'padding' ], components: [ 'card' ] } ],
		[ /^card-gap$/, { tier: 'semantic', purposes: [ 'gap' ], components: [ 'card' ] } ],
		[ /^card-radius$/, { tier: 'semantic', purposes: [ 'radius' ], components: [ 'card' ] } ],
		[ /^card-bg$/, { tier: 'semantic', purposes: [ 'background-color' ], components: [ 'card' ] } ],
		[ /^card-border-color$/, { tier: 'semantic', purposes: [ 'border-color' ], components: [ 'card' ] } ],
		[ /^card-shadow$/, { tier: 'semantic', purposes: [ 'shadow' ], components: [ 'card' ] } ],
		[ /^header-height$/, { tier: 'semantic', purposes: [ 'height' ], components: [ 'header' ] } ],
		[ /^header-padding-block$/, { tier: 'semantic', purposes: [ 'padding' ], axes: [ 'block' ], components: [ 'header' ] } ],
		[ /^header-bg$/, { tier: 'semantic', purposes: [ 'background-color' ], components: [ 'header' ] } ],
		[ /^header-color$/, { tier: 'semantic', purposes: [ 'color' ], components: [ 'header' ] } ],
		[ /^header-border-color$/, { tier: 'semantic', purposes: [ 'border-color' ], components: [ 'header' ] } ],

		// Typography roles.
		[ /^font-(body|heading|mono)$/, { tier: 'semantic', purposes: [ 'font-family' ] } ],
		[ /^(h[1-6]|body-size|lead-size|small-size)$/, { tier: 'semantic', purposes: [ 'font-size' ] } ],
		[ /^line-height-(body|heading)$/, { tier: 'semantic', purposes: [ 'line-height' ] } ],

		// Colour roles (palette "Leapfrog roles").
		[ /^color-(text|text-muted|heading|link|link-hover|on-[a-z]+)$/, { tier: 'semantic', purposes: [ 'color' ] } ],
		[ /^color-(canvas|surface|surface-muted|surface-inverse|surface-hover|overlay|[a-z]+-surface)$/, { tier: 'semantic', purposes: [ 'background-color' ] } ],
		[ /^color-(border|border-strong|border-subtle|focus)$/, { tier: 'semantic', purposes: [ 'border-color' ] } ],

		// Shape, motion and layers.
		[ /^radius-(s|m|l|full)$/, { tier: 'primitive', purposes: [ 'radius' ] } ],
		[ /^border-(thin|thick)$/, { tier: 'primitive', purposes: [ 'border-width' ] } ],
		[ /^shadow-(s|m|l)$/, { tier: 'primitive', purposes: [ 'shadow' ] } ],
		[ /^duration-[a-z]+$/, { tier: 'primitive', purposes: [ 'duration' ] } ],
		[ /^ease-[a-z-]+$/, { tier: 'primitive', purposes: [ 'easing' ] } ],
		[ /^z-[a-z]+$/, { tier: 'primitive', purposes: [ 'z-index' ] } ],

		// Scales.
		[ /^text-(\d?x?s|m|\d?x?l)$/, { tier: 'primitive', purposes: [ 'font-size' ] } ],
		[ /^space-(\d?x?s|m|\d?x?l)$/, { tier: 'primitive', purposes: [ 'spacing' ] } ],
	];

	function variableMeta( variable ) {
		const name = String( ( variable && variable.name ) || '' );
		for ( const [ pattern, meta ] of rules ) {
			if ( pattern.test( name ) ) {
				return { axes: [ 'any' ], components: [], ...meta };
			}
		}
		// Any other palette colour (brand colours, shades, transparency steps).
		if ( variable && variable.type === 'colour' ) {
			return { tier: 'primitive', purposes: [ 'colour' ], axes: [ 'any' ], components: [] };
		}
		return null;
	}

	const controlIdMap = {
		_padding: 'padding', _margin: 'margin', _gridGap: 'gap', _columnGap: 'column-gap', _rowGap: 'row-gap',
		_width: 'width', _widthMin: 'min-width', _widthMax: 'max-width', _height: 'height', _heightMin: 'min-height', _heightMax: 'max-height',
		_inlineSize: 'width', _inlineSizeMin: 'min-width', _inlineSizeMax: 'max-width', _blockSize: 'height', _blockSizeMin: 'min-height', _blockSizeMax: 'max-height',
		_aspectRatio: 'aspect-ratio', _top: 'top', _right: 'right', _bottom: 'bottom', _left: 'left', _zIndex: 'z-index',
		_flexBasis: 'width', _cssTransition: 'transition',
	};

	function componentHint( value ) {
		const text = normalise( value );
		const tests = [
			[ 'button', /(^| )(btn|button)( |$)/ ], [ 'field', /(^| )(field|input|textarea)( |$)/ ], [ 'form', /(^| )form( |$)/ ],
			[ 'card', /(^| )card( |$)/ ], [ 'header', /(^| )header( |$)/ ], [ 'navigation', /(^| )(nav|navigation)( |$)/ ],
		];
		for ( const [ component, regex ] of tests ) {
			if ( regex.test( text ) ) {
				return component;
			}
		}
		return '';
	}

	function activeComponent( d ) {
		const classes = new Set( String( d.elementClasses || '' ).split( /\s+/ ).filter( Boolean ) );
		const type = String( d.elementType || '' ).toLowerCase();
		const tag = String( d.elementTag || '' ).toLowerCase();
		if ( type === 'button' || classes.has( 'lf-btn' ) || classes.has( 'bricks-button' ) ) {
			return 'button';
		}
		if ( [ ...classes ].some( ( c ) => /(^|-)card(-|$|__)/.test( c ) ) ) {
			return 'card';
		}
		if ( tag === 'header' || classes.has( 'header' ) ) {
			return 'header';
		}
		if ( /^(nav-|nav$|dropdown)/.test( type ) || tag === 'nav' ) {
			return 'navigation';
		}
		if ( type === 'form' || classes.has( 'form' ) ) {
			return 'form';
		}
		if ( type === 'section' ) {
			return 'section';
		}
		if ( type === 'container' ) {
			return 'container';
		}
		if ( /^(heading|text|text-basic|post-content|rich-text)/.test( type ) ) {
			return 'prose';
		}
		if ( /^(div|block)$/.test( type ) ) {
			return 'content';
		}
		return '';
	}

	function propertyFromText( value ) {
		const text = normalise( value );
		const direct = [
			[ 'padding', /padding/ ], [ 'margin', /margin/ ], [ 'gap', /(^| )(gap|gutter|spacing)( |$)/ ],
			[ 'min-width', /(^| )min(?:imum)?\.? (width|inline size)( |$)/ ], [ 'max-width', /(^| )max(?:imum)?\.? (width|inline size)( |$)/ ], [ 'width', /(^| )(width|inline size)( |$)/ ],
			[ 'min-height', /(^| )min(?:imum)?\.? (height|block size)( |$)/ ], [ 'max-height', /(^| )max(?:imum)?\.? (height|block size)( |$)/ ], [ 'height', /(^| )(height|block size)( |$)/ ],
			[ 'aspect-ratio', /aspect ratio/ ], [ 'z-index', /z index/ ], [ 'border-radius', /(^| )(border )?radius( |$)/ ],
			[ 'border-width', /border width/ ], [ 'box-shadow', /box shadow|shadow/ ], [ 'font-family', /font family/ ],
			[ 'font-size', /font size|text size/ ], [ 'font-weight', /font weight/ ], [ 'line-height', /line height/ ], [ 'letter-spacing', /letter spacing/ ],
			[ 'background-color', /background colou?r|(^| )(background|bg)( |$)/ ], [ 'border-color', /border colou?r/ ],
			[ 'color', /(^| )(text|icon|font|fill|stroke)? ?colou?r( |$)/ ], [ 'transition-duration', /transition duration|animation duration|(^| )duration( |$)/ ],
			[ 'transition-timing-function', /easing|timing function/ ], [ 'transition', /(^| )transition( |$)/ ], [ 'opacity', /opacity/ ],
			[ 'top', /(^| )top( |$)/ ], [ 'right', /(^| )right( |$)/ ], [ 'bottom', /(^| )bottom( |$)/ ], [ 'left', /(^| )left( |$)/ ],
		];
		for ( const [ property, regex ] of direct ) {
			if ( regex.test( text ) ) {
				return property;
			}
		}
		return '';
	}

	function canonicalProperty( d ) {
		const explicit = String( d.property || '' ).trim().toLowerCase();
		if ( /^(padding|margin)-(top|right|bottom|left|block|block-start|block-end|inline|inline-start|inline-end)$/.test( explicit ) ) {
			return explicit;
		}
		if ( /^border-(top|right|bottom|left)-(color|width|style)$/.test( explicit ) ) {
			return explicit.replace( /^border-(?:top|right|bottom|left)-/, 'border-' );
		}
		if ( /^(fill|stroke)$/.test( explicit ) ) {
			return 'color';
		}
		if ( explicit === 'background' ) {
			return 'background-color';
		}
		if ( /^(top|right|bottom|left|gap|column-gap|row-gap|width|min-width|max-width|height|min-height|max-height|inline-size|block-size|aspect-ratio|z-index|font-family|font-size|font-weight|line-height|letter-spacing|color|background-color|border-color|border-width|border-radius|box-shadow|text-shadow|transition|transition-duration|animation-duration|transition-timing-function|opacity)$/.test( explicit ) ) {
			return explicit.replace( /^inline-size$/, 'width' ).replace( /^block-size$/, 'height' );
		}
		if ( controlIdMap[ d.controlId ] ) {
			return controlIdMap[ d.controlId ];
		}
		return propertyFromText( `${ d.controlId || '' } ${ d.label || '' } ${ d.property || '' }` );
	}

	function axisFor( property ) {
		if ( /-(top|bottom|block|block-start|block-end)$|row-gap/.test( property ) ) {
			return 'block';
		}
		if ( /-(left|right|inline|inline-start|inline-end)$|column-gap/.test( property ) ) {
			return 'inline';
		}
		return 'any';
	}

	function purposeFor( property ) {
		if ( /^padding/.test( property ) ) {
			return 'padding';
		}
		if ( /^margin/.test( property ) ) {
			return 'margin';
		}
		if ( /^(gap|column-gap|row-gap)$/.test( property ) ) {
			return 'gap';
		}
		if ( /^(top|right|bottom|left)$/.test( property ) ) {
			return 'inset';
		}
		if ( /^(min-|max-)?width$/.test( property ) ) {
			return 'width';
		}
		if ( /^(min-|max-)?height$/.test( property ) ) {
			return 'height';
		}
		if ( /shadow$/.test( property ) ) {
			return 'shadow';
		}
		if ( /radius$/.test( property ) ) {
			return 'radius';
		}
		if ( /^(transition-duration|animation-duration)$/.test( property ) ) {
			return 'duration';
		}
		if ( property === 'transition-timing-function' ) {
			return 'easing';
		}
		if ( /^(font-family|font-size|font-weight|line-height|letter-spacing|color|background-color|border-color|border-width|z-index|opacity|aspect-ratio)$/.test( property ) ) {
			return property;
		}
		return '';
	}

	function layoutFor( d ) {
		if ( d.display === 'grid' ) {
			return 'grid';
		}
		if ( /flex/.test( d.display || '' ) ) {
			return d.flexDirection === 'column' || d.flexDirection === 'column-reverse' ? 'flex-column' : 'flex-row';
		}
		return '';
	}

	function resolveControl( descriptor ) {
		const d = descriptor || {};
		const property = canonicalProperty( d );
		const purpose = purposeFor( property );
		if ( ! purpose ) {
			return null;
		}
		const component = componentHint( `${ d.controlId || '' } ${ d.label || '' }` ) || activeComponent( d );
		const label = String( d.property || d.label || property ).replace( /(^|[-_ ])\w/g, ( v ) => v.toUpperCase() ).replace( /[-_]/g, ' ' ).trim();
		return Object.freeze( { property, purpose, axis: axisFor( property ), component, layout: layoutFor( d ), label: label || 'Compatible', isColour: colourPurposes.has( purpose ) } );
	}

	function purposeMatches( purposes, wanted ) {
		return purposes.some( ( p ) => p === wanted
			|| ( p === 'spacing' && spacingPurposes.has( wanted ) )
			|| ( p === 'colour' && colourPurposes.has( wanted ) ) );
	}

	function componentMatches( components, wanted ) {
		if ( ! components || ! components.length ) {
			return true;
		}
		if ( ! wanted ) {
			return false;
		}
		const available = new Set( componentAliases[ wanted ] || [ wanted ] );
		return components.some( ( c ) => available.has( c ) );
	}

	function assess( variable, context ) {
		if ( ! context ) {
			return null;
		}
		const meta = variableMeta( variable );
		if ( ! meta || ! purposeMatches( meta.purposes, context.purpose ) ) {
			return null;
		}
		const madeFor = meta.components.length > 0;
		if ( meta.tier === 'semantic' ) {
			if ( madeFor && ! componentMatches( meta.components, context.component ) ) {
				return null;
			}
			// A token for one axis (e.g. --gutter, inline) only fits a control for that axis.
			if ( ! meta.axes.includes( 'any' ) && ( context.axis === 'any' || ! meta.axes.includes( context.axis ) ) ) {
				return null;
			}
		}
		const rank = meta.tier === 'primitive' ? 2 : madeFor ? 0 : 1;
		return { rank, section: rank === 0 ? 'Recommended' : rank === 1 ? 'Roles' : 'Scale and palette' };
	}

	return { normalise, dedupeVariables, resolveControl, variableMeta, assess };
} );
