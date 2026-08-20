<?php
/**
 * Admin view: All Schedules.
 *
 * Expects $list_table to already be prepared by Webcasata_CS_Admin::render_list_page().
 *
 * @package Webcasata_Content_Scheduler
 * @var Webcasata_CS_List_Table $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$webcasata_cs_errors = get_transient( 'webcasata_cs_admin_errors_' . get_current_user_id() );
if ( $webcasata_cs_errors ) {
	delete_transient( 'webcasata_cs_admin_errors_' . get_current_user_id() );
	foreach ( $webcasata_cs_errors as $webcasata_cs_error ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $webcasata_cs_error['message'] ) );
	}
}

if ( isset( $_GET['webcasata_cs_created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule created.', 'webcasata-content-scheduler' ) . '</p></div>';
}

$current_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$status_tabs = array(
	''                                      => __( 'All', 'webcasata-content-scheduler' ),
	Webcasata_CS_Schedule::STATUS_SCHEDULED => __( 'Scheduled', 'webcasata-content-scheduler' ),
	Webcasata_CS_Schedule::STATUS_ACTIVE    => __( 'Active', 'webcasata-content-scheduler' ),
	Webcasata_CS_Schedule::STATUS_PAUSED    => __( 'Paused', 'webcasata-content-scheduler' ),
	Webcasata_CS_Schedule::STATUS_COMPLETED => __( 'Completed', 'webcasata-content-scheduler' ),
	Webcasata_CS_Schedule::STATUS_FAILED    => __( 'Failed', 'webcasata-content-scheduler' ),
);
?>
<div class="wrap webcasata-cs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Webcasata Content Scheduler', 'webcasata-content-scheduler' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=webcasata-content-scheduler-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'webcasata-content-scheduler' ); ?>
	</a>

	<ul class="subsubsub">
		<?php
		$tab_links = array();
		foreach ( $status_tabs as $status_key => $label ) {
			$url         = add_query_arg(
				array_filter(
					array(
						'page'   => 'webcasata-content-scheduler',
						'status' => $status_key,
					)
				),
				admin_url( 'admin.php' )
			);
			$class       = ( $current_status === $status_key ) ? ' class="current"' : '';
			$tab_links[] = sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( $url ), $class, esc_html( $label ) );
		}
		echo wp_kses_post( implode( ' | ', $tab_links ) );
		?>
	</ul>

	<form method="get">
		<input type="hidden" name="page" value="webcasata-content-scheduler" />
		<?php $list_table->display(); ?>
	</form>
</div>
