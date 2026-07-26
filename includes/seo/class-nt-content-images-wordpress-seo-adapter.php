<?php
/**
 * Native WordPress SEO fallback adapter.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_WordPress_SEO_Adapter implements NT_Content_Images_SEO_Adapter_Interface {
	public function get_id(): string {
		return 'wordpress';
	}

	public function supports(): bool {
		return true;
	}

	public function get_focus_keyphrase( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		$terms = wp_get_post_terms( $post_id, get_object_taxonomies( $post->post_type, 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return sanitize_text_field( (string) $terms[0]->name );
	}

	public function get_seo_title( int $post_id ): string {
		return sanitize_text_field( (string) get_the_title( $post_id ) );
	}

	public function get_seo_description( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		$source = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		return sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $source ), true ), 35, '…' ) );
	}
}
