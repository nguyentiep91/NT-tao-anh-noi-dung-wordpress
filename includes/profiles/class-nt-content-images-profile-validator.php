<?php
/**
 * Validates portable site and brand profiles before persistence.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Profile_Validator {
	/**
	 * Validates normalized profile payloads.
	 *
	 * @param array<string, mixed> $site_profile  Site profile.
	 * @param array<string, mixed> $brand_profile Brand profile.
	 * @return array{status: string, errors: string[], warnings: string[]}
	 */
	public function validate( array $site_profile, array $brand_profile ): array {
		$errors   = array();
		$warnings = array();

		if ( '' === trim( (string) ( $site_profile['site_name'] ?? '' ) ) ) {
			$errors[] = 'missing_site_name';
		}
		if ( '' === trim( (string) ( $site_profile['domain'] ?? '' ) ) ) {
			$errors[] = 'missing_domain';
		}
		if ( empty( $site_profile['enabled_post_types'] ) ) {
			$errors[] = 'missing_enabled_post_types';
		}
		if ( empty( $site_profile['active_rule_packs'] ) || ! in_array( 'generic', (array) $site_profile['active_rule_packs'], true ) ) {
			$errors[] = 'generic_rule_pack_required';
		}

		foreach ( (array) ( $site_profile['enabled_post_types'] ?? array() ) as $post_type ) {
			if ( empty( $site_profile['post_type_mapping'][ $post_type ] ) ) {
				$errors[] = 'missing_post_type_mapping:' . sanitize_key( (string) $post_type );
			}
		}

		if ( '' === trim( (string) ( $brand_profile['brand_name'] ?? '' ) ) ) {
			$warnings[] = 'missing_brand_name';
		}
		if ( ! empty( $brand_profile['logo_required'] ) && 0 === absint( $brand_profile['logo_attachment_id'] ?? 0 ) ) {
			$warnings[] = 'logo_required_without_attachment';
		}
		if ( ! empty( $brand_profile['website_required'] ) && '' === trim( (string) ( $brand_profile['website'] ?? '' ) ) ) {
			$warnings[] = 'website_required_without_url';
		}

		$status = empty( $errors ) ? ( empty( $warnings ) ? 'valid' : 'warning' ) : 'invalid';

		return array(
			'status'   => $status,
			'errors'   => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}
}
