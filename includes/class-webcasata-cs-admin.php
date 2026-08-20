<?php
/**
 * Admin-side functionality: menu, assets, and form handling.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Admin.
 */
class Webcasata_CS_Admin {

	/**
	 * Capability required to manage schedules. A constant (rather than
	 * hardcoding 'manage_options' everywhere) so a future version can
	 * introduce a dedicated capability without a find-and-replace.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The engine instance, used to (un)schedule Action Scheduler
	 * actions when schedules are created, paused, or deleted.
	 *
	 * @var Webcasata_CS_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 *
	 * @param Webcasata_CS_Engine $engine Engine instance.
	 */
	public function __construct( Webcasata_CS_Engine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Register the top-level admin menu and its submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		$hook = add_menu_page(
			__( 'Webcasata Scheduler', 'webcasata-content-scheduler' ),
			__( 'Webcasata Scheduler', 'webcasata-content-scheduler' ),
			self::CAPABILITY,
			'webcasata-content-scheduler',
			array( $this, 'render_list_page' ),
			'dashicons-clock',
			26
		);

		add_submenu_page(
			'webcasata-content-scheduler',
			__( 'All Schedules', 'webcasata-content-scheduler' ),
			__( 'All Schedules', 'webcasata-content-scheduler' ),
			self::CAPABILITY,
			'webcasata-content-scheduler',
			array( $this, 'render_list_page' )
		);

		$new_hook = add_submenu_page(
			'webcasata-content-scheduler',
			__( 'Add New Schedule', 'webcasata-content-scheduler' ),
			__( 'Add New', 'webcasata-content-scheduler' ),
			self::CAPABILITY,
			'webcasata-content-scheduler-new',
			array( $this, 'render_new_page' )
		);

		add_action( "load-{$hook}", array( $this, 'handle_list_actions' ) );
		add_action( "load-{$new_hook}", array( $this, 'handle_new_schedule_submission' ) );
	}

	/**
	 * Enqueue admin CSS/JS, scoped to our own screens only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'webcasata-content-scheduler' ) ) {
			return;
		}

		wp_enqueue_style(
			'webcasata-cs-admin',
			WEBCASATA_CS_URL . 'admin/css/admin.css',
			array(),
			WEBCASATA_CS_VERSION
		);

		wp_enqueue_script(
			'webcasata-cs-admin',
			WEBCASATA_CS_URL . 'admin/js/admin.js',
			array(),
			WEBCASATA_CS_VERSION,
			true
		);
	}

	/**
	 * Render the "All Schedules" list screen.
	 *
	 * @return void
	 */
	public function render_list_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webcasata-content-scheduler' ) );
		}

		$list_table = new Webcasata_CS_List_Table();
		$list_table->prepare_items();

		require WEBCASATA_CS_PATH . 'admin/partials/list-schedules.php';
	}

	/**
	 * Render the "Add New Schedule" form screen.
	 *
	 * @return void
	 */
	public function render_new_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webcasata-content-scheduler' ) );
		}

		$action_choices = Webcasata_CS_Action_Registry::get_choices();

		require WEBCASATA_CS_PATH . 'admin/partials/new-schedule.php';
	}

	/**
	 * Handle row actions (pause / resume / delete) from the list screen.
	 * Runs on `load-{hook}`, i.e. before any HTML has been sent, so
	 * wp_safe_redirect() is always safe to call here.
	 *
	 * @return void
	 */
	public function handle_list_actions() {
		if ( empty( $_GET['webcasata_cs_action'] ) || empty( $_GET['schedule_id'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'webcasata-content-scheduler' ) );
		}

		$schedule_id = absint( $_GET['schedule_id'] );
		$row_action  = sanitize_key( wp_unslash( $_GET['webcasata_cs_action'] ) );

		check_admin_referer( 'webcasata_cs_row_action_' . $schedule_id );

		switch ( $row_action ) {
			case 'pause':
				Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_PAUSED ) );
				Webcasata_CS_Schedule::log( $schedule_id, __( 'Paused by admin.', 'webcasata-content-scheduler' ) );
				break;

			case 'resume':
				$schedule = Webcasata_CS_Schedule::get( $schedule_id );
				if ( $schedule ) {
					Webcasata_CS_Schedule::update( $schedule_id, array( 'status' => Webcasata_CS_Schedule::STATUS_SCHEDULED ) );
					$this->engine->schedule( $schedule_id );
					Webcasata_CS_Schedule::log( $schedule_id, __( 'Resumed by admin.', 'webcasata-content-scheduler' ) );
				}
				break;

			case 'delete':
				$this->engine->unschedule( $schedule_id );
				Webcasata_CS_Schedule::delete( $schedule_id );
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=webcasata-content-scheduler' ) );
		exit;
	}

	/**
	 * Handle submission of the "Add New Schedule" form.
	 *
	 * Runs on `load-{hook}` so a redirect can happen before any output
	 * — required, since we redirect back to the list screen on success.
	 *
	 * @return void
	 */
	public function handle_new_schedule_submission() {
		if ( empty( $_POST['webcasata_cs_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'webcasata-content-scheduler' ) );
		}

		check_admin_referer( 'webcasata_cs_new_schedule' );

		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$target    = $target_id ? get_post( $target_id ) : null;

		if ( ! $target ) {
			add_settings_error( 'webcasata_cs', 'invalid_target', __( 'Please choose a valid post/page to target.', 'webcasata-content-scheduler' ) );
			set_transient( 'webcasata_cs_admin_errors_' . get_current_user_id(), get_settings_errors(), 30 );
			return;
		}

		$schedule_id = Webcasata_CS_Schedule::create(
			array(
				'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'target_type'       => $target->post_type,
				'target_id'         => $target_id,
				'action_type'       => isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '',
				'payload'           => array(
					'new_status' => isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : 'draft',
				),
				'rollback_behavior' => isset( $_POST['rollback_behavior'] ) ? sanitize_key( wp_unslash( $_POST['rollback_behavior'] ) ) : Webcasata_CS_Schedule::ROLLBACK_RESTORE,
				'start_datetime'    => isset( $_POST['start_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['start_datetime'] ) ) : '',
				'end_datetime'      => isset( $_POST['end_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['end_datetime'] ) ) : '',
			)
		);

		if ( is_wp_error( $schedule_id ) ) {
			add_settings_error( 'webcasata_cs', 'create_failed', $schedule_id->get_error_message() );
			set_transient( 'webcasata_cs_admin_errors_' . get_current_user_id(), get_settings_errors(), 30 );
			return;
		}

		$this->engine->schedule( $schedule_id );

		wp_safe_redirect( admin_url( 'admin.php?page=webcasata-content-scheduler&webcasata_cs_created=1' ) );
		exit;
	}
}
