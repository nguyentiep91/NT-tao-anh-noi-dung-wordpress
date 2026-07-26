<?php
/**
 * Rank Math SEO metadata adapter.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Rank_Math_SEO_Adapter implements NT_Content_Images_SEO_Adapter_Interface {
	public function get_id(): string {
		return 'rank_math';
	}

	public function supports(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
	}

	public function get_focus_keyphrase( int $post_id ): string {
		$value = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		$first = trim( explode( ',', $value )[0] ?? '' );
		return sanitize_text_field( $first );
	}

	public function get_seo_title( int $post_id ): string {
		return sanitize_text_field( (string) get_post_meta( $post_id, 'rank_math_title', true ) );
	}

	public function get_seo_description( int $post_id ): string {
		return sanitize_textarea_field( (string) get_post_meta( $post_id, 'rank_math_description', true ) );
	}
}
