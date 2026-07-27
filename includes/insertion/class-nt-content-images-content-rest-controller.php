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

	public function __construct(
		NT_Content_Images_Content_Image_Generator $generator,
		NT_Content_Images_Content_Inserter $inserter,
		NT_Content_Images_Audit_Repository $audits,
		NT_Content_Images_Profile_Repository $profiles
	) {
		$this->generator = $generator;
		$this->inserter  = $inserter;
		$this->audits    = $audits;
		$this->profiles  = $profiles;
	}

	public function register_routes(): void {
		$post_arg = array( 'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) );
		register_rest_route( self::NAMESPACE, '/content-images/candidates', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_candidates' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/content-images/plan', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_plan' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
		register_rest_route( self::NAMESPACE, '/content-images/generate', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'generate' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg + array( 'index' => array( 'required' => false, 'sanitize_callback' => 'absint' ) ) ) );
		register_rest_route( self::NAMESPACE, '/content-images/insert', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'insert' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
		register_rest_route( self::NAMESPACE, '/content-images/rollback', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rollback' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $post_arg ) );
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
				'planned_images' => $word_count > 3000 ? 3 : ( $word_count >= 1500 ? 2 : 1 ),
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
}
