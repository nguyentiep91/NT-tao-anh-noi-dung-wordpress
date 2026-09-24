<?php
/**
 * REST endpoints for overlay templates: config, preview and apply.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Template_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';

	private NT_Content_Images_Overlay_Service $overlay;

	public function __construct( NT_Content_Images_Overlay_Service $overlay ) {
		$this->overlay = $overlay;
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/templates/config', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_config' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route(
			self::NAMESPACE,
			'/generations/(?P<id>\d+)/overlay/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array( 'template' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ) ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/generations/(?P<id>\d+)/overlay',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array( 'template' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ) ),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_config(): WP_REST_Response {
		return rest_ensure_response( $this->overlay->get_status() );
	}

	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->overlay->preview( absint( $request['id'] ), sanitize_key( (string) $request->get_param( 'template' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function apply( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->overlay->apply( absint( $request['id'] ), sanitize_key( (string) $request->get_param( 'template' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
