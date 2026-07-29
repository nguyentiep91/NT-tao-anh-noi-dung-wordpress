<?php
/**
 * Orchestrates deterministic, profile-aware image brief generation.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_Generator {
	private NT_Content_Images_Brief_Source_Builder $source_builder;
	private NT_Content_Images_Intent_Classifier $classifier;
	private NT_Content_Images_Visual_Strategy_Resolver $strategy_resolver;
	private NT_Content_Images_Placement_Planner $placement_planner;
	private NT_Content_Images_Restriction_Builder $restriction_builder;
	private NT_Content_Images_Brief_Validator $validator;
	private NT_Content_Images_Brief_Repository $repository;
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Plan_Settings $plan_settings;

	public function __construct(
		NT_Content_Images_Brief_Source_Builder $source_builder,
		NT_Content_Images_Intent_Classifier $classifier,
		NT_Content_Images_Visual_Strategy_Resolver $strategy_resolver,
		NT_Content_Images_Placement_Planner $placement_planner,
		NT_Content_Images_Restriction_Builder $restriction_builder,
		NT_Content_Images_Brief_Validator $validator,
		NT_Content_Images_Brief_Repository $repository,
		NT_Content_Images_Profile_Repository $profiles,
		?NT_Content_Images_Plan_Settings $plan_settings = null
	) {
		$this->source_builder      = $source_builder;
		$this->classifier          = $classifier;
		$this->strategy_resolver   = $strategy_resolver;
		$this->placement_planner   = $placement_planner;
		$this->restriction_builder = $restriction_builder;
		$this->validator           = $validator;
		$this->repository          = $repository;
		$this->profiles            = $profiles;
		$this->plan_settings       = $plan_settings ?? new NT_Content_Images_Plan_Settings();
	}

	/** @return array<string, mixed>|WP_Error */
	public function generate( int $post_id ) {
		$source = $this->source_builder->build( $post_id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$profile_context = $this->profiles->get_context();
		$site_profile    = $profile_context['site'];
		$brand_profile   = $profile_context['brand'];
		$classification  = $this->classifier->classify( $source );
		$strategy        = $this->strategy_resolver->resolve( $classification['content_type'], $source );
		$target_images   = $this->target_content_image_count( absint( $source['word_count'] ?? 0 ), $classification['content_type'] );
		$current_images  = absint( $source['content_image_count'] ?? 0 );
		$needed_images   = max( 0, $target_images - $current_images );
		$placements      = $this->placement_planner->plan( $source, $needed_images, $classification['content_type'] );
		$content_images  = $this->build_content_images( $needed_images, $placements, $strategy, $source );

		$brief = array(
			'schema_version'             => '1.1',
			'post_id'                    => $post_id,
			'source_content_hash'        => (string) $source['source_content_hash'],
			'profile_version'            => absint( $profile_context['version'] ),
			'profile_hash'               => sanitize_text_field( (string) $profile_context['profile_hash'] ),
			'brand_profile_hash'         => sanitize_text_field( (string) $profile_context['brand_hash'] ),
			'active_rule_packs'          => array_values( array_map( 'sanitize_key', (array) $site_profile['active_rule_packs'] ) ),
			'profile'                    => array(
				'site_name'          => sanitize_text_field( (string) $site_profile['site_name'] ),
				'domain'             => sanitize_text_field( (string) $site_profile['domain'] ),
				'language'           => sanitize_text_field( (string) $site_profile['language'] ),
				'industries'         => array_values( array_map( 'sanitize_key', (array) $site_profile['industries'] ) ),
				'enabled_post_types' => array_values( array_map( 'sanitize_key', (array) $site_profile['enabled_post_types'] ) ),
			),
			'status'                     => 'draft',
			'topic'                      => $this->resolve_topic( $source ),
			'post_type_mapping'          => sanitize_key( (string) ( $source['mapped_content_type'] ?? 'generic_content' ) ),
			'content_type'               => $classification['content_type'],
			'search_intent'              => $classification['search_intent'],
			'classification'             => $classification,
			'priority'                   => array(
				'score' => absint( $source['priority_score'] ?? 0 ),
				'label' => sanitize_key( (string) ( $source['priority_label'] ?? 'low' ) ),
			),
			'visual_strategy'            => $strategy['id'],
			'visual_direction'           => $strategy,
			'featured_image'             => array(
				'required'       => 0 === absint( $source['featured_image_id'] ?? 0 ),
				'purpose'        => __( 'Ảnh đại diện tổng quan cho chủ đề nội dung', 'nt-tao-anh-noi-dung-wordpress' ),
				'visual_type'    => $strategy['featured_type'],
				'aspect_ratio'   => '16:9',
				'text_overlay'   => ! empty( $brand_profile['overlay_enabled'] ),
				'overlay_source' => 'plugin_template',
			),
			'current_content_images'     => $current_images,
			'target_content_images'      => $target_images,
			'recommended_content_images' => $needed_images,
			'content_images'             => $content_images,
			'brand'                      => array(
				'brand_name'         => sanitize_text_field( (string) $brand_profile['brand_name'] ),
				'website'            => esc_url_raw( (string) $brand_profile['website'] ),
				'logo_attachment_id' => absint( $brand_profile['logo_attachment_id'] ),
				'logo_required'      => ! empty( $brand_profile['logo_required'] ),
				'website_required'   => ! empty( $brand_profile['website_required'] ),
				'template_family'    => sanitize_key( (string) $brand_profile['template_family'] ),
				'colors'             => array(
					'primary'   => sanitize_hex_color( (string) $brand_profile['primary_color'] ),
					'secondary' => sanitize_hex_color( (string) $brand_profile['secondary_color'] ),
					'accent'    => sanitize_hex_color( (string) $brand_profile['accent_color'] ),
				),
				'font_family'       => sanitize_text_field( (string) $brand_profile['font_family'] ),
				'text_rendering'    => 'plugin_controlled',
			),
			'restrictions'               => $this->restriction_builder->build( $source, $classification['content_type'] ),
			'source'                     => array(
				'title'                        => $source['title'],
				'focus_keyphrase'              => $source['focus_keyphrase'],
				'seo_adapter'                  => $source['seo_adapter'],
				'word_count'                   => $source['word_count'],
				'headings'                     => $source['headings'],
				'has_shortcode'                => $source['has_shortcode'],
				'has_protected_shortcode'      => $source['has_protected_shortcode'],
				'has_complex_blocks'           => $source['has_complex_blocks'],
				'shortcodes'                    => $source['shortcodes'],
				'protected_shortcodes_present' => $source['protected_shortcodes_present'],
				'block_names'                   => $source['block_names'],
			),
			'generated_at'                => current_time( 'mysql', true ),
		);

		$brief = apply_filters( 'nt_content_images_image_brief', $brief, $source, $profile_context );
		$validation                 = $this->validator->validate( $brief );
		$brief['validation_status'] = $validation['status'];
		$brief['validation']        = $validation;
		$brief_id                   = $this->repository->save( $brief );

		if ( false === $brief_id ) {
			return new WP_Error( 'ntci_brief_store_failed', __( 'Không thể lưu kế hoạch hình ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$brief['id'] = $brief_id;
		return $brief;
	}

	/**
	 * @param int[] $post_ids Post IDs.
	 * @return array{generated: int, failed: int, items: array<int, mixed>}
	 */
	public function generate_batch( array $post_ids ): array {
		$post_ids = array_slice( array_values( array_unique( array_map( 'absint', $post_ids ) ) ), 0, 20 );
		$result   = array( 'generated' => 0, 'failed' => 0, 'items' => array() );
		foreach ( $post_ids as $post_id ) {
			$brief = $this->generate( $post_id );
			if ( is_wp_error( $brief ) ) {
				++$result['failed'];
				$result['items'][] = array( 'post_id' => $post_id, 'error' => $brief->get_error_code(), 'message' => $brief->get_error_message() );
				continue;
			}
			++$result['generated'];
			$result['items'][] = array( 'post_id' => $post_id, 'brief_id' => absint( $brief['id'] ), 'status' => $brief['validation_status'] );
		}
		return $result;
	}

	private function target_content_image_count( int $word_count, string $content_type ): int {
		// Quy tắc số ảnh do quản trị viên cấu hình trong Kế hoạch hình ảnh (0.16.0).
		$target = $this->plan_settings->image_count( $word_count, $content_type );
		return (int) apply_filters( 'nt_content_images_target_content_image_count', $target, $word_count, $content_type );
	}

	/**
	 * @param array<int, array<string, mixed>> $placements Placements.
	 * @param array<string, mixed>             $strategy Strategy.
	 * @param array<string, mixed>             $source Source.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_content_images( int $count, array $placements, array $strategy, array $source ): array {
		$images       = array();
		$visual_types = is_array( $strategy['content_types'] ?? null ) ? $strategy['content_types'] : array( 'conceptual_visual' );
		$headings     = is_array( $source['headings'] ?? null ) ? $source['headings'] : array();
		for ( $index = 0; $index < $count; ++$index ) {
			$placement = $placements[ $index ] ?? array( 'type' => 'manual_review_required', 'safety' => 'warning' );
			$heading   = $headings[ $index ]['text'] ?? '';
			$purpose   = 0 === $index ? __( 'Tạo điểm ngắt thị giác sau phần mở đầu và minh họa chủ đề chính', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'Minh họa phần nội dung quan trọng', 'nt-tao-anh-noi-dung-wordpress' ) . ( $heading ? ': ' . $heading : '' );
			$images[]  = array(
				'index'        => $index + 1,
				'purpose'      => sanitize_text_field( $purpose ),
				'visual_type'  => sanitize_key( (string) $visual_types[ $index % count( $visual_types ) ] ),
				'aspect_ratio' => '16:9',
				'text_overlay' => false,
				'placement'    => $placement,
			);
		}
		return $images;
	}

	/** @param array<string, mixed> $source Source package. */
	private function resolve_topic( array $source ): string {
		$focus = trim( (string) ( $source['focus_keyphrase'] ?? '' ) );
		return sanitize_text_field( '' !== $focus ? $focus : (string) ( $source['title'] ?? '' ) );
	}
}
