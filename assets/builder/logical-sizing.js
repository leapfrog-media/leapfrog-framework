/**
 * Leapfrog Framework: "Logical sizing only" switch in the Bricks Sizing group.
 * Hides Width/Height and their min/max in the builder; saved per site. Ported from CoreBridge.
 */
( () => {
	'use strict';

	const data = window.leapfrogLogicalSizing || {};
	let enabled = Boolean( data.enabled );
	let saving = false;

	const apply = () => {
		document.documentElement.classList.toggle( 'lf-logical-sizing-only', enabled );
		document.querySelectorAll( '.lf-logical-toggle' ).forEach( ( row ) => {
			const toggle = row.querySelector( '.lf-logical-switch' );
			row.classList.toggle( 'is-saving', saving );
			if ( toggle ) {
				toggle.setAttribute( 'aria-checked', enabled ? 'true' : 'false' );
				toggle.disabled = saving;
			}
		} );
	};

	const save = async () => {
		if ( saving || ! data.canManage ) {
			return;
		}
		const next = ! enabled;
		saving = true;
		apply();
		try {
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: new URLSearchParams( { action: data.action, nonce: data.nonce, enabled: next ? '1' : '0' } ).toString(),
			} );
			const result = await response.json();
			if ( ! response.ok || ! result.success ) {
				throw new Error( result?.data?.message || 'Could not save the sizing preference.' );
			}
			enabled = Boolean( result.data.enabled );
		} catch ( error ) {
			// eslint-disable-next-line no-alert
			window.alert( error instanceof Error ? error.message : 'Could not save the sizing preference.' );
		} finally {
			saving = false;
			apply();
		}
	};

	const toggleRow = ( anchor ) => {
		const row = document.createElement( anchor.tagName === 'LI' ? 'li' : 'div' );
		row.className = 'lf-logical-toggle';
		row.dataset.controlkey = 'lfLogicalSizingOnly';
		row.innerHTML = `
			<div class="control">
				<div class="control-inner control-inline has-label">
					<label><span class="text">Logical sizing only</span></label>
					<div class="lf-logical-choice">
						<button class="lf-logical-switch" type="button" role="switch" aria-label="Show only the logical sizing controls">
							<span class="lf-logical-switch__thumb"></span>
						</button>
					</div>
				</div>
			</div>`;
		row.querySelector( '.lf-logical-switch' )?.addEventListener( 'click', save );
		return row;
	};

	const ensure = () => {
		if ( data.canManage ) {
			document.querySelectorAll( '[data-controlKey="_width"]' ).forEach( ( width ) => {
				const parent = width.parentElement;
				if ( ! width.querySelector( ':scope > .control' ) || ! parent || parent.querySelector( ':scope > .lf-logical-toggle' ) ) {
					return;
				}
				const separator = Array.from( parent.children ).find( ( child ) => child.getAttribute( 'data-controlkey' ) === '_sizingSeparator' );
				const row = toggleRow( width );
				if ( separator ) {
					separator.after( row );
				} else {
					parent.insertBefore( row, width );
				}
			} );
		}
		apply();
	};

	const start = () => {
		ensure();
		new MutationObserver( ensure ).observe( document.body, { childList: true, subtree: true } );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
