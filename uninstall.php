<?php
/**
 * Fires when the plugin is deleted from the Plugins screen.
 *
 * WordPress only loads this file directly (never includes it as part
 * of a normal request), and only when the site owner clicks "Delete"
 * — never on simple deactivation. WP.org requires this exact guard so
 * the file can't be executed by requesting it directly over HTTP.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'webcasata_cs_schedules';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

delete_option( 'webcasata_cs_db_version' );

// Action Scheduler's own tables/data are left untouched deliberately:
// if another active plugin (e.g. WooCommerce) also bundles it, those
// tables are shared infrastructure, not ours to delete.
