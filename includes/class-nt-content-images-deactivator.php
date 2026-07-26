<?php
/**
 * Plugin deactivation handler.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Deactivator {
	/**
	 * Clears scheduled hooks without deleting persistent data.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'nt_content_images_process_queue' );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'nt_content_images_process_queue' );
		}
	}
}
