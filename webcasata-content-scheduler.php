<?php
/**
 * Plugin Name:       Webcasata Content Scheduler
 * Plugin URI:        https://webcasata.com/plugins/content-scheduler
 * Description:       Schedule changes to your WordPress content — status, content, images, and more — with automatic rollback when the schedule ends.
 * Version:           0.2.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            Webcasata
 * Author URI:        https://webcasata.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webcasata-content-scheduler
 * Domain Path:       /languages
 *
 * @package Webcasata_Content_Scheduler
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin constants. Prefixed to avoid collisions with any other
 * plugin on the site — required practice for WordPress.org submissions.
 */
define( 'WEBCASATA_CS_VERSION', '0.2.0' );
define( 'WEBCASATA_CS_DB_VERSION', '1.0.0' );
define( 'WEBCASATA_CS_FILE', __FILE__ );
define( 'WEBCASATA_CS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEBCASATA_CS_URL', plugin_dir_url( __FILE__ ) );
define( 'WEBCASATA_CS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Bundle Action Scheduler.
 *
 * Action Scheduler self-registers on the `plugins_loaded` hook and is
 * version-safe to bundle: if another active plugin (e.g. WooCommerce)
 * also bundles it, only the highest version actually initializes. It
 * must be required unconditionally, at the top level of the main file,
 * per the library's own documented integration pattern — not inside a
 * hook callback.
 */
require_once WEBCASATA_CS_PATH . 'lib/action-scheduler/action-scheduler.php';

/**
 * Class autoloading for our own classes.
 *
 * We use a simple explicit loader rather than a Composer autoloader so
 * the plugin has zero build-step dependency for a reviewer checking it
 * out of the WordPress.org SVN repository.
 */
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-loader.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-i18n.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-activator.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-deactivator.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-schedule.php';
require_once WEBCASATA_CS_PATH . 'includes/actions/class-webcasata-cs-action.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-action-registry.php';
require_once WEBCASATA_CS_PATH . 'includes/actions/class-webcasata-cs-action-post-status.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-engine.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-admin.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-list-table.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-blocks.php';
require_once WEBCASATA_CS_PATH . 'includes/class-webcasata-cs-core.php';

/**
 * Activation and deactivation hooks must be registered from the main
 * plugin file, not from an included class file.
 */
register_activation_hook( __FILE__, array( 'Webcasata_CS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Webcasata_CS_Deactivator', 'deactivate' ) );

/**
 * Boots the plugin.
 *
 * Runs on `plugins_loaded` (after Action Scheduler has registered
 * itself) so our engine can safely call as_schedule_single_action()
 * and friends as soon as it wires up its own hooks.
 *
 * @return void
 */
function webcasata_cs_run() {
	$plugin = new Webcasata_CS_Core();
	$plugin->run();
}
add_action( 'plugins_loaded', 'webcasata_cs_run', 20 );
