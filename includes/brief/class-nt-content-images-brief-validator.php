<?php
/**
 * Validates generated image briefs before workflow approval.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_Validator {
	/**
	 * @param array<string, mixed> $brief Image brief.
	 * @return array{status: string, errors: string[], warnings: string[]}
	 */
	public function validate( array $brief ): array {
		$errors   = array();
		$warnings = array();
		$post_id  = absint( $brief['post_id'] ?? 0 );

		if ( 0 === $post_id || ! ( get_post( $post_id ) instanceof WP_Post ) ) {
			$errors[] = 'invalid_post_id';
		}
		if ( 64 !== strlen( (string) ( $brief['source_content_hash'] ?? '' ) ) ) {
			$errors[] = 'invalid_source_content_hash';
		}
		if ( version_compare( (string) ( $brief['schema_version'] ?? '0' ), '1.1', '<' ) ) {
			$warnings[] = 'legacy_brief_schema';
		}
		if ( 64 !== strlen( (string) ( $brief['profile_hash'] ?? '' ) ) ) {
			$errors[] = 'invalid_profile_hash';
		}
		if ( 64 !== strlen( (string) ( $brief['brand_profile_hash'] ?? '' ) ) ) {
			$errors[] = 'invalid_brand_profile_hash';
		}
		if ( 0 === absint( $brief['profile_version'] ?? 0 ) ) {
			$errors[] = 'missing_profile_version';
		}

		$active_packs = is_array( $brief['active_rule_packs'] ?? null ) ? $brief['active_rule_packs'] : array();
		if ( ! in_array( 'generic', $active_packs, true ) ) {
			$errors[] = 'generic_rule_pack_required';
		}

		foreach ( array( 'topic', 'post_type_mapping', 'content_type', 'search_intent', 'visual_strategy' ) as $required ) {
			if ( '' === trim( (string) ( $brief[ $required ] ?? '' ) ) ) {
				$errors[] = 'missing_' . $required;
			}
		}

		$brand = is_array( $brief['brand'] ?? null ) ? $brief['brand'] : array();
		if ( ! empty( $brand['logo_required'] ) && 0 === absint( $brand['logo_attachment_id'] ?? 0 ) ) {
			$warnings[] = 'logo_required_without_attachment';
		}
		if ( ! empty( $brand['website_required'] ) && '' === trim( (string) ( $brand['website'] ?? '' ) ) ) {
			$warnings[] = 'website_required_without_url';
		}

		$count = absint( $brief['recommended_content_images'] ?? 0 );
		if ( $count > 5 ) {
			$errors[] = 'too_many_content_images';
		}
		$content_images = is_array( $brief['content_images'] ?? null ) ? $brief['content_images'] : array();
		if ( count( $content_images ) !== $count ) {
			$errors[] = 'content_image_count_mismatch';
		}

		$restrictions = is_array( $brief['restrictions'] ?? null ) ? $brief['restrictions'] : array();
		if ( empty( $restrictions ) ) {
			$errors[] = 'missing_restrictions';
		}
		if ( ! in_array( 'main_text_must_be_rendered_by_plugin_template', $restrictions, true ) ) {
			$warnings[] = 'plugin_text_rendering_restriction_missing';
		}

		foreach ( $content_images as $image ) {
			$placement = is_array( $image['placement'] ?? null ) ? $image['placement'] : array();
			$type      = sanitize_key( (string) ( $placement['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'after_intro', 'before_heading', 'manual_review_required' ), true ) ) {
				$errors[] = 'invalid_placement_type';
			}
			if ( 'manual_review_required' === $type ) {
				$warnings[] = 'manual_placement_review_required';
			}
		}

		if ( ! empty( $brief['source']['has_complex_blocks'] ) ) {
			$warnings[] = 'complex_blocks_require_manual_review';
		}
		if ( ! empty( $brief['source']['has_protected_shortcode'] ) ) {
			$warnings[] = 'protected_shortcodes_require_manual_review';
		} elseif ( ! empty( $brief['source']['has_shortcode'] ) ) {
			$warnings[] = 'shortcodes_require_safe_insertion_review';
		}

		$status = empty( $errors ) ? ( empty( $warnings ) ? 'valid' : 'warning' ) : 'invalid';
		return array(
			'status'   => $status,
			'errors'   => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}
}
