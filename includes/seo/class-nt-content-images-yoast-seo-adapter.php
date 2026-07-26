<?php
/**
 * Yoast SEO metadata adapter.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Yoast_SEO_Adapter implements NT_Content_Images_SEO_Adapter_Interface {
	public function get_id(): string {
		return 'yoast';
	}

	public function supports(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	public function get_focus_keyphrase( int $post_id ): string {
		return sanitize_text_field( (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
	}

	public function get_seo_title( int $post_id ): string {
		return sanitize_text_field( (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ) );
	}

	public function get_seo_description( int $post_id ): string {
		return sanitize_textarea_field( (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
	}
}
