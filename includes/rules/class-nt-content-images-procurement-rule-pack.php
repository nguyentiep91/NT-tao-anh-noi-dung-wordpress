<?php
/**
 * Optional procurement and tendering rule pack.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Procurement_Rule_Pack implements NT_Content_Images_Rule_Pack_Interface {
	public function get_id(): string {
		return 'procurement';
	}

	public function get_label(): string {
		return __( 'Đấu thầu và mua sắm', 'nt-tao-anh-noi-dung-wordpress' );
	}

	public function get_priority(): int {
		return 50;
	}

	public function get_classification_rules(): array {
		return array(
			array( 'content_type' => 'procurement_guide', 'search_intent' => 'procedural', 'keywords' => array( 'đấu thầu', 'e-hsmt', 'e-hsdt', 'hồ sơ mời thầu', 'hồ sơ dự thầu', 'vneps', 'muasamcong' ), 'weight' => 5 ),
			array( 'content_type' => 'procurement_service', 'search_intent' => 'transactional', 'keywords' => array( 'dịch vụ đấu thầu', 'tư vấn hồ sơ dự thầu', 'hỗ trợ đấu thầu', 'đăng ký mạng đấu thầu' ), 'weight' => 6 ),
			array( 'content_type' => 'procurement_checklist', 'search_intent' => 'procedural', 'keywords' => array( 'checklist hồ sơ', 'lỗi hồ sơ dự thầu', 'kiểm tra e-hsdt', 'chuẩn bị e-hsmt' ), 'weight' => 6 ),
		);
	}

	public function get_restrictions( array $source, string $content_type ): array {
		$text = $this->normalize_source( $source );
		if ( false === strpos( $text, 'đấu thầu' ) && false === strpos( $text, 'vneps' ) && false === strpos( $text, 'e-hs' ) && false === strpos( $text, 'muasamcong' ) ) {
			return array();
		}

		return array(
			'no_fake_vneps_or_procurement_portal_ui',
			'no_invented_tender_codes_or_notices',
			'no_fake_bid_result_or_contract_award',
			'no_official_procurement_screen_replica',
		);
	}

	public function get_blocked_heading_terms(): array {
		return array( 'thông tin liên hệ', 'dịch vụ hỗ trợ', 'đăng ký tài khoản', 'hotline', 'mẫu hồ sơ tải về' );
	}

	/**
	 * @param array<string, mixed> $source Source package.
	 */
	private function normalize_source( array $source ): string {
		$text = implode( ' ', array( (string) ( $source['title'] ?? '' ), (string) ( $source['focus_keyphrase'] ?? '' ), (string) ( $source['excerpt'] ?? '' ) ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
