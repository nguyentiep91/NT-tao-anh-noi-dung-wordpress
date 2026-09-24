<?php
/**
 * Downloads remote raster images with SSRF and MIME protections.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Remote_Image_Downloader {
	private const MAX_BYTES = 25165824;

	/** @return array<string, mixed>|WP_Error */
	public function download( string $url, string $provider ) {
		$url = esc_url_raw( trim( $url ) );
		$provider = sanitize_key( $provider );
		if ( ! $this->is_allowed_url( $url, $provider ) ) {
			return new WP_Error( 'ntci_remote_url_rejected', __( 'URL ảnh từ xa không được phép.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 60,
				'redirection'         => 2,
				'limit_response_size' => self::MAX_BYTES + 1,
				'headers'             => array( 'Accept' => 'image/avif,image/webp,image/png,image/jpeg;q=0.9,*/*;q=0.1' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_remote_download_failed', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error( 'ntci_remote_http_' . absint( $status ), __( 'Không thể tải ảnh từ nguồn đã chọn.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}

		$bytes = wp_remote_retrieve_body( $response );
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_BYTES ) {
			return new WP_Error( 'ntci_remote_bytes_invalid', __( 'Dữ liệu ảnh từ xa trống hoặc vượt quá 25 MB.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$info = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $bytes ) : false;
		$mime = is_array( $info ) ? sanitize_mime_type( (string) ( $info['mime'] ?? '' ) ) : '';
		if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
			return new WP_Error( 'ntci_remote_mime_invalid', __( 'Nguồn không trả về ảnh PNG, JPEG hoặc WebP hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		return array(
			'bytes'        => $bytes,
			'mime_type'    => $mime,
			'extension'    => 'image/png' === $mime ? 'png' : ( 'image/webp' === $mime ? 'webp' : 'jpg' ),
			'width'        => absint( $info[0] ?? 0 ),
			'height'       => absint( $info[1] ?? 0 ),
			'file_size'    => strlen( $bytes ),
			'checksum'     => hash( 'sha256', $bytes ),
			'original_url' => $url,
		);
	}

	private function is_allowed_url( string $url, string $provider ): bool {
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}
		$host = strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) );
		if ( '' === $host || filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		if ( 'pexels' === $provider ) {
			return 'images.pexels.com' === $host;
		}
		if ( 'fal' === $provider ) {
			return 'fal.media' === $host || str_ends_with( $host, '.fal.media' );
		}
		if ( 'openverse' === $provider ) {
			return true;
		}
		return false;
	}
}
