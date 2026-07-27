<?php
/**
 * Stores administrator preferences for the text-overlay template renderer.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Template_Settings {
	private const OPTION = 'nt_content_images_template_settings';

	private NT_Content_Images_Template_Registry $registry;

	public function __construct( NT_Content_Images_Template_Registry $registry ) {
		$this->registry = $registry;
	}

	/** @return array<string, mixed> */
	public function get(): array {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$default_template = sanitize_key( (string) ( $raw['default_template'] ?? NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE ) );
		if ( ! $this->registry->exists( $default_template ) ) {
			$default_template = NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE;
		}
		$content_template = sanitize_key( (string) ( $raw['content_template'] ?? NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE ) );
		if ( ! $this->registry->exists( $content_template ) ) {
			$content_template = NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE;
		}
		$has_payload = array() !== $raw;
		return array(
			'default_template' => $default_template,
			'content_template' => $content_template,
			'show_category'    => $has_payload ? ! empty( $raw['show_category'] ) : true,
			'show_site_name'   => $has_payload ? ! empty( $raw['show_site_name'] ) : true,
			'show_logo'        => $has_payload ? ! empty( $raw['show_logo'] ) : true,
			'auto_overlay'     => $has_payload ? ! empty( $raw['auto_overlay'] ) : true,
		);
	}

	/** @return array<string, mixed> */
	public function get_public(): array {
		return $this->get();
	}

	/** @return array<string, mixed>|WP_Error */
	public function save( array $raw ) {
		$default_template = sanitize_key( (string) ( $raw['default_template'] ?? '' ) );
		if ( ! $this->registry->exists( $default_template ) ) {
			return new WP_Error( 'ntci_template_invalid', __( 'Mẫu chữ mặc định không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$content_template = sanitize_key( (string) ( $raw['content_template'] ?? $default_template ) );
		if ( ! $this->registry->exists( $content_template ) ) {
			return new WP_Error( 'ntci_template_content_invalid', __( 'Mẫu chữ cho ảnh trong bài không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		update_option(
			self::OPTION,
			array(
				'default_template' => $default_template,
				'content_template' => $content_template,
				'show_category'    => ! empty( $raw['show_category'] ),
				'show_site_name'   => ! empty( $raw['show_site_name'] ),
				'show_logo'        => ! empty( $raw['show_logo'] ),
				'auto_overlay'     => ! empty( $raw['auto_overlay'] ),
			),
			false
		);
		return $this->get();
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
	}
}
