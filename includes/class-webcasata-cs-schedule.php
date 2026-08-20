<?php
/**
 * Data model and CRUD for a single schedule row.
 *
 * A "schedule" is the core object of the plugin: WHEN (start/end) an
 * ACTION (identified by a slug registered in the Action Registry)
 * should run against a TARGET (a post/page/CPT ID), and what to do
 * when the schedule ends (rollback behavior).
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Schedule.
 */
class Webcasata_CS_Schedule {

	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_ACTIVE    = 'active';
	const STATUS_PAUSED    = 'paused';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';

	const ROLLBACK_RESTORE = 'restore';
	const ROLLBACK_KEEP    = 'keep';
	const ROLLBACK_NONE    = 'none';

	/**
	 * Get the fully-prefixed custom table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'webcasata_cs_schedules';
	}

	/**
	 * Insert a new schedule row.
	 *
	 * @param array $data {
	 *     Schedule fields.
	 *
	 *     @type string $name              Human-readable schedule name.
	 *     @type string $target_type       Post type slug of the target.
	 *     @type int    $target_id         Post ID being changed.
	 *     @type string $action_type       Slug of a registered action class.
	 *     @type array  $payload           Action-specific settings (will be JSON-encoded).
	 *     @type string $rollback_behavior One of the ROLLBACK_* constants.
	 *     @type string $start_datetime    MySQL datetime (site timezone) or empty.
	 *     @type string $end_datetime      MySQL datetime (site timezone) or empty.
	 *     @type int    $priority          Lower runs first when schedules conflict. Default 10.
	 * }
	 * @return int|WP_Error New schedule ID, or WP_Error on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'name'              => '',
			'status'            => self::STATUS_SCHEDULED,
			'target_type'       => 'post',
			'target_id'         => 0,
			'action_type'       => '',
			'payload'           => array(),
			'rollback_behavior' => self::ROLLBACK_RESTORE,
			'original_value'    => array(),
			'start_datetime'    => null,
			'end_datetime'      => null,
			'priority'          => 10,
			'log'               => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( empty( $data['name'] ) || empty( $data['action_type'] ) || empty( $data['target_id'] ) ) {
			return new WP_Error(
				'webcasata_cs_missing_fields',
				__( 'Name, action type, and target are required to create a schedule.', 'webcasata-content-scheduler' )
			);
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array(
				'name'              => sanitize_text_field( $data['name'] ),
				'status'            => sanitize_key( $data['status'] ),
				'target_type'       => sanitize_key( $data['target_type'] ),
				'target_id'         => absint( $data['target_id'] ),
				'action_type'       => sanitize_key( $data['action_type'] ),
				'payload'           => wp_json_encode( $data['payload'] ),
				'rollback_behavior' => sanitize_key( $data['rollback_behavior'] ),
				'original_value'    => wp_json_encode( $data['original_value'] ),
				'start_datetime'    => $data['start_datetime'] ? self::to_gmt( $data['start_datetime'] ) : null,
				'end_datetime'      => $data['end_datetime'] ? self::to_gmt( $data['end_datetime'] ) : null,
				'priority'          => absint( $data['priority'] ),
				'log'               => wp_json_encode( $data['log'] ),
				'created_by'        => get_current_user_id(),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'webcasata_cs_db_error', __( 'Could not save the schedule to the database.', 'webcasata-content-scheduler' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch a single schedule as an associative array with `payload`,
	 * `original_value`, and `log` already decoded from JSON.
	 *
	 * @param int $id Schedule ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::table_name();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Fetch a page of schedules, optionally filtered by status.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $status   Optional status filter.
	 *     @type int    $per_page Rows per page. Default 20.
	 *     @type int    $paged    1-indexed page number. Default 1.
	 * }
	 * @return array
	 */
	public static function get_list( array $args = array() ) {
		global $wpdb;

		$args  = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'per_page' => 20,
				'paged'    => 1,
			)
		);
		$table = self::table_name();

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		$offset   = max( 0, ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] ) );
		$params[] = absint( $args['per_page'] );
		$params[] = $offset;

		// $table and $where are built entirely from hardcoded strings
		// and sanitize_key() above — never from raw user input — so
		// interpolating them here is safe; %d/%s placeholders still
		// carry every actual value through prepare(). The sniff can't
		// see the extra %s that {$where} conditionally contributes,
		// so it undercounts placeholders against our $params array —
		// a known false positive with dynamically built WHERE clauses.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				"SELECT * FROM {$table} WHERE {$where} ORDER BY start_datetime ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				$params
			),
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode' ), $rows ? $rows : array() );
	}

	/**
	 * Count schedules, optionally filtered by status. Used for
	 * WP_List_Table pagination.
	 *
	 * @param string $status Optional status filter.
	 * @return int
	 */
	public static function count( $status = '' ) {
		global $wpdb;

		$table = self::table_name();

		if ( $status ) {
			return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", sanitize_key( $status ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		// No filter, so no value to place through prepare() — $table
		// is a hardcoded prefix + our own literal table name, never
		// user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Update arbitrary fields on a schedule row. Array/object values
	 * for `payload`, `original_value`, and `log` are JSON-encoded
	 * automatically.
	 *
	 * @param int   $id   Schedule ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		foreach ( array( 'payload', 'original_value', 'log' ) as $json_field ) {
			if ( isset( $data[ $json_field ] ) && ! is_string( $data[ $json_field ] ) ) {
				$data[ $json_field ] = wp_json_encode( $data[ $json_field ] );
			}
		}

		$data['updated_at'] = current_time( 'mysql' );

		// No object-cache layer yet: schedules are only read on the
		// low-traffic admin screens and by Action Scheduler callbacks,
		// not on the front end, so the cost of skipping caching here
		// is low. Worth revisiting once a public-facing read path
		// (e.g. the Scheduled Content Block) is added in a later phase.
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table_name(),
			$data,
			array( 'id' => absint( $id ) )
		);

		return false !== $updated;
	}

	/**
	 * Append a single event to a schedule's log without needing the
	 * caller to fetch, decode, and re-encode the whole log first.
	 *
	 * @param int    $id      Schedule ID.
	 * @param string $message Human-readable log line.
	 * @return void
	 */
	public static function log( $id, $message ) {
		$schedule = self::get( $id );

		if ( ! $schedule ) {
			return;
		}

		$log   = is_array( $schedule['log'] ) ? $schedule['log'] : array();
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => sanitize_text_field( $message ),
		);

		self::update( $id, array( 'log' => $log ) );
	}

	/**
	 * Delete a schedule row outright.
	 *
	 * @param int $id Schedule ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( self::table_name(), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Convert an admin-submitted datetime string — entered in the
	 * site's configured timezone (Settings > General) — into UTC for
	 * storage. All datetime columns in this table are UTC; every
	 * consumer (the engine, the admin display) must go through this
	 * same conversion so schedules fire at the time the admin actually
	 * meant, regardless of server timezone.
	 *
	 * @param string $local_datetime Datetime string in site-local time, e.g. '2026-08-20 00:00:00'.
	 * @return string MySQL datetime string in UTC.
	 */
	private static function to_gmt( $local_datetime ) {
		return get_gmt_from_date( $local_datetime, 'Y-m-d H:i:s' );
	}

	/**
	 * Decode the JSON columns of a raw database row.
	 *
	 * @param array $row Raw row from $wpdb.
	 * @return array
	 */
	private static function decode( array $row ) {
		foreach ( array( 'payload', 'original_value', 'log' ) as $json_field ) {
			$row[ $json_field ] = ! empty( $row[ $json_field ] ) ? json_decode( $row[ $json_field ], true ) : array();
		}

		return $row;
	}
}
