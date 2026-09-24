<?php
/**
 * Contract for portable, independently configurable content rule packs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NT_Content_Images_Rule_Pack_Interface {
	/** Returns the stable machine ID. */
	public function get_id(): string;

	/** Returns the translated admin label. */
	public function get_label(): string;

	/** Higher values win when two rule packs match equally. */
	public function get_priority(): int;

	/**
	 * Returns classification rules.
	 *
	 * Each rule must contain content_type, search_intent, keywords and weight.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_classification_rules(): array;

	/**
	 * Returns restrictions contributed by this pack.
	 *
	 * @param array<string, mixed> $source       Brief source package.
	 * @param string               $content_type Resolved content type.
	 * @return string[]
	 */
	public function get_restrictions( array $source, string $content_type ): array;

	/**
	 * Returns heading terms that should not be automatic image anchors.
	 *
	 * @return string[]
	 */
	public function get_blocked_heading_terms(): array;
}
