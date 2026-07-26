<?php
/**
 * Orchestrates content analysis, prompt creation, image generation and review.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Featured_Image_Generator {
	private NT_Content_Images_Brief_Generator $brief_generator;
	private NT_Content_Images_Brief_Repository $briefs;
	private NT_Content_Images_Featured_Prompt_Builder $prompts;
	private NT_Content_Images_Image_Provider_Interface $provider;
	private NT_Content_Images_Media_Manager $media;
	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Profile_Repository $profiles;

	public function __construct(
		NT_Content_Images_Brief_Generator $brief_generator,
		NT_Content_Images_Brief_Repository $briefs,
		NT_Content_Images_Featured_Prompt_Builder $prompts,
		NT_Content_Images_Image_Provider_Interface $provider,
		NT_Content_Images_Media_Manager $media,
		NT_Content_Images_Generation_Repository $repository,
		NT_Content_Images_Generation_Settings $settings,
		NT_Content_Images_Profile_Repository $profiles
	) {
		$this->brief_generator = $brief_generator;
		$this->briefs          = $briefs;
		$this->prompts         = $prompts;
		$this->provider        = $provider;
		$this->media           = $media;
		$this->repository      = $repository;
		$this->settings        = $settings;
		$this->profiles        = $profiles;
	}

	/** @return array<string, mixed>|WP_Error */
	public function generate( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || 'attachment' === $post->post_type ) {
			return new WP_Error( 'ntci_generation_post_invalid', __( 'Nội dung không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( get_post_thumbnail_id( $post_id ) ) {
			return new WP_Error( 'ntci_generation_featured_exists', __( 'Nội dung này đã có ảnh đại diện. MVP không tự ghi đè ảnh hiện có.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$site = $this->profiles->get_site_profile();
		if ( ! in_array( $post->post_type, (array) ( $site['enabled_post_types'] ?? array() ), true ) ) {
			return new WP_Error( 'ntci_generation_post_type_disabled', __( 'Post type này chưa được bật trong Cấu hình website.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! $this->provider->is_configured() ) {
			return new WP_Error( 'ntci_generation_provider_missing', __( 'Chưa cấu hình API key cho nhà cung cấp ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$latest   = $this->briefs->get_latest_by_post_id( $post_id );
		$brief_id = 0;
		$brief    = array();
		if ( null === $latest || 'outdated' === (string) ( $latest['status'] ?? '' ) ) {
			$generated = $this->brief_generator->generate( $post_id );
			if ( is_wp_error( $generated ) ) {
				return $generated;
			}
			$brief    = $generated;
			$brief_id = absint( $generated['id'] ?? 0 );
		} else {
			$brief    = is_array( $latest['brief'] ?? null ) ? $latest['brief'] : array();
			$brief_id = absint( $latest['id'] ?? 0 );
		}
		if ( empty( $brief ) ) {
			return new WP_Error( 'ntci_generation_brief_missing', __( 'Không thể tạo kế hoạch hình ảnh từ nội dung.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$prompt   = $this->prompts->build( $brief );
		$config   = $this->settings->get();
		$response = $this->provider->generate(
			array(
				'post_id'  => $post_id,
				'brief_id' => $brief_id,
				'prompt'   => $prompt,
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->repository->insert(
				array(
					'post_id'      => $post_id,
					'brief_id'     => $brief_id,
					'provider'     => $this->provider->get_id(),
					'model'        => $config['model'],
					'status'       => 'failed',
					'prompt'       => $prompt,
					'settings'     => $this->public_settings( $config ),
					'error_code'   => $response->get_error_code(),
					'error_message'=> $response->get_error_message(),
				)
			);
			return $response;
		}

		$stored = $this->media->store_featured_candidate( $post_id, $response, $prompt, $config );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$record_id = $this->repository->insert(
			array(
				'post_id'       => $post_id,
				'brief_id'      => $brief_id,
				'attachment_id' => $stored['attachment_id'],
				'provider'      => $response['provider'],
				'model'         => $response['model'],
				'status'        => 'generated',
				'prompt'        => $prompt,
				'settings'      => $this->public_settings( $config ),
				'response'      => array(
					'usage'          => $response['usage'] ?? array(),
					'revised_prompt' => $response['revised_prompt'] ?? '',
					'created'        => $response['created'] ?? 0,
				),
			)
		);
		if ( false === $record_id ) {
			wp_delete_attachment( absint( $stored['attachment_id'] ), true );
			return new WP_Error( 'ntci_generation_store_failed', __( 'Không thể lưu phiên tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		update_post_meta( absint( $stored['attachment_id'] ), '_nt_content_images_generation_id', $record_id );
		do_action( 'nt_content_images_after_generate', $record_id, $post_id, $stored['attachment_id'] );

		return $this->repository->get( $record_id );
	}

	/** @return array<string, mixed>|WP_Error */
	public function approve( int $generation_id ) {
		$item = $this->repository->get( $generation_id );
		if ( null === $item || 'generated' !== (string) $item['status'] ) {
			return new WP_Error( 'ntci_generation_not_approvable', __( 'Ảnh không ở trạng thái chờ duyệt.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$post_id       = absint( $item['post_id'] );
		$attachment_id = absint( $item['attachment_id'] );
		if ( ! get_post( $attachment_id ) ) {
			return new WP_Error( 'ntci_generation_attachment_missing', __( 'Không tìm thấy ảnh trong Media Library.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$current = absint( get_post_thumbnail_id( $post_id ) );
		if ( $current && $current !== $attachment_id ) {
			return new WP_Error( 'ntci_generation_featured_changed', __( 'Bài viết đã được gắn ảnh đại diện khác. Plugin không ghi đè.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
			return new WP_Error( 'ntci_generation_set_thumbnail_failed', __( 'Không thể đặt ảnh đại diện.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$this->repository->update_status( $generation_id, 'approved' );
		update_post_meta( $post_id, '_nt_content_images_featured_generation_id', $generation_id );
		do_action( 'nt_content_images_after_featured_approval', $generation_id, $post_id, $attachment_id );
		return $this->repository->get( $generation_id );
	}

	/** @return array<string, mixed>|WP_Error */
	public function reject( int $generation_id ) {
		$item = $this->repository->get( $generation_id );
		if ( null === $item || 'generated' !== (string) $item['status'] ) {
			return new WP_Error( 'ntci_generation_not_rejectable', __( 'Ảnh không ở trạng thái chờ duyệt.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$this->repository->update_status( $generation_id, 'rejected' );
		return $this->repository->get( $generation_id );
	}

	/** @param array<string, mixed> $settings @return array<string, mixed> */
	private function public_settings( array $settings ): array {
		unset( $settings['api_key'] );
		return $settings;
	}
}
