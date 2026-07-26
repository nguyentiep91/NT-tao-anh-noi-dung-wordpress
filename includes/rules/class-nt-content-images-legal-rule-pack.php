<?php
/**
 * Optional legal and regulatory rule pack.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Legal_Rule_Pack implements NT_Content_Images_Rule_Pack_Interface {
	public function get_id(): string {
		return 'legal';
	}

	public function get_label(): string {
		return __( 'Pháp lý và quy định', 'nt-tao-anh-noi-dung-wordpress' );
	}

	public function get_priority(): int {
		return 40;
	}

	public function get_classification_rules(): array {
		return array(
			array( 'content_type' => 'legal_update', 'search_intent' => 'informational', 'keywords' => array( 'nghị định', 'thông tư', 'luật mới', 'có hiệu lực', 'điểm mới', 'quy định mới', 'legal update' ), 'weight' => 5 ),
			array( 'content_type' => 'legal_explainer', 'search_intent' => 'informational', 'keywords' => array( 'căn cứ pháp lý', 'thẩm quyền', 'điều kiện pháp lý', 'hồ sơ pháp lý', 'quy định tại', 'legal requirements' ), 'weight' => 4 ),
			array( 'content_type' => 'legal_procedure', 'search_intent' => 'procedural', 'keywords' => array( 'thủ tục', 'nộp hồ sơ', 'cơ quan có thẩm quyền', 'trình tự thực hiện' ), 'weight' => 4 ),
		);
	}

	public function get_restrictions( array $source, string $content_type ): array {
		$restrictions = array(
			'no_national_emblem',
			'no_fake_official_documents',
			'no_fake_government_interface',
			'no_ai_generated_legal_claims',
		);

		if ( in_array( $content_type, array( 'legal_update', 'legal_explainer', 'legal_procedure' ), true ) ) {
			$restrictions[] = 'no_invented_article_numbers_or_legal_quotes';
			$restrictions[] = 'no_document_facsimile_presented_as_authentic';
			$restrictions[] = 'no_false_authority_endorsement';
		}

		return $restrictions;
	}

	public function get_blocked_heading_terms(): array {
		return array( 'căn cứ pháp lý', 'miễn trừ trách nhiệm', 'hiệu lực thi hành', 'nguồn tham khảo' );
	}
}
