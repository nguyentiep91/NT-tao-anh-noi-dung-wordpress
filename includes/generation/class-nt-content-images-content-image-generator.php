<?php
/**
 * Generates the planned set of in-article images for one post.
 *
 * Reads the image brief plan (count + placements), generates one image per
 * safe slot and stores each as a reviewable generation record. Nothing is
 * inserted into post_content here — insertion is a separate approved step.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Image_Generator {
	private NT_Content_Images_Brief_Generator $brief_generator;
	private NT_Content_Images_Brief_Repository $briefs;
	private NT_Content_Images_Content_Prompt_Builder $prompts;
	private NT_Content_Images_Image_Provider_Manager $providers;
	private NT_Content_Images_Media_Manager $media;
	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Generation_Lock $lock;
	private NT_Content_Images_Safe_Logger $logger;

	public function __construct(
		NT_Content_Images_Brief_Generator $brief_generator,
		NT_Content_Images_Brief_Repository $briefs,
		NT_Content_Images_Content_Prompt_Builder $prompts,
		NT_Content_Images_Image_Provider_Manager $providers,
		NT_Content_Images_Media_Manager $media,
		NT_Content_Images_Generation_Repository $repository,
		NT_Content_Images_Generation_Settings $settings,
		NT_Content_Images_Profile_Repository $profiles,
		?NT_Content_Images_Generation_Lock $lock = null,
		?NT_Content_Images_Safe_Logger $logger = null
	) {
		$this->brief_generator = $brief_generator;
		$this->briefs          = $briefs;
		$this->prompts         = $prompts;
		$this->providers       = $providers;
		$this->media           = $media;
		$this->repository      = $repository;
		$this->settings        = $settings;
		$this->profiles        = $profiles;
		$this->lock            = $lock ?? new NT_Content_Images_Generation_Lock();
		$this->logger          = $logger ?? new NT_Content_Images_Safe_Logger();
	}

	/**
	 * Returns the plan and current per-slot state for one post.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_plan( int $post_id ) {
		$post = $this->validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$brief = $this->resolve_brief( $post_id );
		if ( is_wp_error( $brief ) ) {
			return $brief;
		}
		$plan    = is_array( $brief['brief']['content_images'] ?? null ) ? $brief['brief']['content_images'] : array();
		$records = $this->get_content_records( $post_id );
		$slots   = array();
		foreach ( $plan as $image ) {
			$index     = absint( $image['index'] ?? 0 );
			$placement = is_array( $image['placement'] ?? null ) ? $image['placement'] : array();
			$record    = null;
			foreach ( $records as $candidate ) {
				if ( absint( $candidate['settings']['image_index'] ?? 0 ) === $index && 'rejected' !== (string) $candidate['status'] && 'failed' !== (string) $candidate['status'] ) {
					$record = $candidate;
				}
			}
			$slots[] = array(
				'index'     => $index,
				'purpose'   => sanitize_text_field( (string) ( $image['purpose'] ?? '' ) ),
				'placement' => $placement,
				'safe'      => 'safe_candidate' === (string) ( $placement['safety'] ?? '' ),
				'record'    => $record,
			);
		}
		return array(
			'post_id'    => $post_id,
			'title'      => get_the_title( $post_id ),
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
			'view_url'   => get_permalink( $post_id ),
			'brief_id'   => absint( $brief['id'] ),
			'slots'      => $slots,
			'records'    => $records,
			'snapshot'   => '' !== (string) get_post_meta( $post_id, '_ntci_content_snapshot', true ),
		);
	}

	/**
	 * Generates images for every safe planned slot (or one slot via $only_index).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_set( int $post_id, int $only_index = 0 ) {
		$post = $this->validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! $this->lock->acquire( $post_id ) ) {
			return new WP_Error( 'ntci_content_generation_locked', __( 'Bài viết này đang có một yêu cầu tạo ảnh khác.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 409 ) );
		}
		try {
			return $this->generate_set_locked( $post, $only_index );
		} finally {
			$this->lock->release( $post_id );
		}
	}

	/** @return array<string, mixed>|WP_Error */
	private function generate_set_locked( WP_Post $post, int $only_index ) {
		$post_id  = absint( $post->ID );
		$provider = $this->providers->get_active();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		if ( ! $provider->is_configured() ) {
			return new WP_Error( 'ntci_content_provider_missing', __( 'Chưa cấu hình API key cho nhà cung cấp ảnh đang chọn.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$brief = $this->resolve_brief( $post_id );
		if ( is_wp_error( $brief ) ) {
			return $brief;
		}
		$plan = is_array( $brief['brief']['content_images'] ?? null ) ? $brief['brief']['content_images'] : array();
		if ( array() === $plan ) {
			return new WP_Error( 'ntci_content_plan_empty', __( 'Kế hoạch hình ảnh của bài này không có ảnh trong nội dung.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$existing = array();
		foreach ( $this->get_content_records( $post_id ) as $record ) {
			if ( in_array( (string) $record['status'], array( 'generated', 'approved', 'inserted' ), true ) ) {
				$existing[ absint( $record['settings']['image_index'] ?? 0 ) ] = true;
			}
		}

		$config    = $this->settings->get();
		$generated = array();
		$skipped   = array();
		$errors    = array();
		foreach ( $plan as $image ) {
			$index     = absint( $image['index'] ?? 0 );
			$placement = is_array( $image['placement'] ?? null ) ? $image['placement'] : array();
			if ( $only_index > 0 && $index !== $only_index ) {
				continue;
			}
			if ( 'safe_candidate' !== (string) ( $placement['safety'] ?? '' ) ) {
				$skipped[] = array( 'index' => $index, 'reason' => 'placement_manual_review' );
				continue;
			}
			if ( isset( $existing[ $index ] ) ) {
				$skipped[] = array( 'index' => $index, 'reason' => 'already_generated' );
				continue;
			}
			$daily_limit = absint( $config['daily_limit'] ?? 0 );
			if ( NT_Content_Images_Usage_Tracker::is_exhausted( $daily_limit ) ) {
				$errors[] = array( 'index' => $index, 'code' => 'ntci_daily_limit_reached', 'message' => NT_Content_Images_Usage_Tracker::limit_error( $daily_limit )->get_error_message() );
				break;
			}

			$prompt   = $this->prompts->build( $brief['brief'], $image, $post );
			$response = $provider->generate(
				array(
					'post_id'  => $post_id,
					'brief_id' => absint( $brief['id'] ),
					'prompt'   => $prompt,
				)
			);
			if ( is_wp_error( $response ) ) {
				$clean = NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() );
				$errors[] = array( 'index' => $index, 'code' => $response->get_error_code(), 'message' => $clean );
				$this->logger->log( 'error', 'content_generation_failed', array( 'post_id' => $post_id, 'index' => $index, 'provider' => $provider->get_id(), 'message' => $clean ) );
				continue;
			}

			NT_Content_Images_Usage_Tracker::increment( $provider->get_id() );
			$heading = sanitize_text_field( (string) ( $placement['heading_text'] ?? '' ) );
			$topic   = sanitize_text_field( (string) ( $brief['brief']['topic'] ?? get_the_title( $post ) ) );
			$stored  = $this->media->store_content_candidate(
				$post_id,
				$response,
				$prompt,
				$config,
				array(
					'alt_text' => '' !== $heading ? $heading . ' — ' . $topic : $topic,
					'caption'  => '' !== $heading ? sprintf( __( 'Minh họa cho phần "%s"', 'nt-tao-anh-noi-dung-wordpress' ), $heading ) : sprintf( __( 'Minh họa chủ đề: %s', 'nt-tao-anh-noi-dung-wordpress' ), $topic ),
					'title'    => '' !== $heading ? $heading . ' — ' . $topic : $topic,
				)
			);
			if ( is_wp_error( $stored ) ) {
				$errors[] = array( 'index' => $index, 'code' => $stored->get_error_code(), 'message' => $stored->get_error_message() );
				continue;
			}
			$record_id = $this->repository->insert(
				array(
					'post_id'       => $post_id,
					'brief_id'      => absint( $brief['id'] ),
					'attachment_id' => absint( $stored['attachment_id'] ),
					'provider'      => $response['provider'],
					'model'         => $response['model'],
					'status'        => 'generated',
					'prompt'        => $prompt,
					'settings'      => array_merge(
						NT_Content_Images_Secret_Redactor::redact( $config ),
						array(
							'image_type'  => 'content',
							'image_index' => $index,
							'placement'   => $placement,
						)
					),
					'response'      => array(
						'usage'   => NT_Content_Images_Secret_Redactor::redact( $response['usage'] ?? array() ),
						'created' => absint( $response['created'] ?? 0 ),
					),
				)
			);
			if ( false === $record_id ) {
				wp_delete_attachment( absint( $stored['attachment_id'] ), true );
				$errors[] = array( 'index' => $index, 'code' => 'ntci_content_store_failed', 'message' => __( 'Không thể lưu phiên tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
				continue;
			}
			update_post_meta( absint( $stored['attachment_id'] ), '_nt_content_images_generation_id', $record_id );
			$this->logger->log( 'info', 'content_generation_completed', array( 'generation_id' => $record_id, 'post_id' => $post_id, 'index' => $index ) );
			// Đọc record trước khi bắn action: auto-cleanup có thể xoá bản gốc ngay sau khi chèn chữ.
			$generated_record = $this->repository->get( $record_id );
			do_action( 'nt_content_images_after_content_generate', $record_id, $post_id, absint( $stored['attachment_id'] ) );
			$generated[] = $generated_record;
		}

		if ( array() === $generated && array() !== $errors ) {
			return new WP_Error( 'ntci_content_generation_failed', sanitize_text_field( (string) $errors[0]['message'] ), array( 'errors' => $errors ) );
		}
		return array(
			'post_id'   => $post_id,
			'generated' => $generated,
			'skipped'   => $skipped,
			'errors'    => $errors,
		);
	}

	/** @return array<int, array<string, mixed>> */
	public function get_content_records( int $post_id ): array {
		$records = array();
		foreach ( $this->repository->get_by_post( $post_id ) as $record ) {
			if ( 'content' === (string) ( $record['settings']['image_type'] ?? '' ) ) {
				$records[] = $record;
			}
		}
		return $records;
	}

	/** @return WP_Post|WP_Error */
	private function validate_post( int $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post->ID ) || 'attachment' === $post->post_type ) {
			return new WP_Error( 'ntci_content_post_invalid', __( 'Nội dung không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$site = $this->profiles->get_site_profile();
		if ( ! in_array( $post->post_type, (array) ( $site['enabled_post_types'] ?? array() ), true ) ) {
			return new WP_Error( 'ntci_content_post_type_disabled', __( 'Post type này chưa được bật trong Cấu hình website.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $post;
	}

	/** @return array{id: int, brief: array<string, mixed>}|WP_Error */
	private function resolve_brief( int $post_id ) {
		$latest = $this->briefs->get_latest_by_post_id( $post_id );
		if ( null !== $latest && 'outdated' !== (string) ( $latest['status'] ?? '' ) && is_array( $latest['brief'] ?? null ) ) {
			return array( 'id' => absint( $latest['id'] ?? 0 ), 'brief' => $latest['brief'] );
		}
		$generated = $this->brief_generator->generate( $post_id );
		if ( is_wp_error( $generated ) ) {
			return $generated;
		}
		return array( 'id' => absint( $generated['id'] ?? 0 ), 'brief' => $generated );
	}
}
