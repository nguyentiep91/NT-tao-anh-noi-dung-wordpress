<?php
/**
 * Persistence layer for read-only content audit results.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Repository {
	/**
	 * Returns one audit record by post ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_by_post_id( int $post_id ): ?array {
		global $wpdb;

		$table_name = NT_Content_Images_Audit_Migrator::get_table_name();
		$query      = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE post_id = %d LIMIT 1",
			$post_id
		);
		$row        = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Inserts or updates one normalized audit result.
	 *
	 * @param array<string, mixed> $result Scanner result.
	 * @return int|false Number of affected rows or false on failure.
	 */
	public function upsert( array $result ) {
		global $wpdb;

		$post_id = isset( $result['post_id'] ) ? absint( $result['post_id'] ) : 0;

		if ( 0 === $post_id ) {
			return false;
		}

		$existing   = $this->get_by_post_id( $post_id );
		$table_name = NT_Content_Images_Audit_Migrator::get_table_name();
		$now         = current_time( 'mysql', true );
		$data        = $this->normalize_result( $result, $now );
		$formats     = $this->get_formats();

		if ( null !== $existing ) {
			return $wpdb->update(
				$table_name,
				$data,
				array( 'post_id' => $post_id ),
				$formats,
				array( '%d' )
			);
		}

		return $wpdb->insert( $table_name, $data, $formats );
	}

	/**
	 * Deletes the derived audit record for one post.
	 */
	public function delete_by_post_id( int $post_id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			NT_Content_Images_Audit_Migrator::get_table_name(),
			array( 'post_id' => absint( $post_id ) ),
			array( '%d' )
		);
	}

	/**
	 * Converts a scanner result to the table schema.
	 *
	 * @param array<string, mixed> $result Scanner result.
	 * @return array<string, int|string|null>
	 */
	private function normalize_result( array $result, string $now ): array {
		$analysis = isset( $result['analysis'] ) && is_array( $result['analysis'] ) ? $result['analysis'] : array();

		return array(
			'post_id'                 => absint( $result['post_id'] ?? 0 ),
			'post_type'               => sanitize_key( (string) ( $result['post_type'] ?? 'post' ) ),
			'post_status'             => sanitize_key( (string) ( $result['post_status'] ?? 'draft' ) ),
			'featured_image_id'       => absint( $result['featured_image_id'] ?? 0 ),
			'content_image_count'     => absint( $result['content_image_count'] ?? 0 ),
			'local_image_count'       => absint( $result['local_image_count'] ?? 0 ),
			'external_image_count'    => absint( $result['external_image_count'] ?? 0 ),
			'missing_alt_count'       => absint( $result['missing_alt_count'] ?? 0 ),
			'word_count'              => absint( $result['word_count'] ?? 0 ),
			'paragraph_count'         => absint( $result['paragraph_count'] ?? 0 ),
			'h2_count'                => absint( $result['h2_count'] ?? 0 ),
			'h3_count'                => absint( $result['h3_count'] ?? 0 ),
			'table_count'             => absint( $result['table_count'] ?? 0 ),
			'list_count'              => absint( $result['list_count'] ?? 0 ),
			'shortcode_count'         => absint( $result['shortcode_count'] ?? 0 ),
			'has_shortcode'           => empty( $result['has_shortcode'] ) ? 0 : 1,
			'has_table'               => empty( $result['has_table'] ) ? 0 : 1,
			'has_complex_blocks'      => empty( $result['has_complex_blocks'] ) ? 0 : 1,
			'yoast_focus_keyphrase'   => sanitize_text_field( (string) ( $result['yoast_focus_keyphrase'] ?? '' ) ),
			'priority_score'          => min( 100, absint( $result['priority_score'] ?? 0 ) ),
			'priority_label'          => sanitize_key( (string) ( $result['priority_label'] ?? 'low' ) ),
			'audit_status'            => sanitize_key( (string) ( $result['audit_status'] ?? 'scanned' ) ),
			'content_hash'            => sanitize_text_field( (string) ( $result['content_hash'] ?? '' ) ),
			'analysis_json'           => wp_json_encode( $analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'scan_error'              => sanitize_textarea_field( (string) ( $result['scan_error'] ?? '' ) ),
			'scanned_at'              => sanitize_text_field( (string) ( $result['scanned_at'] ?? $now ) ),
			'updated_at'              => $now,
		);
	}

	/**
	 * Returns wpdb formats matching normalize_result() order.
	 *
	 * @return string[]
	 */
	private function get_formats(): array {
		return array(
			'%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d',
			'%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d',
			'%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
		);
	}
}