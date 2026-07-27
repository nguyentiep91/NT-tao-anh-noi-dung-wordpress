<?php
/**
 * Batch queue for AI image generation, processed one image per step.
 *
 * Mirrors the audit batch pattern: the job lives in an option, the admin UI
 * keeps calling step() while the page is open, and the job can be paused,
 * resumed or cancelled at any time. Each step performs at most ONE provider
 * request so a step always fits inside a normal admin request.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Queue {
	private const OPTION = 'nt_content_images_generation_queue';
	private const LOCK   = 'nt_content_images_queue_step_lock';

	private NT_Content_Images_Featured_Image_Generator $featured;
	private NT_Content_Images_Content_Image_Generator $content;
	private NT_Content_Images_Audit_Repository $audits;
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Generation_Settings $settings;

	public function __construct(
		NT_Content_Images_Featured_Image_Generator $featured,
		NT_Content_Images_Content_Image_Generator $content,
		NT_Content_Images_Audit_Repository $audits,
		NT_Content_Images_Profile_Repository $profiles,
		NT_Content_Images_Generation_Settings $settings
	) {
		$this->featured = $featured;
		$this->content  = $content;
		$this->audits   = $audits;
		$this->profiles = $profiles;
		$this->settings = $settings;
	}

	/** @return array<string, mixed>|WP_Error */
	public function start( array $args ) {
		$job = $this->get_job();
		if ( null !== $job && in_array( (string) $job['status'], array( 'running', 'paused' ), true ) ) {
			return new WP_Error( 'ntci_queue_active', __( 'Đang có một đợt chạy hàng loạt khác. Hãy tiếp tục hoặc hủy đợt đó trước.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$include_featured = ! empty( $args['include_featured'] );
		$include_content  = ! empty( $args['include_content'] );
		if ( ! $include_featured && ! $include_content ) {
			return new WP_Error( 'ntci_queue_scope_empty', __( 'Hãy chọn ít nhất một loại ảnh: ảnh đại diện hoặc ảnh trong bài.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$limit   = min( 50, max( 1, absint( $args['limit'] ?? 10 ) ) );
		$profile = $this->profiles->get_site_profile();
		$result  = $this->audits->get_list( array( 'page' => 1, 'per_page' => $limit * 3, 'audit_status' => 'scanned', 'orderby' => 'priority_score', 'order' => 'DESC' ) );
		$items   = array();
		foreach ( $result['items'] as $item ) {
			$post_id   = absint( $item['post_id'] );
			$post_type = sanitize_key( (string) ( $item['post_type'] ?? '' ) );
			if ( 0 === $post_id || ! in_array( $post_type, (array) ( $profile['enabled_post_types'] ?? array() ), true ) ) {
				continue;
			}
			$needs_featured = $include_featured && ! get_post_thumbnail_id( $post_id );
			$needs_content  = $include_content;
			if ( ! $needs_featured && ! $needs_content ) {
				continue;
			}
			$items[] = array(
				'post_id'  => $post_id,
				'title'    => sanitize_text_field( (string) $item['title'] ),
				'featured' => $needs_featured ? 'pending' : 'n/a',
				'content'  => $needs_content ? 'pending' : 'n/a',
				'images'   => 0,
				'error'    => '',
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		if ( array() === $items ) {
			return new WP_Error( 'ntci_queue_no_candidates', __( 'Không có bài phù hợp trong kết quả audit cho phạm vi đã chọn.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$job = array(
			'id'           => uniqid( 'ntciq_', false ),
			'status'       => 'running',
			'pause_reason' => '',
			'items'        => $items,
			'images'       => 0,
			'errors'       => 0,
			'created_at'   => time(),
			'updated_at'   => time(),
		);
		$this->save_job( $job );
		return $this->status();
	}

	/** @return array<string, mixed>|null */
	public function get_job(): ?array {
		$job = get_option( self::OPTION, null );
		return is_array( $job ) ? $job : null;
	}

	/** @return array<string, mixed> */
	public function status(): array {
		$job = $this->get_job();
		$usage = NT_Content_Images_Usage_Tracker::get_today();
		$base  = array(
			'daily_limit' => absint( $this->settings->get()['daily_limit'] ),
			'used_today'  => absint( $usage['total'] ),
		);
		if ( null === $job ) {
			return $base + array( 'job' => null );
		}
		$done = 0;
		foreach ( $job['items'] as $item ) {
			if ( ! in_array( 'pending', array( (string) $item['featured'], (string) $item['content'] ), true ) ) {
				$done++;
			}
		}
		return $base + array(
			'job' => array(
				'id'           => (string) $job['id'],
				'status'       => (string) $job['status'],
				'pause_reason' => (string) $job['pause_reason'],
				'total_posts'  => count( $job['items'] ),
				'done_posts'   => $done,
				'images'       => absint( $job['images'] ),
				'errors'       => absint( $job['errors'] ),
				'items'        => $job['items'],
			),
		);
	}

	/**
	 * Processes at most one provider request, then persists progress.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function step() {
		$job = $this->get_job();
		if ( null === $job || 'running' !== (string) $job['status'] ) {
			return new WP_Error( 'ntci_queue_not_running', __( 'Không có đợt chạy hàng loạt nào đang hoạt động.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( false === get_transient( self::LOCK ) ) {
			set_transient( self::LOCK, '1', 3 * MINUTE_IN_SECONDS );
		} else {
			return new WP_Error( 'ntci_queue_busy', __( 'Một bước xử lý khác đang chạy. Thử lại sau ít giây.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 409 ) );
		}

		try {
			foreach ( $job['items'] as $index => $item ) {
				if ( 'pending' === (string) $item['featured'] ) {
					$this->step_featured( $job, $index );
					$this->save_job( $job );
					return $this->status();
				}
				if ( 'pending' === (string) $item['content'] ) {
					$this->step_content( $job, $index );
					$this->save_job( $job );
					return $this->status();
				}
			}
			$job['status'] = 'completed';
			$this->save_job( $job );
			return $this->status();
		} finally {
			delete_transient( self::LOCK );
		}
	}

	/** @return array<string, mixed>|WP_Error */
	public function set_status( string $status ) {
		$job = $this->get_job();
		if ( null === $job ) {
			return new WP_Error( 'ntci_queue_missing', __( 'Chưa có đợt chạy hàng loạt nào.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'running', 'paused', 'cancelled' ), true ) ) {
			return new WP_Error( 'ntci_queue_status_invalid', __( 'Trạng thái không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( in_array( (string) $job['status'], array( 'completed', 'cancelled' ), true ) ) {
			return new WP_Error( 'ntci_queue_finished', __( 'Đợt chạy này đã kết thúc.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$job['status']       = $status;
		$job['pause_reason'] = 'paused' === $status ? 'manual' : '';
		$this->save_job( $job );
		return $this->status();
	}

	/** @param array<string, mixed> $job */
	private function step_featured( array &$job, int $index ): void {
		$post_id = absint( $job['items'][ $index ]['post_id'] );
		$result  = $this->featured->generate( $post_id );
		if ( is_wp_error( $result ) ) {
			$this->handle_error( $job, $index, 'featured', $result );
			return;
		}
		$job['items'][ $index ]['featured'] = 'done';
		$job['items'][ $index ]['images']++;
		$job['images']++;
	}

	/** @param array<string, mixed> $job */
	private function step_content( array &$job, int $index ): void {
		$post_id = absint( $job['items'][ $index ]['post_id'] );
		$plan    = $this->content->get_plan( $post_id );
		if ( is_wp_error( $plan ) ) {
			$this->handle_error( $job, $index, 'content', $plan );
			return;
		}
		$next_slot = 0;
		foreach ( $plan['slots'] as $slot ) {
			if ( ! empty( $slot['safe'] ) && empty( $slot['record'] ) ) {
				$next_slot = absint( $slot['index'] );
				break;
			}
		}
		if ( 0 === $next_slot ) {
			$job['items'][ $index ]['content'] = 'done';
			return;
		}
		$result = $this->content->generate_set( $post_id, $next_slot );
		if ( is_wp_error( $result ) ) {
			$this->handle_error( $job, $index, 'content', $result );
			return;
		}
		if ( array() !== $result['errors'] ) {
			$error = $result['errors'][0];
			$this->handle_error( $job, $index, 'content', new WP_Error( (string) $error['code'], (string) $error['message'] ) );
			return;
		}
		$generated = count( $result['generated'] );
		$job['items'][ $index ]['images'] += $generated;
		$job['images']                    += $generated;
	}

	/** @param array<string, mixed> $job */
	private function handle_error( array &$job, int $index, string $task, WP_Error $error ): void {
		$code = (string) $error->get_error_code();
		if ( 'ntci_daily_limit_reached' === $code ) {
			$job['status']       = 'paused';
			$job['pause_reason'] = 'daily_limit';
			return;
		}
		if ( in_array( $code, array( 'ntci_generation_featured_exists', 'ntci_content_plan_empty' ), true ) ) {
			$job['items'][ $index ][ $task ] = 'skipped';
			return;
		}
		$job['items'][ $index ][ $task ] = 'error';
		$job['items'][ $index ]['error'] = sanitize_text_field( $error->get_error_message() );
		$job['errors']++;
	}

	/** @param array<string, mixed> $job */
	private function save_job( array $job ): void {
		$job['updated_at'] = time();
		update_option( self::OPTION, $job, false );
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
		delete_transient( self::LOCK );
	}
}
