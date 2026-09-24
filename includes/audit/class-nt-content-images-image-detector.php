<?php
/**
 * Detects image references in Gutenberg blocks and classic HTML content.
 *
 * The detector never renders blocks or executes shortcodes.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Image_Detector {
	/**
	 * Detects and normalizes unique content images.
	 *
	 * @return array<int, array<string, int|string|bool>>
	 */
	public function detect( string $content ): array {
		$images = array();
		$seen   = array();
		$blocks = parse_blocks( $content );

		$this->collect_from_blocks( $blocks, $images, $seen );
		$this->collect_from_html( $content, $images, $seen );

		return array_values( $images );
	}

	/**
	 * Recursively collects image-bearing block attributes.
	 *
	 * @param array<int, array<string, mixed>>                   $blocks Parsed blocks.
	 * @param array<int, array<string, int|string|bool>>         $images Normalized images.
	 * @param array<string, bool>                                $seen   Deduplication map.
	 */
	private function collect_from_blocks( array $blocks, array &$images, array &$seen ): void {
		foreach ( $blocks as $block ) {
			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			switch ( $block_name ) {
				case 'core/image':
					$this->add_image(
						$images,
						$seen,
						array(
							'attachment_id' => absint( $attrs['id'] ?? 0 ),
							'url'           => esc_url_raw( (string) ( $attrs['url'] ?? '' ) ),
							'alt'           => sanitize_text_field( (string) ( $attrs['alt'] ?? '' ) ),
							'width'         => absint( $attrs['width'] ?? 0 ),
							'height'        => absint( $attrs['height'] ?? 0 ),
							'source_type'   => 'block',
							'block_name'    => $block_name,
						)
					);
					break;

				case 'core/cover':
					$this->add_image(
						$images,
						$seen,
						array(
							'attachment_id' => absint( $attrs['id'] ?? 0 ),
							'url'           => esc_url_raw( (string) ( $attrs['url'] ?? '' ) ),
							'alt'           => sanitize_text_field( (string) ( $attrs['alt'] ?? '' ) ),
							'width'         => 0,
							'height'        => 0,
							'source_type'   => 'block',
							'block_name'    => $block_name,
						)
					);
					break;

				case 'core/media-text':
					if ( 'image' === (string) ( $attrs['mediaType'] ?? 'image' ) ) {
						$this->add_image(
							$images,
							$seen,
							array(
								'attachment_id' => absint( $attrs['mediaId'] ?? 0 ),
								'url'           => esc_url_raw( (string) ( $attrs['mediaUrl'] ?? '' ) ),
								'alt'           => sanitize_text_field( (string) ( $attrs['mediaAlt'] ?? '' ) ),
								'width'         => 0,
								'height'        => 0,
								'source_type'   => 'block',
								'block_name'    => $block_name,
							)
						);
					}
					break;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collect_from_blocks( $block['innerBlocks'], $images, $seen );
			}
		}
	}

	/**
	 * Collects image tags without executing or rendering post content.
	 *
	 * @param array<int, array<string, int|string|bool>> $images Normalized images.
	 * @param array<string, bool>                        $seen   Deduplication map.
	 */
	private function collect_from_html( string $content, array &$images, array &$seen ): void {
		$processor = new WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			$src   = (string) $processor->get_attribute( 'src' );
			$class = (string) $processor->get_attribute( 'class' );
			$id    = 0;

			if ( preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $class, $matches ) ) {
				$id = absint( $matches[1] );
			}

			$this->add_image(
				$images,
				$seen,
				array(
					'attachment_id' => $id,
					'url'           => esc_url_raw( $src ),
					'alt'           => sanitize_text_field( (string) $processor->get_attribute( 'alt' ) ),
					'width'         => absint( $processor->get_attribute( 'width' ) ),
					'height'        => absint( $processor->get_attribute( 'height' ) ),
					'source_type'   => 'html',
					'block_name'    => '',
				)
			);
		}
	}

	/**
	 * Normalizes, enriches and deduplicates one image record.
	 *
	 * @param array<int, array<string, int|string|bool>> $images Normalized images.
	 * @param array<string, bool>                        $seen   Deduplication map.
	 * @param array<string, int|string>                  $image  Raw image record.
	 */
	private function add_image( array &$images, array &$seen, array $image ): void {
		$attachment_id = absint( $image['attachment_id'] ?? 0 );
		$url           = esc_url_raw( (string) ( $image['url'] ?? '' ) );

		if ( 0 === $attachment_id && '' === $url ) {
			return;
		}

		if ( str_starts_with( $url, 'data:' ) ) {
			return;
		}

		if ( 0 === $attachment_id && '' !== $url ) {
			$attachment_id = absint( attachment_url_to_postid( $url ) );
		}

		if ( 0 !== $attachment_id && '' === $url ) {
			$url = (string) wp_get_attachment_url( $attachment_id );
		}

		$width  = absint( $image['width'] ?? 0 );
		$height = absint( $image['height'] ?? 0 );

		if ( 0 !== $attachment_id && ( 0 === $width || 0 === $height ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );

			if ( is_array( $metadata ) ) {
				$width  = $width ?: absint( $metadata['width'] ?? 0 );
				$height = $height ?: absint( $metadata['height'] ?? 0 );
			}
		}

		if ( ( 0 < $width && $width <= 1 ) || ( 0 < $height && $height <= 1 ) ) {
			return;
		}

		$key = 0 !== $attachment_id ? 'id:' . $attachment_id : 'url:' . untrailingslashit( strtolower( $url ) );

		if ( isset( $seen[ $key ] ) ) {
			return;
		}

		$seen[ $key ] = true;
		$alt          = sanitize_text_field( (string) ( $image['alt'] ?? '' ) );

		if ( '' === $alt && 0 !== $attachment_id ) {
			$alt = sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		}

		$images[] = array(
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'alt'           => $alt,
			'width'         => $width,
			'height'        => $height,
			'source_type'   => sanitize_key( (string) ( $image['source_type'] ?? 'unknown' ) ),
			'block_name'    => sanitize_text_field( (string) ( $image['block_name'] ?? '' ) ),
			'is_external'   => $this->is_external_url( $url ),
			'missing_alt'   => '' === $alt,
		);
	}

	/**
	 * Determines whether an image URL points outside the current WordPress host.
	 */
	private function is_external_url( string $url ): bool {
		if ( '' === $url || str_starts_with( $url, '/' ) ) {
			return false;
		}

		$image_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host  = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		return '' !== $image_host && '' !== $site_host && $image_host !== $site_host;
	}
}