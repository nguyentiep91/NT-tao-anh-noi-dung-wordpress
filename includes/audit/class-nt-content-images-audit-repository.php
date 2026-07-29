<?php
/**
 * Persistence and reporting layer for read-only content audit results.
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
		$query      = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE post_id = %d LIMIT 1", $post_id );
		$row        = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $row ) ? $this->hydrate_row( $row ) : null;
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
			return $wpdb->update( $table_name, $data, array( 'post_id' => $post_id ), $formats, array( '%d' ) );
		}

		return $wpdb->insert( $table_name, $data, $formats );
	}

	/**
	 * Records a sanitized per-post scan error without deleting a previous result.
	 */
	public function mark_error( int $post_id, string $error_code, string $message ): int|false {
		global $wpdb;

		$post       = get_post( $post_id );
		$table_name = NT_Content_Images_Audit_Migrator::get_table_name();
		$now         = current_time( 'mysql', true );
		$error       = sanitize_key( $error_code ) . ': ' . sanitize_textarea_field( $message );
		$existing    = $this->get_by_post_id( $post_id );

		if ( null !== $existing ) {
			return $wpdb->update(
				$table_name,
				array(
					'audit_status' => 'error',
					'scan_error'   => $error,
					'scanned_at'   => $now,
					'updated_at'   => $now,
				),
				array( 'post_id' => $post_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		return $this->upsert(
			array(
				'post_id'       => $post_id,
				'post_type'     => $post instanceof WP_Post ? $post->post_type : 'post',
				'post_status'   => $post instanceof WP_Post ? $post->post_status : 'draft',
				'audit_status'  => 'error',
				'scan_error'    => $error,
				'scanned_at'    => $now,
				'priority_label'=> 'low',
				'analysis'      => array(
					'title' => $post instanceof WP_Post ? get_the_title( $post ) : '',
				),
			)
		);
	}

	/**
	 * Returns aggregate counters for the admin dashboard.
	 *
	 * @return array<string, int>
	 */
	public function get_summary(): array {
		global $wpdb;

		$table_name = NT_Content_Images_Audit_Migrator::get_table_name();
		$sql        = "SELECT
			COUNT(*) AS total,
			SUM(featured_image_id = 0) AS missing_featured,
			SUM(content_image_count = 0) AS missing_content_images,
			SUM(external_image_count > 0) AS has_external_images,
			SUM(missing_alt_count > 0) AS has_missing_alt,
			SUM(priority_score >= 80) AS very_high_priority,
			SUM(has_shortcode = 1) AS has_shortcode,
			SUM(has_complex_blocks = 1) AS has_complex_blocks,
			SUM(audit_status = 'error') AS errors
		FROM {$table_name}";
		$row        = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$defaults   = array(
			'total'                  => 0,
			'missing_featured'       => 0,
			'missing_content_images' => 0,
			'has_external_images'    => 0,
			'has_missing_alt'        => 0,
			'very_high_priority'     => 0,
			'has_shortcode'          => 0,
			'has_complex_blocks'     => 0,
			'errors'                 => 0,
		);

		foreach ( $defaults as $key => $value ) {
			$defaults[ $key ] = absint( $row[ $key ] ?? 0 );
		}

		return $defaults;
	}

	/**
	 * Returns a filtered, paginated list for the admin table.
	 *
	 * @param array<string, mixed> $args List arguments.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function get_list( array $args = array() ): array {
		global $wpdb;

		$table_name = NT_Content_Images_Audit_Migrator::get_table_name();
		$filters    = $this->normalize_list_args( $args );
		$where      = array( '1=1' );
		$params     = array();

		if ( '' !== $filters['search'] ) {
			$where[]  = 'p.post_title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		foreach ( array( 'post_type', 'post_status', 'priority_label', 'audit_status' ) as $field ) {
			if ( '' !== $filters[ $field ] ) {
				$where[]  = "a.{$field} = %s";
				$params[] = $filters[ $field ];
			}
		}

		$this->append_flag_filter( $where, $filters['featured'], 'a.featured_image_id' );
		$this->append_flag_filter( $where, $filters['content_images'], 'a.content_image_count' );
		$this->append_boolean_filter( $where, $filters['has_shortcode'], 'a.has_shortcode' );
		$this->append_boolean_filter( $where, $filters['has_complex_blocks'], 'a.has_complex_blocks' );
		$this->append_positive_filter( $where, $filters['external_images'], 'a.external_image_count' );
		$this->append_positive_filter( $where, $filters['missing_alt'], 'a.missing_alt_count' );

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table_name} a LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id WHERE {$where_sql}";
		$total     = $this->get_prepared_var( $count_sql, $params );
		$offset    = ( $filters['page'] - 1 ) * $filters['per_page'];
		$list_sql  = "SELECT a.*, p.post_title
			FROM {$table_name} a
			LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id
			WHERE {$where_sql}
			ORDER BY {$filters['orderby']} {$filters['order']}
			LIMIT %d OFFSET %d";
		$list_args = array_merge( $params, array( $filters['per_page'], $offset ) );
		$list_sql  = $wpdb->prepare( $list_sql, ...$list_args );
		$rows      = $wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items     = array_map( array( $this, 'hydrate_row' ), is_array( $rows ) ? $rows : array() );

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $filters['page'],
			'per_page' => $filters['per_page'],
		);
	}

	/**
	 * Returns rows for CSV export using the current filters.
	 *
	 * @param array<string, mixed> $args List filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_export_rows( array $args = array() ): array {
		$args['page']     = 1;
		$args['per_page'] = 5000;
		$result           = $this->get_list( $args );

		return $result['items'];
	}

	/**
	 * Deletes the derived audit record for one post.
	 */
	public function delete_by_post_id( int $post_id ): int|false {
		global $wpdb;

		return $wpdb->delete( NT_Content_Images_Audit_Migrator::get_table_name(), array( 'post_id' => absint( $post_id ) ), array( '%d' ) );
	}

	/**
	 * Deletes all plugin-owned audit rows without touching WordPress content.
	 */
	public function delete_all(): int|false {
		global $wpdb;

		$table_name = NT_Content_Images_Audit_Migrator::get_table_name();

		return $wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Hydrates stored JSON and safe WordPress URLs.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate_row( array $row ): array {
		$analysis        = json_decode( (string) ( $row['analysis_json'] ?? '' ), true );
		$row['analysis'] = is_array( $analysis ) ? $analysis : array();
		$row['post_id']  = absint( $row['post_id'] ?? 0 );
		$row['title']    = sanitize_text_field( (string) ( $row['post_title'] ?? $row['analysis']['title'] ?? '' ) );
		$row['edit_url'] = $row['post_id'] ? get_edit_post_link( $row['post_id'], 'raw' ) : '';
		$row['view_url'] = $row['post_id'] ? get_permalink( $row['post_id'] ) : '';
		unset( $row['analysis_json'], $row['post_title'] );

		return $row;
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
			'post_id'               => absint( $result['post_id'] ?? 0 ),
			'post_type'             => sanitize_key( (string) ( $result['post_type'] ?? 'post' ) ),
			'post_status'           => sanitize_key( (string) ( $result['post_status'] ?? 'draft' ) ),
			'featured_image_id'     => absint( $result['featured_image_id'] ?? 0 ),
			'content_image_count'   => absint( $result['content_image_count'] ?? 0 ),
			'local_image_count'     => absint( $result['local_image_count'] ?? 0 ),
			'external_image_count'  => absint( $result['external_image_count'] ?? 0 ),
			'missing_alt_count'     => absint( $result['missing_alt_count'] ?? 0 ),
			'word_count'            => absint( $result['word_count'] ?? 0 ),
			'paragraph_count'       => absint( $result['paragraph_count'] ?? 0 ),
			'h2_count'              => absint( $result['h2_count'] ?? 0 ),
			'h3_count'              => absint( $result['h3_count'] ?? 0 ),
			'table_count'           => absint( $result['table_count'] ?? 0 ),
			'list_count'            => absint( $result['list_count'] ?? 0 ),
			'shortcode_count'       => absint( $result['shortcode_count'] ?? 0 ),
			'has_shortcode'         => empty( $result['has_shortcode'] ) ? 0 : 1,
			'has_table'             => empty( $result['has_table'] ) ? 0 : 1,
			'has_complex_blocks'    => empty( $result['has_complex_blocks'] ) ? 0 : 1,
			'yoast_focus_keyphrase' => sanitize_text_field( (string) ( $result['yoast_focus_keyphrase'] ?? '' ) ),
			'priority_score'        => min( 100, absint( $result['priority_score'] ?? 0 ) ),
			'priority_label'        => sanitize_key( (string) ( $result['priority_label'] ?? 'low' ) ),
			'audit_status'          => sanitize_key( (string) ( $result['audit_status'] ?? 'scanned' ) ),
			'content_hash'          => sanitize_text_field( (string) ( $result['content_hash'] ?? '' ) ),
			'analysis_json'         => wp_json_encode( $analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'scan_error'            => sanitize_textarea_field( (string) ( $result['scan_error'] ?? '' ) ),
			'scanned_at'            => sanitize_text_field( (string) ( $result['scanned_at'] ?? $now ) ),
			'updated_at'            => $now,
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
			'%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s',
			'%s', '%s', '%s',
		);
	}

	/**
	 * Normalizes list filters and sorting.
	 *
	 * @param array<string, mixed> $args Raw arguments.
	 * @return array<string, mixed>
	 */
	private function normalize_list_args( array $args ): array {
		$allowed_orderby = array(
			'priority_score' => 'a.priority_score',
			'scanned_at'     => 'a.scanned_at',
			'word_count'     => 'a.word_count',
			'post_id'        => 'a.post_id',
			'post_title'     => 'p.post_title',
		);
		$orderby_key = sanitize_key( (string) ( $args['orderby'] ?? 'priority_score' ) );
		$per_page    = max( 1, min( 5000, absint( $args['per_page'] ?? 20 ) ) );

		return array(
			'search'             => sanitize_text_field( (string) ( $args['search'] ?? '' ) ),
			'post_type'          => sanitize_key( (string) ( $args['post_type'] ?? '' ) ),
			'post_status'        => sanitize_key( (string) ( $args['post_status'] ?? '' ) ),
			'priority_label'     => sanitize_key( (string) ( $args['priority_label'] ?? '' ) ),
			'audit_status'       => sanitize_key( (string) ( $args['audit_status'] ?? '' ) ),
			'featured'           => sanitize_key( (string) ( $args['featured'] ?? '' ) ),
			'content_images'     => sanitize_key( (string) ( $args['content_images'] ?? '' ) ),
			'has_shortcode'      => sanitize_key( (string) ( $args['has_shortcode'] ?? '' ) ),
			'has_complex_blocks' => sanitize_key( (string) ( $args['has_complex_blocks'] ?? '' ) ),
			'external_images'    => sanitize_key( (string) ( $args['external_images'] ?? '' ) ),
			'missing_alt'        => sanitize_key( (string) ( $args['missing_alt'] ?? '' ) ),
			'orderby'            => $allowed_orderby[ $orderby_key ] ?? $allowed_orderby['priority_score'],
			'order'              => 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC',
			'page'               => max( 1, absint( $args['page'] ?? 1 ) ),
			'per_page'           => $per_page,
		);
	}

	/**
	 * Appends a zero/positive SQL filter.
	 *
	 * @param string[] $where SQL fragments.
	 */
	private function append_flag_filter( array &$where, string $value, string $column ): void {
		if ( 'missing' === $value || 'none' === $value ) {
			$where[] = "{$column} = 0";
		} elseif ( 'present' === $value || 'some' === $value ) {
			$where[] = "{$column} > 0";
		}
	}

	/**
	 * Appends a boolean SQL filter.
	 *
	 * @param string[] $where SQL fragments.
	 */
	private function append_boolean_filter( array &$where, string $value, string $column ): void {
		if ( 'yes' === $value ) {
			$where[] = "{$column} = 1";
		} elseif ( 'no' === $value ) {
			$where[] = "{$column} = 0";
		}
	}

	/**
	 * Appends a positive count filter.
	 *
	 * @param string[] $where SQL fragments.
	 */
	private function append_positive_filter( array &$where, string $value, string $column ): void {
		if ( 'yes' === $value ) {
			$where[] = "{$column} > 0";
		} elseif ( 'no' === $value ) {
			$where[] = "{$column} = 0";
		}
	}

	/**
	 * Executes a prepared scalar query.
	 *
	 * @param mixed[] $params Prepared values.
	 */
	private function get_prepared_var( string $sql, array $params ): int {
		global $wpdb;

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, ...$params );
		}

		return absint( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
