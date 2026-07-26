<?php
/**
 * Stores image-generation settings and resolves provider credentials.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Settings {
	private const OPTION = 'nt_content_images_generation_settings';
	private const OPENAI_KEY_OPTION = 'nt_content_images_openai_api_key';
	private const OPENROUTER_KEY_OPTION = 'nt_content_images_openrouter_api_key';

	/** @return array<string, mixed> */
	public function get(): array {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$provider = in_array( (string) ( $raw['provider'] ?? '' ), array( 'openai', 'openrouter' ), true ) ? (string) $raw['provider'] : 'openai';
		$openai_model = in_array( (string) ( $raw['openai_model'] ?? $raw['model'] ?? '' ), array( 'gpt-image-1', 'gpt-image-1-mini' ), true ) ? (string) ( $raw['openai_model'] ?? $raw['model'] ) : 'gpt-image-1-mini';
		$openrouter_model = $this->sanitize_openrouter_model( (string) ( $raw['openrouter_model'] ?? '' ) );

		return array(
			'provider'         => $provider,
			'model'            => 'openrouter' === $provider ? $openrouter_model : $openai_model,
			'openai_model'     => $openai_model,
			'openrouter_model' => $openrouter_model,
			'quality'          => in_array( (string) ( $raw['quality'] ?? '' ), array( 'low', 'medium', 'high', 'auto' ), true ) ? (string) $raw['quality'] : 'medium',
			'size'             => '1536x1024',
			'output_format'    => 'webp',
			'background'       => 'opaque',
			'target_width'     => 1280,
			'target_height'    => 720,
			'timeout'          => min( 300, max( 60, absint( $raw['timeout'] ?? 180 ) ) ),
		);
	}

	/** @return array<string, mixed> */
	public function get_public(): array {
		$settings = $this->get();
		$settings['providers'] = array(
			'openai' => array(
				'configured' => '' !== $this->get_api_key( 'openai' ),
				'key_source' => $this->get_key_source( 'openai' ),
			),
			'openrouter' => array(
				'configured' => '' !== $this->get_api_key( 'openrouter' ),
				'key_source' => $this->get_key_source( 'openrouter' ),
			),
		);
		$settings['configured'] = '' !== $this->get_api_key( (string) $settings['provider'] );
		$settings['key_source'] = $this->get_key_source( (string) $settings['provider'] );
		return $settings;
	}

	/**
	 * Saves allow-listed settings. Blank keys preserve existing credentials.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save( array $raw ) {
		$current = $this->get();
		$provider = sanitize_key( (string) ( $raw['provider'] ?? $current['provider'] ) );
		$openai_model = sanitize_key( (string) ( $raw['openai_model'] ?? $current['openai_model'] ) );
		$openrouter_model = $this->sanitize_openrouter_model( (string) ( $raw['openrouter_model'] ?? $current['openrouter_model'] ) );
		$quality = sanitize_key( (string) ( $raw['quality'] ?? $current['quality'] ) );
		$timeout = min( 300, max( 60, absint( $raw['timeout'] ?? $current['timeout'] ) ) );

		if ( ! in_array( $provider, array( 'openai', 'openrouter' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_provider', __( 'Nhà cung cấp tạo ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( $openai_model, array( 'gpt-image-1', 'gpt-image-1-mini' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_model', __( 'Model OpenAI không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( 'openrouter' === $provider && '' === $openrouter_model ) {
			return new WP_Error( 'ntci_generation_openrouter_model_missing', __( 'Hãy chọn hoặc nhập model tạo ảnh của OpenRouter.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( $quality, array( 'low', 'medium', 'high', 'auto' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_quality', __( 'Chất lượng ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		update_option(
			self::OPTION,
			array(
				'provider'         => $provider,
				'openai_model'     => $openai_model,
				'openrouter_model' => $openrouter_model,
				'quality'          => $quality,
				'timeout'          => $timeout,
			),
			false
		);

		$this->save_key( 'openai', $raw );
		$this->save_key( 'openrouter', $raw );
		return $this->get_public();
	}

	public function get_api_key( string $provider = '' ): string {
		$provider = '' !== $provider ? sanitize_key( $provider ) : sanitize_key( (string) $this->get()['provider'] );
		if ( 'openrouter' === $provider ) {
			if ( defined( 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY' ) ) {
				return trim( (string) NT_CONTENT_IMAGES_OPENROUTER_API_KEY );
			}
			return trim( (string) get_option( self::OPENROUTER_KEY_OPTION, '' ) );
		}
		if ( defined( 'NT_CONTENT_IMAGES_OPENAI_API_KEY' ) ) {
			return trim( (string) NT_CONTENT_IMAGES_OPENAI_API_KEY );
		}
		return trim( (string) get_option( self::OPENAI_KEY_OPTION, '' ) );
	}

	public function get_key_source( string $provider = '' ): string {
		$provider = '' !== $provider ? sanitize_key( $provider ) : sanitize_key( (string) $this->get()['provider'] );
		$constant = 'openrouter' === $provider ? 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY' : 'NT_CONTENT_IMAGES_OPENAI_API_KEY';
		$option = 'openrouter' === $provider ? self::OPENROUTER_KEY_OPTION : self::OPENAI_KEY_OPTION;
		if ( defined( $constant ) && '' !== trim( (string) constant( $constant ) ) ) {
			return 'wp-config';
		}
		return '' !== trim( (string) get_option( $option, '' ) ) ? 'database' : 'missing';
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
		delete_option( self::OPENAI_KEY_OPTION );
		delete_option( self::OPENROUTER_KEY_OPTION );
	}

	/** @param array<string, mixed> $raw Raw settings. */
	private function save_key( string $provider, array $raw ): void {
		$constant = 'openrouter' === $provider ? 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY' : 'NT_CONTENT_IMAGES_OPENAI_API_KEY';
		$option = 'openrouter' === $provider ? self::OPENROUTER_KEY_OPTION : self::OPENAI_KEY_OPTION;
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
		if ( '' !== $key && strlen( $key ) >= 20 && ! preg_match( '/\s/', $key ) ) {
			update_option( $option, sanitize_text_field( $key ), false );
		}
	}

	private function sanitize_openrouter_model( string $model ): string {
		$model = trim( $model );
		if ( '' === $model || strlen( $model ) > 180 || ! preg_match( '/^[A-Za-z0-9._:-]+\/[A-Za-z0-9._:-]+$/', $model ) ) {
			return '';
		}
		return sanitize_text_field( $model );
	}
}
