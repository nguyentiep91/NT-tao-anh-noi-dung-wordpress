<?php
/**
 * Stores stock-source preferences and credentials.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Source_Settings {
	private const OPTION = 'nt_content_images_source_settings';
	private const PEXELS_KEY_OPTION = 'nt_content_images_pexels_api_key';
	private const OPENVERSE_TOKEN_OPTION = 'nt_content_images_openverse_api_token';

	/** @return array<string, mixed> */
	public function get(): array {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$mode = sanitize_key( (string) ( $raw['mode'] ?? 'stock_first' ) );
		if ( ! in_array( $mode, array( 'manual', 'stock_first', 'ai_first', 'stock_only', 'ai_only' ), true ) ) {
			$mode = 'stock_first';
		}

		return array(
			'mode'                    => $mode,
			'default_stock_provider'  => in_array( (string) ( $raw['default_stock_provider'] ?? '' ), array( 'pexels', 'openverse' ), true ) ? (string) $raw['default_stock_provider'] : 'pexels',
			'openverse_license_codes' => array( 'pdm', 'cc0', 'by', 'by-sa' ),
			'search_cache_ttl'        => min( DAY_IN_SECONDS, max( HOUR_IN_SECONDS, absint( $raw['search_cache_ttl'] ?? 6 * HOUR_IN_SECONDS ) ) ),
			'per_page'                => min( 24, max( 6, absint( $raw['per_page'] ?? 12 ) ) ),
		);
	}

	/** @return array<string, mixed> */
	public function get_public(): array {
		$settings = $this->get();
		$settings['providers'] = array(
			'pexels' => array(
				'configured' => '' !== $this->get_api_key( 'pexels' ),
				'key_source' => $this->get_key_source( 'pexels' ),
			),
			'openverse' => array(
				'configured'   => true,
				'authenticated' => '' !== $this->get_api_key( 'openverse' ),
				'key_source'   => $this->get_key_source( 'openverse' ),
			),
		);
		return $settings;
	}

	/** @return array<string, mixed>|WP_Error */
	public function save( array $raw ) {
		$current = $this->get();
		$mode = sanitize_key( (string) ( $raw['mode'] ?? $current['mode'] ) );
		$provider = sanitize_key( (string) ( $raw['default_stock_provider'] ?? $current['default_stock_provider'] ) );
		if ( ! in_array( $mode, array( 'manual', 'stock_first', 'ai_first', 'stock_only', 'ai_only' ), true ) ) {
			return new WP_Error( 'ntci_source_mode_invalid', __( 'Chế độ chọn nguồn ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( $provider, array( 'pexels', 'openverse' ), true ) ) {
			return new WP_Error( 'ntci_stock_provider_invalid', __( 'Kho ảnh mặc định không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		update_option(
			self::OPTION,
			array(
				'mode'                   => $mode,
				'default_stock_provider' => $provider,
				'search_cache_ttl'       => min( DAY_IN_SECONDS, max( HOUR_IN_SECONDS, absint( $raw['search_cache_ttl'] ?? $current['search_cache_ttl'] ) ) ),
				'per_page'               => min( 24, max( 6, absint( $raw['per_page'] ?? $current['per_page'] ) ) ),
			),
			false
		);
		$this->save_key( 'pexels', $raw );
		$this->save_key( 'openverse', $raw );
		return $this->get_public();
	}

	public function get_api_key( string $provider ): string {
		$provider = sanitize_key( $provider );
		if ( 'pexels' === $provider ) {
			if ( defined( 'NT_CONTENT_IMAGES_PEXELS_API_KEY' ) ) {
				return trim( (string) NT_CONTENT_IMAGES_PEXELS_API_KEY );
			}
			return trim( (string) get_option( self::PEXELS_KEY_OPTION, '' ) );
		}
		if ( 'openverse' === $provider ) {
			if ( defined( 'NT_CONTENT_IMAGES_OPENVERSE_API_TOKEN' ) ) {
				return trim( (string) NT_CONTENT_IMAGES_OPENVERSE_API_TOKEN );
			}
			return trim( (string) get_option( self::OPENVERSE_TOKEN_OPTION, '' ) );
		}
		return '';
	}

	public function get_key_source( string $provider ): string {
		$provider = sanitize_key( $provider );
		$constant = 'pexels' === $provider ? 'NT_CONTENT_IMAGES_PEXELS_API_KEY' : 'NT_CONTENT_IMAGES_OPENVERSE_API_TOKEN';
		$option = 'pexels' === $provider ? self::PEXELS_KEY_OPTION : self::OPENVERSE_TOKEN_OPTION;
		if ( defined( $constant ) && '' !== trim( (string) constant( $constant ) ) ) {
			return 'wp-config';
		}
		return '' !== trim( (string) get_option( $option, '' ) ) ? 'database' : 'missing';
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
		delete_option( self::PEXELS_KEY_OPTION );
		delete_option( self::OPENVERSE_TOKEN_OPTION );
	}

	/** @param array<string, mixed> $raw Raw settings. */
	private function save_key( string $provider, array $raw ): void {
		$constant = 'pexels' === $provider ? 'NT_CONTENT_IMAGES_PEXELS_API_KEY' : 'NT_CONTENT_IMAGES_OPENVERSE_API_TOKEN';
		$option = 'pexels' === $provider ? self::PEXELS_KEY_OPTION : self::OPENVERSE_TOKEN_OPTION;
		$field = $provider . '_api_key';
		$clear = 'clear_' . $provider . '_api_key';
		if ( defined( $constant ) ) {
			return;
		}
		if ( ! empty( $raw[ $clear ] ) ) {
			delete_option( $option );
			return;
		}
		$key = trim( (string) ( $raw[ $field ] ?? '' ) );
		if ( '' !== $key && strlen( $key ) >= 10 && strlen( $key ) <= 1024 && ! preg_match( '/\s/', $key ) ) {
			update_option( $option, sanitize_text_field( $key ), false );
		}
	}
}
