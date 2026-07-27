<?php
/**
 * Cloudflare Workers AI FLUX.1 Schnell provider.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Cloudflare_Image_Provider implements NT_Content_Images_Image_Provider_Interface {
	private const MODEL = '@cf/black-forest-labs/flux-1-schnell';
	private const MAX_IMAGE_BYTES = 25165824;
	private const USAGE_OPTION = 'nt_content_images_cloudflare_daily_usage';
	private NT_Content_Images_Generation_Settings $settings;

	public function __construct( NT_Content_Images_Generation_Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_id(): string {
		return 'cloudflare';
	}

	public function get_label(): string {
		return 'Cloudflare Workers AI';
	}

	public function is_configured(): bool {
		return '' !== $this->settings->get_api_key( 'cloudflare' ) && '' !== $this->settings->get_cloudflare_account_id();
	}

	/** @return array<int, array{id: string, name: string}> */
	public function list_models(): array {
		return array( array( 'id' => self::MODEL, 'name' => 'FLUX.1 Schnell (Cloudflare)' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function generate( array $request ) {
		$token = $this->settings->get_api_key( 'cloudflare' );
		$account_id = $this->settings->get_cloudflare_account_id();
		if ( '' === $token || '' === $account_id ) {
			return new WP_Error( 'ntci_cloudflare_credentials_missing', __( 'Chưa cấu hình Cloudflare Account ID và Workers AI API Token.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$config = $this->settings->get();
		$usage = $this->get_daily_usage();
		if ( $usage['count'] >= absint( $config['cloudflare_daily_limit'] ?? 100 ) ) {
			return new WP_Error( 'ntci_cloudflare_daily_limit', __( 'Đã đạt giới hạn ảnh Cloudflare do quản trị viên đặt cho hôm nay.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 429 ) );
		}
		$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
		if ( '' === $prompt ) {
			return new WP_Error( 'ntci_cloudflare_prompt_invalid', __( 'Prompt Cloudflare không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$prompt = substr( $prompt, 0, 2048 );
		$url = sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s', rawurlencode( $account_id ), self::MODEL );
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $config['timeout'],
				'redirection' => 0,
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
				'body' => wp_json_encode( array( 'prompt' => $prompt, 'steps' => 4, 'seed' => wp_rand( 1, PHP_INT_MAX ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_cloudflare_transport', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['success'] ) ) {
			$message = is_array( $data['errors'][0] ?? null ) ? (string) ( $data['errors'][0]['message'] ?? '' ) : '';
			return new WP_Error( 'ntci_cloudflare_http_' . absint( $status ), '' !== $message ? sanitize_text_field( $message ) : __( 'Cloudflare Workers AI không thể tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		$encoded = is_string( $data['result']['image'] ?? null ) ? $data['result']['image'] : '';
		$bytes = '' !== $encoded ? base64_decode( $encoded, true ) : ''; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$bytes = is_string( $bytes ) ? $bytes : '';
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'ntci_cloudflare_image_missing', __( 'Cloudflare không trả về dữ liệu ảnh hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$info = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $bytes ) : false;
		$mime = is_array( $info ) ? sanitize_mime_type( (string) ( $info['mime'] ?? '' ) ) : '';
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error( 'ntci_cloudflare_mime_invalid', __( 'Cloudflare trả về định dạng ảnh không được hỗ trợ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$this->increment_daily_usage();
		return array(
			'bytes'          => $bytes,
			'mime_type'      => $mime,
			'extension'      => 'image/png' === $mime ? 'png' : ( 'image/webp' === $mime ? 'webp' : 'jpg' ),
			'provider'       => $this->get_id(),
			'model'          => self::MODEL,
			'revised_prompt' => '',
			'usage'          => array( 'steps' => 4 ),
			'created'        => time(),
		);
	}

	/** @return array{date:string,count:int} */
	private function get_daily_usage(): array {
		$today = gmdate( 'Y-m-d' );
		$raw = get_option( self::USAGE_OPTION, array() );
		if ( ! is_array( $raw ) || $today !== (string) ( $raw['date'] ?? '' ) ) {
			return array( 'date' => $today, 'count' => 0 );
		}
		return array( 'date' => $today, 'count' => absint( $raw['count'] ?? 0 ) );
	}

	private function increment_daily_usage(): void {
		$usage = $this->get_daily_usage();
		$usage['count']++;
		update_option( self::USAGE_OPTION, $usage, false );
	}
}
