<?php
/**
 * Builds image safety restrictions from active portable rule packs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Restriction_Builder {
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
	 * Returns machine-readable restrictions from enabled rule packs.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @return string[]
	 */
	public function build( array $source, string $content_type ): array {
		$site_profile = $this->profiles->get_site_profile();
		$active       = is_array( $site_profile['active_rule_packs'] ?? null ) ? $site_profile['active_rule_packs'] : array( 'generic' );
		return $this->registry->get_restrictions( $source, $content_type, $active );
	}
}
