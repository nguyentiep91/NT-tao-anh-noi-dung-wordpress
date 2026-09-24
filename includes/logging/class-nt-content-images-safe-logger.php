<?php
/**
 * Structured logger that never writes secrets.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Safe_Logger {
	/**
	 * @param array<string, mixed> $context Context without image bytes or post content.
	 */
	public function log( string $level, string $event, array $context = array() ): void {
		$level = in_array( $level, array( 'debug', 'info', 'warning', 'error' ), true ) ? $level : 'info';
		$event = sanitize_key( $event );
		$clean = NT_Content_Images_Secret_Redactor::redact( $context );
		$clean = is_array( $clean ) ? $clean : array();

		do_action( 'nt_content_images_log', $level, $event, $clean );

		$enabled = (bool) apply_filters( 'nt_content_images_debug_logging', false, $level, $event );
		if ( ! $enabled || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$payload = array(
			'timestamp' => gmdate( 'c' ),
			'level'     => $level,
			'event'     => $event,
			'context'   => $clean,
		);
		$line = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( is_string( $line ) ) {
			error_log( '[NT Content Images] ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
