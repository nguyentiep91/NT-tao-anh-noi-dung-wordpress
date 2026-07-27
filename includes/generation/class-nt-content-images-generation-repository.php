<?php
/**
 * Persistence layer for generated AI images.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Repository {
	/** @param array<string, mixed> $data */
	public function insert( array $data ): int|false {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = array(
			'post_id'       => absint( $data['post_id'] ?? 0 ),
			'brief_id'      => absint( $data['brief_id'] ?? 0 ),
			'attachment_id' => absint( $data['attachment_id'] ?? 0 ),
			'provider'      => sanitize_key( (string) ( $data['provider'] ?? 'openai' ) ),
			'model'         => sanitize_text_field( (string) ( $data['model'] ?? '' ) ),
			'status'        => sanitize_key( (string) ( $data['status'] ?? 'generated' ) ),
			'prompt'        => sanitize_textarea_field( (string) ( $data['prompt'] ?? '' ) ),
			'prompt_hash'   => hash( 'sha256', (string) ( $data['prompt'] ?? '' ) ),
			'settings_json' => wp_json_encode( is_array( $data['settings'] ?? null ) ? $data['settings'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'response_json' => wp_json_encode( is_array( $data['response'] ?? null ) ? $data['response'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'error_code'    => sanitize_key( (string) ( $data['error_code'] ?? '' ) ),
			'error_message' => sanitize_textarea_field( (string) ( $data['error_message'] ?? '' ) ),
			'created_by'    => get_current_user_id(),
			'created_at'    => $now,
			'updated_at'    => $now,
			'approved_at'   => null,
		);
		if ( 0 === $row['post_id'] ) {
			return false;
		}
		$ok = $wpdb->insert(
			NT_Content_Images_Generation_Migrator::get_table_name(),
			$row,
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return false === $ok ? false : absint( $wpdb->insert_id );
	}

	/** @return array<string, mixed>|null */
	public function get( int $id ): ?array {
		global $wpdb;
		$table = NT_Content_Images_Generation_Migrator::get_table_name();
		$sql   = $wpdb->prepare( "SELECT g.*, p.post_title FROM {$table} g LEFT JOIN {$wpdb->posts} p ON p.ID = g.post_id WHERE g.id = %d LIMIT 1", absint( $id ) );
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/** @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int} */
	public function get_list( array $args = array() ): array {
		global $wpdb;
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 5, absint( $args['per_page'] ?? 20 ) ) );
		$status   = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$search   = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$table    = NT_Content_Images_Generation_Migrator::get_table_name();
		$where    = array( '1=1' );
		$params   = array();
		if ( '' !== $status ) {
			$where[]  = 'g.status = %s';
			$params[] = $status;
		}
		if ( '' !== $search ) {
			$where[]  = 'p.post_title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} g LEFT JOIN {$wpdb->posts} p ON p.ID = g.post_id WHERE {$where_sql}";
		$count_sql = $params ? $wpdb->prepare( $count_sql, ...$params ) : $count_sql;
		$total     = absint( $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$offset    = ( $page - 1 ) * $per_page;
		$list_sql  = "SELECT g.*, p.post_title FROM {$table} g LEFT JOIN {$wpdb->posts} p ON p.ID = g.post_id WHERE {$where_sql} ORDER BY g.created_at DESC LIMIT %d OFFSET %d";
		$list_sql  = $wpdb->prepare( $list_sql, ...array_merge( $params, array( $per_page, $offset ) ) );
		$rows      = $wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'items'    => array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** @return array<int, array<string, mixed>> All generation records of one post, oldest first. */
	public function get_by_post( int $post_id ): array {
		global $wpdb;
		$table = NT_Content_Images_Generation_Migrator::get_table_name();
		$sql   = $wpdb->prepare( "SELECT g.*, p.post_title FROM {$table} g LEFT JOIN {$wpdb->posts} p ON p.ID = g.post_id WHERE g.post_id = %d ORDER BY g.created_at ASC, g.id ASC", absint( $post_id ) );
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	public function update_status( int $id, string $status ): int|false {
		global $wpdb;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'generated', 'approved', 'rejected', 'failed', 'inserted' ), true ) ) {
			return false;
		}
		$data = array(
			'status'      => $status,
			'updated_at'  => current_time( 'mysql', true ),
			'approved_at' => 'approved' === $status ? current_time( 'mysql', true ) : null,
		);
		return $wpdb->update( NT_Content_Images_Generation_Migrator::get_table_name(), $data, array( 'id' => absint( $id ) ), array( '%s', '%s', '%s' ), array( '%d' ) );
	}

	/** Merges traceable provider metadata without changing content or status. */
	public function merge_response( int $id, array $extra ): int|false {
		global $wpdb;
		$item = $this->get( $id );
		if ( null === $item ) {
			return false;
		}
		$response = array_replace_recursive( is_array( $item['response'] ?? null ) ? $item['response'] : array(), $extra );
		return $wpdb->update(
			NT_Content_Images_Generation_Migrator::get_table_name(),
			array(
				'response_json' => wp_json_encode( $response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/** @param array<string, mixed> $row @return array<string, mixed> */
	private function hydrate( array $row ): array {
		$row['id']            = absint( $row['id'] ?? 0 );
		$row['post_id']       = absint( $row['post_id'] ?? 0 );
		$row['brief_id']      = absint( $row['brief_id'] ?? 0 );
		$row['attachment_id'] = absint( $row['attachment_id'] ?? 0 );
		$row['title']         = sanitize_text_field( (string) ( $row['post_title'] ?? get_the_title( $row['post_id'] ) ) );
		$row['settings']      = json_decode( (string) ( $row['settings_json'] ?? '' ), true ) ?: array();
		$row['response']      = json_decode( (string) ( $row['response_json'] ?? '' ), true ) ?: array();
		$row['image_url']     = $row['attachment_id'] ? wp_get_attachment_image_url( $row['attachment_id'], 'large' ) : '';
		$row['edit_url']      = $row['post_id'] ? get_edit_post_link( $row['post_id'], 'raw' ) : '';
		unset( $row['settings_json'], $row['response_json'], $row['post_title'] );
		return $row;
	}
}
