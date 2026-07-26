<?php
/**
 * Plans stable image anchors without modifying post content.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Placement_Planner {
	/**
	 * Creates safe proposed placements.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @return array<int, array<string, mixed>>
	 */
	public function plan( array $source, int $recommended_images, string $content_type ): array {
		$recommended_images = min( 5, max( 0, $recommended_images ) );

		if ( 0 === $recommended_images ) {
			return array();
		}

		$headings   = is_array( $source['headings'] ?? null ) ? $source['headings'] : array();
		$placements = array(
			array(
				'type'            => 'after_intro',
				'paragraph_index' => 3,
				'safety'          => 'safe_candidate',
				'reason'          => 'first_visual_break_after_opening',
			),
		);

		$candidates = $this->filter_heading_candidates( $headings, $content_type );
		$needed     = $recommended_images - 1;

		if ( 0 < $needed && ! empty( $candidates ) ) {
			$step = max( 1, (int) floor( count( $candidates ) / max( 1, $needed ) ) );

			for ( $i = 0; $i < count( $candidates ) && count( $placements ) < $recommended_images; $i += $step ) {
				$heading      = $candidates[ $i ];
				$placements[] = array(
					'type'          => 'before_heading',
					'heading_text'  => sanitize_text_field( (string) ( $heading['text'] ?? '' ) ),
					'heading_level' => absint( $heading['level'] ?? 2 ),
					'heading_index' => absint( $heading['index'] ?? 0 ),
					'safety'        => 'safe_candidate',
					'reason'        => 'section_transition',
				);
			}
		}

		while ( count( $placements ) < $recommended_images ) {
			$placements[] = array(
				'type'   => 'manual_review_required',
				'safety' => 'warning',
				'reason' => 'insufficient_safe_heading_anchors',
			);
		}

		return array_slice( $placements, 0, $recommended_images );
	}

	/**
	 * Filters headings that commonly contain CTA, FAQ or conclusion content.
	 *
	 * @param array<int, array<string, mixed>> $headings Parsed headings.
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_heading_candidates( array $headings, string $content_type ): array {
		$blocked = array(
			'liên hệ',
			'đăng ký',
			'câu hỏi thường gặp',
			'faq',
			'kết luận',
			'thông tin khóa học',
			'học phí',
			'hotline',
		);
		$result = array();

		foreach ( $headings as $heading ) {
			if ( 2 !== absint( $heading['level'] ?? 0 ) ) {
				continue;
			}

			$text = (string) ( $heading['text'] ?? '' );
			$hay  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
			$skip = false;

			foreach ( $blocked as $needle ) {
				if ( false !== strpos( $hay, $needle ) ) {
					$skip = true;
					break;
				}
			}

			if ( ! $skip ) {
				$result[] = $heading;
			}
		}

		if ( 'course' === $content_type || 'service' === $content_type ) {
			return array_slice( $result, 0, 2 );
		}

		return $result;
	}
}
