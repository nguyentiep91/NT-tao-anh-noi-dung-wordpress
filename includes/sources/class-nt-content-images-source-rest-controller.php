<?php
/**
 * REST endpoints for affordable stock image sources.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Source_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';
	private NT_Content_Images_Asset_Service $service;
	private NT_Content_Images_Asset_Repository $assets;
	private NT_Content_Images_Source_Settings $settings;
	private NT_Content_Images_Stock_Provider_Manager $providers;

	public function __construct(
		NT_Content_Images_Asset_Service $service,
		NT_Content_Images_Asset_Repository $assets,
		NT_Content_Images_Source_Settings $settings,
		NT_Content_Images_Stock_Provider_Manager $providers
	) {
		$this->service = $service;
		$this->assets = $assets;
		$this->settings = $settings;
		$this->providers = $providers;
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/sources/config', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_config' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route(
			self::NAMESPACE,
			'/sources/search',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args' => array(
					'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'provider' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
					'query' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
					'page' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/sources/import',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args' => array(
					'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'provider' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
					'asset_id' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'search_query' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
					'license_confirmed' => array( 'required' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
				),
			)
		);
		register_rest_route( self::NAMESPACE, '/sources/assets', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_assets' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_config(): WP_REST_Response {
		$config = $this->settings->get_public();
		$config['provider_registry'] = $this->providers->get_public();
		return rest_ensure_response( $config );
	}

	public function search( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->service->search(
			absint( $request->get_param( 'post_id' ) ),
			sanitize_key( (string) $request->get_param( 'provider' ) ),
			sanitize_text_field( (string) $request->get_param( 'query' ) ),
			max( 1, absint( $request->get_param( 'page' ) ?: 1 ) )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->service->import(
			absint( $request->get_param( 'post_id' ) ),
			sanitize_key( (string) $request->get_param( 'provider' ) ),
			sanitize_text_field( (string) $request->get_param( 'asset_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'search_query' ) ),
			rest_sanitize_boolean( $request->get_param( 'license_confirmed' ) )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_assets( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->assets->get_list( array( 'limit' => min( 50, max( 5, absint( $request->get_param( 'limit' ) ?: 20 ) ) ) ) ) );
	}
}
