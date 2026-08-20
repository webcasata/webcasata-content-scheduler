<?php
/**
 * Action: change a post/page/CPT's status at a scheduled time.
 *
 * Payload shape: array( 'new_status' => 'publish'|'draft'|'private' ).
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Action_Post_Status.
 */
class Webcasata_CS_Action_Post_Status extends Webcasata_CS_Action {

	/**
	 * Unique, stable slug for this action type.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'post_status';
	}

	/**
	 * Human-readable label shown in the admin "What do you want to
	 * change?" dropdown.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Change Post Status', 'webcasata-content-scheduler' );
	}

	/**
	 * Snapshot the target's current post_status before changing it.
	 *
	 * @param array $schedule Decoded schedule row.
	 * @return array
	 */
	public function capture_original( array $schedule ) {
		$post = get_post( $schedule['target_id'] );

		return array(
			'status' => $post ? $post->post_status : 'draft',
		);
	}

	/**
	 * Apply the scheduled status change.
	 *
	 * @param array $schedule Decoded schedule row.
	 * @return true|WP_Error
	 */
	public function apply( array $schedule ) {
		return $this->set_status( $schedule['target_id'], $schedule['payload']['new_status'] ?? 'draft' );
	}

	/**
	 * Restore the status captured by capture_original().
	 *
	 * @param array $schedule Decoded schedule row.
	 * @return true|WP_Error
	 */
	public function rollback( array $schedule ) {
		$restore_to = $schedule['original_value']['status'] ?? 'draft';

		return $this->set_status( $schedule['target_id'], $restore_to );
	}

	/**
	 * Before/after summary for the admin "Schedule Preview" UI.
	 *
	 * @param array $schedule Decoded schedule row.
	 * @return array
	 */
	public function preview( array $schedule ) {
		$post = get_post( $schedule['target_id'] );

		return array(
			/* translators: %s: current post status. */
			'before' => sprintf( __( 'Status: %s', 'webcasata-content-scheduler' ), $post ? $post->post_status : '—' ),
			/* translators: %s: post status this will change to. */
			'after'  => sprintf( __( 'Status: %s', 'webcasata-content-scheduler' ), $schedule['payload']['new_status'] ?? '—' ),
		);
	}

	/**
	 * Shared status-setting helper used by both apply() and rollback().
	 *
	 * @param int    $post_id    Target post ID.
	 * @param string $new_status One of publish|draft|private|pending.
	 * @return true|WP_Error
	 */
	private function set_status( $post_id, $new_status ) {
		$allowed = array( 'publish', 'draft', 'private', 'pending' );

		if ( ! in_array( $new_status, $allowed, true ) ) {
			return new WP_Error(
				'webcasata_cs_invalid_status',
				__( 'Invalid target post status.', 'webcasata-content-scheduler' )
			);
		}

		if ( ! get_post( $post_id ) ) {
			return new WP_Error(
				'webcasata_cs_missing_post',
				__( 'Target post no longer exists.', 'webcasata-content-scheduler' )
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			),
			true
		);

		return is_wp_error( $result ) ? $result : true;
	}
}
