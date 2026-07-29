<?php
/**
 * Calculates structural metrics without rendering post content.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Metrics_Analyzer {
	/**
	 * Complex blocks that require additional insertion safeguards later.
	 *
	 * @var string[]
	 */
	private const COMPLEX_CORE_BLOCKS = array(
		'core/columns',
		'core/cover',
		'core/embed',
		'core/gallery',
		'core/group',
		'core/html',
		'core/media-text',
		'core/shortcode',
		'core/table',
	);

	/**
	 * Analyzes text, headings, blocks and shortcodes.
	 *
	 * @return array<string, int|bool|array<int, string>>
	 */
	public function analyze( string $content ): array {
		$tag_counts = $this->count_html_tags( $content );
		$blocks     = parse_blocks( $content );
		$block_data = $this->collect_block_data( $blocks );
		$shortcodes = $this->detect_shortcodes( $content );
		$text       = $this->extract_readable_text( $content );

		return array(
			'word_count'         => $this->count_words( $text ),
			'paragraph_count'    => $tag_counts['P'],
			'h2_count'           => $tag_counts['H2'],
			'h3_count'           => $tag_counts['H3'],
			'table_count'        => $tag_counts['TABLE'],
			'list_count'         => $tag_counts['UL'] + $tag_counts['OL'],
			'shortcode_count'    => count( $shortcodes ),
			'has_shortcode'      => ! empty( $shortcodes ),
			'has_table'          => 0 < $tag_counts['TABLE'] || in_array( 'core/table', $block_data['block_names'], true ),
			'has_complex_blocks' => $block_data['has_complex_blocks'],
			'block_names'        => $block_data['block_names'],
			'shortcodes'         => $shortcodes,
		);
	}

	/**
	 * Counts relevant HTML tags in serialized block or classic content.
	 *
	 * @return array<string, int>
	 */
	private function count_html_tags( string $content ): array {
		$counts = array(
			'P'     => 0,
			'H2'    => 0,
			'H3'    => 0,
			'TABLE' => 0,
			'UL'    => 0,
			'OL'    => 0,
		);

		$processor = new WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();

			if ( is_string( $tag ) && isset( $counts[ $tag ] ) ) {
				++$counts[ $tag ];
			}
		}

		return $counts;
	}

	/**
	 * Recursively collects unique block names and complexity flags.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array{block_names: array<int, string>, has_complex_blocks: bool}
	 */
	private function collect_block_data( array $blocks ): array {
		$block_names        = array();
		$has_complex_blocks = false;

		$this->walk_blocks( $blocks, $block_names, $has_complex_blocks );
		sort( $block_names );

		return array(
			'block_names'        => array_values( $block_names ),
			'has_complex_blocks' => $has_complex_blocks,
		);
	}

	/**
	 * Walks a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks             Parsed blocks.
	 * @param string[]                         $block_names        Unique block names.
	 * @param bool                             $has_complex_blocks Complexity flag.
	 */
	private function walk_blocks( array $blocks, array &$block_names, bool &$has_complex_blocks ): void {
		foreach ( $blocks as $block ) {
			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

			if ( '' !== $block_name && ! in_array( $block_name, $block_names, true ) ) {
				$block_names[] = $block_name;
			}

			if (
				in_array( $block_name, self::COMPLEX_CORE_BLOCKS, true ) ||
				( '' !== $block_name && ! str_starts_with( $block_name, 'core/' ) )
			) {
				$has_complex_blocks = true;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->walk_blocks( $block['innerBlocks'], $block_names, $has_complex_blocks );
			}
		}
	}

	/**
	 * Detects shortcode tags as text and never executes them.
	 *
	 * @return string[]
	 */
	private function detect_shortcodes( string $content ): array {
		global $shortcode_tags;

		$detected = array();
		$tags     = is_array( $shortcode_tags ) ? array_keys( $shortcode_tags ) : array();

		if ( ! empty( $tags ) ) {
			$pattern = '/' . get_shortcode_regex( $tags ) . '/s';

			if ( preg_match_all( $pattern, $content, $matches ) ) {
				foreach ( $matches[2] as $tag ) {
					$detected[] = sanitize_key( (string) $tag );
				}
			}
		}

		if ( preg_match_all( '/\[(?!\/|\[)([A-Za-z][A-Za-z0-9_-]*)(?:\s[^\]]*)?\]/u', $content, $generic_matches ) ) {
			foreach ( $generic_matches[1] as $tag ) {
				$detected[] = sanitize_key( (string) $tag );
			}
		}

		$detected = array_values( array_unique( array_filter( $detected ) ) );
		sort( $detected );

		return $detected;
	}

	/**
	 * Extracts readable text without executing blocks or shortcodes.
	 */
	private function extract_readable_text( string $content ): string {
		$content = preg_replace( '/<!--\s*\/?wp:[\s\S]*?-->/', ' ', $content ) ?? $content;
		$content = strip_shortcodes( $content );
		$content = preg_replace( '/\[(?:\/?)[A-Za-z][^\]]*\]/u', ' ', $content ) ?? $content;
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/\s+/u', ' ', $content ) ?? $content;

		return trim( $content );
	}

	/**
	 * Counts Unicode word-like tokens, including Vietnamese text.
	 */
	private function count_words( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		preg_match_all( "/[\p{L}\p{N}]+(?:['’.-][\p{L}\p{N}]+)*/u", $text, $matches );

		return count( $matches[0] );
	}
}