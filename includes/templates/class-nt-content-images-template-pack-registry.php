<?php
/**
 * Registers industry-aware template packs and resolves the best pack for a site.
 *
 * Packs do not introduce new renderer layouts. They curate the existing,
 * tested layouts into deterministic pools for featured and in-article images.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Template_Pack_Registry {
	public const DEFAULT_PACK = 'corporate';

	private NT_Content_Images_Template_Registry $templates;

	public function __construct( NT_Content_Images_Template_Registry $templates ) {
		$this->templates = $templates;
	}

	/** @return array<string, array<string, mixed>> */
	public function get_all(): array {
		$packs = array(
			'corporate' => array(
				'id' => 'corporate',
				'label' => __( 'Corporate / Doanh nghiệp', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Bố cục rõ ràng, chắc chắn, phù hợp doanh nghiệp, tư vấn B2B và dịch vụ chuyên nghiệp.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'corporate', 'business', 'enterprise', 'consulting', 'b2b', 'finance', 'procurement' ),
				'rule_packs' => array( 'procurement' ),
				'featured_templates' => array( 'left_panel', 'bottom_gradient', 'top_band', 'right_panel', 'bottom_bar' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'bottom_bar', 'bottom_gradient' ),
			),
			'education' => array(
				'id' => 'education',
				'label' => __( 'Education / Giáo dục', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Thân thiện, dễ đọc, phù hợp khóa học, đào tạo, học viện và nội dung hướng dẫn.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'education', 'training', 'academy', 'school', 'course', 'learning' ),
				'rule_packs' => array( 'education' ),
				'featured_templates' => array( 'left_panel', 'top_band', 'bottom_gradient', 'center_box', 'corner_card' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'bottom_bar', 'left_panel' ),
			),
			'real_estate' => array(
				'id' => 'real_estate',
				'label' => __( 'Real Estate / Bất động sản', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Ưu tiên hình ảnh bất động sản, ít che phủ ảnh, phong cách editorial và premium.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'real_estate', 'realestate', 'property', 'proptech', 'housing', 'architecture' ),
				'rule_packs' => array(),
				'featured_templates' => array( 'corner_card', 'top_gradient', 'bottom_gradient', 'minimal_badge' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'top_gradient' ),
			),
			'certification' => array(
				'id' => 'certification',
				'label' => __( 'Certification / Chứng nhận', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Trang trọng và kỹ thuật cho ISO, chứng nhận, tiêu chuẩn, đánh giá sự phù hợp và compliance.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'certification', 'iso', 'standards', 'compliance', 'quality', 'conformity', 'audit' ),
				'rule_packs' => array(),
				'featured_templates' => array( 'top_band', 'left_panel', 'bottom_bar', 'center_box', 'bottom_gradient' ),
				'content_templates' => array( 'minimal_badge', 'bottom_bar', 'corner_card', 'top_band' ),
			),
			'legal' => array(
				'id' => 'legal',
				'label' => __( 'Legal / Pháp lý', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Nghiêm túc, tiết chế và dễ đọc cho pháp luật, quy định, thủ tục và hồ sơ.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'legal', 'law', 'regulation', 'policy', 'compliance' ),
				'rule_packs' => array( 'legal' ),
				'featured_templates' => array( 'bottom_bar', 'left_panel', 'top_band', 'bottom_gradient' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'bottom_bar' ),
			),
			'news' => array(
				'id' => 'news',
				'label' => __( 'News / Editorial', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Nhịp nhanh kiểu báo chí, ưu tiên headline rõ và bố cục đa dạng cho tin tức.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'news', 'media', 'editorial', 'magazine', 'press' ),
				'rule_packs' => array(),
				'featured_templates' => array( 'bottom_bar', 'bottom_gradient', 'top_gradient', 'corner_card', 'top_band' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'bottom_bar', 'top_gradient' ),
			),
			'technology' => array(
				'id' => 'technology',
				'label' => __( 'Technology / Công nghệ', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Hiện đại, tương phản mạnh cho phần mềm, AI, SaaS, sản phẩm số và công nghệ.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'technology', 'tech', 'software', 'ai', 'saas', 'digital', 'startup' ),
				'rule_packs' => array(),
				'featured_templates' => array( 'right_panel', 'left_panel', 'top_gradient', 'center_box', 'bottom_gradient' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'right_panel', 'top_gradient' ),
			),
			'minimal' => array(
				'id' => 'minimal',
				'label' => __( 'Minimal / Tối giản', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Giữ ảnh sạch và tự nhiên, hạn chế chữ, phù hợp website muốn thiết kế nhẹ và ít nhận diện AI.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'minimal', 'clean', 'simple' ),
				'rule_packs' => array(),
				'featured_templates' => array( 'minimal_badge', 'corner_card', 'bottom_bar' ),
				'content_templates' => array( 'minimal_badge', 'corner_card' ),
			),
			'luxury' => array(
				'id' => 'luxury',
				'label' => __( 'Luxury / Premium', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Ít chi tiết phủ lên ảnh, khoảng thở lớn, phù hợp thương hiệu cao cấp, nội thất và hospitality.', 'nt-tao-anh-noi-dung-wordpress' ),
				'signals' => array( 'luxury', 'premium', 'interior', 'hospitality', 'lifestyle', 'highend' ),
				'rule_packs' => array(),
				'featured_templates' => array( 'corner_card', 'top_gradient', 'bottom_gradient', 'minimal_badge', 'center_box' ),
				'content_templates' => array( 'minimal_badge', 'corner_card', 'top_gradient' ),
			),
		);

		$filtered = apply_filters( 'nt_content_images_template_packs', $packs );
		return $this->sanitize_packs( is_array( $filtered ) ? $filtered : $packs );
	}

	/** @return array<string, mixed>|null */
	public function get( string $id ): ?array {
		$packs = $this->get_all();
		$id = sanitize_key( $id );
		return $packs[ $id ] ?? null;
	}

	public function exists( string $id ): bool {
		return null !== $this->get( $id );
	}

	/** @param array<string, mixed> $site_profile @param array<string, mixed> $brand_profile */
	public function resolve( array $site_profile, array $brand_profile = array(), string $requested = 'auto' ): string {
		$requested = sanitize_key( $requested );
		if ( 'auto' !== $requested && $this->exists( $requested ) ) {
			return $requested;
		}

		$packs = $this->get_all();
		$industries = $this->sanitize_signal_list( (array) ( $site_profile['industries'] ?? array() ) );
		$rule_packs = $this->sanitize_signal_list( (array) ( $site_profile['active_rule_packs'] ?? array() ) );
		$family = sanitize_key( (string) ( $brand_profile['template_family'] ?? '' ) );
		$scores = array_fill_keys( array_keys( $packs ), 0 );

		foreach ( $packs as $id => $pack ) {
			if ( $family === $id ) {
				$scores[ $id ] += 2;
			}
			$scores[ $id ] += 6 * count( array_intersect( $industries, (array) $pack['signals'] ) );
			$scores[ $id ] += 8 * count( array_intersect( $rule_packs, (array) $pack['rule_packs'] ) );
		}

		$winner = self::DEFAULT_PACK;
		$score = -1;
		foreach ( $scores as $id => $candidate_score ) {
			if ( $candidate_score > $score ) {
				$winner = $id;
				$score = $candidate_score;
			}
		}
		if ( $score <= 0 && $this->exists( $family ) ) {
			$winner = $family;
		}

		$filtered = sanitize_key( (string) apply_filters( 'nt_content_images_resolved_template_pack', $winner, $site_profile, $brand_profile, $scores ) );
		return $this->exists( $filtered ) ? $filtered : $winner;
	}

	/** @return array<int, string> */
	public function get_pool( string $pack_id, bool $is_content ): array {
		$pack = $this->get( $pack_id );
		if ( null === $pack ) {
			$pack = $this->get( self::DEFAULT_PACK );
		}
		if ( null === $pack ) {
			return array( NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE );
		}
		return array_values( (array) $pack[ $is_content ? 'content_templates' : 'featured_templates' ] );
	}

	/** @return array<int, array<string, mixed>> */
	public function get_public(): array {
		$items = array();
		foreach ( $this->get_all() as $pack ) {
			$items[] = array(
				'id' => $pack['id'],
				'label' => $pack['label'],
				'description' => $pack['description'],
				'featured_templates' => $pack['featured_templates'],
				'content_templates' => $pack['content_templates'],
			);
		}
		return $items;
	}

	/** @param array<string, mixed> $packs @return array<string, array<string, mixed>> */
	private function sanitize_packs( array $packs ): array {
		$templates = $this->templates->get_all();
		$clean = array();
		foreach ( $packs as $pack ) {
			if ( ! is_array( $pack ) ) {
				continue;
			}
			$id = sanitize_key( (string) ( $pack['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			$featured = $this->sanitize_template_pool( $pack['featured_templates'] ?? array(), $templates );
			$content = $this->sanitize_template_pool( $pack['content_templates'] ?? array(), $templates );
			if ( array() === $featured ) {
				$featured = array( NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE );
			}
			if ( array() === $content ) {
				$content = isset( $templates['minimal_badge'] ) ? array( 'minimal_badge' ) : $featured;
			}
			$clean[ $id ] = array(
				'id' => $id,
				'label' => sanitize_text_field( (string) ( $pack['label'] ?? $id ) ),
				'description' => sanitize_text_field( (string) ( $pack['description'] ?? '' ) ),
				'signals' => $this->sanitize_signal_list( (array) ( $pack['signals'] ?? array() ) ),
				'rule_packs' => $this->sanitize_signal_list( (array) ( $pack['rule_packs'] ?? array() ) ),
				'featured_templates' => $featured,
				'content_templates' => $content,
			);
		}
		return $clean;
	}

	/** @param mixed $raw @param array<string, mixed> $templates @return array<int, string> */
	private function sanitize_template_pool( $raw, array $templates ): array {
		$pool = array();
		foreach ( is_array( $raw ) ? $raw : array() as $id ) {
			$id = sanitize_key( (string) $id );
			if ( isset( $templates[ $id ] ) && ! in_array( $id, $pool, true ) ) {
				$pool[] = $id;
			}
		}
		return $pool;
	}

	/** @param array<int, mixed> $values @return array<int, string> */
	private function sanitize_signal_list( array $values ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $values ) ) ) ) );
	}
}
