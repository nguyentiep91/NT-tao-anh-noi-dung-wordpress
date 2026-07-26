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

$table_name = $wpdb->prefix . 'nt_content_images_audit';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange

delete_option( 'nt_content_images_version' );
delete_option( 'nt_content_images_db_version' );
delete_option( 'nt_content_images_workflow_mode' );
delete_option( 'nt_content_images_delete_data_on_uninstall' );
delete_option( 'nt_content_images_audit_job' );
delete_option( 'nt_content_images_audit_lock' );
