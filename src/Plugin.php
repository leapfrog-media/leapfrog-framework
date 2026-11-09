<?php

declare(strict_types=1);

namespace Leapfrog\Framework;

use Leapfrog\Framework\Admin\FrameworkPage;
use Leapfrog\Framework\Admin\GitHubUpdater;
use Leapfrog\Framework\Admin\NavigationPage;
use Leapfrog\Framework\Bricks\BuilderHelpers;
use Leapfrog\Framework\Bricks\Buttons;
use Leapfrog\Framework\Bricks\ElementSettings;
use Leapfrog\Framework\Bricks\GridExtras;
use Leapfrog\Framework\Bricks\LogicalSizing;
use Leapfrog\Framework\Bricks\ThemeControl;
use Leapfrog\Framework\Bricks\VariablePicker;
use Leapfrog\Framework\Color\ThemeCss;
use Leapfrog\Framework\Rest\RestController;
use Leapfrog\Framework\Rest\SiteController;

/**
 * Wires the plugin's parts into WordPress.
 */
final class Plugin {
	public function register(): void {
		( new FrameworkPage() )->register();
		// Updates from GitHub; the token and status live on Leapfrog Admin's (or Performance's) Updates tab.
		( new GitHubUpdater( FILE, 'leapfrog-framework' ) )->register();
		( new RestController() )->register();
		( new SiteController() )->register();
		( new ThemeControl() )->register();
		ThemeCss::register();
		( new LogicalSizing() )->register();
		( new Buttons() )->register();
		( new VariablePicker() )->register();
		( new GridExtras() )->register();
		( new BuilderHelpers() )->register();
		( new ElementSettings() )->register();
		( new NavigationPage() )->register();
		( new Navigation\Controller() )->register();
		( new Navigation\Frontend() )->register();

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
