<?php
/**
 * REST endpoints for the batch generation queue.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Queue_REST_Controller {
	private const NAMESPACE = 'nt-content-images/v1';

	private NT_Content_Images_Generation_Queue $queue;

	public function __construct( NT_Content_Images_Generation_Queue $queue ) {
		$this->queue = $queue;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/queue/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'include_featured' => array( 'required' => false ),
					'include_content'  => array( 'required' => false ),
					'auto_insert'      => array( 'required' => false ),
					'limit'            => array( 'required' => false, 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route( self::NAMESPACE, '/queue/status', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'status' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/queue/step', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'step' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/queue/pause', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'pause' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/queue/resume', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'resume' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/queue/cancel', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'cancel' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function start( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->queue->start(
			array(
				'include_featured' => rest_sanitize_boolean( $request->get_param( 'include_featured' ) ),
				'include_content'  => rest_sanitize_boolean( $request->get_param( 'include_content' ) ),
				'auto_insert'      => rest_sanitize_boolean( $request->get_param( 'auto_insert' ) ),
				'limit'            => absint( $request->get_param( 'limit' ) ?: 10 ),
			)
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function status(): WP_REST_Response {
		return rest_ensure_response( $this->queue->status() );
	}

	public function step(): WP_REST_Response|WP_Error {
		$result = $this->queue->step();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function pause(): WP_REST_Response|WP_Error {
		$result = $this->queue->set_status( 'paused' );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function resume(): WP_REST_Response|WP_Error {
		$result = $this->queue->set_status( 'running' );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function cancel(): WP_REST_Response|WP_Error {
		$result = $this->queue->set_status( 'cancelled' );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
