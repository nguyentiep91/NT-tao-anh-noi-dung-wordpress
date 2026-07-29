<?php
/**
 * Resolves the active image provider without coupling generation to one API.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Image_Provider_Manager {
	private NT_Content_Images_Generation_Settings $settings;

	/** @var array<string, NT_Content_Images_Image_Provider_Interface> */
	private array $providers = array();

	/**
	 * @param NT_Content_Images_Image_Provider_Interface[] $providers Providers.
	 */
	public function __construct( NT_Content_Images_Generation_Settings $settings, array $providers ) {
		$this->settings = $settings;
		foreach ( $providers as $provider ) {
			if ( $provider instanceof NT_Content_Images_Image_Provider_Interface ) {
				$this->providers[ $provider->get_id() ] = $provider;
			}
		}

		/** Allows third-party providers to be registered without changing core. */
		$this->providers = apply_filters( 'nt_content_images_image_providers', $this->providers, $settings );
	}

	/** @return NT_Content_Images_Image_Provider_Interface|WP_Error */
	public function get_active() {
		$id = sanitize_key( (string) $this->settings->get()['provider'] );
		if ( ! isset( $this->providers[ $id ] ) || ! $this->providers[ $id ] instanceof NT_Content_Images_Image_Provider_Interface ) {
			return new WP_Error( 'ntci_provider_not_found', __( 'Nhà cung cấp tạo ảnh không tồn tại.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $this->providers[ $id ];
	}

	/** @return array<string, array<string, mixed>> */
	public function get_public(): array {
		$items = array();
		foreach ( $this->providers as $id => $provider ) {
			if ( ! $provider instanceof NT_Content_Images_Image_Provider_Interface ) {
				continue;
			}
			$items[ $id ] = array(
				'id'         => $id,
				'label'      => $provider->get_label(),
				'configured' => $provider->is_configured(),
			);
		}
		return $items;
	}

	/** @return array<int, array{id: string, name: string}>|WP_Error */
	public function list_models( string $provider_id ) {
		$provider_id = sanitize_key( $provider_id );
		if ( ! isset( $this->providers[ $provider_id ] ) ) {
			return new WP_Error( 'ntci_provider_not_found', __( 'Nhà cung cấp tạo ảnh không tồn tại.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $this->providers[ $provider_id ]->list_models();
	}

	/** @return array<int, array{id: string, name: string, price_label: string}>|WP_Error */
	public function list_text_models( string $provider_id ) {
		$provider_id = sanitize_key( $provider_id );
		$provider    = $this->providers[ $provider_id ] ?? null;
		if ( null === $provider || ! method_exists( $provider, 'list_text_models' ) ) {
			return new WP_Error( 'ntci_provider_no_text_models', __( 'Nhà cung cấp này không hỗ trợ danh sách model văn bản.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $provider->list_text_models();
	}
}
