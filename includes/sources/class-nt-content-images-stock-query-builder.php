<?php
/**
 * Builds concise stock-photo search terms from an image brief.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Stock_Query_Builder {
	/**
	 * @param array<string, mixed> $brief Image brief.
	 * @return array<string, mixed>
	 */
	public function build( array $brief ): array {
		$topic = sanitize_text_field( (string) ( $brief['topic'] ?? $brief['source']['title'] ?? '' ) );
		$type = sanitize_key( (string) ( $brief['content_type'] ?? 'general' ) );
		$intent = sanitize_key( (string) ( $brief['search_intent'] ?? 'informational' ) );
		$haystack = strtolower( $topic . ' ' . str_replace( '_', ' ', $type ) . ' ' . str_replace( '_', ' ', $intent ) );

		$queries = array();
		if ( preg_match( '/(đấu thầu|procurement|tender|bid)/u', $haystack ) ) {
			$queries = array( 'professional document review', 'business team digital workflow', 'office procurement meeting' );
		} elseif ( preg_match( '/(pháp lý|luật|nghị định|legal|law|compliance)/u', $haystack ) ) {
			$queries = array( 'professional legal documents office', 'business compliance meeting', 'document review desk' );
		} elseif ( preg_match( '/(khóa học|đào tạo|giảng viên|education|training|course)/u', $haystack ) ) {
			$queries = array( 'professional training class', 'adult education workshop', 'business classroom learning' );
		} elseif ( preg_match( '/(xây dựng|construction|engineer|architect)/u', $haystack ) ) {
			$queries = array( 'construction engineer planning', 'architect project meeting', 'construction site professional' );
		} elseif ( preg_match( '/(fda|iso|certification|chứng nhận|quality)/u', $haystack ) ) {
			$queries = array( 'quality assurance professional', 'business certification audit', 'quality management documents' );
		} else {
			$queries = array( 'professional business concept', 'modern office teamwork', 'digital business workflow' );
		}

		$queries = apply_filters( 'nt_content_images_stock_queries', $queries, $brief );
		$queries = array_values( array_unique( array_filter( array_map( array( $this, 'sanitize_query' ), is_array( $queries ) ? $queries : array() ) ) ) );
		if ( empty( $queries ) ) {
			$queries = array( 'professional business concept' );
		}

		return array(
			'primary_query'    => $queries[0],
			'fallback_queries' => array_slice( $queries, 1, 3 ),
			'orientation'      => 'landscape',
			'locale'           => 'vi-VN',
		);
	}

	private function sanitize_query( string $query ): string {
		$query = strtolower( remove_accents( wp_strip_all_tags( $query ) ) );
		$query = preg_replace( '/\b(?:https?|www)\S+|\[[^\]]+\]|\d{3,}|[^a-z0-9\s-]+/i', ' ', $query ) ?? '';
		$query = trim( preg_replace( '/\s+/', ' ', $query ) ?? '' );
		return substr( $query, 0, 120 );
	}
}
