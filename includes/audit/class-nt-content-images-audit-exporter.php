<?php
/**
 * Exports filtered audit rows to a UTF-8 CSV file.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Exporter {
	private NT_Content_Images_Audit_Repository $repository;

	/**
	 * Sets exporter dependencies.
	 */
	public function __construct( NT_Content_Images_Audit_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers the authenticated admin-post action.
	 */
	public function register(): void {
		add_action( 'admin_post_nt_content_images_audit_export', array( $this, 'handle' ) );
	}

	/**
	 * Streams the CSV response and terminates the request.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền xuất báo cáo audit.', 'nt-tao-anh-noi-dung-wordpress' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'nt_content_images_audit_export' );

		$filters = $this->read_filters();
		$rows    = $this->repository->get_export_rows( $filters );
		$filename = 'nt-content-images-audit-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Không thể tạo tệp CSV.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv(
			$output,
			array(
				'post_id', 'post_title', 'post_url', 'edit_url', 'post_type', 'post_status',
				'featured_image_id', 'featured_image_status', 'content_image_count',
				'local_image_count', 'external_image_count', 'missing_alt_count',
				'word_count', 'h2_count', 'h3_count', 'shortcode_count',
				'has_complex_blocks', 'yoast_focus_keyphrase', 'priority_score',
				'priority_label', 'audit_status', 'scanned_at',
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array_map(
					array( $this, 'escape_cell' ),
					array(
						$row['post_id'] ?? 0,
						$row['title'] ?? '',
						$row['view_url'] ?? '',
						$row['edit_url'] ?? '',
						$row['post_type'] ?? '',
						$row['post_status'] ?? '',
						$row['featured_image_id'] ?? 0,
						empty( $row['featured_image_id'] ) ? 'missing' : 'present',
						$row['content_image_count'] ?? 0,
						$row['local_image_count'] ?? 0,
						$row['external_image_count'] ?? 0,
						$row['missing_alt_count'] ?? 0,
						$row['word_count'] ?? 0,
						$row['h2_count'] ?? 0,
						$row['h3_count'] ?? 0,
						$row['shortcode_count'] ?? 0,
						$row['has_complex_blocks'] ?? 0,
						$row['yoast_focus_keyphrase'] ?? '',
						$row['priority_score'] ?? 0,
						$row['priority_label'] ?? '',
						$row['audit_status'] ?? '',
						$row['scanned_at'] ?? '',
					)
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Reads only supported filters from the query string.
	 *
	 * @return array<string, string>
	 */
	private function read_filters(): array {
		$keys    = array(
			'search', 'post_type', 'post_status', 'priority_label', 'audit_status',
			'featured', 'content_images', 'has_shortcode', 'has_complex_blocks',
			'external_images', 'missing_alt', 'orderby', 'order',
		);
		$filters = array();

		foreach ( $keys as $key ) {
			$value           = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters[ $key ] = sanitize_text_field( (string) $value );
		}

		return $filters;
	}

	/**
	 * Prevents spreadsheet formula execution while preserving readable values.
	 *
	 * @param mixed $value Cell value.
	 */
	private function escape_cell( $value ): string {
		$value = (string) $value;

		if ( preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
