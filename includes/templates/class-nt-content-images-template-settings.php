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
	private NT_Content_Images_Template_Pack_Registry $pack_registry;

	public function __construct( NT_Content_Images_Template_Registry $registry, ?NT_Content_Images_Template_Pack_Registry $pack_registry = null ) {
		$this->registry = $registry;
		$this->pack_registry = $pack_registry ?? new NT_Content_Images_Template_Pack_Registry( $registry );
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
		$template_mode = sanitize_key( (string) ( $raw['template_mode'] ?? 'fixed' ) );
		if ( ! in_array( $template_mode, array( 'fixed', 'mix', 'smart' ), true ) ) {
			$template_mode = 'fixed';
		}
		$template_pack = sanitize_key( (string) ( $raw['template_pack'] ?? 'auto' ) );
		if ( 'auto' !== $template_pack && ! $this->pack_registry->exists( $template_pack ) ) {
			$template_pack = 'auto';
		}
		$has_payload = array() !== $raw;
		return array(
			'default_template' => $default_template,
			'content_template' => $content_template,
			'template_mode' => $template_mode,
			'template_pack' => $template_pack,
			'mix_templates' => $this->sanitize_pool( $raw['mix_templates'] ?? null ),
			'show_category' => $has_payload ? ! empty( $raw['show_category'] ) : true,
			'show_site_name' => $has_payload ? ! empty( $raw['show_site_name'] ) : true,
			'show_logo' => $has_payload ? ! empty( $raw['show_logo'] ) : true,
			'auto_overlay' => $has_payload ? ! empty( $raw['auto_overlay'] ) : true,
		);
	}

	/** @param array<string, mixed> $site_profile @param array<string, mixed> $brand_profile */
	public function pick_template( int $post_id, int $slot, bool $is_content, array $site_profile = array(), array $brand_profile = array() ): string {
		$settings = $this->get();
		if ( 'smart' === (string) $settings['template_mode'] ) {
			$pack_id = $this->pack_registry->resolve( $site_profile, $brand_profile, (string) $settings['template_pack'] );
			return self::pick_from_pool( $this->pack_registry->get_pool( $pack_id, $is_content ), $post_id, $slot );
		}
		if ( 'mix' !== (string) $settings['template_mode'] || array() === $settings['mix_templates'] ) {
			return $is_content ? (string) $settings['content_template'] : (string) $settings['default_template'];
		}
		return self::pick_from_pool( $settings['mix_templates'], $post_id, $slot );
	}

	/** @param array<int, string> $pool */
	public static function pick_from_pool( array $pool, int $post_id, int $slot ): string {
		$pool = array_values( array_filter( array_map( 'strval', $pool ) ) );
		if ( array() === $pool ) {
			return NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE;
		}
		return $pool[ ( absint( $post_id ) + absint( $slot ) ) % count( $pool ) ];
	}

	/** @param array<string, mixed> $site_profile @param array<string, mixed> $brand_profile */
	public function resolve_pack( array $site_profile, array $brand_profile = array() ): string {
		return $this->pack_registry->resolve( $site_profile, $brand_profile, (string) $this->get()['template_pack'] );
	}

	/** @return array<string, mixed>|null */
	public function get_pack( string $id ): ?array {
		return $this->pack_registry->get( $id );
	}

	/** @return array<int, array<string, mixed>> */
	public function get_packs_public(): array {
		return $this->pack_registry->get_public();
	}

	/** @param mixed $raw @return array<int, string> */
	private function sanitize_pool( $raw ): array {
		$all = $this->registry->get_all();
		if ( is_array( $raw ) ) {
			$pool = array();
			foreach ( $raw as $id ) {
				$id = sanitize_key( (string) $id );
				if ( isset( $all[ $id ] ) && ! in_array( $id, $pool, true ) ) {
					$pool[] = $id;
				}
			}
			if ( array() !== $pool ) {
				return $pool;
			}
		}
		$pool = array();
		foreach ( $all as $template ) {
			if ( ! empty( $template['shows_title'] ) ) {
				$pool[] = (string) $template['id'];
			}
		}
		return $pool;
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
		$template_mode = sanitize_key( (string) ( $raw['template_mode'] ?? 'fixed' ) );
		if ( ! in_array( $template_mode, array( 'fixed', 'mix', 'smart' ), true ) ) {
			$template_mode = 'fixed';
		}
		$template_pack = sanitize_key( (string) ( $raw['template_pack'] ?? 'auto' ) );
		if ( 'auto' !== $template_pack && ! $this->pack_registry->exists( $template_pack ) ) {
			return new WP_Error( 'ntci_template_pack_invalid', __( 'Template Pack không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		update_option(
			self::OPTION,
			array(
				'default_template' => $default_template,
				'content_template' => $content_template,
				'template_mode' => $template_mode,
				'template_pack' => $template_pack,
				'mix_templates' => $this->sanitize_pool( $raw['mix_templates'] ?? null ),
				'show_category' => ! empty( $raw['show_category'] ),
				'show_site_name' => ! empty( $raw['show_site_name'] ),
				'show_logo' => ! empty( $raw['show_logo'] ),
				'auto_overlay' => ! empty( $raw['auto_overlay'] ),
			),
			false
		);
		return $this->get();
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
	}
}
