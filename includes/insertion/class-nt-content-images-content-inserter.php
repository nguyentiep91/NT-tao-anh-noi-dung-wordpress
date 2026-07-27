<?php
/**
 * Inserts approved in-article images into post_content with undo support.
 *
 * A snapshot of the original content is stored before the first insertion;
 * rollback restores it verbatim. Anchors that cannot be found are skipped —
 * the inserter never guesses positions.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Inserter {
	public const SNAPSHOT_META = '_ntci_content_snapshot';

	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Safe_Logger $logger;

	public function __construct( NT_Content_Images_Generation_Repository $repository, ?NT_Content_Images_Safe_Logger $logger = null ) {
		$this->repository = $repository;
		$this->logger     = $logger ?? new NT_Content_Images_Safe_Logger();
	}

	/**
	 * Inserts every approved-but-not-inserted content image of the post.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function insert( int $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'ntci_insert_post_missing', __( 'Không tìm thấy bài viết.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$by_index         = array();
		$inserted_indexes = array();
		foreach ( $this->repository->get_by_post( $post_id ) as $record ) {
			if ( 'content' !== (string) ( $record['settings']['image_type'] ?? '' ) ) {
				continue;
			}
			$index = absint( $record['settings']['image_index'] ?? 0 );
			if ( 'inserted' === (string) $record['status'] ) {
				$inserted_indexes[ $index ] = true;
			}
			if ( 'approved' === (string) $record['status'] ) {
				// Một slot chỉ chèn một ảnh: bản ghi mới nhất (thường là bản đã chèn chữ) thắng.
				$by_index[ $index ] = $record;
			}
		}
		// Slot đã có ảnh nằm trong bài thì không chèn thêm để tránh trùng.
		$by_index = array_diff_key( $by_index, $inserted_indexes );
		if ( array() === $by_index ) {
			return new WP_Error( 'ntci_insert_nothing_approved', __( 'Chưa có ảnh nội dung nào được duyệt để chèn. Hãy duyệt ảnh trước.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		ksort( $by_index );
		$records = array_values( $by_index );

		$original = (string) $post->post_content;
		$content  = $original;
		$inserted = array();
		$skipped  = array();
		foreach ( $records as $record ) {
			$attachment_id = absint( $record['attachment_id'] );
			if ( 0 === $attachment_id || ! get_post( $attachment_id ) ) {
				$skipped[] = array( 'id' => absint( $record['id'] ), 'reason' => 'attachment_missing' );
				continue;
			}
			$placement = is_array( $record['settings']['placement'] ?? null ) ? $record['settings']['placement'] : array();
			$figure    = $this->build_figure( $attachment_id, self::is_block_content( $content ) );
			if ( '' === $figure ) {
				$skipped[] = array( 'id' => absint( $record['id'] ), 'reason' => 'image_url_missing' );
				continue;
			}
			$updated = self::inject_placement( $content, $placement, $figure );
			if ( null === $updated ) {
				$skipped[] = array( 'id' => absint( $record['id'] ), 'reason' => 'anchor_not_found' );
				continue;
			}
			$content    = $updated;
			$inserted[] = absint( $record['id'] );
		}

		if ( array() === $inserted ) {
			return new WP_Error( 'ntci_insert_no_anchor', __( 'Không tìm được vị trí an toàn nào trong nội dung để chèn ảnh.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'skipped' => $skipped ) );
		}

		if ( '' === (string) get_post_meta( $post_id, self::SNAPSHOT_META, true ) ) {
			$snapshot_json = wp_json_encode(
				array(
					'content'    => $original,
					'created_at' => time(),
					'created_by' => get_current_user_id(),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
			// wp_slash() vì update_post_meta/wp_update_post luôn unslash dữ liệu đầu vào — thiếu nó JSON sẽ hỏng.
			update_post_meta( $post_id, self::SNAPSHOT_META, wp_slash( (string) $snapshot_json ) );
		}

		$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $content ) ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		foreach ( $inserted as $record_id ) {
			$this->repository->update_status( $record_id, 'inserted' );
		}
		$this->logger->log( 'info', 'content_images_inserted', array( 'post_id' => $post_id, 'inserted' => $inserted, 'skipped' => $skipped ) );
		do_action( 'nt_content_images_after_insert', $post_id, $inserted );

		return array(
			'post_id'  => $post_id,
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'view_url' => get_permalink( $post_id ),
		);
	}

	/**
	 * Restores the pre-insertion content snapshot.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function rollback( int $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'ntci_rollback_post_missing', __( 'Không tìm thấy bài viết.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$raw      = (string) get_post_meta( $post_id, self::SNAPSHOT_META, true );
		$snapshot = json_decode( $raw, true );
		if ( ! is_array( $snapshot ) || ! is_string( $snapshot['content'] ?? null ) ) {
			return new WP_Error( 'ntci_rollback_snapshot_missing', __( 'Bài viết này không có bản lưu trước khi chèn ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( (string) $snapshot['content'] ) ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		delete_post_meta( $post_id, self::SNAPSHOT_META );
		$restored = array();
		foreach ( $this->repository->get_by_post( $post_id ) as $record ) {
			if ( 'content' === (string) ( $record['settings']['image_type'] ?? '' ) && 'inserted' === (string) $record['status'] ) {
				$this->repository->update_status( absint( $record['id'] ), 'approved' );
				$restored[] = absint( $record['id'] );
			}
		}
		$this->logger->log( 'info', 'content_images_rolled_back', array( 'post_id' => $post_id, 'records' => $restored ) );
		return array( 'post_id' => $post_id, 'restored_records' => $restored );
	}

	public static function is_block_content( string $content ): bool {
		return false !== strpos( $content, '<!-- wp:' );
	}

	/**
	 * Pure string insertion — returns the new content or null when no safe
	 * anchor matches the placement. Never appends blindly.
	 */
	public static function inject_placement( string $content, array $placement, string $figure ): ?string {
		$type = (string) ( $placement['type'] ?? '' );
		if ( 'after_intro' === $type ) {
			return self::inject_after_paragraph( $content, max( 1, absint( $placement['paragraph_index'] ?? 3 ) ), $figure );
		}
		if ( 'before_heading' === $type ) {
			return self::inject_before_heading( $content, (string) ( $placement['heading_text'] ?? '' ), $figure );
		}
		return null;
	}

	private static function inject_after_paragraph( string $content, int $paragraph_index, string $figure ): ?string {
		$closer = self::is_block_content( $content ) ? '<!-- /wp:paragraph -->' : '</p>';
		$offset = 0;
		$found  = 0;
		$anchor = -1;
		while ( false !== ( $position = stripos( $content, $closer, $offset ) ) ) {
			$found++;
			$anchor = $position + strlen( $closer );
			if ( $found >= $paragraph_index ) {
				break;
			}
			$offset = $anchor;
		}
		if ( 0 === $found ) {
			return null;
		}
		return substr( $content, 0, $anchor ) . $figure . substr( $content, $anchor );
	}

	private static function inject_before_heading( string $content, string $heading_text, string $figure ): ?string {
		$needle = self::normalize_text( $heading_text );
		if ( '' === $needle ) {
			return null;
		}
		if ( self::is_block_content( $content ) ) {
			$offset = 0;
			while ( false !== ( $start = strpos( $content, '<!-- wp:heading', $offset ) ) ) {
				$end = strpos( $content, '<!-- /wp:heading -->', $start );
				if ( false === $end ) {
					break;
				}
				$block = substr( $content, $start, $end - $start );
				if ( self::normalize_text( wp_strip_all_tags( $block ) ) === $needle ) {
					return substr( $content, 0, $start ) . $figure . substr( $content, $start );
				}
				$offset = $end + 1;
			}
			return null;
		}
		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $key => $match ) {
				if ( self::normalize_text( wp_strip_all_tags( (string) $matches[2][ $key ][0] ) ) === $needle ) {
					return substr( $content, 0, (int) $match[1] ) . $figure . substr( $content, (int) $match[1] );
				}
			}
		}
		return null;
	}

	private static function normalize_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		$text = trim( $text );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/** Builds the figure markup (Gutenberg image block or plain figure). */
	private function build_figure( int $attachment_id, bool $blocks ): string {
		$url = (string) wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( '' === $url ) {
			return '';
		}
		$alt     = sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$title   = sanitize_text_field( (string) get_the_title( $attachment_id ) );
		$caption = sanitize_text_field( (string) get_post_field( 'post_excerpt', $attachment_id ) );
		$figcaption = '' !== $caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : '';
		$title_attr = '' !== $title ? ' title="' . esc_attr( $title ) . '"' : '';
		$figure  = '<figure class="wp-block-image size-large ntci-content-image"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $title_attr . ' class="wp-image-' . absint( $attachment_id ) . '"/>' . $figcaption . '</figure>';
		if ( $blocks ) {
			return "\n\n<!-- wp:image {\"id\":" . absint( $attachment_id ) . ",\"sizeSlug\":\"large\",\"linkDestination\":\"none\",\"className\":\"ntci-content-image\"} -->\n" . $figure . "\n<!-- /wp:image -->\n\n";
		}
		return "\n\n" . $figure . "\n\n";
	}
}
