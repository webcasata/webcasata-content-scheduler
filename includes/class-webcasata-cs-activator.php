<?php
/**
 * Fired during plugin activation.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Activator.
 */
class Webcasata_CS_Activator {

	/**
	 * Create/upgrade the custom table and set default options.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		update_option( 'webcasata_cs_db_version', WEBCASATA_CS_DB_VERSION );

		// Flush rewrite rules pre-emptively in case a future phase adds
		// a public post type or REST endpoint that needs them.
		flush_rewrite_rules();
	}

	/**
	 * Create the schedules table.
	 *
	 * Uses dbDelta(), which requires very specific formatting: each
	 * field and key on its own line, two spaces between PRIMARY KEY
	 * and the definition, KEY instead of INDEX, etc. Deviating from
	 * this formatting silently breaks upgrades for existing installs.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Webcasata_CS_Schedule::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			target_type varchar(50) NOT NULL DEFAULT 'post',
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action_type varchar(50) NOT NULL DEFAULT '',
			payload longtext,
			rollback_behavior varchar(20) NOT NULL DEFAULT 'restore',
			original_value longtext,
			start_datetime datetime DEFAULT NULL,
			end_datetime datetime DEFAULT NULL,
			as_start_action_id bigint(20) unsigned DEFAULT NULL,
			as_end_action_id bigint(20) unsigned DEFAULT NULL,
			priority smallint(5) unsigned NOT NULL DEFAULT 10,
			log longtext,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY target (target_type,target_id),
			KEY start_datetime (start_datetime),
			KEY end_datetime (end_datetime)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
