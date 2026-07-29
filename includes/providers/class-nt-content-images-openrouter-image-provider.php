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
	private const MODELS_FALLBACK_ENDPOINT = 'https://openrouter.ai/api/v1/models?output_modalities=image';
	private const TEXT_MODELS_ENDPOINT = 'https://openrouter.ai/api/v1/models';
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
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$result = $this->request_model_list( self::MODELS_ENDPOINT, $key );
		if ( is_wp_error( $result ) ) {
			$fallback = $this->request_model_list( self::MODELS_FALLBACK_ENDPOINT, $key );
			if ( is_wp_error( $fallback ) ) {
				return $result;
			}
			$result = $fallback;
		}

		$models = array();
		foreach ( $result as $item ) {
			$id = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
			if ( '' === $id || ! preg_match( '/^[A-Za-z0-9._:-]+\/[A-Za-z0-9._:-]+$/', $id ) ) {
				continue;
			}
			$models[] = array(
				'id'   => $id,
				'name' => sanitize_text_field( (string) ( $item['name'] ?? $id ) ),
			);
		}

		usort(
			$models,
			static fn ( array $left, array $right ): int => strcasecmp( (string) $left['name'], (string) $right['name'] )
		);

		if ( empty( $models ) ) {
			return new WP_Error( 'ntci_openrouter_models_empty', __( 'OpenRouter không trả về model tạo ảnh khả dụng cho API key này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		set_transient( $cache_key, $models, 15 * MINUTE_IN_SECONDS );
		return $models;
	}

	/**
	 * Full catalog of text-output models (dùng cho soạn alt/caption).
	 *
	 * @return array<int, array{id: string, name: string, price_label: string}>|WP_Error
	 */
	public function list_text_models() {
		$key = $this->settings->get_api_key( 'openrouter' );
		if ( '' === $key ) {
			return new WP_Error( 'ntci_openrouter_key_missing', __( 'Chưa cấu hình OpenRouter API key.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$cache_key = 'ntci_or_text_models_' . substr( hash( 'sha256', $key ), 0, 16 );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$result = $this->request_model_list( self::TEXT_MODELS_ENDPOINT, $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$models = self::normalize_text_models( $result );
		if ( empty( $models ) ) {
			return new WP_Error( 'ntci_openrouter_text_models_empty', __( 'OpenRouter không trả về model văn bản khả dụng cho API key này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		set_transient( $cache_key, $models, 15 * MINUTE_IN_SECONDS );
		return $models;
	}

	/**
	 * Filters raw /models rows down to text-output models. Pure — unit tested.
	 *
	 * @param array<int, mixed> $rows Raw model rows from OpenRouter.
	 * @return array<int, array{id: string, name: string, price_label: string}>
	 */
	public static function normalize_text_models( array $rows ): array {
		$models = array();
		foreach ( $rows as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
			if ( '' === $id || ! preg_match( '/^[A-Za-z0-9._:-]+\/[A-Za-z0-9._:-]+$/', $id ) ) {
				continue;
			}
			$outputs = $item['architecture']['output_modalities'] ?? null;
			if ( ! is_array( $outputs ) || ! in_array( 'text', array_map( 'strval', $outputs ), true ) ) {
				continue;
			}
			$models[] = array(
				'id'          => $id,
				'name'        => sanitize_text_field( (string) ( $item['name'] ?? $id ) ),
				'price_label' => self::format_price_per_million( (string) ( $item['pricing']['prompt'] ?? '' ) ),
			);
		}
		usort(
			$models,
			static fn ( array $left, array $right ): int => strcasecmp( (string) $left['name'], (string) $right['name'] )
		);
		return $models;
	}

	/** Converts OpenRouter per-token USD pricing to a compact per-1M label. */
	public static function format_price_per_million( string $per_token ): string {
		if ( '' === $per_token || ! is_numeric( $per_token ) ) {
			return '';
		}
		$per_million = (float) $per_token * 1000000;
		if ( $per_million <= 0 ) {
			return __( 'miễn phí', 'nt-tao-anh-noi-dung-wordpress' );
		}
		$decimals = $per_million >= 1 ? 2 : 3;
		return '~$' . rtrim( rtrim( number_format( $per_million, $decimals, '.', '' ), '0' ), '.' ) . '/1M';
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

		$base = apply_filters(
			'nt_content_images_openrouter_image_request',
			array(
				'model'         => $model,
				'prompt'        => $prompt,
				'n'             => 1,
				'aspect_ratio'  => '16:9',
				'output_format' => 'webp',
			),
			$request
		);

		/**
		 * Each OpenRouter image model accepts a different parameter set (e.g.
		 * Black Forest Labs rejects webp). Retry with safer bodies whenever the
		 * provider rejects a request parameter.
		 */
		$attempts = array(
			$base,
			array_merge( $base, array( 'output_format' => 'png' ) ),
			array_intersect_key( $base, array( 'model' => true, 'prompt' => true ) ),
		);

		$data       = null;
		$last_error = null;
		foreach ( $attempts as $body ) {
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
				return new WP_Error( 'ntci_openrouter_transport_error', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
			}
			$status = wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $status >= 200 && $status < 300 ) {
				$last_error = null;
				break;
			}
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? $data['message'] ?? '' ) : '';
			$message = NT_Content_Images_Secret_Redactor::redact_message( $message );
			$last_error = new WP_Error( 'ntci_openrouter_http_' . absint( $status ), '' !== $message ? $message : __( 'OpenRouter không thể tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
			if ( ! $this->is_parameter_rejection( $status, $message ) ) {
				break;
			}
		}
		if ( null !== $last_error ) {
			return $last_error;
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
		$image_info = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $bytes ) : false;
		$detected = is_array( $image_info ) ? sanitize_mime_type( (string) ( $image_info['mime'] ?? '' ) ) : '';
		$mime = in_array( $media_type, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ? $media_type : $detected;
		if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) || ( '' !== $detected && $mime !== $detected ) ) {
			return new WP_Error( 'ntci_openrouter_mime_invalid', __( 'OpenRouter trả về định dạng ảnh không hợp lệ hoặc MIME không khớp dữ liệu thật.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$extension = 'image/webp' === $mime ? 'webp' : ( 'image/jpeg' === $mime ? 'jpg' : 'png' );

		return array(
			'bytes'          => $bytes,
			'mime_type'      => $mime,
			'extension'      => $extension,
			'provider'       => $this->get_id(),
			'model'          => sanitize_text_field( $model ),
			'revised_prompt' => '',
			'usage'          => is_array( $data['usage'] ?? null ) ? NT_Content_Images_Secret_Redactor::redact( $data['usage'] ) : array(),
			'created'        => absint( $data['created'] ?? time() ),
		);
	}

	/** Detects "unsupported parameter" rejections that are worth retrying with a safer body. */
	private function is_parameter_rejection( int $status, string $message ): bool {
		if ( 400 !== $status ) {
			return false;
		}
		return (bool) preg_match( '/parameter|not supported|unsupported|Accepted:/i', $message );
	}

	/** @return array<int, array<string, mixed>>|WP_Error */
	private function request_model_list( string $endpoint, string $key ) {
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => $this->headers( $key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_openrouter_models_transport', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data['data'] ?? null ) ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? $data['message'] ?? '' ) : '';
			$message = NT_Content_Images_Secret_Redactor::redact_message( $message );
			return new WP_Error(
				'ntci_openrouter_models_failed',
				'' !== $message ? $message : __( 'Không thể tải danh sách model ảnh từ OpenRouter.', 'nt-tao-anh-noi-dung-wordpress' ),
				array( 'status' => $status )
			);
		}

		return $data['data'];
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
