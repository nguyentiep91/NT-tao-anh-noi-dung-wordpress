<?php
/**
 * Discovers public WordPress post types without hard-coding a website schema.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Post_Type_Registry {
	/**
	 * Returns supported post type objects keyed by slug.
	 *
	 * @return array<string, WP_Post_Type>
	 */
	public function get_available(): array {
		$objects = get_post_types(
			array(
				'show_ui' => true,
			),
			'objects'
		);

		$available = array();
		foreach ( $objects as $slug => $object ) {
			if ( 'attachment' === $slug || ! $object instanceof WP_Post_Type ) {
				continue;
			}
			if ( ! $object->public && ! $object->publicly_queryable ) {
				continue;
			}
			$available[ sanitize_key( $slug ) ] = $object;
		}

		/** Filters post types that can participate in audit and brief workflows. */
		$available = apply_filters( 'nt_content_images_supported_post_types', $available );
		return is_array( $available ) ? $available : array();
	}

	/** @return string[] */
	public function get_slugs(): array {
		return array_keys( $this->get_available() );
	}

	/**
	 * Returns labels for settings screens.
	 *
	 * @return array<string, string>
	 */
	public function get_labels(): array {
		$labels = array();
		foreach ( $this->get_available() as $slug => $object ) {
			$labels[ $slug ] = sanitize_text_field( (string) ( $object->labels->name ?? $slug ) );
		}
		return $labels;
	}

	/**
	 * Restricts a requested list to currently available post types.
	 *
	 * @param string[] $requested Requested slugs.
	 * @return string[]
	 */
	public function constrain( array $requested ): array {
		$requested = array_values( array_unique( array_filter( array_map( 'sanitize_key', $requested ) ) ) );
		return array_values( array_intersect( $requested, $this->get_slugs() ) );
	}
}
