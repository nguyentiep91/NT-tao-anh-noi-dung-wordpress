<?php
/**
 * Builds a compact, sanitized source package for image brief generation.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_Source_Builder {
	private NT_Content_Images_Audit_Repository $audit_repository;

	/**
	 * Sets dependencies.
	 */
	public function __construct( NT_Content_Images_Audit_Repository $audit_repository ) {
		$this->audit_repository = $audit_repository;
	}

	/**
	 * Builds a source package without rendering blocks or executing shortcodes.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function build( int $post_id ) {
		$post  = get_post( $post_id );
		$audit = $this->audit_repository->get_by_post_id( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'ntci_brief_post_not_found', __( 'Không tìm thấy bài viết.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		if ( null === $audit || 'scanned' !== (string) ( $audit['audit_status'] ?? '' ) ) {
			return new WP_Error( 'ntci_brief_audit_required', __( 'Bài viết phải được audit thành công trước khi tạo brief.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$analysis = isset( $audit['analysis'] ) && is_array( $audit['analysis'] ) ? $audit['analysis'] : array();

		return array(
			'post_id'               => $post_id,
			'post_type'             => sanitize_key( $post->post_type ),
			'post_status'           => sanitize_key( $post->post_status ),
			'title'                 => sanitize_text_field( get_the_title( $post ) ),
			'slug'                  => sanitize_title( $post->post_name ),
			'excerpt'               => $this->build_excerpt( $post ),
			'intro'                 => $this->extract_intro( (string) $post->post_content ),
			'headings'              => $this->extract_headings( (string) $post->post_content ),
			'taxonomies'            => is_array( $analysis['taxonomies'] ?? null ) ? $analysis['taxonomies'] : array(),
			'focus_keyphrase'       => sanitize_text_field( (string) ( $audit['yoast_focus_keyphrase'] ?? '' ) ),
			'word_count'            => absint( $audit['word_count'] ?? 0 ),
			'featured_image_id'     => absint( $audit['featured_image_id'] ?? 0 ),
			'content_image_count'   => absint( $audit['content_image_count'] ?? 0 ),
			'priority_score'        => absint( $audit['priority_score'] ?? 0 ),
			'priority_label'        => sanitize_key( (string) ( $audit['priority_label'] ?? 'low' ) ),
			'has_shortcode'         => ! empty( $audit['has_shortcode'] ),
			'has_complex_blocks'    => ! empty( $audit['has_complex_blocks'] ),
			'shortcodes'            => is_array( $analysis['shortcodes'] ?? null ) ? array_values( $analysis['shortcodes'] ) : array(),
			'block_names'           => is_array( $analysis['block_names'] ?? null ) ? array_values( $analysis['block_names'] ) : array(),
			'source_content_hash'   => sanitize_text_field( (string) ( $audit['content_hash'] ?? '' ) ),
			'modified_at'           => sanitize_text_field( (string) $post->post_modified_gmt ),
		);
	}

	/**
	 * Returns a compact sanitized excerpt.
	 */
	private function build_excerpt( WP_Post $post ): string {
		$excerpt = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		$excerpt = wp_strip_all_tags( strip_shortcodes( $excerpt ), true );

		return sanitize_textarea_field( wp_trim_words( $excerpt, 70, '…' ) );
	}

	/**
	 * Extracts the opening text without rendering blocks.
	 */
	private function extract_intro( string $content ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $content ), true );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;

		return sanitize_textarea_field( wp_trim_words( trim( $text ), 120, '…' ) );
	}

	/**
	 * Extracts H2/H3 anchors from serialized content.
	 *
	 * @return array<int, array{level: int, text: string, index: int}>
	 */
	private function extract_headings( string $content ): array {
		$headings  = array();
		$processor = new WP_HTML_Tag_Processor( $content );
		$index     = 0;

		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();

			if ( ! in_array( $tag, array( 'H2', 'H3' ), true ) ) {
				continue;
			}

			$text = '';

			if ( method_exists( $processor, 'get_modifiable_text' ) ) {
				$text = (string) $processor->get_modifiable_text();
			}

			if ( '' === trim( $text ) ) {
				continue;
			}

			++$index;
			$headings[] = array(
				'level' => 'H2' === $tag ? 2 : 3,
				'text'  => sanitize_text_field( wp_strip_all_tags( $text ) ),
				'index' => $index,
			);
		}

		if ( empty( $headings ) && preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				++$index;
				$headings[] = array(
					'level' => absint( $match[1] ),
					'text'  => sanitize_text_field( wp_strip_all_tags( $match[2] ) ),
					'index' => $index,
				);
			}
		}

		return array_slice( $headings, 0, 30 );
	}
}
