<?php
/**
 * The scheduling engine.
 *
 * Talks to Action Scheduler (a real queue with retries and logging,
 * far more reliable than raw WP-Cron for time-sensitive triggers) and
 * dispatches to whichever Webcasata_CS_Action is registered for a
 * given schedule's `action_type`.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Engine.
 */
class Webcasata_CS_Engine {

	/**
	 * Action Scheduler group name, used so we can cancel every action
	 * belonging to this plugin at once (see the deactivator).
	 *
	 * @var string
	 */
	const GROUP = 'webcasata-content-scheduler';

	/**
	 * Register our Action Scheduler hook callbacks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'webcasata_cs_run_start', array( $this, 'handle_start' ) );
		add_action( 'webcasata_cs_run_end', array( $this, 'handle_end' ) );
	}

	/**
	 * Queue the start and (optional) end triggers for a schedule with
	 * Action Scheduler, and store the resulting action IDs so they can
	 * be cancelled later if the schedule is edited, paused, or deleted.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function schedule( $schedule_id ) {
		$schedule = Webcasata_CS_Schedule::get( $schedule_id );

		if ( ! $schedule ) {
			return;
		}

		$this->unschedule( $schedule_id );

		$updates = array();

		if ( ! empty( $schedule['start_datetime'] ) ) {
			$timestamp = $this->to_timestamp( $schedule['start_datetime'] );

			$updates['as_start_action_id'] = as_schedule_single_action(
				$timestamp,
				'webcasata_cs_run_start',
				array( 'schedule_id' => (int) $schedule_id ),
				self::GROUP
			);
		}

		if ( ! empty( $schedule['end_datetime'] ) ) {
			$timestamp = $this->to_timestamp( $schedule['end_datetime'] );

			$updates['as_end_action_id'] = as_schedule_single_action(
				$timestamp,
				'webcasata_cs_run_end',
				array( 'schedule_id' => (int) $schedule_id ),
				self::GROUP
			);
		}

		if ( $updates ) {
			Webcasata_CS_Schedule::update( $schedule_id, $updates );
		}
	}

	/**
	 * Cancel any pending Action Scheduler actions for a schedule
	 * without deleting the schedule row itself.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function unschedule( $schedule_id ) {
		as_unschedule_action( 'webcasata_cs_run_start', array( 'schedule_id' => (int) $schedule_id ), self::GROUP );
		as_unschedule_action( 'webcasata_cs_run_end', array( 'schedule_id' => (int) $schedule_id ), self::GROUP );
	}

	/**
	 * Action Scheduler callback: a schedule's start time has arrived.
	 *
	 * Captures the pre-change state (for rollback), applies the
	 * change, and logs the result.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function handle_start( $schedule_id ) {
		$schedule = Webcasata_CS_Schedule::get( $schedule_id );

		if ( ! $schedule || Webcasata_CS_Schedule::STATUS_PAUSED === $schedule['status'] ) {
			return;
		}

		$action = Webcasata_CS_Action_Registry::get( $schedule['action_type'] );

		if ( ! $action ) {
			Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_FAILED ) );
			Webcasata_CS_Schedule::log( $schedule_id, __( 'Failed: unknown action type.', 'webcasata-content-scheduler' ) );
			return;
		}

		// Snapshot the current state BEFORE changing anything, so
		// rollback has something accurate to restore.
		$original = $action->capture_original( $schedule );
		Webcasata_CS_Schedule::update( $schedule_id, array( 'original_value' => $original ) );

		// Re-fetch with the fresh original_value for apply().
		$schedule = Webcasata_CS_Schedule::get( $schedule_id );
		$result   = $action->apply( $schedule );

		if ( is_wp_error( $result ) ) {
			Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_FAILED ) );
			Webcasata_CS_Schedule::log( $schedule_id, $result->get_error_message() );
			return;
		}

		$new_status = empty( $schedule['end_datetime'] ) ? Webcasata_CS_Schedule::STATUS_COMPLETED : Webcasata_CS_Schedule::STATUS_ACTIVE;
		Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => $new_status ) );
		Webcasata_CS_Schedule::log( $schedule_id, __( 'Schedule started and change applied.', 'webcasata-content-scheduler' ) );
	}

	/**
	 * Action Scheduler callback: a schedule's end time has arrived.
	 *
	 * Applies the configured rollback_behavior.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function handle_end( $schedule_id ) {
		$schedule = Webcasata_CS_Schedule::get( $schedule_id );

		if ( ! $schedule || Webcasata_CS_Schedule::STATUS_PAUSED === $schedule['status'] ) {
			return;
		}

		if ( Webcasata_CS_Schedule::ROLLBACK_RESTORE !== $schedule['rollback_behavior'] ) {
			Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_COMPLETED ) );
			Webcasata_CS_Schedule::log( $schedule_id, __( 'Schedule ended. Value kept (no rollback configured).', 'webcasata-content-scheduler' ) );
			return;
		}

		$action = Webcasata_CS_Action_Registry::get( $schedule['action_type'] );

		if ( ! $action ) {
			return;
		}

		$result = $action->rollback( $schedule );

		if ( is_wp_error( $result ) ) {
			Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_FAILED ) );
			Webcasata_CS_Schedule::log( $schedule_id, $result->get_error_message() );
			return;
		}

		Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_COMPLETED ) );
		Webcasata_CS_Schedule::log( $schedule_id, __( 'Schedule ended. Original value restored automatically.', 'webcasata-content-scheduler' ) );
	}

	/**
	 * Convert a MySQL datetime that is ALREADY stored in UTC
	 * (Webcasata_CS_Schedule always stores UTC — see ::to_gmt()) into
	 * a Unix timestamp for Action Scheduler, which itself always
	 * schedules in UTC. Appending "GMT" tells strtotime() not to
	 * reinterpret the string using the server's default timezone.
	 *
	 * @param string $utc_mysql_datetime MySQL datetime string, UTC.
	 * @return int
	 */
	private function to_timestamp( $utc_mysql_datetime ) {
		return (int) strtotime( $utc_mysql_datetime . ' GMT' );
	}
}
