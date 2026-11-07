import { __ } from '@wordpress/i18n';

import Shape from './Shape';

/**
 * Component tokens: buttons, form fields, cards, header.
 */
export default function Components( props ) {
	return (
		<Shape
			{ ...props }
			endpoint="components"
			title={ __( 'Components', 'leapfrog-framework' ) }
			intro={ __( 'Tokens for buttons, form fields, cards and the header, saved as Bricks variables in the category "Components". Bricks buttons use the button tokens straight away. The defaults point at your scales and roles, so change them only where a component should differ.', 'leapfrog-framework' ) }
		/>
	);
}
