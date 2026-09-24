<?php
/**
 * Persists one audit job and a short-lived process lock in WordPress options.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Job_Store {
	private const JOB_OPTION  = 'nt_content_images_audit_job';
	private const LOCK_OPTION = 'nt_content_images_audit_lock';
	private const LOCK_TTL    = 120;

	/**
	 * Creates a new audit job unless another job is active.
	 *
	 * @param array<string, mixed> $config Normalized job configuration.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $config, int $total_posts ) {
		$current = $this->get();

		if ( is_array( $current ) && in_array( $current['status'] ?? '', array( 'running', 'paused' ), true ) ) {
			return new WP_Error(
				'nt_content_images_audit_job_active',
				__( 'Đang có một tiến trình audit chưa hoàn tất.', 'nt-tao-anh-noi-dung-wordpress' )
			);
		}

		$now = current_time( 'mysql', true );
		$job = array(
			'job_id'          => 'audit_' . wp_generate_uuid4(),
			'status'          => 'running',
			'config'          => $config,
			'total_posts'     => max( 0, $total_posts ),
			'processed_posts' => 0,
			'scanned_posts'   => 0,
			'skipped_posts'   => 0,
			'failed_posts'    => 0,
			'last_post_id'    => 0,
			'last_error'      => '',
			'started_at'      => $now,
			'updated_at'      => $now,
			'completed_at'    => '',
		);

		update_option( self::JOB_OPTION, $job, false );

		return $job;
	}

	/**
	 * Returns the current job, or null when none exists.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get(): ?array {
		$job = get_option( self::JOB_OPTION, null );

		return is_array( $job ) ? $job : null;
	}

	/**
	 * Persists a complete job snapshot.
	 *
	 * @param array<string, mixed> $job Job state.
	 */
	public function save( array $job ): array {
		$job['updated_at'] = current_time( 'mysql', true );
		update_option( self::JOB_OPTION, $job, false );

		return $job;
	}

	/**
	 * Changes the current job status.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function set_status( string $status ) {
		$allowed = array( 'running', 'paused', 'completed', 'cancelled', 'failed' );
		$status  = sanitize_key( $status );
		$job     = $this->get();

		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'nt_content_images_invalid_job_status', __( 'Trạng thái audit không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		if ( null === $job ) {
			return new WP_Error( 'nt_content_images_missing_job', __( 'Không tìm thấy tiến trình audit.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$job['status'] = $status;

		if ( in_array( $status, array( 'completed', 'cancelled', 'failed' ), true ) ) {
			$job['completed_at'] = current_time( 'mysql', true );
		}

		return $this->save( $job );
	}

	/**
	 * Removes the current job state and lock.
	 */
	public function clear(): void {
		delete_option( self::JOB_OPTION );
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Acquires an atomic short-lived lock for one process request.
	 */
	public function acquire_lock(): bool {
		$expires = time() + self::LOCK_TTL;

		if ( add_option( self::LOCK_OPTION, $expires, '', false ) ) {
			return true;
		}

		$current_expiry = absint( get_option( self::LOCK_OPTION, 0 ) );

		if ( $current_expiry > 0 && $current_expiry < time() ) {
			delete_option( self::LOCK_OPTION );

			return add_option( self::LOCK_OPTION, $expires, '', false );
		}

		return false;
	}

	/**
	 * Releases the current process lock.
	 */
	public function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}
}
