<?php
/**
 * Openverse open-license image provider.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Openverse_Stock_Provider implements NT_Content_Images_Stock_Provider_Interface {
	private const ENDPOINT = 'https://api.openverse.org/v1/images/';
	private const ALLOWED_LICENSES = array( 'pdm', 'cc0', 'by', 'by-sa' );

	private NT_Content_Images_Source_Settings $settings;

	public function __construct( NT_Content_Images_Source_Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_id(): string {
		return 'openverse';
	}

	public function get_label(): string {
		return 'Openverse';
	}

	public function is_configured(): bool {
		return true;
	}

	/** @return array<string, mixed>|WP_Error */
	public function search( array $request ) {
		$query = sanitize_text_field( (string) ( $request['query'] ?? '' ) );
		if ( '' === $query || strlen( $query ) > 200 ) {
			return new WP_Error( 'ntci_openverse_query_invalid', __( 'Từ khóa tìm Openverse không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$page = max( 1, absint( $request['page'] ?? 1 ) );
		$per_page = min( 24, max( 6, absint( $request['per_page'] ?? 12 ) ) );
		$url = add_query_arg(
			array(
				'q'         => $query,
				'license'   => implode( ',', self::ALLOWED_LICENSES ),
				'page_size' => $per_page,
				'page'      => $page,
			),
			self::ENDPOINT
		);
		$response = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => $this->headers() ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ntci_openverse_transport', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) ) {
			return new WP_Error( 'ntci_openverse_http_' . absint( $status ), __( 'Openverse không thể trả kết quả tìm kiếm.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}

		$items = array();
		foreach ( is_array( $data['results'] ?? null ) ? $data['results'] : array() as $image ) {
			$normalized = $this->normalize( is_array( $image ) ? $image : array() );
			if ( ! empty( $normalized ) ) {
				$items[] = $normalized;
			}
		}
		return array(
			'provider'   => $this->get_id(),
			'query'      => $query,
			'items'      => $items,
			'page'       => $page,
			'per_page'   => $per_page,
			'total'      => absint( $data['result_count'] ?? count( $items ) ),
			'page_count' => absint( $data['page_count'] ?? 0 ),
		);
	}

	/** @return array<string, mixed>|WP_Error */
	public function get_asset( string $asset_id ) {
		$asset_id = sanitize_text_field( $asset_id );
		if ( '' === $asset_id || ! preg_match( '/^[a-f0-9-]{20,50}$/i', $asset_id ) ) {
			return new WP_Error( 'ntci_openverse_asset_invalid', __( 'Mã ảnh Openverse không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$response = wp_remote_get( self::ENDPOINT . rawurlencode( $asset_id ) . '/', array( 'timeout' => 30, 'redirection' => 0, 'headers' => $this->headers() ) );
		$status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || 200 !== $status || ! is_array( $data ) ) {
			return new WP_Error( 'ntci_openverse_asset_failed', __( 'Không thể tải metadata ảnh Openverse.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => $status ) );
		}
		$item = $this->normalize( $data );
		return empty( $item ) ? new WP_Error( 'ntci_openverse_asset_rejected', __( 'Ảnh Openverse không đáp ứng chính sách giấy phép của plugin.', 'nt-tao-anh-noi-dung-wordpress' ) ) : $item;
	}

	/** @return array<string, string> */
	private function headers(): array {
		$headers = array( 'Accept' => 'application/json' );
		$token = $this->settings->get_api_key( 'openverse' );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		return $headers;
	}

	/** @param array<string, mixed> $image @return array<string, mixed> */
	private function normalize( array $image ): array {
		$id = sanitize_text_field( (string) ( $image['id'] ?? '' ) );
		$license = strtolower( sanitize_key( (string) ( $image['license'] ?? '' ) ) );
		$download = esc_url_raw( (string) ( $image['url'] ?? '' ) );
		$page = esc_url_raw( (string) ( $image['foreign_landing_url'] ?? '' ) );
		if ( '' === $id || ! in_array( $license, self::ALLOWED_LICENSES, true ) || '' === $download || '' === $page || ! empty( $image['mature'] ) ) {
			return array();
		}
		$filetype = strtolower( sanitize_key( (string) ( $image['filetype'] ?? '' ) ) );
		if ( 'svg' === $filetype ) {
			return array();
		}
		$creator = sanitize_text_field( (string) ( $image['creator'] ?? '' ) );
		$attribution = sanitize_text_field( (string) ( $image['attribution'] ?? '' ) );
		if ( '' === $attribution ) {
			$attribution = trim( sprintf( '%s by %s (%s)', sanitize_text_field( (string) ( $image['title'] ?? 'Openverse image' ) ), $creator ?: 'Unknown creator', strtoupper( $license ) ) );
		}
		return array(
			'provider'                      => $this->get_id(),
			'asset_id'                      => $id,
			'title'                         => sanitize_text_field( (string) ( $image['title'] ?? 'Openverse image' ) ),
			'preview_url'                   => esc_url_raw( (string) ( $image['thumbnail'] ?? $download ) ),
			'download_url'                  => $download,
			'source_page_url'               => $page,
			'creator_name'                  => $creator,
			'creator_url'                   => esc_url_raw( (string) ( $image['creator_url'] ?? '' ) ),
			'license_code'                  => $license,
			'license_version'               => sanitize_text_field( (string) ( $image['license_version'] ?? '' ) ),
			'license_url'                   => esc_url_raw( (string) ( $image['license_url'] ?? '' ) ),
			'attribution'                   => $attribution,
			'width'                         => absint( $image['width'] ?? 0 ),
			'height'                        => absint( $image['height'] ?? 0 ),
			'mime_type'                     => $this->mime_from_filetype( $filetype ),
			'source'                        => sanitize_key( (string) ( $image['source'] ?? '' ) ),
			'openverse_provider'            => sanitize_key( (string) ( $image['provider'] ?? '' ) ),
			'requires_license_confirmation' => in_array( $license, array( 'by', 'by-sa' ), true ),
		);
	}

	private function mime_from_filetype( string $filetype ): string {
		if ( in_array( $filetype, array( 'jpg', 'jpeg' ), true ) ) {
			return 'image/jpeg';
		}
		if ( 'png' === $filetype ) {
			return 'image/png';
		}
		if ( 'webp' === $filetype ) {
			return 'image/webp';
		}
		return '';
	}
}
