<?php
/**
 * Resolves SEO metadata through vendor-neutral adapters.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_SEO_Adapter_Manager {
	/** @var NT_Content_Images_SEO_Adapter_Interface[] */
	private array $adapters;

	public function __construct() {
		$adapters = array(
			new NT_Content_Images_Yoast_SEO_Adapter(),
			new NT_Content_Images_Rank_Math_SEO_Adapter(),
			new NT_Content_Images_WordPress_SEO_Adapter(),
		);
		$adapters = apply_filters( 'nt_content_images_seo_adapters', $adapters );
		$this->adapters = array_values(
			array_filter(
				is_array( $adapters ) ? $adapters : array(),
				static fn( $adapter ): bool => $adapter instanceof NT_Content_Images_SEO_Adapter_Interface
			)
		);
	}

	/**
	 * Returns the best available SEO metadata package.
	 *
	 * @return array{adapter: string, focus_keyphrase: string, seo_title: string, seo_description: string}
	 */
	public function get_metadata( int $post_id ): array {
		$result = array(
			'adapter'         => 'wordpress',
			'focus_keyphrase' => '',
			'seo_title'       => '',
			'seo_description' => '',
		);

		foreach ( $this->adapters as $adapter ) {
			if ( ! $adapter->supports() ) {
				continue;
			}
			$focus      = $adapter->get_focus_keyphrase( $post_id );
			$title      = $adapter->get_seo_title( $post_id );
			$description= $adapter->get_seo_description( $post_id );

			if ( '' === $result['focus_keyphrase'] && '' !== $focus ) {
				$result['focus_keyphrase'] = $focus;
			}
			if ( '' === $result['seo_title'] && '' !== $title ) {
				$result['seo_title'] = $title;
			}
			if ( '' === $result['seo_description'] && '' !== $description ) {
				$result['seo_description'] = $description;
			}
			if ( 'wordpress' !== $adapter->get_id() && ( '' !== $focus || '' !== $title || '' !== $description ) ) {
				$result['adapter'] = sanitize_key( $adapter->get_id() );
			}
		}

		return apply_filters( 'nt_content_images_seo_metadata', $result, $post_id );
	}
}
