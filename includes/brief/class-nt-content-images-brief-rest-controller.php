<?php
/**
 * REST controller for image brief generation and workflow.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';
	private NT_Content_Images_Brief_Generator $generator;
	private NT_Content_Images_Brief_Repository $repository;
	private NT_Content_Images_Audit_Repository $audit_repository;

	/**
	 * Sets controller dependencies.
	 */
	public function __construct(
		NT_Content_Images_Brief_Generator $generator,
		NT_Content_Images_Brief_Repository $repository,
		NT_Content_Images_Audit_Repository $audit_repository
	) {
		$this->generator        = $generator;
		$this->repository       = $repository;
		$this->audit_repository = $audit_repository;
	}

	/**
	 * Registers authenticated admin routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/briefs/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/briefs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_list' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/briefs/candidates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_candidates' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/briefs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/briefs/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/briefs/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Checks administrative capability.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Generates one or multiple briefs.
	 */
	public function generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_ids = $request->get_param( 'post_ids' );
		$post_id  = absint( $request->get_param( 'post_id' ) );

		if ( ! is_array( $post_ids ) ) {
			$post_ids = $post_id ? array( $post_id ) : array();
		}

		$post_ids = array_values( array_filter( array_unique( array_map( 'absint', $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return new WP_Error( 'ntci_brief_missing_posts', __( 'Chưa chọn bài viết để tạo kế hoạch hình ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 400 ) );
		}

		if ( count( $post_ids ) > 20 ) {
			return new WP_Error( 'ntci_brief_batch_limit', __( 'Mỗi lần chỉ được tạo tối đa 20 brief.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( $this->generator->generate_batch( $post_ids ) );
	}

	/**
	 * Returns paginated briefs.
	 */
	public function get_list( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			$this->repository->get_list(
				array(
					'page'         => absint( $request->get_param( 'page' ) ?: 1 ),
					'per_page'     => absint( $request->get_param( 'per_page' ) ?: 20 ),
					'search'       => sanitize_text_field( (string) $request->get_param( 'search' ) ),
					'status'       => sanitize_key( (string) $request->get_param( 'status' ) ),
					'content_type' => sanitize_key( (string) $request->get_param( 'content_type' ) ),
				)
			)
		);
	}

	/**
	 * Returns high-value audited candidates that do not yet have a current brief.
	 */
	public function get_candidates( WP_REST_Request $request ): WP_REST_Response {
		$limit  = min( 50, max( 5, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$result = $this->audit_repository->get_list(
			array(
				'page'           => 1,
				'per_page'       => $limit,
				'priority_label' => sanitize_key( (string) ( $request->get_param( 'priority_label' ) ?: 'very_high' ) ),
				'content_images' => 'none',
				'audit_status'   => 'scanned',
			)
		);
		$items = array();

		foreach ( $result['items'] as $item ) {
			$latest = $this->repository->get_latest_by_post_id( absint( $item['post_id'] ) );
			$items[] = array(
				'post_id'        => absint( $item['post_id'] ),
				'title'          => sanitize_text_field( (string) $item['title'] ),
				'word_count'     => absint( $item['word_count'] ),
				'priority_score' => absint( $item['priority_score'] ),
				'priority_label' => sanitize_key( (string) $item['priority_label'] ),
				'has_brief'      => null !== $latest && 'outdated' !== (string) $latest['status'],
				'brief_id'       => null !== $latest ? absint( $latest['id'] ) : 0,
			);
		}

		return rest_ensure_response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	/**
	 * Returns one brief.
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$item = $this->repository->get( absint( $request['id'] ) );

		if ( null === $item ) {
			return new WP_Error( 'ntci_brief_not_found', __( 'Không tìm thấy kế hoạch hình ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $item );
	}

	/**
	 * Updates workflow status.
	 */
	public function update_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = absint( $request['id'] );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$item   = $this->repository->get( $id );

		if ( null === $item ) {
			return new WP_Error( 'ntci_brief_not_found', __( 'Không tìm thấy kế hoạch hình ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 404 ) );
		}

		if ( 'approved' === $status && 'invalid' === (string) $item['validation_status'] ) {
			return new WP_Error( 'ntci_brief_invalid', __( 'Không thể duyệt một brief không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 400 ) );
		}

		$updated = $this->repository->update_status( $id, $status );

		if ( false === $updated ) {
			return new WP_Error( 'ntci_brief_status_failed', __( 'Không thể cập nhật trạng thái.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( $this->repository->get( $id ) );
	}

	/**
	 * Returns brief counters.
	 */
	public function get_summary(): WP_REST_Response {
		return rest_ensure_response( $this->repository->get_summary() );
	}
}
