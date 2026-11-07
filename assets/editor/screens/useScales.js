import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Shared state for the Typography and Spacing screens: load, live preview (Bricks' own maths), save.
 */
export default function useScales( restPath, onDirtyChange ) {
	const [ data, setData ] = useState( null );
	const [ typography, setTypographyState ] = useState( null );
	const [ spacing, setSpacingState ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const timer = useRef();

	const apply = ( res ) => {
		setData( res );
		setTypographyState( res.typography );
		setSpacingState( res.spacing );
		setPreview( res.preview );
	};

	useEffect( () => {
		apiFetch( { path: `${ restPath }/scales` } )
			.then( apply )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [ restPath ] );

	useEffect( () => {
		onDirtyChange?.( dirty );
		const warn = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		if ( dirty ) {
			window.addEventListener( 'beforeunload', warn );
		}
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty, onDirtyChange ] );

	// Regenerate the preview shortly after each change.
	useEffect( () => {
		if ( ! dirty || ! typography || ! spacing ) {
			return;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			apiFetch( { path: `${ restPath }/scales/preview`, method: 'POST', data: { typography, spacing } } )
				.then( ( res ) => setPreview( res.preview ) )
				.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		}, 250 );
		return () => clearTimeout( timer.current );
	}, [ typography, spacing, dirty, restPath ] );

	const edit = ( setter ) => ( next ) => {
		setter( next );
		setDirty( true );
		setNotice( null );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: `${ restPath }/scales/save`, method: 'POST', data: { typography, spacing, revision: data.revision } } )
			.then( ( res ) => {
				apply( res );
				setDirty( false );
				const s = res.summary;
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: 1: created, 2: updated, 3: removed, 4: unchanged */
						__( 'Saved to Bricks: %1$d added, %2$d updated, %3$d removed, %4$d unchanged.', 'leapfrog-framework' ),
						s.created,
						s.updated,
						s.deleted,
						s.unchanged
					),
				} );
			} )
			.catch( ( e ) => {
				if ( e.data?.revision ) {
					setData( ( d ) => ( { ...d, revision: e.data.revision } ) );
				}
				setNotice( { status: 'error', text: e.message } );
			} )
			.finally( () => setSaving( false ) );
	};

	return {
		data,
		typography,
		spacing,
		preview,
		setTypography: edit( setTypographyState ),
		setSpacing: edit( setSpacingState ),
		dirty,
		saving,
		notice,
		setNotice,
		save,
	};
}
