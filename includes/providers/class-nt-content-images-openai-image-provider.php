<?php
/**
 * OpenAI Images API adapter.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_OpenAI_Image_Provider implements NT_Content_Images_Image_Provider_Interface {
	private const ENDPOINT = 'https://api.openai.com/v1/images/generations';
	private const MAX_IMAGE_BYTES = 25165824;

	private NT_Content_Images_Generation_Settings $settings;

	public function __construct( NT_Content_Images_Generation_Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_id(): string {
		return 'openai';
	}

	public function get_label(): string {
		return 'OpenAI Images';
	}

	public function is_configured(): bool {
		return '' !== $this->settings->get_api_key( 'openai' );
	}

	/** @return array<int, array{id: string, name: string}> */
	public function list_models(): array {
		return array(
			array( 'id' => 'gpt-image-1-mini', 'name' => 'gpt-image-1-mini' ),
			array( 'id' => 'gpt-image-1', 'name' => 'gpt-image-1' ),
		);
	}

	/** @return array<string, mixed>|WP_Error */
	public function generate( array $request ) {
		$api_key = $this->settings->get_api_key( 'openai' );
		if ( '' === $api_key ) {
			return new WP_Error( 'ntci_openai_key_missing', __( 'Chưa cấu hình OpenAI API key.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$config = $this->settings->get();
		$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
		if ( '' === $prompt || strlen( $prompt ) > 12000 ) {
			return new WP_Error( 'ntci_openai_prompt_invalid', __( 'Prompt tạo ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$body = array(
			'model'         => $config['openai_model'],
			'prompt'        => $prompt,
			'size'          => $config['size'],
			'quality'       => $config['quality'],
			'output_format' => $config['output_format'],
			'background'    => $config['background'],
			'n'             => 1,
		);
		$body = apply_filters( 'nt_content_images_openai_image_request', $body, $request );

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => $config['timeout'],
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_openai_transport_error', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			return new WP_Error( 'ntci_openai_http_' . absint( $status ), '' !== $message ? sanitize_text_field( $message ) : __( 'OpenAI không thể tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		if ( ! is_array( $data ) || ! is_array( $data['data'][0] ?? null ) ) {
			return new WP_Error( 'ntci_openai_response_invalid', __( 'OpenAI trả về dữ liệu ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$item  = $data['data'][0];
		$bytes = '';
		if ( ! empty( $item['b64_json'] ) && is_string( $item['b64_json'] ) ) {
			$bytes = base64_decode( $item['b64_json'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$bytes = is_string( $bytes ) ? $bytes : '';
		} elseif ( ! empty( $item['url'] ) && is_string( $item['url'] ) ) {
			$download = wp_safe_remote_get( esc_url_raw( $item['url'] ), array( 'timeout' => 90, 'redirection' => 2 ) );
			if ( ! is_wp_error( $download ) && 200 === wp_remote_retrieve_response_code( $download ) ) {
				$bytes = wp_remote_retrieve_body( $download );
			}
		}
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'ntci_openai_image_missing', __( 'Không nhận được dữ liệu ảnh hợp lệ từ OpenAI.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		return array(
			'bytes'          => $bytes,
			'mime_type'      => 'image/webp',
			'extension'      => 'webp',
			'provider'       => $this->get_id(),
			'model'          => sanitize_text_field( (string) $config['openai_model'] ),
			'revised_prompt' => sanitize_textarea_field( (string) ( $item['revised_prompt'] ?? '' ) ),
			'usage'          => is_array( $data['usage'] ?? null ) ? $data['usage'] : array(),
			'created'        => absint( $data['created'] ?? time() ),
		);
	}
}
