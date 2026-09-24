<?php
/**
 * Creates and upgrades the read-only audit table.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Migrator {
	/**
	 * Option used to track the installed audit schema version.
	 */
	private const DB_VERSION_OPTION = 'nt_content_images_db_version';

	/**
	 * Returns the fully prefixed audit table name.
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'nt_content_images_audit';
	}

	/**
	 * Runs the schema migration only when the stored version is outdated.
	 */
	public static function maybe_upgrade(): void {
		$installed_version = (string) get_option( self::DB_VERSION_OPTION, '0' );

		if ( version_compare( $installed_version, NT_CONTENT_IMAGES_DB_VERSION, '>=' ) ) {
			return;
		}

		self::migrate();
	}

	/**
	 * Creates or updates the audit table using WordPress dbDelta.
	 */
	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(32) NOT NULL DEFAULT 'post',
			post_status varchar(24) NOT NULL DEFAULT 'draft',
			featured_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			content_image_count int(10) unsigned NOT NULL DEFAULT 0,
			local_image_count int(10) unsigned NOT NULL DEFAULT 0,
			external_image_count int(10) unsigned NOT NULL DEFAULT 0,
			missing_alt_count int(10) unsigned NOT NULL DEFAULT 0,
			word_count int(10) unsigned NOT NULL DEFAULT 0,
			paragraph_count int(10) unsigned NOT NULL DEFAULT 0,
			h2_count int(10) unsigned NOT NULL DEFAULT 0,
			h3_count int(10) unsigned NOT NULL DEFAULT 0,
			table_count int(10) unsigned NOT NULL DEFAULT 0,
			list_count int(10) unsigned NOT NULL DEFAULT 0,
			shortcode_count int(10) unsigned NOT NULL DEFAULT 0,
			has_shortcode tinyint(1) unsigned NOT NULL DEFAULT 0,
			has_table tinyint(1) unsigned NOT NULL DEFAULT 0,
			has_complex_blocks tinyint(1) unsigned NOT NULL DEFAULT 0,
			yoast_focus_keyphrase text NULL,
			priority_score smallint(5) unsigned NOT NULL DEFAULT 0,
			priority_label varchar(24) NOT NULL DEFAULT 'low',
			audit_status varchar(24) NOT NULL DEFAULT 'scanned',
			content_hash char(64) NOT NULL DEFAULT '',
			analysis_json longtext NULL,
			scan_error text NULL,
			scanned_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY post_type_status (post_type, post_status),
			KEY priority_score (priority_score),
			KEY audit_status (audit_status),
			KEY content_hash (content_hash)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, NT_CONTENT_IMAGES_DB_VERSION, false );
	}
}