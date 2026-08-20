<?php
/**
 * "All Schedules" list table, built on WordPress's own WP_List_Table
 * so it gets standard admin styling, sorting chrome, and pagination
 * for free and looks native next to Posts/Pages/etc.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Webcasata_CS_List_Table.
 */
class Webcasata_CS_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'schedule',
				'plural'   => 'schedules',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'name'   => __( 'Schedule', 'webcasata-content-scheduler' ),
			'target' => __( 'Target', 'webcasata-content-scheduler' ),
			'action' => __( 'Action', 'webcasata-content-scheduler' ),
			'when'   => __( 'When', 'webcasata-content-scheduler' ),
			'status' => __( 'Status', 'webcasata-content-scheduler' ),
		);
	}

	/**
	 * Fetch, sort, and paginate rows from the database.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$status       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->items = Webcasata_CS_Schedule::get_list(
			array(
				'status'   => $status,
				'per_page' => $per_page,
				'paged'    => $current_page,
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => Webcasata_CS_Schedule::count( $status ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Message shown when there are no schedules yet.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No schedules yet. Click "Add New" to create your first one.', 'webcasata-content-scheduler' );
	}

	/**
	 * Render the "Schedule" column with row actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_name( $item ) {
		$id = (int) $item['id'];

		$actions = array();

		if ( Webcasata_CS_Schedule::STATUS_PAUSED === $item['status'] ) {
			$actions['resume'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->row_action_url( 'resume', $id ) ),
				esc_html__( 'Resume', 'webcasata-content-scheduler' )
			);
		} elseif ( in_array( $item['status'], array( Webcasata_CS_Schedule::STATUS_SCHEDULED, Webcasata_CS_Schedule::STATUS_ACTIVE ), true ) ) {
			$actions['pause'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->row_action_url( 'pause', $id ) ),
				esc_html__( 'Pause', 'webcasata-content-scheduler' )
			);
		}

		$actions['delete'] = sprintf(
			'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $this->row_action_url( 'delete', $id ) ),
			esc_js( __( 'Delete this schedule? This cannot be undone.', 'webcasata-content-scheduler' ) ),
			esc_html__( 'Delete', 'webcasata-content-scheduler' )
		);

		return sprintf( '<strong>%1$s</strong>%2$s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Render the "Target" column as a link to edit the target post.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_target( $item ) {
		$post = get_post( $item['target_id'] );

		if ( ! $post ) {
			return esc_html__( '(deleted)', 'webcasata-content-scheduler' );
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $post->ID ) ),
			esc_html( get_the_title( $post ) )
		);
	}

	/**
	 * Render the "Action" column using the registered action's label.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_action( $item ) {
		$action = Webcasata_CS_Action_Registry::get( $item['action_type'] );

		return $action ? esc_html( $action->get_label() ) : esc_html( $item['action_type'] );
	}

	/**
	 * Render the "When" column, converting stored UTC times back to
	 * the site's configured timezone for display.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_when( $item ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$start = $item['start_datetime'] ? get_date_from_gmt( $item['start_datetime'], $format ) : '—';
		$end   = $item['end_datetime'] ? get_date_from_gmt( $item['end_datetime'], $format ) : '—';

		return sprintf(
			/* translators: 1: start date/time, 2: end date/time */
			esc_html__( 'Start: %1$s%3$sEnd: %2$s', 'webcasata-content-scheduler' ),
			esc_html( $start ),
			esc_html( $end ),
			'<br />'
		);
	}

	/**
	 * Render the "Status" column as a small colored badge.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_status( $item ) {
		return sprintf(
			'<span class="webcasata-cs-badge webcasata-cs-badge--%1$s">%2$s</span>',
			esc_attr( $item['status'] ),
			esc_html( ucfirst( $item['status'] ) )
		);
	}

	/**
	 * Default column renderer fallback.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Build a nonce-protected URL for a row action link.
	 *
	 * @param string $row_action  'pause' | 'resume' | 'delete'.
	 * @param int    $schedule_id Schedule ID.
	 * @return string
	 */
	private function row_action_url( $row_action, $schedule_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                => 'webcasata-content-scheduler',
					'webcasata_cs_action' => $row_action,
					'schedule_id'         => $schedule_id,
				),
				admin_url( 'admin.php' )
			),
			'webcasata_cs_row_action_' . $schedule_id
		);
	}
}
