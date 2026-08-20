<?php
/**
 * Admin view: Add New Schedule.
 *
 * @package Webcasata_Content_Scheduler
 * @var array $action_choices Slug => label pairs from Webcasata_CS_Action_Registry::get_choices().
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

// Phase 1 ships one action type (Post Status), so this dropdown has
// one option today. Every future phase adds an option here for free
// by registering with the webcasata_cs_register_actions filter — this
// view never needs to change.
$recent_posts = get_posts(
	array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
		'posts_per_page' => 50,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	)
);
?>
<div class="wrap webcasata-cs-wrap">
	<h1><?php esc_html_e( 'Add New Schedule', 'webcasata-content-scheduler' ); ?></h1>

	<form method="post" class="webcasata-cs-form">
		<?php wp_nonce_field( 'webcasata_cs_new_schedule' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="webcasata-name"><?php esc_html_e( 'Schedule Name', 'webcasata-content-scheduler' ); ?></label></th>
				<td>
					<input type="text" id="webcasata-name" name="name" class="regular-text" required
						placeholder="<?php esc_attr_e( 'e.g. Diwali Homepage Banner', 'webcasata-content-scheduler' ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="webcasata-target"><?php esc_html_e( 'Target', 'webcasata-content-scheduler' ); ?></label></th>
				<td>
					<select id="webcasata-target" name="target_id" required>
						<option value=""><?php esc_html_e( '— Select a post or page —', 'webcasata-content-scheduler' ); ?></option>
						<?php foreach ( $recent_posts as $recent_post ) : ?>
							<option value="<?php echo esc_attr( $recent_post->ID ); ?>">
								<?php echo esc_html( sprintf( '%s (%s)', get_the_title( $recent_post ), get_post_type_object( $recent_post->post_type )->labels->singular_name ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Showing your 50 most recently modified posts/pages.', 'webcasata-content-scheduler' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="webcasata-action-type"><?php esc_html_e( 'What do you want to change?', 'webcasata-content-scheduler' ); ?></label></th>
				<td>
					<select id="webcasata-action-type" name="action_type" required>
						<?php foreach ( $action_choices as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="webcasata-new-status"><?php esc_html_e( 'New Status', 'webcasata-content-scheduler' ); ?></label></th>
				<td>
					<select id="webcasata-new-status" name="new_status">
						<option value="publish"><?php esc_html_e( 'Published', 'webcasata-content-scheduler' ); ?></option>
						<option value="draft"><?php esc_html_e( 'Draft', 'webcasata-content-scheduler' ); ?></option>
						<option value="private"><?php esc_html_e( 'Private', 'webcasata-content-scheduler' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending Review', 'webcasata-content-scheduler' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="webcasata-start"><?php esc_html_e( 'Start Date/Time', 'webcasata-content-scheduler' ); ?></label></th>
				<td>
					<input type="datetime-local" id="webcasata-start" name="start_datetime" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: site timezone string */
							esc_html__( 'Uses your site timezone: %s', 'webcasata-content-scheduler' ),
							esc_html( wp_timezone_string() )
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="webcasata-end"><?php esc_html_e( 'End Date/Time (optional)', 'webcasata-content-scheduler' ); ?></label></th>
				<td>
					<input type="datetime-local" id="webcasata-end" name="end_datetime" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'After Schedule Ends', 'webcasata-content-scheduler' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="rollback_behavior" value="restore" checked="checked" />
							<?php esc_html_e( 'Restore the original value automatically', 'webcasata-content-scheduler' ); ?>
						</label><br />
						<label>
							<input type="radio" name="rollback_behavior" value="keep" />
							<?php esc_html_e( 'Keep the new value', 'webcasata-content-scheduler' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Schedule', 'webcasata-content-scheduler' ), 'primary', 'webcasata_cs_submit' ); ?>
	</form>
</div>
