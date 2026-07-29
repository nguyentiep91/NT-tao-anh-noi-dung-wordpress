<?php
/**
 * Builds a practical featured-image prompt from an image brief.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Featured_Prompt_Builder {
	private NT_Content_Images_Profile_Repository $profiles;

	public function __construct( NT_Content_Images_Profile_Repository $profiles ) {
		$this->profiles = $profiles;
	}

	/**
	 * @param array<string, mixed> $brief Image brief payload.
	 */
	public function build( array $brief ): string {
		$context     = $this->profiles->get_context();
		$brand       = $context['brand'];
		$topic       = sanitize_text_field( (string) ( $brief['topic'] ?? $brief['source']['title'] ?? '' ) );
		$type        = sanitize_key( (string) ( $brief['content_type'] ?? 'general_education' ) );
		$intent      = sanitize_key( (string) ( $brief['search_intent'] ?? 'informational' ) );
		$direction   = is_array( $brief['visual_direction'] ?? null ) ? $brief['visual_direction'] : array();
		$art         = sanitize_textarea_field( (string) ( $direction['art_direction'] ?? 'Candid documentary photography with a clear subject and credible everyday working context.' ) );
		$restrictions= is_array( $brief['restrictions'] ?? null ) ? array_map( 'sanitize_key', $brief['restrictions'] ) : array();
		$colors      = implode( ', ', array_filter( array( (string) ( $brand['primary_color'] ?? '' ), (string) ( $brand['secondary_color'] ?? '' ), (string) ( $brand['accent_color'] ?? '' ) ) ) );
		$restriction_text = implode( ', ', array_map( static fn( string $item ): string => str_replace( '_', ' ', $item ), array_slice( $restrictions, 0, 20 ) ) );

		$parts = array(
			'Create one professional featured image for a WordPress article.',
			'Topic: ' . $topic . '.',
			'Content type: ' . str_replace( '_', ' ', $type ) . '. Search intent: ' . str_replace( '_', ' ', $intent ) . '.',
			'Visual direction: ' . $art,
			'Use a credible Vietnamese professional context when relevant, without national symbols or official branding.',
			'Landscape composition generated at 1536x1024 and designed to remain visually balanced after a centered 16:9 crop.',
			'Keep the main subject away from the outer edges. Use a clear focal point, realistic proportions and an uncluttered background.',
			'Photorealistic candid style, like a real photo taken during an ordinary working day: natural ambient light with soft imperfect shadows, true-to-life skin and fabric textures, believable everyday details, unposed natural expressions, slight depth of field, mild film grain.',
			'Avoid the polished AI stock-photo look: no glossy CGI or 3D-render feel, no oversaturated colors, no flawless studio lighting, no posed models smiling at the camera, no sterile spotless environment.',
			'Use brand-inspired colors only as subtle accents where natural: ' . ( '' !== $colors ? $colors : 'restrained corporate colors' ) . '.',
			'Do not generate readable text, letters, numbers, logos, watermarks, signatures, certificates, seals or website interfaces.',
		);
		if ( '' !== $restriction_text ) {
			$parts[] = 'Mandatory safety constraints: ' . $restriction_text . '.';
		}
		$parts[] = 'The image must work as a clean editorial background; all accurate typography and branding will be added later by WordPress.';

		$prompt = implode( "\n", $parts );
		$prompt = apply_filters( 'nt_content_images_featured_prompt', $prompt, $brief, $context );

		return substr( trim( (string) $prompt ), 0, 8000 );
	}
}
