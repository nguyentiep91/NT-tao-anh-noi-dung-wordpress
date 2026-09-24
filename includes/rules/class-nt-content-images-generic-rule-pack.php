<?php
/**
 * Generic rules that are safe defaults for any WordPress website.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generic_Rule_Pack implements NT_Content_Images_Rule_Pack_Interface {
	public function get_id(): string {
		return 'generic';
	}

	public function get_label(): string {
		return __( 'Dùng chung', 'nt-tao-anh-noi-dung-wordpress' );
	}

	public function get_priority(): int {
		return 10;
	}

	public function get_classification_rules(): array {
		return array(
			array( 'content_type' => 'how_to', 'search_intent' => 'procedural', 'keywords' => array( 'hướng dẫn', 'cách ', 'quy trình', 'các bước', 'thực hiện', 'how to', 'tutorial' ), 'weight' => 3 ),
			array( 'content_type' => 'checklist', 'search_intent' => 'procedural', 'keywords' => array( 'checklist', 'danh sách kiểm tra', 'cần chuẩn bị', 'lưu ý', 'sai sót' ), 'weight' => 3 ),
			array( 'content_type' => 'comparison', 'search_intent' => 'comparative', 'keywords' => array( 'so sánh', 'khác nhau', 'phân biệt', 'đối chiếu', ' versus ', ' vs ' ), 'weight' => 4 ),
			array( 'content_type' => 'definition', 'search_intent' => 'informational', 'keywords' => array( ' là gì', 'khái niệm', 'định nghĩa', 'viết tắt', 'meaning of' ), 'weight' => 3 ),
			array( 'content_type' => 'service', 'search_intent' => 'transactional', 'keywords' => array( 'dịch vụ', 'tư vấn', 'liên hệ', 'đăng ký dịch vụ', 'báo giá', 'service' ), 'weight' => 3 ),
			array( 'content_type' => 'event', 'search_intent' => 'event_discovery', 'keywords' => array( 'sự kiện', 'hội thảo', 'khai mạc', 'lịch tổ chức', 'webinar', 'event' ), 'weight' => 3 ),
			array( 'content_type' => 'news', 'search_intent' => 'informational', 'keywords' => array( 'tin tức', 'mới nhất', 'cập nhật', 'thông báo', 'news' ), 'weight' => 2 ),
			array( 'content_type' => 'case_study', 'search_intent' => 'informational', 'keywords' => array( 'tình huống', 'case study', 'kinh nghiệm thực tế', 'ví dụ thực tế' ), 'weight' => 3 ),
			array( 'content_type' => 'general_content', 'search_intent' => 'informational', 'keywords' => array(), 'weight' => 1 ),
		);
	}

	public function get_restrictions( array $source, string $content_type ): array {
		$restrictions = array(
			'no_random_or_unreadable_text',
			'no_fake_logos',
			'no_fake_seals_or_signatures',
			'no_invented_phone_numbers_or_addresses',
			'no_specific_real_person_likeness_without_source',
			'main_text_must_be_rendered_by_plugin_template',
		);

		if ( 'event' === $content_type ) {
			$restrictions[] = 'no_invented_event_date_or_venue_in_generated_background';
		}

		return $restrictions;
	}

	public function get_blocked_heading_terms(): array {
		return array( 'liên hệ', 'contact', 'đăng ký', 'register', 'faq', 'câu hỏi thường gặp', 'kết luận', 'conclusion', 'hotline' );
	}
}
