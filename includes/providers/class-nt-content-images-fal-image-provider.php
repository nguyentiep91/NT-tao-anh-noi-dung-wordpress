<?php
/**
 * fal.ai queue-backed FLUX Schnell provider.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Fal_Image_Provider implements NT_Content_Images_Image_Provider_Interface {
	private const MODEL = 'fal-ai/flux/schnell';
	private const QUEUE_ENDPOINT = 'https://queue.fal.run/fal-ai/flux/schnell';
	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Remote_Image_Downloader $downloader;

	public function __construct( NT_Content_Images_Generation_Settings $settings, NT_Content_Images_Remote_Image_Downloader $downloader ) {
		$this->settings = $settings;
		$this->downloader = $downloader;
	}

	public function get_id(): string {
		return 'fal';
	}

	public function get_label(): string {
		return 'fal.ai FLUX Schnell';
	}

	public function is_configured(): bool {
		return '' !== $this->settings->get_api_key( 'fal' );
	}

	/** @return array<int, array{id: string, name: string}> */
	public function list_models(): array {
		return array( array( 'id' => self::MODEL, 'name' => 'FLUX.1 Schnell (fal.ai)' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function generate( array $request ) {
		$key = $this->settings->get_api_key( 'fal' );
		$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
		if ( '' === $key ) {
			return new WP_Error( 'ntci_fal_key_missing', __( 'Chưa cấu hình fal.ai API key.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( '' === $prompt || strlen( $prompt ) > 12000 ) {
			return new WP_Error( 'ntci_fal_prompt_invalid', __( 'Prompt fal.ai không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$headers = array( 'Authorization' => 'Key ' . $key, 'Content-Type' => 'application/json' );
		$submit = wp_remote_post(
			self::QUEUE_ENDPOINT,
			array(
				'timeout' => 45,
				'redirection' => 0,
				'headers' => $headers,
				'body' => wp_json_encode(
					array(
						'prompt' => $prompt,
						'image_size' => 'landscape_16_9',
						'num_inference_steps' => 4,
						'num_images' => 1,
						'enable_safety_checker' => true,
						'output_format' => 'jpeg',
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $submit ) ) {
			return new WP_Error( 'ntci_fal_submit_transport', NT_Content_Images_Secret_Redactor::redact_message( $submit->get_error_message() ) );
		}
		$status_code = wp_remote_retrieve_response_code( $submit );
		$data = json_decode( wp_remote_retrieve_body( $submit ), true );
		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'ntci_fal_submit_http_' . absint( $status_code ), __( 'fal.ai không thể nhận yêu cầu tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status_code ) );
		}
		$request_id = sanitize_text_field( (string) ( $data['request_id'] ?? '' ) );
		$status_url = esc_url_raw( (string) ( $data['status_url'] ?? '' ) );
		$response_url = esc_url_raw( (string) ( $data['response_url'] ?? '' ) );
		if ( '' === $request_id || ! $this->is_queue_url( $status_url ) || ! $this->is_queue_url( $response_url ) ) {
			return new WP_Error( 'ntci_fal_submit_invalid', __( 'fal.ai trả về thông tin hàng đợi không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$completed = false;
		for ( $attempt = 0; $attempt < 24; $attempt++ ) {
			$poll = wp_remote_get( add_query_arg( 'logs', '0', $status_url ), array( 'timeout' => 20, 'redirection' => 0, 'headers' => $headers ) );
			if ( is_wp_error( $poll ) ) {
				return new WP_Error( 'ntci_fal_status_transport', NT_Content_Images_Secret_Redactor::redact_message( $poll->get_error_message() ) );
			}
			$poll_data = json_decode( wp_remote_retrieve_body( $poll ), true );
			$status = sanitize_key( (string) ( $poll_data['status'] ?? '' ) );
			if ( 'completed' === $status ) {
				$completed = true;
				break;
			}
			if ( in_array( $status, array( 'failed', 'error', 'cancelled' ), true ) ) {
				return new WP_Error( 'ntci_fal_generation_failed', __( 'fal.ai báo yêu cầu tạo ảnh thất bại.', 'nt-tao-anh-noi-dung-wordpress' ) );
			}
			sleep( 3 );
		}
		if ( ! $completed ) {
			return new WP_Error( 'ntci_fal_timeout', __( 'fal.ai chưa hoàn tất ảnh trong thời gian chờ. Có thể thử lại sau.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'request_id' => $request_id ) );
		}
		$result_response = wp_remote_get( $response_url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => $headers ) );
		$result = is_wp_error( $result_response ) ? null : json_decode( wp_remote_retrieve_body( $result_response ), true );
		if ( is_wp_error( $result_response ) || ! is_array( $result ) ) {
			return new WP_Error( 'ntci_fal_result_failed', __( 'Không thể lấy kết quả ảnh fal.ai.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$payload = is_array( $result['payload'] ?? null ) ? $result['payload'] : $result;
		$image = is_array( $payload['images'][0] ?? null ) ? $payload['images'][0] : array();
		$image_url = esc_url_raw( (string) ( $image['url'] ?? '' ) );
		$download = $this->downloader->download( $image_url, 'fal' );
		if ( is_wp_error( $download ) ) {
			return $download;
		}
		return array_merge(
			$download,
			array(
				'provider'       => $this->get_id(),
				'model'          => self::MODEL,
				'revised_prompt' => '',
				'usage'          => array( 'request_id' => $request_id, 'seed' => absint( $payload['seed'] ?? 0 ), 'timings' => is_array( $payload['timings'] ?? null ) ? $payload['timings'] : array() ),
				'created'        => time(),
			)
		);
	}

	private function is_queue_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) && 'queue.fal.run' === strtolower( (string) ( $parts['host'] ?? '' ) );
	}
}
