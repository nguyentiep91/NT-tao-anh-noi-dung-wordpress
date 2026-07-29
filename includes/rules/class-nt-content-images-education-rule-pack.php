<?php
/**
 * Optional education and training rule pack.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Education_Rule_Pack implements NT_Content_Images_Rule_Pack_Interface {
	public function get_id(): string {
		return 'education';
	}

	public function get_label(): string {
		return __( 'Giáo dục và đào tạo', 'nt-tao-anh-noi-dung-wordpress' );
	}

	public function get_priority(): int {
		return 30;
	}

	public function get_classification_rules(): array {
		return array(
			array( 'content_type' => 'course', 'search_intent' => 'transactional', 'keywords' => array( 'khóa học', 'đào tạo', 'học viên', 'giảng viên', 'bồi dưỡng', 'ôn thi', 'course', 'training' ), 'weight' => 5 ),
			array( 'content_type' => 'education_guide', 'search_intent' => 'procedural', 'keywords' => array( 'bài giảng', 'giáo trình', 'tài liệu học', 'hướng dẫn học', 'luyện thi' ), 'weight' => 4 ),
			array( 'content_type' => 'education_event', 'search_intent' => 'event_discovery', 'keywords' => array( 'khai giảng', 'lịch học', 'lịch thi', 'buổi học', 'lớp học' ), 'weight' => 5 ),
		);
	}

	public function get_restrictions( array $source, string $content_type ): array {
		$restrictions = array();

		if ( in_array( $content_type, array( 'course', 'education_guide', 'education_event' ), true ) ) {
			$restrictions[] = 'no_invented_course_schedule_or_fee';
			$restrictions[] = 'no_fake_instructor_identity_or_qualification';
			$restrictions[] = 'no_false_education_accreditation_claim';
		}

		return $restrictions;
	}

	public function get_blocked_heading_terms(): array {
		return array( 'học phí', 'lịch học', 'đăng ký khóa học', 'thông tin khóa học', 'giảng viên', 'hotline' );
	}
}
