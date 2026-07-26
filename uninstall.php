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

delete_option( 'nt_content_images_version' );
delete_option( 'nt_content_images_db_version' );
delete_option( 'nt_content_images_brief_db_version' );
delete_option( 'nt_content_images_workflow_mode' );
delete_option( 'nt_content_images_delete_data_on_uninstall' );
delete_option( 'nt_content_images_audit_job' );
delete_option( 'nt_content_images_audit_lock' );
