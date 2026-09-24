<?php
/**
 * Secured REST endpoints for audit jobs and reports.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';

	private NT_Content_Images_Audit_Batch_Runner $runner;
	private NT_Content_Images_Audit_Repository $repository;
	private NT_Content_Images_Audit_Query $query;

	/**
	 * Sets REST dependencies.
	 */
	public function __construct(
		NT_Content_Images_Audit_Batch_Runner $runner,
		NT_Content_Images_Audit_Repository $repository,
		NT_Content_Images_Audit_Query $query
	) {
		$this->runner     = $runner;
		$this->repository = $repository;
		$this->query      = $query;
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		$permission = array( $this, 'check_permission' );

		register_rest_route(
			self::NAMESPACE,
			'/audit/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/audit/process',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process' ),
				'permission_callback' => $permission,
			)
		);

		foreach ( array( 'pause', 'resume', 'cancel' ) as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/audit/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action ),
					'permission_callback' => $permission,
				)
			);
		}

		register_rest_route(
			self::NAMESPACE,
			'/audit/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/audit/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'summary' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/audit/posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'posts' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/audit/posts/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'post_detail' ),
				'permission_callback' => $permission,
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ): bool {
							return absint( $value ) > 0;
						},
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/audit/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'config' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Allows administrators only. Cookie-authenticated REST requests also require a valid REST nonce.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Starts an audit job.
	 */
	public function start( WP_REST_Request $request ) {
		$result = $this->runner->start(
			array(
				'post_types'    => (array) $request->get_param( 'post_types' ),
				'post_statuses' => (array) $request->get_param( 'post_statuses' ),
				'batch_size'    => absint( $request->get_param( 'batch_size' ) ),
				'scan_mode'     => sanitize_key( (string) $request->get_param( 'scan_mode' ) ),
			)
		);

		return $this->respond( $result, 201 );
	}

	/**
	 * Processes one batch.
	 */
	public function process() {
		return $this->respond( $this->runner->process() );
	}

	/**
	 * Pauses the current job.
	 */
	public function pause() {
		return $this->respond( $this->runner->pause() );
	}

	/**
	 * Resumes the current job.
	 */
	public function resume() {
		return $this->respond( $this->runner->resume() );
	}

	/**
	 * Cancels the current job.
	 */
	public function cancel() {
		return $this->respond( $this->runner->cancel() );
	}

	/**
	 * Returns current job state.
	 */
	public function status(): WP_REST_Response {
		return new WP_REST_Response( $this->runner->status(), 200 );
	}

	/**
	 * Returns aggregate audit metrics.
	 */
	public function summary(): WP_REST_Response {
		return new WP_REST_Response( $this->repository->get_summary(), 200 );
	}

	/**
	 * Returns filtered audit rows.
	 */
	public function posts( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->repository->get_list( $this->get_list_args( $request ) ), 200 );
	}

	/**
	 * Returns one hydrated audit record.
	 */
	public function post_detail( WP_REST_Request $request ) {
		$record = $this->repository->get_by_post_id( absint( $request['id'] ) );

		if ( null === $record ) {
			return new WP_Error( 'nt_content_images_audit_record_not_found', __( 'Chưa có kết quả audit cho bài viết này.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $record, 200 );
	}

	/**
	 * Returns safe selectable options for the admin screen.
	 */
	public function config(): WP_REST_Response {
		$post_types = array();

		foreach ( $this->query->get_allowed_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( $object ) {
				$post_types[] = array(
					'value' => $post_type,
					'label' => $object->labels->singular_name,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'post_types'    => $post_types,
				'post_statuses' => $this->query->get_allowed_post_statuses(),
				'batch_sizes'   => array( 10, 20, 30, 50 ),
				'scan_modes'    => array( 'new', 'changed', 'all' ),
			),
			200
		);
	}

	/**
	 * Converts WP_Error or data into a REST response.
	 *
	 * @param mixed $result Result.
	 */
	private function respond( $result, int $success_status = 200 ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, $success_status );
	}

	/**
	 * Extracts supported list filters from the request.
	 *
	 * @return array<string, mixed>
	 */
	private function get_list_args( WP_REST_Request $request ): array {
		$keys = array(
			'search', 'post_type', 'post_status', 'priority_label', 'audit_status',
			'featured', 'content_images', 'has_shortcode', 'has_complex_blocks',
			'external_images', 'missing_alt', 'orderby', 'order', 'page', 'per_page',
		);
		$args = array();

		foreach ( $keys as $key ) {
			$args[ $key ] = $request->get_param( $key );
		}

		return $args;
	}
}
