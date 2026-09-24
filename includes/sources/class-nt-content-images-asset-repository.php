<?php
/**
 * Persistence for imported and generated image assets.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Asset_Repository {
	/** @param array<string, mixed> $data */
	public function insert( array $data ): int|false {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = array(
			'post_id'           => absint( $data['post_id'] ?? 0 ),
			'brief_id'          => absint( $data['brief_id'] ?? 0 ),
			'generation_id'     => absint( $data['generation_id'] ?? 0 ),
			'attachment_id'     => absint( $data['attachment_id'] ?? 0 ),
			'source_kind'       => sanitize_key( (string) ( $data['source_kind'] ?? 'stock' ) ),
			'provider'          => sanitize_key( (string) ( $data['provider'] ?? '' ) ),
			'provider_asset_id' => sanitize_text_field( (string) ( $data['provider_asset_id'] ?? '' ) ),
			'source_page_url'   => esc_url_raw( (string) ( $data['source_page_url'] ?? '' ) ),
			'original_url'      => esc_url_raw( (string) ( $data['original_url'] ?? '' ) ),
			'creator_name'      => sanitize_text_field( (string) ( $data['creator_name'] ?? '' ) ),
			'creator_url'       => esc_url_raw( (string) ( $data['creator_url'] ?? '' ) ),
			'license_code'      => sanitize_key( (string) ( $data['license_code'] ?? '' ) ),
			'license_version'   => sanitize_text_field( (string) ( $data['license_version'] ?? '' ) ),
			'license_url'       => esc_url_raw( (string) ( $data['license_url'] ?? '' ) ),
			'attribution_text'  => sanitize_text_field( (string) ( $data['attribution_text'] ?? '' ) ),
			'search_query'      => sanitize_text_field( (string) ( $data['search_query'] ?? '' ) ),
			'prompt_hash'       => sanitize_text_field( (string) ( $data['prompt_hash'] ?? '' ) ),
			'width'             => absint( $data['width'] ?? 0 ),
			'height'            => absint( $data['height'] ?? 0 ),
			'mime_type'         => sanitize_mime_type( (string) ( $data['mime_type'] ?? '' ) ),
			'file_size'         => absint( $data['file_size'] ?? 0 ),
			'checksum'          => sanitize_text_field( (string) ( $data['checksum'] ?? '' ) ),
			'status'            => sanitize_key( (string) ( $data['status'] ?? 'imported' ) ),
			'selected_by'       => get_current_user_id(),
			'approved_by'       => 0,
			'created_at'        => $now,
			'updated_at'        => $now,
			'approved_at'       => null,
		);
		if ( 0 === $row['post_id'] || 0 === $row['attachment_id'] || '' === $row['provider'] ) {
			return false;
		}
		$ok = $wpdb->insert( NT_Content_Images_Asset_Migrator::get_table_name(), $row );
		return false === $ok ? false : absint( $wpdb->insert_id );
	}

	/** @return array<string, mixed>|null */
	public function get( int $id ): ?array {
		global $wpdb;
		$table = NT_Content_Images_Asset_Migrator::get_table_name();
		$sql = $wpdb->prepare( "SELECT a.*, p.post_title FROM {$table} a LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id WHERE a.id = %d LIMIT 1", absint( $id ) );
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/** @return array{items: array<int, array<string, mixed>>, total: int} */
	public function get_list( array $args = array() ): array {
		global $wpdb;
		$limit = min( 50, max( 5, absint( $args['limit'] ?? 20 ) ) );
		$status = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$table = NT_Content_Images_Asset_Migrator::get_table_name();
		$where = '';
		$params = array();
		if ( '' !== $status ) {
			$where = 'WHERE a.status = %s';
			$params[] = $status;
		}
		$params[] = $limit;
		$sql = $wpdb->prepare( "SELECT a.*, p.post_title FROM {$table} a LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id {$where} ORDER BY a.created_at DESC LIMIT %d", ...$params );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'items' => array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() ), 'total' => is_array( $rows ) ? count( $rows ) : 0 );
	}

	/** @return array<string, mixed>|null */
	public function get_by_provider_asset_for_post( int $post_id, string $provider, string $provider_asset_id ): ?array {
		global $wpdb;
		$table = NT_Content_Images_Asset_Migrator::get_table_name();
		$sql = $wpdb->prepare(
			"SELECT a.*, p.post_title FROM {$table} a LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id WHERE a.post_id = %d AND a.provider = %s AND a.provider_asset_id = %s LIMIT 1",
			absint( $post_id ),
			sanitize_key( $provider ),
			sanitize_text_field( $provider_asset_id )
		);
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function delete( int $id ): int|false {
		global $wpdb;
		return $wpdb->delete( NT_Content_Images_Asset_Migrator::get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public function link_generation( int $asset_id, int $generation_id ): int|false {
		global $wpdb;
		return $wpdb->update(
			NT_Content_Images_Asset_Migrator::get_table_name(),
			array( 'generation_id' => absint( $generation_id ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $asset_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public function mark_approved_by_generation( int $generation_id ): void {
		global $wpdb;
		$wpdb->update(
			NT_Content_Images_Asset_Migrator::get_table_name(),
			array( 'status' => 'approved', 'approved_by' => get_current_user_id(), 'approved_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'generation_id' => absint( $generation_id ) )
		);
	}

	/** @param array<string, mixed> $row @return array<string, mixed> */
	private function hydrate( array $row ): array {
		foreach ( array( 'id', 'post_id', 'brief_id', 'generation_id', 'attachment_id', 'width', 'height', 'file_size', 'selected_by', 'approved_by' ) as $field ) {
			$row[ $field ] = absint( $row[ $field ] ?? 0 );
		}
		$row['title'] = sanitize_text_field( (string) ( $row['post_title'] ?? '' ) );
		$row['image_url'] = $row['attachment_id'] ? wp_get_attachment_image_url( $row['attachment_id'], 'large' ) : '';
		unset( $row['post_title'] );
		return $row;
	}
}
