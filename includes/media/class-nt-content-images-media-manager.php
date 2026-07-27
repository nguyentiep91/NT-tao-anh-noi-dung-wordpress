<?php
/**
 * Saves generated or imported image bytes into the WordPress Media Library.
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
		return $this->store_candidate(
			$post_id,
			$image,
			$settings,
			array(
				'source_kind' => 'ai',
				'provider'    => sanitize_key( (string) ( $image['provider'] ?? 'ai' ) ),
				'prompt_hash' => hash( 'sha256', $prompt ),
				'caption'     => __( 'Ảnh được tạo tự động từ nội dung bài viết và đang chờ duyệt.', 'nt-tao-anh-noi-dung-wordpress' ),
			)
		);
	}

	/**
	 * Saves one in-article illustration with its own section-specific alt text.
	 *
	 * @param array<string, mixed> $image Provider image response.
	 * @param array<string, mixed> $meta  alt_text and caption for the section.
	 * @return array<string, mixed>|WP_Error
	 */
	public function store_content_candidate( int $post_id, array $image, string $prompt, array $settings, array $meta = array() ) {
		return $this->store_candidate(
			$post_id,
			$image,
			$settings,
			array(
				'source_kind' => 'ai',
				'provider'    => sanitize_key( (string) ( $image['provider'] ?? 'ai' ) ),
				'prompt_hash' => hash( 'sha256', $prompt ),
				'alt_text'    => sanitize_text_field( (string) ( $meta['alt_text'] ?? '' ) ),
				'caption'     => sanitize_text_field( (string) ( $meta['caption'] ?? '' ) ),
				'title'       => sanitize_text_field( (string) ( $meta['title'] ?? '' ) ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $image Downloaded image bytes.
	 * @param array<string, mixed> $asset Normalized stock metadata.
	 * @param array<string, mixed> $settings Image settings.
	 * @return array<string, mixed>|WP_Error
	 */
	public function store_stock_candidate( int $post_id, array $image, array $asset, array $settings ) {
		return $this->store_candidate(
			$post_id,
			$image,
			$settings,
			array(
				'source_kind'       => 'stock',
				'provider'          => sanitize_key( (string) ( $asset['provider'] ?? 'stock' ) ),
				'provider_asset_id' => sanitize_text_field( (string) ( $asset['asset_id'] ?? '' ) ),
				'source_page_url'   => esc_url_raw( (string) ( $asset['source_page_url'] ?? '' ) ),
				'creator_name'      => sanitize_text_field( (string) ( $asset['creator_name'] ?? '' ) ),
				'creator_url'       => esc_url_raw( (string) ( $asset['creator_url'] ?? '' ) ),
				'license_code'      => sanitize_key( (string) ( $asset['license_code'] ?? '' ) ),
				'license_url'       => esc_url_raw( (string) ( $asset['license_url'] ?? '' ) ),
				'attribution'       => sanitize_text_field( (string) ( $asset['attribution'] ?? '' ) ),
				'caption'           => sanitize_text_field( (string) ( $asset['attribution'] ?? __( 'Ảnh kho được nhập và đang chờ duyệt.', 'nt-tao-anh-noi-dung-wordpress' ) ) ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $image Image bytes and MIME.
	 * @param array<string, mixed> $settings Target dimensions.
	 * @param array<string, mixed> $metadata Trace metadata.
	 * @return array<string, mixed>|WP_Error
	 */
	private function store_candidate( int $post_id, array $image, array $settings, array $metadata ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'ntci_media_post_missing', __( 'Không tìm thấy nội dung để lưu ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$bytes = (string) ( $image['bytes'] ?? '' );
		if ( '' === $bytes || strlen( $bytes ) > 25165824 ) {
			return new WP_Error( 'ntci_media_bytes_missing', __( 'Dữ liệu ảnh trống hoặc vượt quá 25 MB.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$info = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $bytes ) : false;
		$detected_mime = is_array( $info ) ? sanitize_mime_type( (string) ( $info['mime'] ?? '' ) ) : '';
		if ( ! in_array( $detected_mime, array( 'image/webp', 'image/png', 'image/jpeg' ), true ) ) {
			return new WP_Error( 'ntci_media_mime_invalid', __( 'Dữ liệu không phải ảnh PNG, JPEG hoặc WebP hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		/**
		 * Provider PNG (FLUX trả về ~1.5 MB/ảnh) được nén sang WebP trước khi
		 * lưu để giảm dung lượng trang. Tắt bằng filter khi cần giữ nguyên gốc.
		 */
		if ( apply_filters( 'nt_content_images_convert_webp', true, $detected_mime, $metadata ) ) {
			$converted = self::convert_bytes_to_webp( $bytes, $detected_mime, absint( apply_filters( 'nt_content_images_webp_quality', 82 ) ) );
			if ( null !== $converted && strlen( $converted['bytes'] ) < strlen( $bytes ) ) {
				$bytes         = $converted['bytes'];
				$detected_mime = $converted['mime'];
			}
		}

		/**
		 * Chống trùng lặp: cùng bài viết, cùng kích thước đích và cùng byte ảnh
		 * thì tái sử dụng attachment sẵn có thay vì tạo file mới trong Media Library.
		 */
		$bytes_hash = hash( 'sha256', $post_id . '|' . absint( $settings['target_width'] ?? 1280 ) . 'x' . absint( $settings['target_height'] ?? 720 ) . '|' . $bytes );
		$existing   = $this->find_reusable_duplicate( $bytes_hash );
		if ( null !== $existing ) {
			return $existing;
		}

		$extension = 'image/png' === $detected_mime ? 'png' : ( 'image/jpeg' === $detected_mime ? 'jpg' : 'webp' );
		$slug = sanitize_title( get_the_title( $post ) );
		$suffix = sanitize_key( (string) ( $metadata['provider'] ?? $metadata['source_kind'] ?? 'image' ) );
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['path'] ) ) {
			return new WP_Error( 'ntci_media_upload_dir_failed', sanitize_text_field( (string) ( $uploads['error'] ?? __( 'Thư mục uploads không khả dụng.', 'nt-tao-anh-noi-dung-wordpress' ) ) ) );
		}
		$filename = wp_unique_filename( $uploads['path'], ( $slug ?: 'featured-image-' . $post_id ) . '-' . ( $suffix ?: 'image' ) . '.' . $extension );
		$upload = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'ntci_media_upload_failed', sanitize_text_field( (string) $upload['error'] ) );
		}

		$file = (string) $upload['file'];
		$this->crop_to_target( $file, absint( $settings['target_width'] ?? 1280 ), absint( $settings['target_height'] ?? 720 ) );
		$attachment_title = '' !== (string) ( $metadata['title'] ?? '' ) ? (string) $metadata['title'] : get_the_title( $post );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $detected_mime,
				'post_title'     => sanitize_text_field( $attachment_title ),
				'post_content'   => '',
				'post_excerpt'   => sanitize_text_field( (string) ( $metadata['caption'] ?? '' ) ),
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
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $attachment_metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $attachment_metadata );
		}
		$alt_text = '' !== (string) ( $metadata['alt_text'] ?? '' ) ? (string) $metadata['alt_text'] : get_the_title( $post );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		update_post_meta( $attachment_id, '_nt_content_images_generated', 'ai' === (string) ( $metadata['source_kind'] ?? '' ) ? '1' : '0' );
		update_post_meta( $attachment_id, '_nt_content_images_source_post_id', $post_id );
		update_post_meta( $attachment_id, '_ntci_bytes_hash', $bytes_hash );
		$text_meta = array(
			'_ntci_source_kind'              => $metadata['source_kind'] ?? '',
			'_ntci_source_provider'          => $metadata['provider'] ?? '',
			'_ntci_source_asset_id'          => $metadata['provider_asset_id'] ?? '',
			'_ntci_creator_name'             => $metadata['creator_name'] ?? '',
			'_ntci_license_code'             => $metadata['license_code'] ?? '',
			'_ntci_attribution_text'         => $metadata['attribution'] ?? '',
			'_nt_content_images_prompt_hash' => $metadata['prompt_hash'] ?? '',
		);
		foreach ( $text_meta as $key => $value ) {
			if ( '' !== (string) $value ) {
				update_post_meta( $attachment_id, $key, sanitize_text_field( (string) $value ) );
			}
		}
		foreach ( array(
			'_ntci_source_page_url' => $metadata['source_page_url'] ?? '',
			'_ntci_creator_url'     => $metadata['creator_url'] ?? '',
			'_ntci_license_url'     => $metadata['license_url'] ?? '',
		) as $key => $value ) {
			if ( '' !== (string) $value ) {
				update_post_meta( $attachment_id, $key, esc_url_raw( (string) $value ) );
			}
		}

		$final_info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$final_size = file_exists( $file ) ? filesize( $file ) : false;
		$final_checksum = file_exists( $file ) ? hash_file( 'sha256', $file ) : false;
		return array(
			'attachment_id' => absint( $attachment_id ),
			'file'          => $file,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'mime_type'     => $detected_mime,
			'width'         => is_array( $final_info ) ? absint( $final_info[0] ?? 0 ) : absint( $info[0] ?? 0 ),
			'height'        => is_array( $final_info ) ? absint( $final_info[1] ?? 0 ) : absint( $info[1] ?? 0 ),
			'file_size'     => false === $final_size ? strlen( $bytes ) : absint( $final_size ),
			'checksum'      => is_string( $final_checksum ) ? $final_checksum : hash( 'sha256', $bytes ),
		);
	}

	/**
	 * Finds an existing plugin attachment holding exactly the same image bytes.
	 *
	 * @return array<string, mixed>|null Same shape as store_candidate() result, null when no duplicate.
	 */
	private function find_reusable_duplicate( string $bytes_hash ): ?array {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'attachment' WHERE pm.meta_key = '_ntci_bytes_hash' AND pm.meta_value = %s ORDER BY pm.post_id DESC LIMIT 1",
			$bytes_hash
		);
		$attachment_id = absint( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( $attachment_id < 1 ) {
			return null;
		}
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || ! file_exists( $file ) ) {
			return null;
		}
		$info     = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$checksum = hash_file( 'sha256', $file );
		return array(
			'attachment_id' => $attachment_id,
			'file'          => $file,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'mime_type'     => (string) get_post_mime_type( $attachment_id ),
			'width'         => is_array( $info ) ? absint( $info[0] ?? 0 ) : 0,
			'height'        => is_array( $info ) ? absint( $info[1] ?? 0 ) : 0,
			'file_size'     => absint( (int) filesize( $file ) ),
			'checksum'      => is_string( $checksum ) ? $checksum : '',
			'reused'        => true,
		);
	}

	/**
	 * Re-encodes PNG/JPEG bytes as WebP when GD supports it.
	 *
	 * @return array{bytes: string, mime: string}|null Null when conversion is unavailable or failed.
	 */
	public static function convert_bytes_to_webp( string $bytes, string $mime, int $quality = 82 ) {
		if ( 'image/webp' === $mime || '' === $bytes ) {
			return null;
		}
		if ( ! function_exists( 'imagewebp' ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $image ) {
			return null;
		}
		imagepalettetotruecolor( $image );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		ob_start();
		$ok      = imagewebp( $image, null, max( 1, min( 100, $quality ) ) );
		$encoded = (string) ob_get_clean();
		imagedestroy( $image );
		if ( ! $ok || '' === $encoded ) {
			return null;
		}
		return array(
			'bytes' => $encoded,
			'mime'  => 'image/webp',
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
