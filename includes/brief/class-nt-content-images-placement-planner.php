<?php
/**
 * Plans stable image anchors with website-neutral safety policies.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Placement_Planner {
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Rule_Pack_Registry $rule_packs;

	public function __construct(
		NT_Content_Images_Profile_Repository $profiles,
		NT_Content_Images_Rule_Pack_Registry $rule_packs
	) {
		$this->profiles   = $profiles;
		$this->rule_packs = $rule_packs;
	}

	/**
	 * Creates safe proposed placements without changing content.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @return array<int, array<string, mixed>>
	 */
	public function plan( array $source, int $recommended_images, string $content_type ): array {
		$recommended_images = min( 5, max( 0, $recommended_images ) );
		if ( 0 === $recommended_images ) {
			return array();
		}

		$site_profile = $this->profiles->get_site_profile();
		$policy       = sanitize_key( (string) ( $site_profile['builder_policy'] ?? 'safe_only' ) );
		$requires_manual = ! empty( $source['has_protected_shortcode'] ) || ( ! empty( $source['has_complex_blocks'] ) && in_array( $policy, array( 'safe_only', 'manual_for_builders' ), true ) );

		if ( $requires_manual ) {
			return array_fill(
				0,
				$recommended_images,
				array(
					'type'   => 'manual_review_required',
					'safety' => 'warning',
					'reason' => ! empty( $source['has_protected_shortcode'] ) ? 'protected_shortcode_present' : 'complex_builder_content',
				)
			);
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
		$candidates = $this->filter_heading_candidates( $headings, $content_type, $site_profile );
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
			$placements[] = array( 'type' => 'manual_review_required', 'safety' => 'warning', 'reason' => 'insufficient_safe_heading_anchors' );
		}

		return array_slice( $placements, 0, $recommended_images );
	}

	/**
	 * @param array<int, array<string, mixed>> $headings Parsed headings.
	 * @param array<string, mixed>             $site_profile Site profile.
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_heading_candidates( array $headings, string $content_type, array $site_profile ): array {
		$active  = is_array( $site_profile['active_rule_packs'] ?? null ) ? $site_profile['active_rule_packs'] : array( 'generic' );
		$blocked = array_merge(
			$this->rule_packs->get_blocked_heading_terms( $active ),
			is_array( $site_profile['blocked_heading_terms'] ?? null ) ? $site_profile['blocked_heading_terms'] : array()
		);
		$blocked = array_map(
			static fn( $term ): string => function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $term, 'UTF-8' ) : strtolower( (string) $term ),
			$blocked
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
				if ( '' !== $needle && false !== strpos( $hay, $needle ) ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip ) {
				$result[] = $heading;
			}
		}

		if ( in_array( $content_type, array( 'course', 'service', 'procurement_service', 'education_event' ), true ) ) {
			return array_slice( $result, 0, 2 );
		}
		return $result;
	}
}
