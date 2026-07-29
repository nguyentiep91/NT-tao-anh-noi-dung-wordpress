<?php
/**
 * Classifies content through active, portable rule packs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Intent_Classifier {
	private NT_Content_Images_Rule_Pack_Registry $registry;
	private NT_Content_Images_Profile_Repository $profiles;

	public function __construct(
		NT_Content_Images_Rule_Pack_Registry $registry,
		NT_Content_Images_Profile_Repository $profiles
	) {
		$this->registry = $registry;
		$this->profiles = $profiles;
	}

	/**
	 * Classifies a compact source package using only enabled rule packs.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @return array{content_type: string, search_intent: string, confidence: float, signals: string[], rule_packs: string[]}
	 */
	public function classify( array $source ): array {
		$site_profile = $this->profiles->get_site_profile();
		$active       = is_array( $site_profile['active_rule_packs'] ?? null ) ? $site_profile['active_rule_packs'] : array( 'generic' );

		return $this->registry->classify( $source, $active );
	}
}
