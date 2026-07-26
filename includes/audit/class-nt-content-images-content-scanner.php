<?php
/**
 * Coordinates read-only post scanning and optional audit persistence.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Scanner {
	private NT_Content_Images_Image_Detector $image_detector;
	private NT_Content_Images_Content_Metrics_Analyzer $metrics_analyzer;
	private NT_Content_Images_Priority_Calculator $priority_calculator;
	private NT_Content_Images_Audit_Repository $repository;

	/**
	 * Sets scanner dependencies.
	 */
	public function __construct(
		NT_Content_Images_Image_Detector $image_detector,
		NT_Content_Images_Content_Metrics_Analyzer $metrics_analyzer,
		NT_Content_Images_Priority_Calculator $priority_calculator,
		NT_Content_Images_Audit_Repository $repository
	) {
		$this->image_detector      = $image_detector;
		$this->metrics_analyzer    = $metrics_analyzer;
		$this->priority_calculator = $priority_calculator;
		$this->repository          = $repository;
	}

	/**
	 * Scans one post without changing post, postmeta or attachment data.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function scan( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'nt_content_images_post_not_found',
				__( 'Không tìm thấy nội dung cần kiểm tra.', 'nt-tao-anh-noi-dung-wordpress' )
			);
		}

		if ( wp_is_post_revision( $post_id ) || 'attachment' === $post->post_type ) {
			return new WP_Error(
				'nt_content_images_unsupported_post',
				__( 'Loại nội dung này không thuộc phạm vi audit.', 'nt-tao-anh-noi-dung-wordpress' )
			);
		}

		$featured_image_id = absint( get_post_thumbnail_id( $post_id ) );
		$images            = $this->image_detector->detect( (string) $post->post_content );
		$metrics           = $this->metrics_analyzer->analyze( (string) $post->post_content );
		$focus_keyphrase   = sanitize_text_field( (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		$image_summary     = $this->summarize_images( $images );
		$priority          = $this->priority_calculator->calculate(
			array(
				'post_status'           => $post->post_status,
				'featured_image_id'     => $featured_image_id,
				'content_image_count'   => count( $images ),
				'word_count'            => $metrics['word_count'],
				'yoast_focus_keyphrase' => $focus_keyphrase,
			)
		);
		$scanned_at        = current_time( 'mysql', true );

		return array(
			'post_id'               => $post_id,
			'post_type'             => $post->post_type,
			'post_status'           => $post->post_status,
			'featured_image_id'     => $featured_image_id,
			'content_image_count'   => count( $images ),
			'local_image_count'     => $image_summary['local'],
			'external_image_count'  => $image_summary['external'],
			'missing_alt_count'     => $image_summary['missing_alt'],
			'word_count'            => $metrics['word_count'],
			'paragraph_count'       => $metrics['paragraph_count'],
			'h2_count'              => $metrics['h2_count'],
			'h3_count'              => $metrics['h3_count'],
			'table_count'           => $metrics['table_count'],
			'list_count'            => $metrics['list_count'],
			'shortcode_count'       => $metrics['shortcode_count'],
			'has_shortcode'         => $metrics['has_shortcode'],
			'has_table'             => $metrics['has_table'],
			'has_complex_blocks'    => $metrics['has_complex_blocks'],
			'yoast_focus_keyphrase' => $focus_keyphrase,
			'priority_score'        => $priority['score'],
			'priority_label'        => $priority['label'],
			'audit_status'          => 'scanned',
			'content_hash'          => $this->calculate_hash_for_post( $post, $featured_image_id ),
			'scan_error'            => '',
			'scanned_at'            => $scanned_at,
			'analysis'              => array(
				'title'                      => get_the_title( $post ),
				'slug'                       => $post->post_name,
				'author_id'                  => absint( $post->post_author ),
				'published_at'               => $post->post_date_gmt,
				'modified_at'                => $post->post_modified_gmt,
				'taxonomies'                 => $this->get_taxonomy_terms( $post ),
				'images'                     => $images,
				'block_names'                => $metrics['block_names'],
				'shortcodes'                 => $metrics['shortcodes'],
				'featured_image_alt'         => $this->get_featured_image_alt( $featured_image_id ),
				'recommended_content_images' => $priority['recommended_content_images'],
				'priority_reasons'           => $priority['reasons'],
			),
		);
	}

	/**
	 * Scans and stores one derived audit record.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function scan_and_store( int $post_id ) {
		$result = $this->scan( $post_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$stored = $this->repository->upsert( $result );

		if ( false === $stored ) {
			return new WP_Error(
				'nt_content_images_audit_store_failed',
				__( 'Không thể lưu kết quả audit.', 'nt-tao-anh-noi-dung-wordpress' )
			);
		}

		return $result;
	}

	/**
	 * Calculates the lightweight hash used to skip unchanged posts.
	 *
	 * @return string|WP_Error
	 */
	public function get_content_hash( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'nt_content_images_post_not_found', __( 'Không tìm thấy nội dung cần kiểm tra.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		return $this->calculate_hash_for_post( $post, absint( get_post_thumbnail_id( $post_id ) ) );
	}

	/**
	 * Returns the repository for list, batch and export modules.
	 */
	public function get_repository(): NT_Content_Images_Audit_Repository {
		return $this->repository;
	}

	/**
	 * Summarizes normalized image flags.
	 *
	 * @param array<int, array<string, mixed>> $images Detected images.
	 * @return array{local: int, external: int, missing_alt: int}
	 */
	private function summarize_images( array $images ): array {
		$summary = array(
			'local'       => 0,
			'external'    => 0,
			'missing_alt' => 0,
		);

		foreach ( $images as $image ) {
			if ( ! empty( $image['is_external'] ) ) {
				++$summary['external'];
			} else {
				++$summary['local'];
			}

			if ( ! empty( $image['missing_alt'] ) ) {
				++$summary['missing_alt'];
			}
		}

		return $summary;
	}

	/**
	 * Builds a deterministic hash to support incremental rescans.
	 */
	private function calculate_hash_for_post( WP_Post $post, int $featured_image_id ): string {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					(string) $post->post_title,
					(string) $post->post_content,
					(string) $featured_image_id,
					(string) $post->post_modified_gmt,
				)
			)
		);
	}

	/**
	 * Returns taxonomy terms without rendering content.
	 *
	 * @return array<string, array<int, array{id: int, name: string, slug: string}>>
	 */
	private function get_taxonomy_terms( WP_Post $post ): array {
		$result     = array();
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$result[ $taxonomy ] = array_map(
				static function ( WP_Term $term ): array {
					return array(
						'id'   => absint( $term->term_id ),
						'name' => sanitize_text_field( $term->name ),
						'slug' => sanitize_title( $term->slug ),
					);
				},
				$terms
			);
		}

		return $result;
	}

	/**
	 * Returns featured image alt text when available.
	 */
	private function get_featured_image_alt( int $attachment_id ): string {
		if ( 0 === $attachment_id ) {
			return '';
		}

		return sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}
}
