<?php
/**
 * Canva Connect API client for assets, designs and exports.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Canva_Client {
	private const API_BASE = 'https://api.canva.com/rest/v1';
	private const MAX_UPLOAD_BYTES = 52428800;

	private NT_Content_Images_Canva_OAuth $oauth;

	public function __construct( NT_Content_Images_Canva_OAuth $oauth ) {
		$this->oauth = $oauth;
	}

	/** @return array<string, mixed>|WP_Error */
	public function upload_attachment( int $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return new WP_Error( 'ntci_canva_attachment_missing', __( 'Không đọc được tệp ảnh WordPress để gửi sang Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$size = filesize( $file );
		if ( false === $size || $size < 1 || $size > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error( 'ntci_canva_attachment_size', __( 'Ảnh vượt giới hạn Canva hoặc có kích thước không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return new WP_Error( 'ntci_canva_attachment_read_failed', __( 'Không thể đọc dữ liệu ảnh để gửi sang Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$name = sanitize_text_field( get_the_title( $attachment_id ) ?: basename( $file ) );
		$name = mb_substr( $name, 0, 50 );
		$metadata = wp_json_encode(
			array(
				'name_base64' => base64_encode( $name ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		$result = $this->request(
			'POST',
			'/asset-uploads',
			$bytes,
			array(
				'Content-Type'          => 'application/octet-stream',
				'Asset-Upload-Metadata' => $metadata,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$job_id = sanitize_text_field( (string) ( $result['job']['id'] ?? '' ) );
		if ( '' === $job_id ) {
			return new WP_Error( 'ntci_canva_upload_job_missing', __( 'Canva không trả về mã upload job.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $this->poll_job( '/asset-uploads/' . rawurlencode( $job_id ), 'asset' );
	}

	/** @return array<string, mixed>|WP_Error */
	public function create_design( string $asset_id, string $title, int $width = 1280, int $height = 720 ) {
		$result = $this->request(
			'POST',
			'/designs',
			array(
				'type'        => 'type_and_asset',
				'design_type' => array(
					'type'   => 'custom',
					'width'  => min( 8000, max( 40, $width ) ),
					'height' => min( 8000, max( 40, $height ) ),
				),
				'asset_id'    => sanitize_text_field( $asset_id ),
				'title'       => mb_substr( sanitize_text_field( $title ), 0, 255 ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$design = is_array( $result['design'] ?? null ) ? $result['design'] : array();
		if ( empty( $design['id'] ) ) {
			return new WP_Error( 'ntci_canva_design_missing', __( 'Canva không trả về thiết kế hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $design;
	}

	/** @return array<string, mixed>|WP_Error */
	public function export_design_png( string $design_id, int $width = 1280, int $height = 720 ) {
		$result = $this->request(
			'POST',
			'/exports',
			array(
				'design_id' => sanitize_text_field( $design_id ),
				'format'    => array(
					'type'   => 'png',
					'width'  => min( 25000, max( 40, $width ) ),
					'height' => min( 25000, max( 40, $height ) ),
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$job_id = sanitize_text_field( (string) ( $result['job']['id'] ?? '' ) );
		if ( '' === $job_id ) {
			return new WP_Error( 'ntci_canva_export_job_missing', __( 'Canva không trả về mã export job.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$job = $this->poll_job( '/exports/' . rawurlencode( $job_id ), 'urls' );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$url = esc_url_raw( (string) ( $job['urls'][0] ?? '' ) );
		if ( '' === $url || ! $this->is_canva_download_url( $url ) ) {
			return new WP_Error( 'ntci_canva_export_url_invalid', __( 'Canva trả về URL tải ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$download = wp_remote_get( $url, array( 'timeout' => 120, 'redirection' => 2 ) );
		if ( is_wp_error( $download ) || 200 !== wp_remote_retrieve_response_code( $download ) ) {
			return new WP_Error( 'ntci_canva_export_download_failed', __( 'Không thể tải ảnh đã xuất từ Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$bytes = wp_remote_retrieve_body( $download );
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error( 'ntci_canva_export_bytes_invalid', __( 'Dữ liệu ảnh xuất từ Canva không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return array(
			'bytes'      => $bytes,
			'mime_type'  => 'image/png',
			'extension'  => 'png',
			'provider'   => 'canva',
			'model'      => 'canva-design-export',
			'usage'      => array(),
			'created'    => time(),
			'export_job' => $job_id,
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private function request( string $method, string $path, $body = null, array $extra_headers = array() ) {
		$token = $this->oauth->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$headers = array_merge(
			array(
				'Authorization' => 'Bearer ' . $token,
			),
			$extra_headers
		);
		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => 120,
			'redirection' => 0,
			'headers'     => $headers,
		);
		if ( null !== $body ) {
			if ( is_array( $body ) ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} else {
				$args['body'] = $body;
			}
		}
		$response = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_canva_transport_error', $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['message'] ?? $data['error']['message'] ?? '' ) : '';
			return new WP_Error( 'ntci_canva_http_' . absint( $status ), '' !== $message ? sanitize_text_field( $message ) : __( 'Canva Connect API báo lỗi.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		return is_array( $data ) ? $data : array();
	}

	/** @return array<string, mixed>|WP_Error */
	private function poll_job( string $path, string $result_key ) {
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$result = $this->request( 'GET', $path );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$job = is_array( $result['job'] ?? null ) ? $result['job'] : array();
			$status = sanitize_key( (string) ( $job['status'] ?? '' ) );
			if ( 'success' === $status ) {
				if ( 'asset' === $result_key && ! is_array( $job['asset'] ?? null ) ) {
					return new WP_Error( 'ntci_canva_job_result_missing', __( 'Canva hoàn tất nhưng không trả về asset.', 'nt-tao-anh-noi-dung-wordpress' ) );
				}
				return $job;
			}
			if ( 'failed' === $status ) {
				$message = sanitize_text_field( (string) ( $job['error']['message'] ?? __( 'Canva xử lý tác vụ thất bại.', 'nt-tao-anh-noi-dung-wordpress' ) ) );
				return new WP_Error( 'ntci_canva_job_failed', $message );
			}
			sleep( 1 );
		}
		return new WP_Error( 'ntci_canva_job_timeout', __( 'Canva chưa hoàn tất tác vụ trong thời gian chờ.', 'nt-tao-anh-noi-dung-wordpress' ) );
	}

	private function is_canva_download_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		return 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) && ( 'canva.com' === $host || str_ends_with( $host, '.canva.com' ) );
	}
}
