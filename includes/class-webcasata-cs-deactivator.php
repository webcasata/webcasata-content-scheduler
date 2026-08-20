<?php
/**
 * Fired during plugin deactivation.
 *
 * Deactivation is not uninstallation: per WordPress.org guidelines we
 * must NOT delete user data here. We only cancel pending scheduled
 * actions so they don't fire while the plugin is inactive; the table
 * and its rows are left intact so re-activating the plugin resumes
 * cleanly. Data removal only happens in uninstall.php, and only if
 * the site owner has opted in via the plugin's settings.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Deactivator.
 */
class Webcasata_CS_Deactivator {

	/**
	 * Cancel any pending Action Scheduler actions belonging to us.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), 'webcasata-content-scheduler' );
		}

		flush_rewrite_rules();
	}
}
