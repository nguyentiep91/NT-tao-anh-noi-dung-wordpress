<?php
/**
 * REST endpoints for AI featured-image generation, providers and Canva.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';

	private NT_Content_Images_Featured_Image_Generator $generator;
	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Audit_Repository $audits;
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Image_Provider_Manager $providers;
	private NT_Content_Images_Canva_Settings $canva_settings;
	private NT_Content_Images_Canva_Design_Service $canva_designs;

	public function __construct(
		NT_Content_Images_Featured_Image_Generator $generator,
		NT_Content_Images_Generation_Repository $repository,
		NT_Content_Images_Generation_Settings $settings,
		NT_Content_Images_Audit_Repository $audits,
		NT_Content_Images_Profile_Repository $profiles,
		NT_Content_Images_Image_Provider_Manager $providers,
		NT_Content_Images_Canva_Settings $canva_settings,
		NT_Content_Images_Canva_Design_Service $canva_designs
	) {
		$this->generator      = $generator;
		$this->repository     = $repository;
		$this->settings       = $settings;
		$this->audits         = $audits;
		$this->profiles       = $profiles;
		$this->providers      = $providers;
		$this->canva_settings = $canva_settings;
		$this->canva_designs  = $canva_designs;
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/generations/featured', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'generate_featured' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) ) ) );
		register_rest_route( self::NAMESPACE, '/generations', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_list' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/candidates', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_candidates' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/config', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_config' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/providers/(?P<provider>[a-z0-9_-]+)/models', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_provider_models' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_item' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/(?P<id>\d+)/approve', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'approve' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/(?P<id>\d+)/reject', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reject' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/canva/status', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_canva_status' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/(?P<id>\d+)/canva/design', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_canva_design' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/generations/(?P<id>\d+)/canva/import', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'import_canva_design' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function generate_featured( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->generator->generate( absint( $request->get_param( 'post_id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_list( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->repository->get_list( array( 'page' => absint( $request->get_param( 'page' ) ?: 1 ), 'per_page' => absint( $request->get_param( 'per_page' ) ?: 20 ), 'status' => sanitize_key( (string) $request->get_param( 'status' ) ), 'search' => sanitize_text_field( (string) $request->get_param( 'search' ) ) ) ) );
	}

	public function get_candidates( WP_REST_Request $request ): WP_REST_Response {
		$limit   = min( 50, max( 5, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$profile = $this->profiles->get_site_profile();
		$result  = $this->audits->get_list( array( 'page' => 1, 'per_page' => $limit * 3, 'audit_status' => 'scanned', 'featured' => 'missing', 'orderby' => 'priority_score', 'order' => 'DESC' ) );
		$items = array();
		foreach ( $result['items'] as $item ) {
			$post_type = sanitize_key( (string) ( $item['post_type'] ?? '' ) );
			if ( ! in_array( $post_type, (array) ( $profile['enabled_post_types'] ?? array() ), true ) ) {
				continue;
			}
			$post_id = absint( $item['post_id'] );
			if ( get_post_thumbnail_id( $post_id ) ) {
				continue;
			}
			$items[] = array(
				'post_id'        => $post_id,
				'title'          => sanitize_text_field( (string) $item['title'] ),
				'post_type'      => $post_type,
				'word_count'     => absint( $item['word_count'] ?? 0 ),
				'priority_score' => absint( $item['priority_score'] ?? 0 ),
				'priority_label' => sanitize_key( (string) ( $item['priority_label'] ?? '' ) ),
				'edit_url'       => esc_url_raw( (string) ( $item['edit_url'] ?? '' ) ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		return rest_ensure_response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	public function get_config(): WP_REST_Response {
		$config = $this->settings->get_public();
		$config['provider_registry'] = $this->providers->get_public();
		$config['canva'] = $this->canva_settings->get_public();
		return rest_ensure_response( $config );
	}

	public function get_provider_models( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->providers->list_models( sanitize_key( (string) $request['provider'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'items' => $result ) );
	}

	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$item = $this->repository->get( absint( $request['id'] ) );
		return null === $item ? new WP_Error( 'ntci_generation_not_found', __( 'Không tìm thấy ảnh đã tạo.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 404 ) ) : rest_ensure_response( $item );
	}

	public function approve( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->generator->approve( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function reject( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->generator->reject( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_canva_status(): WP_REST_Response {
		return rest_ensure_response( $this->canva_settings->get_public() );
	}

	public function create_canva_design( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->canva_designs->create_design( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function import_canva_design( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->canva_designs->import_design( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
