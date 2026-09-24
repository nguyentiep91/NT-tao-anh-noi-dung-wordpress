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

	public function get_label(): string;

	public function is_configured(): bool;

	/**
	 * Returns models that can be selected in the administration screen.
	 *
	 * @return array<int, array{id: string, name: string}>|WP_Error
	 */
	public function list_models();

	/**
	 * @param array<string, mixed> $request Provider-neutral request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate( array $request );
}
