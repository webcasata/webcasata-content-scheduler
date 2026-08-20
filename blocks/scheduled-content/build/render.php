<?php
/**
 * Server-side render for webcasata-cs/scheduled-content.
 *
 * Included by WordPress core's block.json "render" support, which
 * wraps this file in output buffering — echo here, don't return a
 * string (a bare `return;` is fine to bail out early with no output).
 *
 * $attributes, $content, and $block are provided by WordPress core.
 *
 * IMPORTANT — full-page caching: this file only re-evaluates when
 * WordPress actually renders the page. On a site with a full-page
 * cache (WP Rocket, W3TC, a CDN, etc.), a cached page can serve a
 * stale visibility state until the cache expires or is purged. When
 * "hideOutsideSchedule" is off, the content stays in the page (just
 * visually hidden) specifically so view.js can correct a stale cached
 * state client-side using the visitor's own clock. When it's on, the
 * content is genuinely absent from a cached page's HTML and no amount
 * of client-side JS can recover it — that trade-off is explained to
 * the site owner in the block's sidebar control.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$webcasata_cs_start_local = isset( $attributes['startDatetime'] ) ? $attributes['startDatetime'] : '';
$webcasata_cs_end_local   = isset( $attributes['endDatetime'] ) ? $attributes['endDatetime'] : '';
$webcasata_cs_hide_fully  = ! empty( $attributes['hideOutsideSchedule'] );

// Same site-timezone-to-UTC conversion used everywhere else in the
// plugin (Webcasata_CS_Schedule::to_gmt() / Webcasata_CS_Engine::to_timestamp()),
// duplicated here since a block render.php can't easily depend on a
// class that may not have loaded yet at this point in the request.
$webcasata_cs_start_ts = $webcasata_cs_start_local
	? strtotime( get_gmt_from_date( $webcasata_cs_start_local, 'Y-m-d H:i:s' ) . ' GMT' )
	: null;
$webcasata_cs_end_ts   = $webcasata_cs_end_local
	? strtotime( get_gmt_from_date( $webcasata_cs_end_local, 'Y-m-d H:i:s' ) . ' GMT' )
	: null;
$webcasata_cs_now_ts   = time();

$webcasata_cs_is_visible =
	( null === $webcasata_cs_start_ts || $webcasata_cs_now_ts >= $webcasata_cs_start_ts ) &&
	( null === $webcasata_cs_end_ts || $webcasata_cs_now_ts <= $webcasata_cs_end_ts );

// Outside the window and configured to fully disappear: render nothing.
if ( ! $webcasata_cs_is_visible && $webcasata_cs_hide_fully ) {
	return;
}

$webcasata_cs_classes = array( 'webcasata-cs-scheduled-content' );
if ( ! $webcasata_cs_is_visible ) {
	$webcasata_cs_classes[] = 'webcasata-cs-is-hidden';
}

$webcasata_cs_wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                   => implode( ' ', $webcasata_cs_classes ),
		// Milliseconds since epoch (UTC), read by view.js to
		// re-validate against the visitor's own clock.
		'data-webcasata-cs-start' => $webcasata_cs_start_ts ? $webcasata_cs_start_ts * 1000 : '',
		'data-webcasata-cs-end'   => $webcasata_cs_end_ts ? $webcasata_cs_end_ts * 1000 : '',
	)
);

echo '<div ' . $webcasata_cs_wrapper_attributes . '>' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attributes is pre-escaped by get_block_wrapper_attributes(); $content is WordPress's own already-sanitized InnerBlocks output.
