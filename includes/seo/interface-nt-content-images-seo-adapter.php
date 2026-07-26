<?php
/**
 * Contract for reading SEO metadata without coupling the plugin to one vendor.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NT_Content_Images_SEO_Adapter_Interface {
	/** Returns the stable adapter ID. */
	public function get_id(): string;

	/** Returns whether the adapter's SEO plugin is active. */
	public function supports(): bool;

	/** Returns a focus keyphrase or an empty string. */
	public function get_focus_keyphrase( int $post_id ): string;

	/** Returns an SEO title or an empty string. */
	public function get_seo_title( int $post_id ): string;

	/** Returns an SEO description or an empty string. */
	public function get_seo_description( int $post_id ): string;
}
