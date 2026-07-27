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
	private const CLOUDFLARE_TOKEN_OPTION = 'nt_content_images_cloudflare_api_token';
	private const CLOUDFLARE_ACCOUNT_OPTION = 'nt_content_images_cloudflare_account_id';
	private const FAL_KEY_OPTION = 'nt_content_images_fal_api_key';

	/** @return array<string, mixed> */
	public function get(): array {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$providers = array( 'openai', 'openrouter', 'cloudflare', 'fal' );
		$provider = in_array( (string) ( $raw['provider'] ?? '' ), $providers, true ) ? (string) $raw['provider'] : 'openai';
		$openai_model = in_array( (string) ( $raw['openai_model'] ?? $raw['model'] ?? '' ), array( 'gpt-image-1', 'gpt-image-1-mini' ), true ) ? (string) ( $raw['openai_model'] ?? $raw['model'] ) : 'gpt-image-1-mini';
		$openrouter_model = $this->sanitize_openrouter_model( (string) ( $raw['openrouter_model'] ?? '' ) );
		$model = match ( $provider ) {
			'openrouter' => $openrouter_model,
			'cloudflare' => '@cf/black-forest-labs/flux-1-schnell',
			'fal'        => 'fal-ai/flux/schnell',
			default      => $openai_model,
		};

		return array(
			'provider'                => $provider,
			'model'                   => $model,
			'openai_model'            => $openai_model,
			'openrouter_model'        => $openrouter_model,
			'quality'                 => in_array( (string) ( $raw['quality'] ?? '' ), array( 'low', 'medium', 'high', 'auto' ), true ) ? (string) $raw['quality'] : 'medium',
			'size'                    => '1536x1024',
			'output_format'           => 'webp',
			'background'              => 'opaque',
			'target_width'            => 1280,
			'target_height'           => 720,
			'timeout'                 => min( 300, max( 60, absint( $raw['timeout'] ?? 180 ) ) ),
			'cloudflare_daily_limit'  => min( 500, max( 1, absint( $raw['cloudflare_daily_limit'] ?? 100 ) ) ),
			'daily_limit'             => min( 1000, max( 1, absint( $raw['daily_limit'] ?? 100 ) ) ),
			'auto_cleanup'            => ! isset( $raw['auto_cleanup'] ) || ! empty( $raw['auto_cleanup'] ),
		);
	}

	/** @return array<string, mixed> */
	public function get_public(): array {
		$settings = $this->get();
		$settings['providers'] = array();
		foreach ( array( 'openai', 'openrouter', 'cloudflare', 'fal' ) as $provider ) {
			$settings['providers'][ $provider ] = array(
				'configured' => 'cloudflare' === $provider ? ( '' !== $this->get_api_key( $provider ) && '' !== $this->get_cloudflare_account_id() ) : '' !== $this->get_api_key( $provider ),
				'key_source' => $this->get_key_source( $provider ),
			);
		}
		$settings['providers']['cloudflare']['account_configured'] = '' !== $this->get_cloudflare_account_id();
		$settings['providers']['cloudflare']['account_source'] = $this->get_cloudflare_account_source();
		$settings['providers']['openrouter']['model_configured'] = '' !== $settings['openrouter_model'];
		$settings['providers']['openrouter']['ready'] = $settings['providers']['openrouter']['configured'] && $settings['providers']['openrouter']['model_configured'];
		$settings['configured'] = 'cloudflare' === $settings['provider'] ? ( '' !== $this->get_api_key( 'cloudflare' ) && '' !== $this->get_cloudflare_account_id() ) : '' !== $this->get_api_key( (string) $settings['provider'] );
		$settings['ready'] = 'openrouter' === $settings['provider'] ? $settings['providers']['openrouter']['ready'] : $settings['configured'];
		$settings['key_source'] = $this->get_key_source( (string) $settings['provider'] );
		return $settings;
	}

	/** @return array<string, mixed>|WP_Error */
	public function save( array $raw ) {
		$current = $this->get();
		$provider = sanitize_key( (string) ( $raw['provider'] ?? $current['provider'] ) );
		$openai_model = sanitize_key( (string) ( $raw['openai_model'] ?? $current['openai_model'] ) );
		$openrouter_model_raw = trim( (string) ( $raw['openrouter_model'] ?? $current['openrouter_model'] ) );
		$openrouter_model = $this->sanitize_openrouter_model( $openrouter_model_raw );
		$quality = sanitize_key( (string) ( $raw['quality'] ?? $current['quality'] ) );
		$timeout = min( 300, max( 60, absint( $raw['timeout'] ?? $current['timeout'] ) ) );
		$cloudflare_daily_limit = min( 500, max( 1, absint( $raw['cloudflare_daily_limit'] ?? $current['cloudflare_daily_limit'] ) ) );
		$daily_limit = min( 1000, max( 1, absint( $raw['daily_limit'] ?? $current['daily_limit'] ) ) );
		$auto_cleanup = array_key_exists( 'auto_cleanup', $raw ) ? ! empty( $raw['auto_cleanup'] ) : ! empty( $current['auto_cleanup'] );

		if ( ! in_array( $provider, array( 'openai', 'openrouter', 'cloudflare', 'fal' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_provider', __( 'Nhà cung cấp tạo ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( $openai_model, array( 'gpt-image-1', 'gpt-image-1-mini' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_model', __( 'Model OpenAI không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( 'openrouter' === $provider && '' !== $openrouter_model_raw && '' === $openrouter_model ) {
			return new WP_Error( 'ntci_generation_openrouter_model_missing', __( 'Model OpenRouter không hợp lệ. Hãy chọn model từ danh sách hoặc nhập đúng dạng provider/model.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( $quality, array( 'low', 'medium', 'high', 'auto' ), true ) ) {
			return new WP_Error( 'ntci_generation_invalid_quality', __( 'Chất lượng ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$cloudflare_key_raw = trim( (string) ( $raw['cloudflare_api_key'] ?? '' ) );
		if ( '' !== $cloudflare_key_raw && preg_match( '/^[a-f0-9]{37}$/i', $cloudflare_key_raw ) ) {
			return new WP_Error(
				'ntci_generation_cloudflare_global_key',
				__( 'Giá trị vừa nhập giống Global API Key của Cloudflare — loại này không dùng được cho Workers AI. Hãy vào Cloudflare → My Profile → API Tokens → Create Token, chọn mẫu "Workers AI", rồi dán API Token (40 ký tự) vào đây.', 'nt-tao-anh-noi-dung-wordpress' )
			);
		}

		update_option(
			self::OPTION,
			array(
				'provider'               => $provider,
				'openai_model'           => $openai_model,
				'openrouter_model'       => $openrouter_model,
				'quality'                => $quality,
				'timeout'                => $timeout,
				'cloudflare_daily_limit' => $cloudflare_daily_limit,
				'daily_limit'            => $daily_limit,
				'auto_cleanup'           => $auto_cleanup ? 1 : 0,
			),
			false
		);

		foreach ( array( 'openai', 'openrouter', 'cloudflare', 'fal' ) as $provider_id ) {
			$this->save_key( $provider_id, $raw );
		}
		$this->save_cloudflare_account_id( $raw );
		return $this->get_public();
	}

	public function get_api_key( string $provider = '' ): string {
		$provider = '' !== $provider ? sanitize_key( $provider ) : sanitize_key( (string) $this->get()['provider'] );
		$map = array(
			'openai'     => array( 'constant' => 'NT_CONTENT_IMAGES_OPENAI_API_KEY', 'option' => self::OPENAI_KEY_OPTION ),
			'openrouter' => array( 'constant' => 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY', 'option' => self::OPENROUTER_KEY_OPTION ),
			'cloudflare' => array( 'constant' => 'NT_CONTENT_IMAGES_CLOUDFLARE_API_TOKEN', 'option' => self::CLOUDFLARE_TOKEN_OPTION ),
			'fal'        => array( 'constant' => 'NT_CONTENT_IMAGES_FAL_API_KEY', 'option' => self::FAL_KEY_OPTION ),
		);
		if ( ! isset( $map[ $provider ] ) ) {
			return '';
		}
		if ( defined( $map[ $provider ]['constant'] ) ) {
			return trim( (string) constant( $map[ $provider ]['constant'] ) );
		}
		return trim( (string) get_option( $map[ $provider ]['option'], '' ) );
	}

	public function get_cloudflare_account_id(): string {
		if ( defined( 'NT_CONTENT_IMAGES_CLOUDFLARE_ACCOUNT_ID' ) ) {
			return trim( (string) NT_CONTENT_IMAGES_CLOUDFLARE_ACCOUNT_ID );
		}
		return trim( (string) get_option( self::CLOUDFLARE_ACCOUNT_OPTION, '' ) );
	}

	public function get_key_source( string $provider = '' ): string {
		$provider = '' !== $provider ? sanitize_key( $provider ) : sanitize_key( (string) $this->get()['provider'] );
		$constants = array( 'openai' => 'NT_CONTENT_IMAGES_OPENAI_API_KEY', 'openrouter' => 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY', 'cloudflare' => 'NT_CONTENT_IMAGES_CLOUDFLARE_API_TOKEN', 'fal' => 'NT_CONTENT_IMAGES_FAL_API_KEY' );
		$options = array( 'openai' => self::OPENAI_KEY_OPTION, 'openrouter' => self::OPENROUTER_KEY_OPTION, 'cloudflare' => self::CLOUDFLARE_TOKEN_OPTION, 'fal' => self::FAL_KEY_OPTION );
		if ( ! isset( $constants[ $provider ], $options[ $provider ] ) ) {
			return 'missing';
		}
		if ( defined( $constants[ $provider ] ) && '' !== trim( (string) constant( $constants[ $provider ] ) ) ) {
			return 'wp-config';
		}
		return '' !== trim( (string) get_option( $options[ $provider ], '' ) ) ? 'database' : 'missing';
	}

	public function get_cloudflare_account_source(): string {
		if ( defined( 'NT_CONTENT_IMAGES_CLOUDFLARE_ACCOUNT_ID' ) && '' !== trim( (string) NT_CONTENT_IMAGES_CLOUDFLARE_ACCOUNT_ID ) ) {
			return 'wp-config';
		}
		return '' !== trim( (string) get_option( self::CLOUDFLARE_ACCOUNT_OPTION, '' ) ) ? 'database' : 'missing';
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
		delete_option( self::OPENAI_KEY_OPTION );
		delete_option( self::OPENROUTER_KEY_OPTION );
		delete_option( self::CLOUDFLARE_TOKEN_OPTION );
		delete_option( self::CLOUDFLARE_ACCOUNT_OPTION );
		delete_option( self::FAL_KEY_OPTION );
	}

	/** @param array<string, mixed> $raw Raw settings. */
	private function save_key( string $provider, array $raw ): void {
		$constants = array( 'openai' => 'NT_CONTENT_IMAGES_OPENAI_API_KEY', 'openrouter' => 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY', 'cloudflare' => 'NT_CONTENT_IMAGES_CLOUDFLARE_API_TOKEN', 'fal' => 'NT_CONTENT_IMAGES_FAL_API_KEY' );
		$options = array( 'openai' => self::OPENAI_KEY_OPTION, 'openrouter' => self::OPENROUTER_KEY_OPTION, 'cloudflare' => self::CLOUDFLARE_TOKEN_OPTION, 'fal' => self::FAL_KEY_OPTION );
		if ( ! isset( $constants[ $provider ], $options[ $provider ] ) || defined( $constants[ $provider ] ) ) {
			return;
		}
		$field = $provider . '_api_key';
		$clear = 'clear_' . $provider . '_api_key';
		if ( ! empty( $raw[ $clear ] ) ) {
			delete_option( $options[ $provider ] );
			return;
		}
		$key = trim( (string) ( $raw[ $field ] ?? '' ) );
		if ( '' !== $key && strlen( $key ) >= 10 && strlen( $key ) <= 2048 && ! preg_match( '/\s/', $key ) ) {
			update_option( $options[ $provider ], sanitize_text_field( $key ), false );
		}
	}

	/** @param array<string, mixed> $raw Raw settings. */
	private function save_cloudflare_account_id( array $raw ): void {
		if ( defined( 'NT_CONTENT_IMAGES_CLOUDFLARE_ACCOUNT_ID' ) ) {
			return;
		}
		if ( ! empty( $raw['clear_cloudflare_account_id'] ) ) {
			delete_option( self::CLOUDFLARE_ACCOUNT_OPTION );
			return;
		}
		$value = trim( (string) ( $raw['cloudflare_account_id'] ?? '' ) );
		if ( '' !== $value && strlen( $value ) <= 64 && preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			update_option( self::CLOUDFLARE_ACCOUNT_OPTION, sanitize_text_field( $value ), false );
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
