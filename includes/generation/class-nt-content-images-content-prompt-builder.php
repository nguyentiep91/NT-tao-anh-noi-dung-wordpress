<?php
/**
 * Builds prompts for in-article illustrations from one planned image slot.
 *
 * Unlike the featured prompt, these images are quiet supporting visuals:
 * no space reserved for typography and stronger variation between slots.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Prompt_Builder {
	private NT_Content_Images_Profile_Repository $profiles;

	public function __construct( NT_Content_Images_Profile_Repository $profiles ) {
		$this->profiles = $profiles;
	}

	/**
	 * @param array<string, mixed> $brief Image brief payload.
	 * @param array<string, mixed> $image One entry of the brief content_images plan.
	 */
	public function build( array $brief, array $image, WP_Post $post ): string {
		$context   = $this->profiles->get_context();
		$brand     = $context['brand'];
		$topic     = sanitize_text_field( (string) ( $brief['topic'] ?? get_the_title( $post ) ) );
		$type      = sanitize_key( (string) ( $brief['content_type'] ?? 'general_education' ) );
		$placement = is_array( $image['placement'] ?? null ) ? $image['placement'] : array();
		$heading   = sanitize_text_field( (string) ( $placement['heading_text'] ?? '' ) );
		$visual    = str_replace( '_', ' ', sanitize_key( (string) ( $image['visual_type'] ?? 'conceptual_visual' ) ) );
		$index     = absint( $image['index'] ?? 1 );
		$excerpt   = $this->section_excerpt( $post, $heading );
		$colors    = implode( ', ', array_filter( array( (string) ( $brand['primary_color'] ?? '' ), (string) ( $brand['secondary_color'] ?? '' ) ) ) );
		$restrictions = is_array( $brief['restrictions'] ?? null ) ? array_map( 'sanitize_key', $brief['restrictions'] ) : array();
		$restriction_text = implode( ', ', array_map( static fn( string $item ): string => str_replace( '_', ' ', $item ), array_slice( $restrictions, 0, 20 ) ) );

		$angles = array(
			'medium shot with a person-centered everyday moment',
			'close-up detail shot of hands, tools or documents in use',
			'wide environmental shot showing the working context',
			'over-the-shoulder perspective focused on the task',
			'still-life arrangement of relevant real objects on a desk',
		);

		// Cảnh do quản trị viên tự mô tả trong Kế hoạch hình ảnh thay thế phần cảnh
		// tự động; khung kỹ thuật (phong cách thật, bố cục, cấm chữ) vẫn giữ nguyên.
		$custom_scene = sanitize_textarea_field( (string) ( $image['custom_scene'] ?? '' ) );
		$scene_parts  = '' !== $custom_scene
			? array(
				'Scene requested by the site editor (follow it faithfully): ' . $custom_scene,
				'Article topic for context: ' . $topic . '.',
			)
			: array(
				'Article topic: ' . $topic . '. Content type: ' . str_replace( '_', ' ', $type ) . '.',
				'' !== $heading ? 'This image illustrates the section: "' . $heading . '".' : 'This image is a visual break right after the article introduction.',
				'' !== $excerpt ? 'Section context: ' . $excerpt : '',
				'Visual approach: ' . $visual . '. Camera angle for variation: ' . $angles[ ( $index - 1 ) % count( $angles ) ] . '.',
			);

		$parts = array_merge(
			array( 'Create one supporting in-article illustration for a WordPress blog section. It is NOT a hero banner: keep it calm, editorial and secondary to the text around it.' ),
			$scene_parts,
			array( 'Use a credible Vietnamese professional context when relevant, without national symbols or official branding.' )
		);
		$parts = array_merge( $parts, array(
			'Photorealistic candid style, like a real photo taken during an ordinary working day: natural ambient light with soft imperfect shadows, true-to-life textures, believable everyday details, unposed natural expressions, slight depth of field, mild film grain.',
			'Avoid the polished AI stock-photo look: no glossy CGI or 3D-render feel, no oversaturated colors, no flawless studio lighting, no posed models smiling at the camera.',
			'Landscape 16:9 composition with a single clear subject and generous negative space; it must read well at medium size inside an article column.',
			'Use brand colors only as barely-noticeable accents if natural: ' . ( '' !== $colors ? $colors : 'restrained neutral tones' ) . '.',
			'Do not generate any readable text, letters, numbers, logos, watermarks, signatures, certificates, seals, charts with labels or user interfaces.',
		) );
		if ( '' !== $restriction_text ) {
			$parts[] = 'Mandatory safety constraints: ' . $restriction_text . '.';
		}

		$prompt = implode( "\n", array_filter( $parts ) );
		$prompt = apply_filters( 'nt_content_images_content_prompt', $prompt, $brief, $image, $post );

		return substr( trim( (string) $prompt ), 0, 8000 );
	}

	/** Extracts up to ~400 chars of plain text after the section heading (or the intro). */
	private function section_excerpt( WP_Post $post, string $heading ): string {
		$plain = wp_strip_all_tags( (string) $post->post_content );
		$plain = (string) preg_replace( '/\s+/u', ' ', $plain );
		if ( '' === trim( $plain ) ) {
			return '';
		}
		$start = 0;
		if ( '' !== $heading ) {
			$position = function_exists( 'mb_stripos' ) ? mb_stripos( $plain, $heading ) : stripos( $plain, $heading );
			if ( false !== $position ) {
				$start = (int) $position + ( function_exists( 'mb_strlen' ) ? mb_strlen( $heading ) : strlen( $heading ) );
			}
		}
		$excerpt = function_exists( 'mb_substr' ) ? mb_substr( $plain, $start, 400 ) : substr( $plain, $start, 400 );
		return sanitize_text_field( trim( $excerpt ) );
	}
}
