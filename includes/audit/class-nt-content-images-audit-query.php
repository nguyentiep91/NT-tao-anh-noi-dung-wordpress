<?php
/**
 * Queries WordPress content IDs for audit batches.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Query {
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Post_Type_Registry $post_types;

	public function __construct(
		NT_Content_Images_Profile_Repository $profiles,
		NT_Content_Images_Post_Type_Registry $post_types
	) {
		$this->profiles  = $profiles;
		$this->post_types = $post_types;
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{post_ids: int[], total: int}
	 */
	public function get_post_ids( array $args = array() ): array {
		$normalized = $this->normalize_args( $args );
		$query      = new WP_Query(
			array(
				'post_type'              => $normalized['post_types'],
				'post_status'            => $normalized['post_statuses'],
				'posts_per_page'         => $normalized['per_page'],
				'offset'                 => $normalized['offset'],
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return array( 'post_ids' => array_map( 'absint', $query->posts ), 'total' => absint( $query->found_posts ) );
	}

	public function count_posts( array $args = array() ): int {
		$args['per_page'] = 1;
		$args['offset']   = 0;
		$result = $this->get_post_ids( $args );
		return $result['total'];
	}

	/** @return string[] */
	public function get_allowed_post_types(): array {
		$site    = $this->profiles->get_site_profile();
		$enabled = is_array( $site['enabled_post_types'] ?? null ) ? $site['enabled_post_types'] : array( 'post', 'page' );
		$allowed = $this->post_types->constrain( $enabled );
		return apply_filters( 'nt_content_images_audit_post_types', $allowed, $site );
	}

	/** @return string[] */
	public function get_allowed_post_statuses(): array {
		return array( 'publish', 'draft', 'pending', 'private', 'future' );
	}

	/**
	 * @param array<string, mixed> $args Raw args.
	 * @return array{post_types: string[], post_statuses: string[], per_page: int, offset: int}
	 */
	private function normalize_args( array $args ): array {
		$allowed_types    = $this->get_allowed_post_types();
		$allowed_statuses = $this->get_allowed_post_statuses();
		$post_types       = $this->sanitize_list( (array) ( $args['post_types'] ?? $allowed_types ) );
		$post_statuses    = $this->sanitize_list( (array) ( $args['post_statuses'] ?? array( 'publish' ) ) );
		$post_types       = array_values( array_intersect( $post_types, $allowed_types ) );
		$post_statuses    = array_values( array_intersect( $post_statuses, $allowed_statuses ) );
		if ( empty( $post_types ) ) {
			$post_types = $allowed_types;
		}
		if ( empty( $post_statuses ) ) {
			$post_statuses = array( 'publish' );
		}
		return array(
			'post_types'    => $post_types,
			'post_statuses' => $post_statuses,
			'per_page'      => max( 1, min( 50, absint( $args['per_page'] ?? 20 ) ) ),
			'offset'        => max( 0, absint( $args['offset'] ?? 0 ) ),
		);
	}

	/** @param array<int, mixed> $values Raw values. @return string[] */
	private function sanitize_list( array $values ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $values ) ) ) ) );
	}
}
