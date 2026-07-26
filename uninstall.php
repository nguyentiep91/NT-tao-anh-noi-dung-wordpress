<?php
/**
 * Uninstall routine.
 *
 * Persistent data is removed only when the administrator explicitly enables
 * the delete-on-uninstall option.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'nt_content_images_delete_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

$audit_table = $wpdb->prefix . 'nt_content_images_audit';
$brief_table = $wpdb->prefix . 'nt_content_image_briefs';
$wpdb->query( "DROP TABLE IF EXISTS {$brief_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$audit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange

foreach (
	array(
		'nt_content_images_version',
		'nt_content_images_db_version',
		'nt_content_images_brief_db_version',
		'nt_content_images_workflow_mode',
		'nt_content_images_delete_data_on_uninstall',
		'nt_content_images_audit_job',
		'nt_content_images_audit_lock',
		'nt_content_images_site_profile',
		'nt_content_images_brand_profile',
		'nt_content_images_profile_version',
	)
	as $option
) {
	delete_option( $option );
}
