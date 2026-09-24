<?php
/**
 * Pexels stock photo provider.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Pexels_Stock_Provider implements NT_Content_Images_Stock_Provider_Interface {
	private const SEARCH_ENDPOINT = 'https://api.pexels.com/v1/search';
	private const PHOTO_ENDPOINT = 'https://api.pexels.com/v1/photos/';

	private NT_Content_Images_Source_Settings $settings;

	public function __construct( NT_Content_Images_Source_Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_id(): string {
		return 'pexels';
	}

	public function get_label(): string {
		return 'Pexels';
	}

	public function is_configured(): bool {
		return '' !== $this->settings->get_api_key( 'pexels' );
	}

	/** @return array<string, mixed>|WP_Error */
	public function search( array $request ) {
		$key = $this->settings->get_api_key( 'pexels' );
		if ( '' === $key ) {
			return new WP_Error( 'ntci_pexels_key_missing', __( 'Chưa cấu hình Pexels API key.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$query = sanitize_text_field( (string) ( $request['query'] ?? '' ) );
		if ( '' === $query || strlen( $query ) > 200 ) {
			return new WP_Error( 'ntci_pexels_query_invalid', __( 'Từ khóa tìm ảnh Pexels không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$per_page = min( 24, max( 6, absint( $request['per_page'] ?? 12 ) ) );
		$page = max( 1, absint( $request['page'] ?? 1 ) );
		$url = add_query_arg(
			array(
				'query'       => $query,
				'orientation' => 'landscape',
				'locale'      => 'vi-VN',
				'per_page'    => $per_page,
				'page'        => $page,
			),
			self::SEARCH_ENDPOINT
		);
		$response = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Authorization' => $key ) ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_pexels_transport', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) ) {
			return new WP_Error( 'ntci_pexels_http_' . absint( $status ), __( 'Pexels không thể trả kết quả tìm kiếm.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}

		$items = array();
		foreach ( is_array( $data['photos'] ?? null ) ? $data['photos'] : array() as $photo ) {
			$normalized = $this->normalize( is_array( $photo ) ? $photo : array() );
			if ( ! empty( $normalized ) ) {
				$items[] = $normalized;
			}
		}
		return array(
			'provider' => $this->get_id(),
			'query'    => $query,
			'items'    => $items,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => absint( $data['total_results'] ?? count( $items ) ),
			'quota'    => array(
				'limit'     => absint( wp_remote_retrieve_header( $response, 'x-ratelimit-limit' ) ),
				'remaining' => absint( wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ) ),
				'reset'     => absint( wp_remote_retrieve_header( $response, 'x-ratelimit-reset' ) ),
			),
		);
	}

	/** @return array<string, mixed>|WP_Error */
	public function get_asset( string $asset_id ) {
		$key = $this->settings->get_api_key( 'pexels' );
		$id = absint( $asset_id );
		if ( '' === $key || 0 === $id ) {
			return new WP_Error( 'ntci_pexels_asset_invalid', __( 'Không thể lấy thông tin ảnh Pexels.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$response = wp_remote_get( self::PHOTO_ENDPOINT . $id, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Authorization' => $key ) ) );
		$status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || 200 !== $status || ! is_array( $data ) ) {
			return new WP_Error( 'ntci_pexels_asset_failed', __( 'Không thể tải metadata ảnh Pexels.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		$item = $this->normalize( $data );
		return empty( $item ) ? new WP_Error( 'ntci_pexels_asset_empty', __( 'Pexels trả về ảnh không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) ) : $item;
	}

	/** @param array<string, mixed> $photo @return array<string, mixed> */
	private function normalize( array $photo ): array {
		$id = absint( $photo['id'] ?? 0 );
		$src = is_array( $photo['src'] ?? null ) ? $photo['src'] : array();
		$download = esc_url_raw( (string) ( $src['large2x'] ?? $src['original'] ?? $src['large'] ?? '' ) );
		$preview = esc_url_raw( (string) ( $src['medium'] ?? $src['landscape'] ?? $download ) );
		$page = esc_url_raw( (string) ( $photo['url'] ?? '' ) );
		if ( 0 === $id || '' === $download || '' === $page ) {
			return array();
		}
		$creator = sanitize_text_field( (string) ( $photo['photographer'] ?? '' ) );
		return array(
			'provider'        => $this->get_id(),
			'asset_id'        => (string) $id,
			'title'           => sanitize_text_field( (string) ( $photo['alt'] ?? 'Pexels photo' ) ),
			'preview_url'     => $preview,
			'download_url'    => $download,
			'source_page_url' => $page,
			'creator_name'    => $creator,
			'creator_url'     => esc_url_raw( (string) ( $photo['photographer_url'] ?? '' ) ),
			'license_code'    => 'pexels',
			'license_version' => '',
			'license_url'     => 'https://www.pexels.com/license/',
			'attribution'     => sprintf( 'Photo by %s on Pexels', $creator ?: 'Pexels contributor' ),
			'width'           => absint( $photo['width'] ?? 0 ),
			'height'          => absint( $photo['height'] ?? 0 ),
			'mime_type'       => 'image/jpeg',
			'requires_license_confirmation' => false,
		);
	}
}
