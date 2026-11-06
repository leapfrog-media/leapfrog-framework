<?php

declare(strict_types=1);

namespace Leapfrog\Framework;

use Leapfrog\Framework\Admin\FrameworkPage;
use Leapfrog\Framework\Rest\RestController;

/**
 * Wires the plugin's parts into WordPress.
 */
final class Plugin {
	public function register(): void {
		( new FrameworkPage() )->register();
		( new RestController() )->register();

		add_action( 'admin_notices', array( $this, 'requirements_notice' ) );
	}

	/**
	 * Shows what is missing (for example Bricks not active) to administrators.
	 */
	public function requirements_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$problems = Requirements::problems();
		if ( ! $problems ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Leapfrog Framework:', 'leapfrog-framework' ) . '</strong> ' . esc_html( implode( ' ', $problems ) ) . '</p></div>';
	}
}
