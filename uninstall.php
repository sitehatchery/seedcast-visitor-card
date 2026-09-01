<?php
/**
 * Uninstall.
 *
 * @package SeedcastVisitorCard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Settings go. The cards do not.
 *
 * Those are the church's record of people who said they were coming, some of
 * whom may since have arrived and stayed. Deleting a plugin is not the same as
 * asking for that to be thrown away, and the shared submissions table is not
 * ours alone to drop: other Seedcast plugins keep their own rows in it.
 *
 * The Visitors screen has an export and a delete for anyone who does want the
 * data gone, and that is where the decision belongs.
 */

$scvc_options = array(
	'scvc_template',
	'scvc_show_details',
	'scvc_details_heading',
	'scvc_accent',
	'scvc_accent_dark',
	'scvc_status_legend',
	'scvc_status_choices',
	'scvc_status_off',
	'scvc_interests_legend',
	'scvc_interests_choices',
	'scvc_interests_off',
	'scvc_disable_css',
	'scvc_heading',
	'scvc_intro',
	'scvc_button',
	'scvc_confirmation',
	'scvc_consent',
	'scvc_suspended',
	'scvc_suspended_until',
	'scvc_suspended_message',
	'scvc_notify',
	'scvc_notify_to',
	'scvc_followup',
	'scvc_followup_subject',
	'scvc_followup_body',
	'scvc_thanks_page',
	'scvc_activated_on',
	'scvc_field_name',
	'scvc_field_email',
	'scvc_field_phone',
	'scvc_field_party',
	'scvc_field_heard',
	'scvc_field_message',
	'scvc_field_status',
	'scvc_field_interests',
);

foreach ( $scvc_options as $scvc_option ) {
	delete_option( $scvc_option );
}

// Per-post-type placement switches, whose names depend on which other plugins
// were installed, so they cannot be listed above.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$scvc_leftovers = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'scvc_embed_' ) . '%'
	)
);

foreach ( (array) $scvc_leftovers as $scvc_leftover ) {
	delete_option( $scvc_leftover );
}
