<?php
/**
 * Base class every scheduleable Action must extend.
 *
 * This is the piece of the architecture that lets us add new
 * capabilities (taxonomies, featured image, ACF, WooCommerce price...)
 * in later phases without touching the scheduling engine at all: the
 * engine only ever talks to this interface, never to a concrete
 * action's implementation details.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Action.
 */
abstract class Webcasata_CS_Action {

	/**
	 * Unique, stable slug for this action type. Stored in the
	 * `action_type` column, so once a slug ships it must never change.
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * Human-readable label shown in the "What do you want to change?"
	 * dropdown in the admin UI.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Read the CURRENT value of whatever this action is about to
	 * change, before touching anything. The engine stores whatever
	 * this returns as `original_value` so rollback can restore it
	 * later — this is what makes schedules reversible.
	 *
	 * @param array $schedule Decoded schedule row (see Webcasata_CS_Schedule::get()).
	 * @return array Snapshot of the current state.
	 */
	abstract public function capture_original( array $schedule );

	/**
	 * Apply the scheduled change. Called when a schedule's start time
	 * is reached.
	 *
	 * @param array $schedule Decoded schedule row, including `payload`
	 *                        (the new values chosen in the admin UI).
	 * @return true|WP_Error
	 */
	abstract public function apply( array $schedule );

	/**
	 * Undo the scheduled change, restoring whatever `capture_original()`
	 * recorded. Called when a schedule's end time is reached and its
	 * rollback_behavior is `restore`.
	 *
	 * @param array $schedule Decoded schedule row, including `original_value`.
	 * @return true|WP_Error
	 */
	abstract public function rollback( array $schedule );

	/**
	 * Optional human-readable before/after summary for the "Schedule
	 * Preview" UI. Concrete actions are encouraged to override this;
	 * the default is a generic fallback so it's never required.
	 *
	 * @param array $schedule Decoded schedule row.
	 * @return array {
	 *     @type string $before Description of the current state.
	 *     @type string $after  Description of the state once applied.
	 * }
	 */
	public function preview( array $schedule ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $schedule is required by the interface; this generic fallback intentionally ignores it, concrete actions override with real logic.
		return array(
			'before' => __( 'Current value', 'webcasata-content-scheduler' ),
			'after'  => __( 'Scheduled value', 'webcasata-content-scheduler' ),
		);
	}
}
