<?php
/**
 * Stores Canva Connect integration credentials and OAuth tokens.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Canva_Settings {
	private const CREDENTIALS_OPTION = 'nt_content_images_canva_credentials';
	private const TOKENS_OPTION = 'nt_content_images_canva_tokens';

	public function get_client_id(): string {
		if ( defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_ID' ) ) {
			return trim( (string) NT_CONTENT_IMAGES_CANVA_CLIENT_ID );
		}
		$raw = get_option( self::CREDENTIALS_OPTION, array() );
		return is_array( $raw ) ? trim( (string) ( $raw['client_id'] ?? '' ) ) : '';
	}

	public function get_client_secret(): string {
		if ( defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_SECRET' ) ) {
			return trim( (string) NT_CONTENT_IMAGES_CANVA_CLIENT_SECRET );
		}
		$raw = get_option( self::CREDENTIALS_OPTION, array() );
		return is_array( $raw ) ? trim( (string) ( $raw['client_secret'] ?? '' ) ) : '';
	}

	public function get_redirect_uri(): string {
		if ( defined( 'NT_CONTENT_IMAGES_CANVA_REDIRECT_URI' ) && '' !== trim( (string) NT_CONTENT_IMAGES_CANVA_REDIRECT_URI ) ) {
			return esc_url_raw( (string) NT_CONTENT_IMAGES_CANVA_REDIRECT_URI );
		}
		return admin_url( 'admin-post.php?action=nt_content_images_canva_callback' );
	}

	/** @return array<string, mixed> */
	public function get_tokens(): array {
		$tokens = get_option( self::TOKENS_OPTION, array() );
		return is_array( $tokens ) ? $tokens : array();
	}

	/** @param array<string, mixed> $tokens OAuth token response. */
	public function save_tokens( array $tokens ): void {
		$current = $this->get_tokens();
		$access_token = trim( (string) ( $tokens['access_token'] ?? '' ) );
		$refresh_token = trim( (string) ( $tokens['refresh_token'] ?? $current['refresh_token'] ?? '' ) );
		if ( '' === $access_token ) {
			return;
		}
		update_option(
			self::TOKENS_OPTION,
			array(
				'access_token'  => $access_token,
				'refresh_token' => $refresh_token,
				'expires_at'    => time() + max( 300, absint( $tokens['expires_in'] ?? 14400 ) ),
				'scope'         => sanitize_text_field( (string) ( $tokens['scope'] ?? $current['scope'] ?? '' ) ),
			),
			false
		);
	}

	/** @return array<string, mixed>|WP_Error */
	public function save_credentials( array $raw ) {
		$client_id = trim( (string) ( $raw['client_id'] ?? '' ) );
		$client_secret = trim( (string) ( $raw['client_secret'] ?? '' ) );
		$current = get_option( self::CREDENTIALS_OPTION, array() );
		$current = is_array( $current ) ? $current : array();

		if ( ! defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_ID' ) && '' !== $client_id ) {
			if ( strlen( $client_id ) > 255 || preg_match( '/\s/', $client_id ) ) {
				return new WP_Error( 'ntci_canva_client_id_invalid', __( 'Canva Client ID không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
			}
			$current['client_id'] = sanitize_text_field( $client_id );
		}
		if ( ! defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_SECRET' ) && '' !== $client_secret ) {
			if ( strlen( $client_secret ) < 10 || strlen( $client_secret ) > 512 || preg_match( '/\s/', $client_secret ) ) {
				return new WP_Error( 'ntci_canva_client_secret_invalid', __( 'Canva Client Secret không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
			}
			$current['client_secret'] = sanitize_text_field( $client_secret );
		}
		if ( ! empty( $raw['clear_credentials'] ) && ! defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_ID' ) && ! defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_SECRET' ) ) {
			delete_option( self::CREDENTIALS_OPTION );
			$this->clear_tokens();
			return $this->get_public();
		}
		update_option( self::CREDENTIALS_OPTION, $current, false );
		return $this->get_public();
	}

	public function has_credentials(): bool {
		return '' !== $this->get_client_id() && '' !== $this->get_client_secret();
	}

	public function is_connected(): bool {
		$tokens = $this->get_tokens();
		return '' !== trim( (string) ( $tokens['refresh_token'] ?? $tokens['access_token'] ?? '' ) );
	}

	/** @return array<string, mixed> */
	public function get_public(): array {
		$tokens = $this->get_tokens();
		$credential_source = ( defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_ID' ) || defined( 'NT_CONTENT_IMAGES_CANVA_CLIENT_SECRET' ) ) ? 'wp-config' : ( $this->has_credentials() ? 'database' : 'missing' );
		return array(
			'credentials_configured' => $this->has_credentials(),
			'credential_source'       => $credential_source,
			'connected'               => $this->is_connected(),
			'expires_at'              => absint( $tokens['expires_at'] ?? 0 ),
			'scope'                   => sanitize_text_field( (string) ( $tokens['scope'] ?? '' ) ),
			'redirect_uri'            => $this->get_redirect_uri(),
		);
	}

	public function clear_tokens(): void {
		delete_option( self::TOKENS_OPTION );
	}

	public static function delete_options(): void {
		delete_option( self::CREDENTIALS_OPTION );
		delete_option( self::TOKENS_OPTION );
	}
}
