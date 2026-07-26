<?php
/**
 * Classifies content type and search intent with deterministic rules.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Intent_Classifier {
	/**
	 * Classifies a compact source package.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @return array{content_type: string, search_intent: string, confidence: float, signals: array<int, string>}
	 */
	public function classify( array $source ): array {
		$text    = $this->build_search_text( $source );
		$scores  = array(
			'legal_update'      => $this->score( $text, array( 'nghị định', 'thông tư', 'luật mới', 'có hiệu lực', 'điểm mới', 'quy định mới' ) ),
			'legal_explainer'   => $this->score( $text, array( 'quy định', 'điều kiện', 'căn cứ pháp lý', 'thẩm quyền', 'hồ sơ pháp lý' ) ),
			'how_to'            => $this->score( $text, array( 'hướng dẫn', 'cách ', 'quy trình', 'các bước', 'thực hiện', 'thủ tục' ) ),
			'checklist'         => $this->score( $text, array( 'checklist', 'danh sách kiểm tra', 'cần chuẩn bị', 'lưu ý', 'sai sót' ) ),
			'comparison'        => $this->score( $text, array( 'so sánh', 'khác nhau', 'phân biệt', 'đối chiếu', 'vs ' ) ),
			'definition'        => $this->score( $text, array( ' là gì', 'khái niệm', 'định nghĩa', 'viết tắt' ) ),
			'course'            => $this->score( $text, array( 'khóa học', 'đào tạo', 'học viên', 'giảng viên', 'ôn thi', 'bồi dưỡng' ) ),
			'service'           => $this->score( $text, array( 'dịch vụ', 'tư vấn', 'hỗ trợ hồ sơ', 'liên hệ', 'đăng ký dịch vụ' ) ),
			'news'              => $this->score( $text, array( 'tin tức', 'mới nhất', 'cập nhật', 'thông báo' ) ),
			'event'             => $this->score( $text, array( 'sự kiện', 'hội thảo', 'khai giảng', 'lịch học', 'tổ chức ngày' ) ),
			'case_study'        => $this->score( $text, array( 'tình huống', 'case study', 'kinh nghiệm thực tế', 'ví dụ thực tế' ) ),
			'general_education' => 1,
		);

		arsort( $scores );
		$content_type = (string) array_key_first( $scores );
		$top_score    = (int) reset( $scores );
		$confidence   = min( 0.98, max( 0.35, 0.35 + ( $top_score * 0.12 ) ) );
		$intent       = $this->intent_for_type( $content_type, $text );

		return array(
			'content_type'  => $content_type,
			'search_intent' => $intent,
			'confidence'    => round( $confidence, 2 ),
			'signals'       => $this->collect_signals( $text, $content_type ),
		);
	}

	/**
	 * Builds a normalized Vietnamese search string.
	 */
	private function build_search_text( array $source ): string {
		$terms = array();

		foreach ( (array) ( $source['taxonomies'] ?? array() ) as $taxonomy_terms ) {
			foreach ( (array) $taxonomy_terms as $term ) {
				$terms[] = is_array( $term ) ? (string) ( $term['name'] ?? '' ) : (string) $term;
			}
		}

		$text = implode(
			' ',
			array(
				(string) ( $source['title'] ?? '' ),
				(string) ( $source['focus_keyphrase'] ?? '' ),
				(string) ( $source['excerpt'] ?? '' ),
				implode( ' ', $terms ),
			)
		);

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Scores keyword matches.
	 *
	 * @param string[] $needles Keywords.
	 */
	private function score( string $text, array $needles ): int {
		$score = 0;

		foreach ( $needles as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				++$score;
			}
		}

		return $score;
	}

	/**
	 * Maps content type to primary search intent.
	 */
	private function intent_for_type( string $type, string $text ): string {
		if ( in_array( $type, array( 'service', 'course' ), true ) ) {
			return 'transactional';
		}

		if ( 'event' === $type ) {
			return 'event_discovery';
		}

		if ( 'comparison' === $type ) {
			return 'comparative';
		}

		if ( in_array( $type, array( 'how_to', 'checklist' ), true ) ) {
			return 'procedural';
		}

		if ( false !== strpos( $text, 'đăng nhập' ) || false !== strpos( $text, 'truy cập' ) ) {
			return 'navigational';
		}

		return 'informational';
	}

	/**
	 * Returns explainable classifier signals.
	 *
	 * @return string[]
	 */
	private function collect_signals( string $text, string $type ): array {
		$signals = array( 'rule:' . $type );

		foreach ( array( 'nghị định', 'hướng dẫn', 'so sánh', 'khóa học', 'dịch vụ', 'hội thảo', 'checklist' ) as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				$signals[] = 'keyword:' . sanitize_title( $needle );
			}
		}

		return array_values( array_unique( $signals ) );
	}
}
