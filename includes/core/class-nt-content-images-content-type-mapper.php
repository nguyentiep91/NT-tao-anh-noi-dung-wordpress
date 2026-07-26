<?php
/**
 * Maps arbitrary WordPress post types to portable semantic content groups.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Type_Mapper {
	/**
	 * Resolves one post type through the active site profile.
	 *
	 * @param array<string, mixed> $site_profile Site profile.
	 */
	public function map( string $post_type, array $site_profile ): string {
		$post_type = sanitize_key( $post_type );
		$mapping   = is_array( $site_profile['post_type_mapping'] ?? null ) ? $site_profile['post_type_mapping'] : array();
		$resolved  = sanitize_key( (string) ( $mapping[ $post_type ] ?? '' ) );

		if ( '' === $resolved ) {
			$resolved = 'post' === $post_type ? 'article' : ( 'page' === $post_type ? 'page' : 'generic_content' );
		}

		/** Filters the portable content mapping for one WordPress post type. */
		return sanitize_key( (string) apply_filters( 'nt_content_images_post_type_mapping', $resolved, $post_type, $site_profile ) );
	}
}
