<?php
/**
 * REST endpoints for planned in-article images: plan, generate, insert, rollback.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';

	private NT_Content_Images_Content_Image_Generator $generator;
	private NT_Content_Images_Content_Inserter $inserter;
	private NT_Content_Images_Audit_Repository $audits;
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Media_Cleanup $cleanup;
	private NT_Content_Images_Plan_Settings $plan_settings;

	public function __construct(
		NT_Content_Images_Content_Image_Generator $generator,
		NT_Content_Images_Content_Inserter $inserter,
		NT_Content_Images_Audit_Repository $audits,
		NT_Content_Images_Profile_Repository $profiles,
		NT_Content_Images_Media_Cleanup $cleanup,
		?NT_Content_Images_Plan_Settings $plan_settings = null
	) {
		$this->generator     = $generator;
		$this->inserter      = $inserter;
		$this->audits        = $audits;
		$this->profiles      = $profiles;
		$this->cleanup       = $cleanup;
		$this->plan_settings = $plan_settings ?? new NT_Content_Images_Plan_Settings();
	}

	public function register_routes(): void {
		$post_arg = array( 'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) );
		register_rest_route( self::NAMESPACE, '/content-images/candidates', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_candidates' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/content-images/plan', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_plan' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
		register_rest_route( self::NAMESPACE, '/content-images/generate', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'generate' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg + array( 'index' => array( 'required' => false, 'sanitize_callback' => 'absint' ) ) ) );
		register_rest_route( self::NAMESPACE, '/content-images/insert', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'insert' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
		register_rest_route( self::NAMESPACE, '/content-images/rollback', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rollback' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
		register_rest_route( self::NAMESPACE, '/content-images/cleanup', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'cleanup' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/content-images/plan-override', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'plan_override' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/** Audited posts ordered by length — the ones worth illustrating first. */
	public function get_candidates( WP_REST_Request $request ): WP_REST_Response {
		$limit   = min( 50, max( 5, absint( $request->get_param( 'limit' ) ?: 30 ) ) );
		$profile = $this->profiles->get_site_profile();
		$result  = $this->audits->get_list( array( 'page' => 1, 'per_page' => $limit * 2, 'audit_status' => 'scanned', 'orderby' => 'word_count', 'order' => 'DESC' ) );
		$items   = array();
		foreach ( $result['items'] as $item ) {
			$post_type = sanitize_key( (string) ( $item['post_type'] ?? '' ) );
			if ( ! in_array( $post_type, (array) ( $profile['enabled_post_types'] ?? array() ), true ) ) {
				continue;
			}
			$word_count = absint( $item['word_count'] ?? 0 );
			$items[] = array(
				'post_id'        => absint( $item['post_id'] ),
				'title'          => sanitize_text_field( (string) $item['title'] ),
				'post_type'      => $post_type,
				'word_count'     => $word_count,
				'planned_images' => $this->plan_settings->image_count( $word_count, '' ),
				'content_images' => absint( $item['content_image_count'] ?? 0 ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		return rest_ensure_response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	public function get_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->generator->get_plan( absint( $request->get_param( 'post_id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->generator->generate_set( absint( $request->get_param( 'post_id' ) ), absint( $request->get_param( 'index' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function insert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->inserter->insert( absint( $request->get_param( 'post_id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rollback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->inserter->rollback( absint( $request->get_param( 'post_id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Deletes every plugin image that is not used as thumbnail or inside content. */
	public function cleanup( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->cleanup->sweep() );
	}

	/**
	 * Applies one per-post plan customization then returns the refreshed plan.
	 *
	 * Body: post_id + một trong các thao tác:
	 * - reset=true                     → xoá mọi tuỳ chỉnh
	 * - add_heading="..."             → thêm vị trí ảnh tại heading
	 * - remove_extra=<index>           → xoá vị trí do người dùng thêm
	 * - index + enabled/heading_text/custom_scene → sửa một vị trí
	 */
	public function plan_override( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! get_post( $post_id ) instanceof WP_Post ) {
			return new WP_Error( 'ntci_plan_post_missing', __( 'Không tìm thấy bài viết.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$headings = array_column( NT_Content_Images_Plan_Overrides::list_headings( (string) get_post_field( 'post_content', $post_id ) ), 'text' );

		if ( rest_sanitize_boolean( $request->get_param( 'reset' ) ) ) {
			NT_Content_Images_Plan_Overrides::reset( $post_id );
			return $this->respond_with_plan( $post_id );
		}

		$add_heading = sanitize_text_field( (string) $request->get_param( 'add_heading' ) );
		if ( '' !== $add_heading ) {
			if ( ! in_array( $add_heading, $headings, true ) ) {
				return new WP_Error( 'ntci_plan_heading_unknown', __( 'Mục này không có trong nội dung bài viết.', 'nt-tao-anh-noi-dung-wordpress' ) );
			}
			$plan = $this->generator->get_plan( $post_id );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			$enabled_total = count( array_filter( $plan['slots'], static fn ( array $slot ): bool => ! empty( $slot['enabled'] ) ) );
			$added         = NT_Content_Images_Plan_Overrides::add_extra( $post_id, $add_heading, $enabled_total );
			if ( is_wp_error( $added ) ) {
				return $added;
			}
			return $this->respond_with_plan( $post_id );
		}

		$remove_extra = absint( $request->get_param( 'remove_extra' ) );
		if ( $remove_extra > 0 ) {
			NT_Content_Images_Plan_Overrides::remove_extra( $post_id, $remove_extra );
			return $this->respond_with_plan( $post_id );
		}

		$index = absint( $request->get_param( 'index' ) );
		if ( $index < 1 ) {
			return new WP_Error( 'ntci_plan_index_invalid', __( 'Vị trí ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$patch = array();
		if ( null !== $request->get_param( 'enabled' ) ) {
			$patch['enabled'] = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		}
		if ( null !== $request->get_param( 'heading_text' ) ) {
			$heading = sanitize_text_field( (string) $request->get_param( 'heading_text' ) );
			if ( '' !== $heading && ! in_array( $heading, $headings, true ) ) {
				return new WP_Error( 'ntci_plan_heading_unknown', __( 'Mục này không có trong nội dung bài viết.', 'nt-tao-anh-noi-dung-wordpress' ) );
			}
			$patch['heading_text'] = $heading;
		}
		if ( null !== $request->get_param( 'custom_scene' ) ) {
			$patch['custom_scene'] = sanitize_textarea_field( (string) $request->get_param( 'custom_scene' ) );
		}
		if ( array() === $patch ) {
			return new WP_Error( 'ntci_plan_patch_empty', __( 'Không có thay đổi nào để lưu.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		NT_Content_Images_Plan_Overrides::update_slot( $post_id, $index, $patch );
		return $this->respond_with_plan( $post_id );
	}

	private function respond_with_plan( int $post_id ): WP_REST_Response|WP_Error {
		$plan = $this->generator->get_plan( $post_id );
		return is_wp_error( $plan ) ? $plan : rest_ensure_response( $plan );
	}
}
