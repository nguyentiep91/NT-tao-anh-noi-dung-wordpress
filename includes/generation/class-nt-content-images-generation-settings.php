<?php
/**
 * Stores non-secret image generation settings and resolves the OpenAI API key.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Settings {
	private const OPTION = 'nt_content_images_generation_settings';
	private const KEY_OPTION = 'nt_content_images_openai_api_key';

	/** @return array<string, mixed> */
	public function get(): array {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'provider'      => 'openai',
			'model'         => in_array( (string) ( $raw['model'] ?? '' ), array( 'gpt-image-1', 'gpt-image-1-mini' ), true ) ? (string) $raw['model'] : 'gpt-image-1-mini',
			'quality'       => in_array( (string) ( $raw['quality'] ?? '' ), array( 'low', 'medium', 'high', 'auto' ), true ) ? (string) $raw['quality'] : 'medium',
			'size'          => '1536x1024',
			'output_format' => 'webp',
			'background'    => 'opaque',
			'target_width'  => 1280,
			'target_height' => 720,
			'timeout'       => min( 240, max( 60, absint( $raw['timeout'] ?? 180 ) ) ),
		);
	}

	/** @return array<string, mixed> */
	public function get_public(): array {
		$settings = $this->get();
		$settings['configured'] = '' !== $this->get_api_key();
		$settings['key_source'] = $this->get_key_source();

		return $settings;
	}

	/**
	 * Saves allow-listed settings. A blank key preserves the existing key.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save( array $raw ) {
		$current = $this->get();
		$model   = sanitize_key( (string) ( $raw['model'] ?? $current['model'] ) );
		$quality = sanitize_key( (string) ( $raw['quality'] ?? $current['quality'] ) );
		$timeout = min( 240, max( 60, absint( $raw['timeout'] ?? $current['timeout'] ) ) );

		if ( ! in_array( $model, array( 'gpt-image-1', 'gpt-image-1-mini' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_model', __( 'Model tạo ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( $quality, array( 'low', 'medium', 'high', 'auto' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_quality', __( 'Chất lượng ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		update_option(
			self::OPTION,
			array(
				'model'   => $model,
				'quality' => $quality,
				'timeout' => $timeout,
			),
			false
		);

		if ( ! defined( 'NT_CONTENT_IMAGES_OPENAI_API_KEY' ) ) {
			if ( ! empty( $raw['clear_api_key'] ) ) {
				delete_option( self::KEY_OPTION );
			} else {
				$key = trim( (string) ( $raw['api_key'] ?? '' ) );
				if ( '' !== $key ) {
					if ( strlen( $key ) < 20 || preg_match( '/\s/', $key ) ) {
						return new WP_Error( 'ntci_generation_invalid_key', __( 'API key không đúng định dạng.', 'nt-tao-anh-noi-dung-wordpress' ) );
					}
					update_option( self::KEY_OPTION, sanitize_text_field( $key ), false );
				}
			}
		}

		return $this->get_public();
	}

	public function get_api_key(): string {
		if ( defined( 'NT_CONTENT_IMAGES_OPENAI_API_KEY' ) ) {
			return trim( (string) NT_CONTENT_IMAGES_OPENAI_API_KEY );
		}

		return trim( (string) get_option( self::KEY_OPTION, '' ) );
	}

	public function get_key_source(): string {
		if ( defined( 'NT_CONTENT_IMAGES_OPENAI_API_KEY' ) && '' !== trim( (string) NT_CONTENT_IMAGES_OPENAI_API_KEY ) ) {
			return 'wp-config';
		}

		return '' !== trim( (string) get_option( self::KEY_OPTION, '' ) ) ? 'database' : 'missing';
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
		delete_option( self::KEY_OPTION );
	}
}
