<?php
/**
 * Prevents duplicate paid image requests for the same post.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Lock {
	private const PREFIX = 'nt_content_images_generation_lock_';
	private const TTL = 600;

	public function acquire( int $post_id ): bool {
		$post_id = absint( $post_id );
		if ( 0 === $post_id ) {
			return false;
		}

		$key = self::PREFIX . $post_id;
		$now = time();
		if ( add_option( $key, $now, '', false ) ) {
			return true;
		}

		$created = absint( get_option( $key, 0 ) );
		if ( $created > 0 && ( $created + self::TTL ) < $now ) {
			delete_option( $key );
			return add_option( $key, $now, '', false );
		}

		return false;
	}

	public function release( int $post_id ): void {
		$post_id = absint( $post_id );
		if ( $post_id > 0 ) {
			delete_option( self::PREFIX . $post_id );
		}
	}

	public static function delete_all(): void {
		global $wpdb;
		$like = $wpdb->esc_like( self::PREFIX ) . '%';
		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
