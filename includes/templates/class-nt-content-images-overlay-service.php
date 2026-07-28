<?php
/**
 * Applies overlay templates to reviewed images and keeps the approval workflow.
 *
 * The overlay output is always saved as a NEW attachment and a NEW generation
 * record in `generated` state; the source image is never modified.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Overlay_Service {
	private NT_Content_Images_Template_Registry $registry;
	private NT_Content_Images_Template_Settings $settings;
	private NT_Content_Images_Overlay_Renderer $renderer;
	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Media_Manager $media;
	private NT_Content_Images_Generation_Settings $generation_settings;
	private NT_Content_Images_Profile_Repository $profiles;

	public function __construct(
		NT_Content_Images_Template_Registry $registry,
		NT_Content_Images_Template_Settings $settings,
		NT_Content_Images_Overlay_Renderer $renderer,
		NT_Content_Images_Generation_Repository $repository,
		NT_Content_Images_Media_Manager $media,
		NT_Content_Images_Generation_Settings $generation_settings,
		NT_Content_Images_Profile_Repository $profiles
	) {
		$this->registry            = $registry;
		$this->settings            = $settings;
		$this->renderer            = $renderer;
		$this->repository          = $repository;
		$this->media               = $media;
		$this->generation_settings = $generation_settings;
		$this->profiles            = $profiles;
	}

	/** Hooks automatic overlay onto freshly generated AI images when enabled. */
	public function register(): void {
		add_action( 'nt_content_images_after_generate', array( $this, 'maybe_auto_overlay' ) );
		add_action( 'nt_content_images_after_content_generate', array( $this, 'maybe_auto_overlay' ) );
	}

	/**
	 * Applies the default template right after an AI generation so review
	 * candidates already carry the title and branding. Failures stay silent:
	 * the plain AI record remains available for manual overlay.
	 */
	public function maybe_auto_overlay( int $generation_id ): void {
		if ( empty( $this->settings->get()['auto_overlay'] ) ) {
			return;
		}
		$status = $this->get_status();
		if ( empty( $status['ready'] ) ) {
			return;
		}
		$this->apply( absint( $generation_id ), '' );
	}

	/** @return array<string, mixed> */
	public function get_status(): array {
		$context = $this->profiles->get_context();
		$brand   = is_array( $context['brand'] ?? null ) ? $context['brand'] : array();
		$fonts   = $this->renderer->get_font_status();
		$logo_id = absint( $brand['logo_attachment_id'] ?? 0 );
		return array(
			'gd_supported'    => NT_Content_Images_Overlay_Renderer::is_supported(),
			'fonts_ready'     => (bool) $fonts['ready'],
			'fonts_missing'   => $fonts['missing'],
			'ready'           => NT_Content_Images_Overlay_Renderer::is_supported() && $fonts['ready'] && ! empty( $brand['overlay_enabled'] ),
			'overlay_enabled' => ! empty( $brand['overlay_enabled'] ),
			'logo_configured' => $logo_id > 0 && '' !== (string) get_attached_file( $logo_id ),
			'templates'       => $this->registry->get_public(),
			'settings'        => $this->settings->get_public(),
		);
	}

	/** @return array<string, mixed>|WP_Error */
	public function preview( int $generation_id, string $template_id = '' ) {
		$prepared = $this->prepare( $generation_id, $template_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		return array(
			'generation_id' => $generation_id,
			'template'      => $prepared['template']['id'],
			'preview'       => 'data:' . $prepared['rendered']['mime'] . ';base64,' . base64_encode( $prepared['rendered']['bytes'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'width'         => $prepared['rendered']['width'],
			'height'        => $prepared['rendered']['height'],
		);
	}

	/** @return array<string, mixed>|WP_Error */
	public function apply( int $generation_id, string $template_id = '' ) {
		$prepared = $this->prepare( $generation_id, $template_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$item       = $prepared['item'];
		$template   = $prepared['template'];
		$config     = $this->generation_settings->get();
		$is_content = 'content' === (string) ( $item['settings']['image_type'] ?? '' );
		$image      = array(
			'bytes'    => $prepared['rendered']['bytes'],
			'provider' => 'template',
		);
		// Bản chèn chữ kế thừa alt/caption/title từ ảnh gốc (nơi AI đã soạn); thiếu thì rơi về mẫu theo heading.
		$source_att = absint( $item['attachment_id'] );
		$placement  = is_array( $item['settings']['placement'] ?? null ) ? $item['settings']['placement'] : array();
		$heading    = trim( (string) ( $placement['heading_text'] ?? '' ) );
		$topic      = (string) get_the_title( absint( $item['post_id'] ) );
		$meta       = array(
			'alt_text' => (string) get_post_meta( $source_att, '_wp_attachment_image_alt', true ),
			'caption'  => (string) get_post_field( 'post_excerpt', $source_att ),
			'title'    => (string) get_the_title( $source_att ),
		);
		if ( '' === trim( $meta['alt_text'] ) ) {
			$meta['alt_text'] = '' !== $heading ? $heading . ' — ' . $topic : $topic;
		}
		if ( '' === trim( $meta['caption'] ) && $is_content ) {
			$meta['caption'] = '' !== $heading ? sprintf( __( 'Minh họa cho phần "%s"', 'nt-tao-anh-noi-dung-wordpress' ), $heading ) : sprintf( __( 'Minh họa chủ đề: %s', 'nt-tao-anh-noi-dung-wordpress' ), $topic );
		}
		if ( $is_content ) {
			$stored = $this->media->store_content_candidate( absint( $item['post_id'] ), $image, (string) $item['prompt'], $config, $meta );
		} else {
			$stored = $this->media->store_featured_candidate( absint( $item['post_id'] ), $image, (string) $item['prompt'], $config, $meta );
		}
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$slot_settings = $is_content ? array(
			'image_type'  => 'content',
			'image_index' => absint( $item['settings']['image_index'] ?? 0 ),
			'placement'   => is_array( $item['settings']['placement'] ?? null ) ? $item['settings']['placement'] : array(),
		) : array();
		$new_id = $this->repository->insert(
			array(
				'post_id'       => absint( $item['post_id'] ),
				'brief_id'      => absint( $item['brief_id'] ),
				'attachment_id' => absint( $stored['attachment_id'] ),
				'provider'      => 'template',
				'model'         => $template['id'],
				'status'        => 'generated',
				'prompt'        => (string) $item['prompt'],
				'settings'      => array_merge(
					array(
						'template'      => $template['id'],
						'target_width'  => absint( $config['target_width'] ),
						'target_height' => absint( $config['target_height'] ),
						'output_format' => 'image/webp' === (string) $prepared['rendered']['mime'] ? 'webp' : 'png',
					),
					$slot_settings
				),
				'response'      => array(
					'overlay' => array(
						'source_generation_id' => $generation_id,
						'template'             => $template['id'],
						'rendered_at'          => time(),
					),
				),
			)
		);
		if ( false === $new_id ) {
			wp_delete_attachment( absint( $stored['attachment_id'] ), true );
			return new WP_Error( 'ntci_overlay_store_failed', __( 'Không thể lưu ảnh đã chèn chữ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		update_post_meta( absint( $stored['attachment_id'] ), '_nt_content_images_generation_id', $new_id );
		update_post_meta( absint( $stored['attachment_id'] ), '_ntci_overlay_template', sanitize_key( (string) $template['id'] ) );
		$this->repository->merge_response( $generation_id, array( 'overlay' => array( 'latest_overlay_generation_id' => $new_id ) ) );
		do_action( 'nt_content_images_after_overlay', $new_id, $generation_id, absint( $stored['attachment_id'] ) );
		return $this->repository->get( $new_id );
	}

	/**
	 * Validates the generation record and renders the requested template.
	 *
	 * @return array{item: array<string, mixed>, template: array<string, mixed>, rendered: array<string, mixed>}|WP_Error
	 */
	private function prepare( int $generation_id, string $template_id ) {
		$item = $this->repository->get( absint( $generation_id ) );
		if ( null === $item ) {
			return new WP_Error( 'ntci_overlay_generation_missing', __( 'Không tìm thấy phiên tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! in_array( (string) $item['status'], array( 'generated', 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'ntci_overlay_status_invalid', __( 'Ảnh này không ở trạng thái có thể chèn chữ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( 'template' === (string) $item['provider'] ) {
			return new WP_Error( 'ntci_overlay_already_applied', __( 'Ảnh này đã được chèn chữ. Hãy chọn ảnh nền gốc để áp dụng mẫu khác.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$attachment_id = absint( $item['attachment_id'] );
		$source_file   = $attachment_id > 0 ? (string) get_attached_file( $attachment_id ) : '';
		if ( '' === $source_file || ! file_exists( $source_file ) ) {
			return new WP_Error( 'ntci_overlay_attachment_missing', __( 'Không tìm thấy file ảnh gốc trong Media Library.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$template_id = sanitize_key( $template_id );
		if ( '' === $template_id ) {
			$defaults    = $this->settings->get();
			$template_id = 'content' === (string) ( $item['settings']['image_type'] ?? '' )
				? (string) $defaults['content_template']
				: (string) $defaults['default_template'];
		}
		$template = $this->registry->get( $template_id );
		if ( null === $template ) {
			return new WP_Error( 'ntci_overlay_template_missing', __( 'Mẫu chữ không tồn tại.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$data = $this->build_data( $item, $template );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rendered = $this->renderer->render( $source_file, $template, $data );
		if ( is_wp_error( $rendered ) ) {
			return $rendered;
		}
		return array(
			'item'     => $item,
			'template' => $template,
			'rendered' => $rendered,
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private function build_data( array $item, array $template ) {
		$post = get_post( absint( $item['post_id'] ) );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'ntci_overlay_post_missing', __( 'Không tìm thấy bài viết của ảnh này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$context = $this->profiles->get_context();
		$brand   = is_array( $context['brand'] ?? null ) ? $context['brand'] : array();
		if ( empty( $brand['overlay_enabled'] ) ) {
			return new WP_Error( 'ntci_overlay_disabled', __( 'Chèn chữ đang tắt trong Cấu hình website → thương hiệu.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$settings = $this->settings->get();
		$config   = $this->generation_settings->get();

		$logo_file = '';
		if ( ! empty( $settings['show_logo'] ) ) {
			$logo_id   = absint( $brand['logo_attachment_id'] ?? 0 );
			$logo_file = $logo_id > 0 ? (string) get_attached_file( $logo_id ) : '';
			if ( '' !== $logo_file && ! file_exists( $logo_file ) ) {
				$logo_file = '';
			}
		}
		$website = (string) ( $brand['website'] ?? '' );
		$parts   = wp_parse_url( $website );
		$host    = is_array( $parts ) ? (string) ( $parts['host'] ?? '' ) : '';

		$overlay_title = (string) $post->post_title;
		if ( 'content' === (string) ( $item['settings']['image_type'] ?? '' ) ) {
			$placement     = is_array( $item['settings']['placement'] ?? null ) ? $item['settings']['placement'] : array();
			$heading       = trim( (string) ( $placement['heading_text'] ?? '' ) );
			$overlay_title = '' !== $heading ? $heading : $overlay_title;
		}
		$data = array(
			'title'         => ! empty( $template['shows_title'] ) ? sanitize_text_field( $overlay_title ) : '',
			'badge'         => ! empty( $settings['show_category'] ) ? $this->resolve_badge( $post ) : '',
			'brand_name'    => ! empty( $settings['show_site_name'] ) ? sanitize_text_field( (string) ( $brand['brand_name'] ?? '' ) ) : '',
			'website_host'  => ! empty( $settings['show_site_name'] ) ? sanitize_text_field( $host ) : '',
			'logo_file'     => $logo_file,
			'colors'        => array(
				'primary'   => (string) ( $brand['primary_color'] ?? '' ),
				'secondary' => (string) ( $brand['secondary_color'] ?? '' ),
				'accent'    => (string) ( $brand['accent_color'] ?? '' ),
			),
			'target_width'  => absint( $config['target_width'] ),
			'target_height' => absint( $config['target_height'] ),
		);

		/** Allows adjusting overlay content per post before rendering. */
		$filtered = apply_filters( 'nt_content_images_overlay_data', $data, $item, $template, $post );
		return is_array( $filtered ) ? $filtered : $data;
	}

	private function resolve_badge( WP_Post $post ): string {
		$terms = get_the_terms( $post, 'category' );
		if ( is_array( $terms ) && array() !== $terms ) {
			return sanitize_text_field( (string) $terms[0]->name );
		}
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
		foreach ( (array) $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->public ) || 'post_format' === (string) $taxonomy->name ) {
				continue;
			}
			$terms = get_the_terms( $post, (string) $taxonomy->name );
			if ( is_array( $terms ) && array() !== $terms ) {
				return sanitize_text_field( (string) $terms[0]->name );
			}
		}
		return '';
	}
}
