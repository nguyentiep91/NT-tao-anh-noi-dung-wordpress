<?php
/**
 * Plugin activation handler.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Activator {
	/**
	 * Sets initial non-sensitive options and creates plugin-owned tables.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( NT_CONTENT_IMAGES_FILE ) );
			wp_die(
				esc_html__( 'NT – Tạo ảnh cho nội dung WordPress yêu cầu PHP 8.1 trở lên.', 'nt-tao-anh-noi-dung-wordpress' )
			);
		}

		add_option( 'nt_content_images_version', NT_CONTENT_IMAGES_VERSION, '', false );
		add_option( 'nt_content_images_workflow_mode', 'draft_review', '', false );
		add_option( 'nt_content_images_delete_data_on_uninstall', 'no', '', false );

		NT_Content_Images_Audit_Migrator::migrate();
	}
}
