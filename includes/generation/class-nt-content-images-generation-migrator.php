<?php
/**
 * Creates and upgrades the AI image generation table.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Migrator {
	private const VERSION_OPTION = 'nt_content_images_generation_db_version';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'nt_content_image_generations';
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

		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			brief_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(40) NOT NULL DEFAULT 'openai',
			model varchar(80) NOT NULL DEFAULT '',
			status varchar(24) NOT NULL DEFAULT 'generated',
			prompt longtext NOT NULL,
			prompt_hash char(64) NOT NULL DEFAULT '',
			settings_json longtext NOT NULL,
			response_json longtext NOT NULL,
			error_code varchar(100) NOT NULL DEFAULT '',
			error_message text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			approved_at datetime NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY brief_id (brief_id),
			KEY attachment_id (attachment_id),
			KEY status (status),
			KEY prompt_hash (prompt_hash)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::VERSION_OPTION, NT_CONTENT_IMAGES_DB_VERSION, false );
	}
}
