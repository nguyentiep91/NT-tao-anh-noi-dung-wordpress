<?php
/**
 * Contract for searchable stock-image providers.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NT_Content_Images_Stock_Provider_Interface {
	public function get_id(): string;

	public function get_label(): string;

	public function is_configured(): bool;

	/**
	 * Search and return normalized candidates.
	 *
	 * @param array<string, mixed> $request Search request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function search( array $request );

	/**
	 * Return a normalized current asset record.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_asset( string $asset_id );
}
