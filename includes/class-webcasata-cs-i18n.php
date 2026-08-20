<?php
/**
 * Handles loading the plugin's translated strings.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_I18n.
 */
class Webcasata_CS_I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * As of WordPress 4.6, plugins hosted on WordPress.org no longer
	 * need to call load_plugin_textdomain() for translations to load —
	 * they're served automatically from translate.wordpress.org. We
	 * call it anyway for correctness on sites installing the plugin
	 * outside of WordPress.org (e.g. from a zip during development).
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'webcasata-content-scheduler',
			false,
			dirname( WEBCASATA_CS_BASENAME ) . '/languages/'
		);
	}
}
