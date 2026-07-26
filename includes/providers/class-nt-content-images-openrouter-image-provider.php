<?php
/**
 * OpenRouter Unified Image API adapter.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_OpenRouter_Image_Provider implements NT_Content_Images_Image_Provider_Interface {
	private const ENDPOINT = 'https://openrouter.ai/api/v1/images';
	private const MODELS_ENDPOINT = 'https://openrouter.ai/api/v1/images/models';
	private const MAX_IMAGE_BYTES = 25165824;

	private NT_Content_Images_Generation_Settings $settings;

	public function __construct( NT_Content_Images_Generation_Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_id(): string {
		return 'openrouter';
	}

	public function get_label(): string {
		return 'OpenRouter Images';
	}

	public function is_configured(): bool {
		return '' !== $this->settings->get_api_key( 'openrouter' );
	}

	/** @return array<int, array{id: string, name: string}>|WP_Error */
	public function list_models() {
		$key = $this->settings->get_api_key( 'openrouter' );
		if ( '' === $key ) {
			return new WP_Error( 'ntci_openrouter_key_missing', __( 'Chưa cấu hình OpenRouter API key.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$cache_key = 'ntci_or_models_' . substr( hash( 'sha256', $key ), 0, 16 );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get(
			self::MODELS_ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => $this->headers( $key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_openrouter_models_transport', $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data['data'] ?? null ) ) {
			return new WP_Error( 'ntci_openrouter_models_failed', __( 'Không thể tải danh sách model ảnh từ OpenRouter.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$models = array();
		foreach ( $data['data'] as $item ) {
			$id = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			$models[] = array(
				'id'   => $id,
				'name' => sanitize_text_field( (string) ( $item['name'] ?? $id ) ),
			);
		}
		set_transient( $cache_key, $models, 15 * MINUTE_IN_SECONDS );
		return $models;
	}

	/** @return array<string, mixed>|WP_Error */
	public function generate( array $request ) {
		$key = $this->settings->get_api_key( 'openrouter' );
		$config = $this->settings->get();
		$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
		$model = trim( (string) $config['openrouter_model'] );
		if ( '' === $key ) {
			return new WP_Error( 'ntci_openrouter_key_missing', __( 'Chưa cấu hình OpenRouter API key.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( '' === $model ) {
			return new WP_Error( 'ntci_openrouter_model_missing', __( 'Chưa chọn model tạo ảnh OpenRouter.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( '' === $prompt || strlen( $prompt ) > 12000 ) {
			return new WP_Error( 'ntci_openrouter_prompt_invalid', __( 'Prompt tạo ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$body = apply_filters(
			'nt_content_images_openrouter_image_request',
			array(
				'model'  => $model,
				'prompt' => $prompt,
			),
			$request
		);
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => $config['timeout'],
				'redirection' => 0,
				'headers'     => $this->headers( $key ),
				'body'        => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_openrouter_transport_error', $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? $data['message'] ?? '' ) : '';
			return new WP_Error( 'ntci_openrouter_http_' . absint( $status ), '' !== $message ? sanitize_text_field( $message ) : __( 'OpenRouter không thể tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		$item = is_array( $data['data'][0] ?? null ) ? $data['data'][0] : array();
		$encoded = is_string( $item['b64_json'] ?? null ) ? $item['b64_json'] : '';
		$bytes = '' !== $encoded ? base64_decode( $encoded, true ) : ''; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$bytes = is_string( $bytes ) ? $bytes : '';
		$media_type = sanitize_mime_type( (string) ( $item['media_type'] ?? '' ) );
		if ( 'image/svg+xml' === $media_type ) {
			return new WP_Error( 'ntci_openrouter_vector_not_supported', __( 'Model OpenRouter trả về SVG. Hãy chọn model ảnh raster PNG, JPEG hoặc WebP.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'ntci_openrouter_image_missing', __( 'Không nhận được dữ liệu ảnh raster hợp lệ từ OpenRouter.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$detected = wp_get_image_mime( $bytes );
		$mime = in_array( $media_type, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ? $media_type : ( $detected ?: 'image/png' );
		$extension = 'image/webp' === $mime ? 'webp' : ( 'image/jpeg' === $mime ? 'jpg' : 'png' );

		return array(
			'bytes'          => $bytes,
			'mime_type'      => $mime,
			'extension'      => $extension,
			'provider'       => $this->get_id(),
			'model'          => sanitize_text_field( $model ),
			'revised_prompt' => '',
			'usage'          => is_array( $data['usage'] ?? null ) ? $data['usage'] : array(),
			'created'        => absint( $data['created'] ?? time() ),
		);
	}

	/** @return array<string, string> */
	private function headers( string $key ): array {
		return array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => home_url( '/' ),
			'X-Title'       => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
		);
	}
}
