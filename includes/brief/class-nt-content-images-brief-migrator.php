<?php
/**
 * Creates and upgrades the image brief table.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_Migrator {
	private const VERSION_OPTION = 'nt_content_images_brief_db_version';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'nt_content_image_briefs';
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

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			source_content_hash char(64) NOT NULL DEFAULT '',
			profile_version int(10) unsigned NOT NULL DEFAULT 1,
			profile_hash char(64) NOT NULL DEFAULT '',
			brand_profile_hash char(64) NOT NULL DEFAULT '',
			rule_packs_json text NOT NULL,
			post_type_mapping varchar(40) NOT NULL DEFAULT 'generic_content',
			brief_version int(10) unsigned NOT NULL DEFAULT 1,
			status varchar(24) NOT NULL DEFAULT 'draft',
			topic text NOT NULL,
			content_type varchar(40) NOT NULL DEFAULT 'general_content',
			search_intent varchar(32) NOT NULL DEFAULT 'informational',
			visual_strategy varchar(80) NOT NULL DEFAULT 'professional_editorial',
			featured_image_required tinyint(1) unsigned NOT NULL DEFAULT 0,
			recommended_content_images int(10) unsigned NOT NULL DEFAULT 0,
			validation_status varchar(24) NOT NULL DEFAULT 'warning',
			brief_json longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			approved_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_hash_version (post_id, source_content_hash, brief_version),
			KEY post_id (post_id),
			KEY status (status),
			KEY validation_status (validation_status),
			KEY content_type (content_type),
			KEY profile_hash (profile_hash),
			KEY post_type_mapping (post_type_mapping)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::VERSION_OPTION, NT_CONTENT_IMAGES_DB_VERSION, false );
	}
}
