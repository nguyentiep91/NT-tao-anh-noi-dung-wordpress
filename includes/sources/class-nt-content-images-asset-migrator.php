<?php
/**
 * Creates and upgrades the imported image asset table.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Asset_Migrator {
	private const VERSION_OPTION = 'nt_content_images_asset_db_version';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'nt_content_image_assets';
	}

	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, NT_CONTENT_IMAGES_DB_VERSION, '>=' ) ) {
			return;
		}
		self::migrate();
	}

	public static function migrate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::get_table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			brief_id bigint(20) unsigned NOT NULL DEFAULT 0,
			generation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_kind varchar(20) NOT NULL DEFAULT 'stock',
			provider varchar(40) NOT NULL DEFAULT '',
			provider_asset_id varchar(191) NOT NULL DEFAULT '',
			source_page_url text NOT NULL,
			original_url text NOT NULL,
			creator_name varchar(191) NOT NULL DEFAULT '',
			creator_url text NOT NULL,
			license_code varchar(40) NOT NULL DEFAULT '',
			license_version varchar(40) NOT NULL DEFAULT '',
			license_url text NOT NULL,
			attribution_text text NOT NULL,
			search_query varchar(255) NOT NULL DEFAULT '',
			prompt_hash char(64) NOT NULL DEFAULT '',
			width int(10) unsigned NOT NULL DEFAULT 0,
			height int(10) unsigned NOT NULL DEFAULT 0,
			mime_type varchar(80) NOT NULL DEFAULT '',
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			checksum char(64) NOT NULL DEFAULT '',
			status varchar(24) NOT NULL DEFAULT 'imported',
			selected_by bigint(20) unsigned NOT NULL DEFAULT 0,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			approved_at datetime NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY brief_id (brief_id),
			KEY generation_id (generation_id),
			KEY attachment_id (attachment_id),
			KEY provider (provider),
			KEY provider_asset_id (provider_asset_id),
			KEY status (status),
			KEY checksum (checksum)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::VERSION_OPTION, NT_CONTENT_IMAGES_DB_VERSION, false );
	}
}
