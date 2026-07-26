<?php
/**
 * Persistence layer for image briefs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_Repository {
	/**
	 * Returns the latest brief for one post.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_latest_by_post_id( int $post_id ): ?array {
		global $wpdb;

		$table = NT_Content_Images_Brief_Migrator::get_table_name();
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE post_id = %d ORDER BY brief_version DESC, id DESC LIMIT 1",
			absint( $post_id )
		);
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Returns one brief by database ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		$table = NT_Content_Images_Brief_Migrator::get_table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) );
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Stores a generated brief as a new version or updates an identical version.
	 *
	 * @param array<string, mixed> $brief Generated brief.
	 * @return int|false Brief ID or false.
	 */
	public function save( array $brief ) {
		global $wpdb;

		$post_id = absint( $brief['post_id'] ?? 0 );
		$hash    = sanitize_text_field( (string) ( $brief['source_content_hash'] ?? '' ) );

		if ( 0 === $post_id || 64 !== strlen( $hash ) ) {
			return false;
		}

		$table    = NT_Content_Images_Brief_Migrator::get_table_name();
		$existing = $this->get_latest_by_post_id( $post_id );
		$version  = 1;

		if ( null !== $existing ) {
			$version = (int) $existing['brief_version'];

			if ( $existing['source_content_hash'] !== $hash ) {
				$this->mark_post_briefs_outdated( $post_id );
				++$version;
			}
		}

		$now  = current_time( 'mysql', true );
		$data = array(
			'post_id'                    => $post_id,
			'source_content_hash'        => $hash,
			'brief_version'              => $version,
			'status'                     => sanitize_key( (string) ( $brief['status'] ?? 'draft' ) ),
			'topic'                      => sanitize_text_field( (string) ( $brief['topic'] ?? '' ) ),
			'content_type'               => sanitize_key( (string) ( $brief['content_type'] ?? 'general_education' ) ),
			'search_intent'              => sanitize_key( (string) ( $brief['search_intent'] ?? 'informational' ) ),
			'visual_strategy'            => sanitize_key( (string) ( $brief['visual_strategy'] ?? 'professional_editorial' ) ),
			'featured_image_required'    => empty( $brief['featured_image']['required'] ) ? 0 : 1,
			'recommended_content_images' => min( 5, absint( $brief['recommended_content_images'] ?? 0 ) ),
			'validation_status'          => sanitize_key( (string) ( $brief['validation_status'] ?? 'warning' ) ),
			'brief_json'                 => wp_json_encode( $brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'created_by'                 => get_current_user_id(),
			'created_at'                 => $now,
			'updated_at'                 => $now,
			'approved_at'                => null,
		);
		$formats = array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' );

		if ( null !== $existing && $existing['source_content_hash'] === $hash ) {
			$updated = $wpdb->update( $table, $data, array( 'id' => absint( $existing['id'] ) ), $formats, array( '%d' ) );

			return false === $updated ? false : absint( $existing['id'] );
		}

		$inserted = $wpdb->insert( $table, $data, $formats );

		return false === $inserted ? false : absint( $wpdb->insert_id );
	}

	/**
	 * Updates workflow status.
	 */
	public function update_status( int $id, string $status ): int|false {
		global $wpdb;

		$allowed = array( 'draft', 'pending_review', 'approved', 'rejected', 'outdated' );
		$status  = sanitize_key( $status );

		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$data = array(
			'status'      => $status,
			'updated_at'  => current_time( 'mysql', true ),
			'approved_at' => 'approved' === $status ? current_time( 'mysql', true ) : null,
		);

		return $wpdb->update(
			NT_Content_Images_Brief_Migrator::get_table_name(),
			$data,
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Returns filtered brief list.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function get_list( array $args = array() ): array {
		global $wpdb;

		$page      = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page  = min( 100, max( 5, absint( $args['per_page'] ?? 20 ) ) );
		$search    = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$status    = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$type      = sanitize_key( (string) ( $args['content_type'] ?? '' ) );
		$table     = NT_Content_Images_Brief_Migrator::get_table_name();
		$where     = array( '1=1' );
		$params    = array();

		if ( '' !== $search ) {
			$where[]  = '(b.topic LIKE %s OR p.post_title LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $status ) {
			$where[]  = 'b.status = %s';
			$params[] = $status;
		}

		if ( '' !== $type ) {
			$where[]  = 'b.content_type = %s';
			$params[] = $type;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} b LEFT JOIN {$wpdb->posts} p ON p.ID = b.post_id WHERE {$where_sql}";
		$count_sql = empty( $params ) ? $count_sql : $wpdb->prepare( $count_sql, ...$params );
		$total     = absint( $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$offset    = ( $page - 1 ) * $per_page;
		$list_sql  = "SELECT b.*, p.post_title FROM {$table} b LEFT JOIN {$wpdb->posts} p ON p.ID = b.post_id WHERE {$where_sql} ORDER BY b.updated_at DESC LIMIT %d OFFSET %d";
		$list_sql  = $wpdb->prepare( $list_sql, ...array_merge( $params, array( $per_page, $offset ) ) );
		$rows      = $wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items'    => array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Returns dashboard counters.
	 *
	 * @return array<string, int>
	 */
	public function get_summary(): array {
		global $wpdb;

		$table = NT_Content_Images_Brief_Migrator::get_table_name();
		$row   = $wpdb->get_row(
			"SELECT COUNT(*) total, SUM(status = 'draft') drafts, SUM(status = 'pending_review') pending, SUM(status = 'approved') approved, SUM(status = 'outdated') outdated, SUM(validation_status = 'invalid') invalid FROM {$table}",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'total'    => absint( $row['total'] ?? 0 ),
			'drafts'   => absint( $row['drafts'] ?? 0 ),
			'pending'  => absint( $row['pending'] ?? 0 ),
			'approved' => absint( $row['approved'] ?? 0 ),
			'outdated' => absint( $row['outdated'] ?? 0 ),
			'invalid'  => absint( $row['invalid'] ?? 0 ),
		);
	}

	/**
	 * Marks previous versions outdated when source content changes.
	 */
	private function mark_post_briefs_outdated( int $post_id ): void {
		global $wpdb;

		$wpdb->update(
			NT_Content_Images_Brief_Migrator::get_table_name(),
			array( 'status' => 'outdated', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'post_id' => absint( $post_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Hydrates JSON and useful post URLs.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$brief          = json_decode( (string) ( $row['brief_json'] ?? '' ), true );
		$row['brief']   = is_array( $brief ) ? $brief : array();
		$row['id']      = absint( $row['id'] ?? 0 );
		$row['post_id'] = absint( $row['post_id'] ?? 0 );
		$row['title']   = sanitize_text_field( (string) ( $row['post_title'] ?? get_the_title( $row['post_id'] ) ) );
		$row['edit_url']= $row['post_id'] ? get_edit_post_link( $row['post_id'], 'raw' ) : '';
		unset( $row['brief_json'], $row['post_title'] );

		return $row;
	}
}
