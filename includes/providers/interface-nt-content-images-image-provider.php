<?php
/**
 * Contract for AI image providers.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NT_Content_Images_Image_Provider_Interface {
	public function get_id(): string;

	public function is_configured(): bool;

	/**
	 * @param array<string, mixed> $request Provider-neutral request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate( array $request );
}
