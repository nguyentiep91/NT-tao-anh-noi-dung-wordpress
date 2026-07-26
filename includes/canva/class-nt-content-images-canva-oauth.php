<?php
/**
 * Canva OAuth 2.0 Authorization Code + PKCE flow.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Canva_OAuth {
	private const AUTHORIZE_ENDPOINT = 'https://www.canva.com/api/oauth/authorize';
	private const TOKEN_ENDPOINT = 'https://api.canva.com/rest/v1/oauth/token';
	private const SCOPES = 'asset:read asset:write design:content:read design:content:write design:meta:read';

	private NT_Content_Images_Canva_Settings $settings;

	public function __construct( NT_Content_Images_Canva_Settings $settings ) {
		$this->settings = $settings;
	}

	/** @return string|WP_Error */
	public function get_authorization_url( int $user_id ) {
		if ( ! $this->settings->has_credentials() ) {
			return new WP_Error( 'ntci_canva_credentials_missing', __( 'Chưa cấu hình Canva Client ID và Client Secret.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$verifier = $this->base64url( random_bytes( 64 ) );
		$challenge = $this->base64url( hash( 'sha256', $verifier, true ) );
		$state = $this->base64url( random_bytes( 48 ) );
		set_transient(
			'ntci_canva_oauth_' . md5( $state ),
			array(
				'user_id'       => absint( $user_id ),
				'code_verifier' => $verifier,
				'created_at'    => time(),
			),
			15 * MINUTE_IN_SECONDS
		);
		return add_query_arg(
			array(
				'code_challenge'        => $challenge,
				'code_challenge_method' => 's256',
				'scope'                 => self::SCOPES,
				'response_type'         => 'code',
				'client_id'             => $this->settings->get_client_id(),
				'redirect_uri'          => $this->settings->get_redirect_uri(),
				'state'                 => $state,
			),
			self::AUTHORIZE_ENDPOINT
		);
	}

	/** @return true|WP_Error */
	public function handle_callback( string $code, string $state, int $user_id ) {
		$state = trim( $state );
		$flow = get_transient( 'ntci_canva_oauth_' . md5( $state ) );
		delete_transient( 'ntci_canva_oauth_' . md5( $state ) );
		if ( '' === $state || ! is_array( $flow ) || absint( $flow['user_id'] ?? 0 ) !== absint( $user_id ) ) {
			return new WP_Error( 'ntci_canva_oauth_state_invalid', __( 'Phiên kết nối Canva không hợp lệ hoặc đã hết hạn.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$result = $this->token_request(
			array(
				'grant_type'    => 'authorization_code',
				'code_verifier' => (string) $flow['code_verifier'],
				'code'          => trim( $code ),
				'redirect_uri'  => $this->settings->get_redirect_uri(),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->settings->save_tokens( $result );
		return true;
	}

	/** @return string|WP_Error */
	public function get_access_token() {
		$tokens = $this->settings->get_tokens();
		$access = trim( (string) ( $tokens['access_token'] ?? '' ) );
		$expires_at = absint( $tokens['expires_at'] ?? 0 );
		if ( '' !== $access && $expires_at > time() + 120 ) {
			return $access;
		}
		$refresh = trim( (string) ( $tokens['refresh_token'] ?? '' ) );
		if ( '' === $refresh ) {
			return new WP_Error( 'ntci_canva_not_connected', __( 'Tài khoản Canva chưa được kết nối.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$result = $this->token_request(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->settings->save_tokens( $result );
		return trim( (string) $result['access_token'] );
	}

	/** @param array<string, string> $body @return array<string, mixed>|WP_Error */
	private function token_request( array $body ) {
		$credentials = base64_encode( $this->settings->get_client_id() . ':' . $this->settings->get_client_secret() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout'     => 60,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Basic ' . $credentials,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'        => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_canva_token_transport', $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$message = is_array( $data ) ? (string) ( $data['message'] ?? $data['error_description'] ?? $data['error'] ?? '' ) : '';
			return new WP_Error( 'ntci_canva_token_failed', '' !== $message ? sanitize_text_field( $message ) : __( 'Canva không thể cấp access token.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		return $data;
	}

	private function base64url( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
