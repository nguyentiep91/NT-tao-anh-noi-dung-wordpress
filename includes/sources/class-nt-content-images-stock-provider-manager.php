<?php
/**
 * Registry for stock image providers.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Stock_Provider_Manager {
	/** @var array<string, NT_Content_Images_Stock_Provider_Interface> */
	private array $providers = array();

	/** @param NT_Content_Images_Stock_Provider_Interface[] $providers Providers. */
	public function __construct( array $providers ) {
		foreach ( $providers as $provider ) {
			if ( $provider instanceof NT_Content_Images_Stock_Provider_Interface ) {
				$this->providers[ $provider->get_id() ] = $provider;
			}
		}
		$this->providers = apply_filters( 'nt_content_images_stock_providers', $this->providers );
	}

	/** @return NT_Content_Images_Stock_Provider_Interface|WP_Error */
	public function get( string $provider_id ) {
		$provider_id = sanitize_key( $provider_id );
		if ( ! isset( $this->providers[ $provider_id ] ) || ! $this->providers[ $provider_id ] instanceof NT_Content_Images_Stock_Provider_Interface ) {
			return new WP_Error( 'ntci_stock_provider_not_found', __( 'Kho ảnh không tồn tại.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $this->providers[ $provider_id ];
	}

	/** @return array<string, array<string, mixed>> */
	public function get_public(): array {
		$items = array();
		foreach ( $this->providers as $id => $provider ) {
			$items[ $id ] = array(
				'id'         => $id,
				'label'      => $provider->get_label(),
				'configured' => $provider->is_configured(),
			);
		}
		return $items;
	}
}
