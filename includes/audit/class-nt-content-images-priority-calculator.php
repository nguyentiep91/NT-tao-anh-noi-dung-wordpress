<?php
/**
 * Calculates image-work priority from normalized audit signals.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Priority_Calculator {
	/**
	 * Calculates a capped score, label and recommended image count.
	 *
	 * @param array<string, mixed> $signals Audit signals.
	 * @return array{score: int, label: string, recommended_content_images: int, reasons: array<int, string>}
	 */
	public function calculate( array $signals ): array {
		$score        = 0;
		$reasons      = array();
		$word_count   = absint( $signals['word_count'] ?? 0 );
		$image_count  = absint( $signals['content_image_count'] ?? 0 );
		$featured_id  = absint( $signals['featured_image_id'] ?? 0 );
		$post_status  = sanitize_key( (string) ( $signals['post_status'] ?? 'draft' ) );
		$focus_key    = trim( (string) ( $signals['yoast_focus_keyphrase'] ?? '' ) );
		$recommended  = $this->recommended_content_images( $word_count );

		if ( 'publish' === $post_status ) {
			$score     += 20;
			$reasons[] = 'published';
		} elseif ( in_array( $post_status, array( 'draft', 'pending', 'private' ), true ) ) {
			$score     -= 15;
			$reasons[] = 'not_published';
		}

		if ( 0 === $featured_id ) {
			$score     += 30;
			$reasons[] = 'missing_featured_image';
		}

		if ( 0 === $image_count ) {
			$score     += 25;
			$reasons[] = 'no_content_images';
		} elseif ( $image_count < $recommended ) {
			$score     += 10;
			$reasons[] = 'insufficient_content_images';
		} elseif ( 0 !== $featured_id ) {
			$score     -= 20;
			$reasons[] = 'image_coverage_sufficient';
		}

		if ( $word_count >= 3000 ) {
			$score     += 20;
			$reasons[] = 'very_long_content';
		} elseif ( $word_count >= 2000 ) {
			$score     += 15;
			$reasons[] = 'long_content';
		} elseif ( $word_count >= 1500 ) {
			$score     += 10;
			$reasons[] = 'medium_long_content';
		}

		if ( '' !== $focus_key ) {
			$score     += 5;
			$reasons[] = 'has_focus_keyphrase';
		}

		/**
		 * Filters the raw priority score before it is capped to 0–100.
		 *
		 * @param int                  $score   Raw score.
		 * @param array<string, mixed> $signals Audit signals.
		 */
		$score = (int) apply_filters( 'nt_content_images_priority_score', $score, $signals );
		$score = max( 0, min( 100, $score ) );

		return array(
			'score'                      => $score,
			'label'                      => $this->label_for_score( $score ),
			'recommended_content_images' => $recommended,
			'reasons'                    => array_values( array_unique( $reasons ) ),
		);
	}

	/**
	 * Returns the recommended number of in-content images by article length.
	 */
	private function recommended_content_images( int $word_count ): int {
		if ( $word_count > 3000 ) {
			return 3;
		}

		if ( $word_count >= 1500 ) {
			return 2;
		}

		return 1;
	}

	/**
	 * Maps the score to a stable machine-readable label.
	 */
	private function label_for_score( int $score ): string {
		if ( $score >= 80 ) {
			return 'very_high';
		}

		if ( $score >= 60 ) {
			return 'high';
		}

		if ( $score >= 40 ) {
			return 'medium';
		}

		return 'low';
	}
}