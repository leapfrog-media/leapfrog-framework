import { __, sprintf } from '@wordpress/i18n';

/**
 * Stand-in for screens that arrive in later milestones.
 */
export default function Placeholder( { screen } ) {
	return (
		<section className="lf-screen">
			<h1 className="lf-screen__title">{ screen.label }</h1>
			<p className="lf-muted">
				{ sprintf(
					/* translators: %d: milestone number */
					__( 'This screen is built in milestone %d.', 'leapfrog-framework' ),
					screen.milestone
				) }
			</p>
		</section>
	);
}
