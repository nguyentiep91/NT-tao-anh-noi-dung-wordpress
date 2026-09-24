<?php
/**
 * Runs read-only content audits in small resumable batches.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Batch_Runner {
	private NT_Content_Images_Audit_Query $query;
	private NT_Content_Images_Content_Scanner $scanner;
	private NT_Content_Images_Audit_Repository $repository;
	private NT_Content_Images_Audit_Job_Store $job_store;

	/**
	 * Sets batch dependencies.
	 */
	public function __construct(
		NT_Content_Images_Audit_Query $query,
		NT_Content_Images_Content_Scanner $scanner,
		NT_Content_Images_Audit_Repository $repository,
		NT_Content_Images_Audit_Job_Store $job_store
	) {
		$this->query      = $query;
		$this->scanner    = $scanner;
		$this->repository = $repository;
		$this->job_store  = $job_store;
	}

	/**
	 * Starts a new audit job.
	 *
	 * @param array<string, mixed> $config Raw configuration.
	 * @return array<string, mixed>|WP_Error
	 */
	public function start( array $config ) {
		$config = $this->normalize_config( $config );
		$total  = $this->query->count_posts( $config );

		return $this->job_store->create( $config, $total );
	}

	/**
	 * Processes one batch and returns the persisted job state.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function process() {
		if ( ! $this->job_store->acquire_lock() ) {
			return new WP_Error(
				'nt_content_images_audit_busy',
				__( 'Một batch audit khác đang được xử lý. Vui lòng thử lại sau ít giây.', 'nt-tao-anh-noi-dung-wordpress' ),
				array( 'status' => 409 )
			);
		}

		try {
			$job = $this->job_store->get();

			if ( null === $job ) {
				return new WP_Error( 'nt_content_images_missing_job', __( 'Không tìm thấy tiến trình audit.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 404 ) );
			}

			if ( 'running' !== ( $job['status'] ?? '' ) ) {
				return $this->with_progress( $job );
			}

			$config = is_array( $job['config'] ?? null ) ? $job['config'] : array();
			$batch  = $this->query->get_post_ids(
				array_merge(
					$config,
					array(
						'offset'   => absint( $job['processed_posts'] ?? 0 ),
						'per_page' => absint( $config['batch_size'] ?? 20 ),
					)
				)
			);

			if ( empty( $batch['post_ids'] ) ) {
				$job['status']       = 'completed';
				$job['completed_at'] = current_time( 'mysql', true );

				return $this->with_progress( $this->job_store->save( $job ) );
			}

			foreach ( $batch['post_ids'] as $post_id ) {
				++$job['processed_posts'];
				$job['last_post_id'] = $post_id;
				$existing            = $this->repository->get_by_post_id( $post_id );
				$mode                = sanitize_key( (string) ( $config['scan_mode'] ?? 'changed' ) );

				if ( 'new' === $mode && null !== $existing ) {
					++$job['skipped_posts'];
					continue;
				}

				if ( 'changed' === $mode && null !== $existing ) {
					$current_hash = $this->scanner->get_content_hash( $post_id );

					if ( ! is_wp_error( $current_hash ) && hash_equals( (string) ( $existing['content_hash'] ?? '' ), $current_hash ) ) {
						++$job['skipped_posts'];
						continue;
					}
				}

				try {
					$result = $this->scanner->scan_and_store( $post_id );

					if ( is_wp_error( $result ) ) {
						$this->record_error( $job, $post_id, $result );
						continue;
					}

					++$job['scanned_posts'];
				} catch ( Throwable $throwable ) {
					$error = new WP_Error(
						'nt_content_images_scan_exception',
						__( 'Đã xảy ra lỗi không mong muốn khi phân tích bài viết.', 'nt-tao-anh-noi-dung-wordpress' )
					);
					$this->record_error( $job, $post_id, $error );
				}
			}

			if ( absint( $job['processed_posts'] ) >= absint( $job['total_posts'] ) ) {
				$job['status']       = 'completed';
				$job['completed_at'] = current_time( 'mysql', true );
			}

			return $this->with_progress( $this->job_store->save( $job ) );
		} finally {
			$this->job_store->release_lock();
		}
	}

	/**
	 * Pauses the current job.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function pause() {
		$result = $this->job_store->set_status( 'paused' );

		return is_wp_error( $result ) ? $result : $this->with_progress( $result );
	}

	/**
	 * Resumes the current paused job.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function resume() {
		$job = $this->job_store->get();

		if ( null === $job || 'paused' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error( 'nt_content_images_job_not_paused', __( 'Tiến trình audit không ở trạng thái tạm dừng.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$result = $this->job_store->set_status( 'running' );

		return is_wp_error( $result ) ? $result : $this->with_progress( $result );
	}

	/**
	 * Cancels the current job.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function cancel() {
		$result = $this->job_store->set_status( 'cancelled' );

		return is_wp_error( $result ) ? $result : $this->with_progress( $result );
	}

	/**
	 * Returns the current job state.
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array {
		$job = $this->job_store->get();

		if ( null === $job ) {
			return array(
				'status'          => 'idle',
				'total_posts'     => 0,
				'processed_posts' => 0,
				'scanned_posts'   => 0,
				'skipped_posts'   => 0,
				'failed_posts'    => 0,
				'progress'        => 0,
			);
		}

		return $this->with_progress( $job );
	}

	/**
	 * Normalizes job configuration with safe hard limits.
	 *
	 * @param array<string, mixed> $config Raw config.
	 * @return array<string, mixed>
	 */
	private function normalize_config( array $config ): array {
		$allowed_modes = array( 'new', 'changed', 'all' );
		$mode          = sanitize_key( (string) ( $config['scan_mode'] ?? 'changed' ) );

		return array(
			'post_types'    => array_map( 'sanitize_key', (array) ( $config['post_types'] ?? array( 'post', 'page' ) ) ),
			'post_statuses' => array_map( 'sanitize_key', (array) ( $config['post_statuses'] ?? array( 'publish' ) ) ),
			'batch_size'    => max( 5, min( 50, absint( $config['batch_size'] ?? 20 ) ) ),
			'scan_mode'     => in_array( $mode, $allowed_modes, true ) ? $mode : 'changed',
		);
	}

	/**
	 * Records one sanitized error and increments counters.
	 *
	 * @param array<string, mixed> $job Job state.
	 */
	private function record_error( array &$job, int $post_id, WP_Error $error ): void {
		++$job['failed_posts'];
		$job['last_error'] = sanitize_text_field( $error->get_error_message() );
		$this->repository->mark_error( $post_id, (string) $error->get_error_code(), $error->get_error_message() );
	}

	/**
	 * Adds a stable progress percentage to the public job response.
	 *
	 * @param array<string, mixed> $job Job state.
	 * @return array<string, mixed>
	 */
	private function with_progress( array $job ): array {
		$total           = max( 0, absint( $job['total_posts'] ?? 0 ) );
		$processed       = max( 0, absint( $job['processed_posts'] ?? 0 ) );
		$job['progress'] = 0 === $total ? ( 'completed' === ( $job['status'] ?? '' ) ? 100 : 0 ) : round( min( 100, ( $processed / $total ) * 100 ), 2 );

		return $job;
	}
}
