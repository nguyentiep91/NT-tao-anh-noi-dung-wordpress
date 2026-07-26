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
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( NT_CONTENT_IMAGES_FILE ) );
			wp_die( esc_html__( 'NT – Tạo ảnh cho nội dung WordPress yêu cầu PHP 8.1 trở lên.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		add_option( 'nt_content_images_version', NT_CONTENT_IMAGES_VERSION, '', false );
		add_option( 'nt_content_images_workflow_mode', 'draft_review', '', false );
		add_option( 'nt_content_images_delete_data_on_uninstall', 'no', '', false );
		add_option( 'nt_content_images_profile_version', 1, '', false );

		NT_Content_Images_Audit_Migrator::migrate();
		NT_Content_Images_Brief_Migrator::migrate();
		NT_Content_Images_Generation_Migrator::migrate();
		NT_Content_Images_Asset_Migrator::migrate();

		$post_types = new NT_Content_Images_Post_Type_Registry();
		$rule_packs = new NT_Content_Images_Rule_Pack_Registry();
		$profiles = new NT_Content_Images_Profile_Repository( $post_types, $rule_packs, new NT_Content_Images_Profile_Validator() );
		$profiles->save( NT_Content_Images_Site_Profile::defaults( $post_types->get_slugs() ), NT_Content_Images_Brand_Profile::defaults() );
	}
}
