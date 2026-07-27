<?php
/**
 * Keeps the Media Library free of unused plugin-generated images.
 *
 * Policy: only images actually used by the site (featured thumbnail or
 * referenced inside live post content) are kept. Intermediate images —
 * the plain AI base after a text overlay replaced it, rejected candidates,
 * superseded slot candidates after insertion — are deleted together with
 * their generation records. The service never touches attachments it did
 * not create and never deletes an attachment that is still referenced
 * anywhere on the site or by another generation record.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Media_Cleanup {
	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Safe_Logger $logger;

	public function __construct(
		NT_Content_Images_Generation_Repository $repository,
		NT_Content_Images_Generation_Settings $settings,
		?NT_Content_Images_Safe_Logger $logger = null
	) {
		$this->repository = $repository;
		$this->settings   = $settings;
		$this->logger     = $logger ?? new NT_Content_Images_Safe_Logger();
	}

	/** Auto cleanup rides on the existing workflow actions; priority 20 runs after default listeners. */
	public function register(): void {
		add_action( 'nt_content_images_after_overlay', array( $this, 'handle_after_overlay' ), 20, 2 );
		add_action( 'nt_content_images_after_insert', array( $this, 'handle_after_insert' ), 20, 2 );
		add_action( 'nt_content_images_after_featured_approval', array( $this, 'handle_after_featured_approval' ), 20, 2 );
		add_action( 'nt_content_images_after_reject', array( $this, 'handle_after_reject' ), 20, 1 );
	}

	private function enabled(): bool {
		return ! empty( $this->settings->get()['auto_cleanup'] );
	}

	/** The overlay version replaces the plain AI base: the base has no use of its own. */
	public function handle_after_overlay( int $overlay_id, int $source_id ): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$source = $this->repository->get( absint( $source_id ) );
		if ( null === $source || 'template' === (string) $source['provider'] ) {
			return;
		}
		if ( ! in_array( (string) $source['status'], array( 'generated', 'rejected' ), true ) ) {
			return;
		}
		$result = $this->delete_record_if_unused( $source );
		if ( $result['deleted_record'] ) {
			$this->logger->log( 'info', 'cleanup_overlay_source_removed', array( 'source_id' => absint( $source_id ), 'overlay_id' => absint( $overlay_id ) ) );
		}
	}

	/**
	 * After an insert, other candidates of the just-filled slots are superseded.
	 *
	 * @param array<int, int> $inserted_ids Generation record ids that were inserted.
	 */
	public function handle_after_insert( int $post_id, array $inserted_ids ): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$indexes = array();
		foreach ( $inserted_ids as $record_id ) {
			$record = $this->repository->get( absint( $record_id ) );
			if ( null !== $record && 'content' === (string) ( $record['settings']['image_type'] ?? '' ) ) {
				$indexes[ absint( $record['settings']['image_index'] ?? 0 ) ] = true;
			}
		}
		if ( array() === $indexes ) {
			return;
		}
		$deleted = 0;
		foreach ( $this->repository->get_by_post( absint( $post_id ) ) as $record ) {
			if ( 'content' !== (string) ( $record['settings']['image_type'] ?? '' ) ) {
				continue;
			}
			if ( ! isset( $indexes[ absint( $record['settings']['image_index'] ?? 0 ) ] ) ) {
				continue;
			}
			if ( ! in_array( (string) $record['status'], array( 'generated', 'approved', 'rejected' ), true ) ) {
				continue;
			}
			$result = $this->delete_record_if_unused( $record );
			if ( $result['deleted_record'] ) {
				$deleted++;
			}
		}
		if ( $deleted > 0 ) {
			$this->logger->log( 'info', 'cleanup_after_insert', array( 'post_id' => absint( $post_id ), 'deleted' => $deleted ) );
		}
	}

	/** Once a featured image is approved, the losing candidates of that post are junk. */
	public function handle_after_featured_approval( int $generation_id, int $post_id ): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$deleted = 0;
		foreach ( $this->repository->get_by_post( absint( $post_id ) ) as $record ) {
			if ( 'content' === (string) ( $record['settings']['image_type'] ?? '' ) ) {
				continue;
			}
			if ( absint( $record['id'] ) === absint( $generation_id ) ) {
				continue;
			}
			if ( ! in_array( (string) $record['status'], array( 'generated', 'rejected' ), true ) ) {
				continue;
			}
			$result = $this->delete_record_if_unused( $record );
			if ( $result['deleted_record'] ) {
				$deleted++;
			}
		}
		if ( $deleted > 0 ) {
			$this->logger->log( 'info', 'cleanup_after_featured_approval', array( 'post_id' => absint( $post_id ), 'deleted' => $deleted ) );
		}
	}

	/** Rejecting means "never use this image" — the file goes with the decision. */
	public function handle_after_reject( int $generation_id ): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$record = $this->repository->get( absint( $generation_id ) );
		if ( null === $record || 'rejected' !== (string) $record['status'] ) {
			return;
		}
		$result = $this->delete_record_if_unused( $record );
		if ( $result['deleted_record'] ) {
			$this->logger->log( 'info', 'cleanup_rejected_removed', array( 'generation_id' => absint( $generation_id ) ) );
		}
	}

	/**
	 * Full sweep: deletes every plugin image that is not used on the site,
	 * regardless of workflow status. Used images and foreign attachments stay.
	 *
	 * @return array{scanned: int, deleted_records: int, deleted_attachments: int, freed_bytes: int, kept: int}
	 */
	public function sweep(): array {
		$stats = array(
			'scanned'             => 0,
			'deleted_records'     => 0,
			'deleted_attachments' => 0,
			'freed_bytes'         => 0,
			'kept'                => 0,
		);
		foreach ( $this->repository->get_all_light() as $record ) {
			$stats['scanned']++;
			$result = $this->delete_record_if_unused( $record );
			if ( $result['deleted_record'] ) {
				$stats['deleted_records']++;
			} else {
				$stats['kept']++;
			}
			if ( $result['deleted_attachment'] ) {
				$stats['deleted_attachments']++;
				$stats['freed_bytes'] += $result['freed'];
			}
		}
		$this->logger->log( 'info', 'cleanup_sweep_completed', $stats );
		return $stats;
	}

	/**
	 * Deletes one generation record and its attachment when nothing uses it.
	 *
	 * @param array<string, mixed> $record Generation record (id, attachment_id at minimum).
	 * @return array{deleted_record: bool, deleted_attachment: bool, freed: int}
	 */
	public function delete_record_if_unused( array $record ): array {
		$result    = array(
			'deleted_record'     => false,
			'deleted_attachment' => false,
			'freed'              => 0,
		);
		$record_id = absint( $record['id'] ?? 0 );
		if ( $record_id < 1 ) {
			return $result;
		}
		$attachment_id = absint( $record['attachment_id'] ?? 0 );
		if ( $attachment_id > 0 && get_post( $attachment_id ) instanceof WP_Post ) {
			if ( $this->is_attachment_used( $attachment_id ) ) {
				return $result;
			}
			$plugin_owned = '' !== (string) get_post_meta( $attachment_id, '_nt_content_images_source_post_id', true );
			if ( $plugin_owned && ! $this->is_attachment_shared( $attachment_id, $record_id ) ) {
				$file = (string) get_attached_file( $attachment_id );
				$size = '' !== $file && file_exists( $file ) ? (int) filesize( $file ) : 0;
				if ( ! wp_delete_attachment( $attachment_id, true ) ) {
					return $result;
				}
				$result['deleted_attachment'] = true;
				$result['freed']              = $size;
			}
		}
		$result['deleted_record'] = $this->repository->delete( $record_id );
		return $result;
	}

	/** True when the attachment is a featured thumbnail or referenced in any live content. */
	public function is_attachment_used( int $attachment_id ): bool {
		global $wpdb;
		if ( $attachment_id < 1 ) {
			return false;
		}
		$thumb_sql = $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %s AND p.post_type <> 'revision' LIMIT 1",
			(string) $attachment_id
		);
		if ( null !== $wpdb->get_var( $thumb_sql ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			return true;
		}
		$filenames = array( (string) get_attached_file( $attachment_id ) );
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		foreach ( is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : array() as $size ) {
			$filenames[] = (string) ( $size['file'] ?? '' );
		}
		$where  = array();
		$params = array();
		foreach ( self::get_reference_needles( $attachment_id, $filenames ) as $needle ) {
			$where[]  = 'post_content LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment') AND post_status <> 'auto-draft' AND (" . implode( ' OR ', $where ) . ') LIMIT 1';
		$sql = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return null !== $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Search needles that identify one attachment inside post_content.
	 *
	 * Filenames are matched exactly (file gốc + từng bản resize từ attachment
	 * metadata) để không nhầm với file anh em cùng tiền tố (ten-file.webp vs
	 * ten-file-1.webp do wp_unique_filename sinh ra).
	 *
	 * @param array<int, string> $filenames Original file plus resized variant names.
	 * @return array<int, string>
	 */
	public static function get_reference_needles( int $attachment_id, array $filenames = array() ): array {
		$needles = array(
			'wp-image-' . $attachment_id . '"',
			'wp-image-' . $attachment_id . ' ',
			'"id":' . $attachment_id . ',',
			'"id":' . $attachment_id . '}',
		);
		foreach ( $filenames as $file ) {
			$basename = basename( str_replace( '\\', '/', (string) $file ) );
			if ( '' !== $basename && '.' !== $basename && ! in_array( $basename, $needles, true ) ) {
				$needles[] = $basename;
			}
		}
		return $needles;
	}

	/** True when another generation record still references the same attachment (dedup reuse). */
	private function is_attachment_shared( int $attachment_id, int $record_id ): bool {
		global $wpdb;
		$table = NT_Content_Images_Generation_Migrator::get_table_name();
		$sql   = $wpdb->prepare( "SELECT id FROM {$table} WHERE attachment_id = %d AND id <> %d LIMIT 1", absint( $attachment_id ), absint( $record_id ) );
		return null !== $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
