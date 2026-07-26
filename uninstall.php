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

delete_option( 'nt_content_images_version' );
delete_option( 'nt_content_images_workflow_mode' );
delete_option( 'nt_content_images_delete_data_on_uninstall' );
