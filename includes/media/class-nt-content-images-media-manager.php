<?php
/**
 * Saves generated image bytes into the WordPress Media Library.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Media_Manager {
	/**
	 * @param array<string, mixed> $image Provider image response.
	 * @param array<string, mixed> $settings Generation settings.
	 * @return array<string, mixed>|WP_Error
	 */
	public function store_featured_candidate( int $post_id, array $image, string $prompt, array $settings ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'ntci_media_post_missing', __( 'Không tìm thấy nội dung để lưu ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$bytes = (string) ( $image['bytes'] ?? '' );
		if ( '' === $bytes ) {
			return new WP_Error( 'ntci_media_bytes_missing', __( 'Dữ liệu ảnh trống.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$extension = sanitize_key( (string) ( $image['extension'] ?? 'webp' ) );
		$extension = in_array( $extension, array( 'webp', 'png', 'jpg', 'jpeg' ), true ) ? $extension : 'webp';
		$slug      = sanitize_title( get_the_title( $post ) );
		$filename  = wp_unique_filename( wp_upload_dir()['path'], ( $slug ?: 'featured-image-' . $post_id ) . '-ai.' . $extension );
		$upload    = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'ntci_media_upload_failed', sanitize_text_field( (string) $upload['error'] ) );
		}

		$file = (string) $upload['file'];
		$this->crop_to_target( $file, absint( $settings['target_width'] ?? 1280 ), absint( $settings['target_height'] ?? 720 ) );
		$filetype = wp_check_filetype( basename( $file ), null );
		$mime     = (string) ( $filetype['type'] ?: ( $image['mime_type'] ?? 'image/webp' ) );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => sanitize_mime_type( $mime ),
				'post_title'     => sanitize_text_field( get_the_title( $post ) ),
				'post_content'   => '',
				'post_excerpt'   => __( 'Ảnh được tạo tự động từ nội dung bài viết và đang chờ duyệt.', 'nt-tao-anh-noi-dung-wordpress' ),
				'post_status'    => 'inherit',
			),
			$file,
			$post_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $file );
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( get_the_title( $post ) ) );
		update_post_meta( $attachment_id, '_nt_content_images_generated', '1' );
		update_post_meta( $attachment_id, '_nt_content_images_source_post_id', $post_id );
		update_post_meta( $attachment_id, '_nt_content_images_prompt_hash', hash( 'sha256', $prompt ) );

		return array(
			'attachment_id' => absint( $attachment_id ),
			'file'          => $file,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'mime_type'     => $mime,
		);
	}

	private function crop_to_target( string $file, int $width, int $height ): void {
		if ( $width < 1 || $height < 1 ) {
			return;
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return;
		}
		$resized = $editor->resize( $width, $height, true );
		if ( is_wp_error( $resized ) ) {
			return;
		}
		$editor->save( $file );
	}
}
