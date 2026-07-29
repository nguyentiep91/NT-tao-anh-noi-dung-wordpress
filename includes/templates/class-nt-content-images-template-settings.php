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
			'template_mode'    => 'mix' === sanitize_key( (string) ( $raw['template_mode'] ?? 'fixed' ) ) ? 'mix' : 'fixed',
			'mix_templates'    => $this->sanitize_pool( $raw['mix_templates'] ?? null ),
			'show_category'    => $has_payload ? ! empty( $raw['show_category'] ) : true,
			'show_site_name'   => $has_payload ? ! empty( $raw['show_site_name'] ) : true,
			'show_logo'        => $has_payload ? ! empty( $raw['show_logo'] ) : true,
			'auto_overlay'     => $has_payload ? ! empty( $raw['auto_overlay'] ) : true,
		);
	}

	/**
	 * Picks the overlay template for one image slot.
	 *
	 * Chế độ mix chọn luân phiên xác định (không random): các slot trong cùng
	 * bài dùng mẫu khác nhau, các bài kế nhau dịch vòng theo post_id — tạo lại
	 * ảnh cùng slot vẫn ra đúng mẫu cũ.
	 *
	 * @param int $post_id Post owning the image.
	 * @param int $slot    0 = featured, 1..N = content image index.
	 */
	public function pick_template( int $post_id, int $slot, bool $is_content ): string {
		$settings = $this->get();
		if ( 'mix' !== (string) $settings['template_mode'] || array() === $settings['mix_templates'] ) {
			return $is_content ? (string) $settings['content_template'] : (string) $settings['default_template'];
		}
		return self::pick_from_pool( $settings['mix_templates'], $post_id, $slot );
	}

	/**
	 * Deterministic rotation: (post_id + slot) mod pool. Pure — unit tested.
	 *
	 * @param array<int, string> $pool Template ids joining the mix.
	 */
	public static function pick_from_pool( array $pool, int $post_id, int $slot ): string {
		$pool = array_values( array_filter( array_map( 'strval', $pool ) ) );
		if ( array() === $pool ) {
			return NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE;
		}
		$index = ( absint( $post_id ) + absint( $slot ) ) % count( $pool );
		return $pool[ $index ];
	}

	/**
	 * @param mixed $raw Raw pool value from option/form.
	 * @return array<int, string> Valid template ids, defaults to every title-showing template.
	 */
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
		// Mặc định: mọi mẫu có tiêu đề (minimal_badge không có chữ nên phải tự tick thêm).
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
		update_option(
			self::OPTION,
			array(
				'default_template' => $default_template,
				'content_template' => $content_template,
				'template_mode'    => 'mix' === sanitize_key( (string) ( $raw['template_mode'] ?? 'fixed' ) ) ? 'mix' : 'fixed',
				'mix_templates'    => $this->sanitize_pool( $raw['mix_templates'] ?? null ),
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
