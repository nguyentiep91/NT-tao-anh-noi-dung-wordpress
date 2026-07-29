<?php
/**
 * Registers built-in overlay templates that place text and branding on images.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Template_Registry {
	public const DEFAULT_TEMPLATE = 'bottom_gradient';

	/** Layouts the renderer knows how to draw. */
	public const SUPPORTED_LAYOUTS = array( 'bottom_gradient', 'left_panel', 'top_band', 'center_box', 'minimal_badge', 'top_gradient', 'right_panel', 'bottom_bar', 'corner_card' );

	/** @return array<string, array<string, mixed>> */
	public function get_all(): array {
		$templates = array(
			'bottom_gradient' => array(
				'id'          => 'bottom_gradient',
				'label'       => __( 'Dải tối phía dưới', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Chuyển sắc tối ở phần dưới ảnh, tiêu đề bên trái, nhãn chuyên mục và thương hiệu. Phù hợp cho hầu hết bài viết.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'bottom_gradient',
				'shows_title' => true,
			),
			'left_panel' => array(
				'id'          => 'left_panel',
				'label'       => __( 'Khối màu bên trái', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Panel màu thương hiệu phủ nửa trái ảnh, tiêu đề lớn dễ đọc. Phù hợp bài hướng dẫn và tin chuyên ngành.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'left_panel',
				'shows_title' => true,
			),
			'top_band' => array(
				'id'          => 'top_band',
				'label'       => __( 'Dải thương hiệu phía trên', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Dải màu thương hiệu trên cùng chứa logo và chuyên mục, tiêu đề nằm trên nền chuyển sắc phía dưới.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'top_band',
				'shows_title' => true,
			),
			'center_box' => array(
				'id'          => 'center_box',
				'label'       => __( 'Khối trung tâm', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Hộp mờ bo góc ở giữa ảnh, tiêu đề căn giữa. Phù hợp ảnh nền ít chi tiết ở trung tâm.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'center_box',
				'shows_title' => true,
			),
			'minimal_badge' => array(
				'id'          => 'minimal_badge',
				'label'       => __( 'Tối giản không tiêu đề', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Chỉ gắn nhãn chuyên mục và thương hiệu ở góc, giữ ảnh tự nhiên nhất. Phù hợp ảnh minh hoạ trong nội dung.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'minimal_badge',
				'shows_title' => false,
			),
			'top_gradient' => array(
				'id'          => 'top_gradient',
				'label'       => __( 'Dải tối phía trên', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Chuyển sắc tối ở phần trên ảnh, tiêu đề nằm trên cao, thương hiệu góc phải. Hợp ảnh có chi tiết chính ở nửa dưới.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'top_gradient',
				'shows_title' => true,
			),
			'right_panel' => array(
				'id'          => 'right_panel',
				'label'       => __( 'Khối màu bên phải', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Panel màu thương hiệu phủ nửa phải ảnh, tiêu đề lớn dễ đọc. Đảo chiều của khối bên trái để tránh lặp.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'right_panel',
				'shows_title' => true,
			),
			'bottom_bar' => array(
				'id'          => 'bottom_bar',
				'label'       => __( 'Thanh màu phía dưới', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Thanh màu thương hiệu đặc ở đáy ảnh chứa tiêu đề, nhãn chuyên mục vắt lên mép thanh. Kiểu báo chí gọn gàng.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'bottom_bar',
				'shows_title' => true,
			),
			'corner_card' => array(
				'id'          => 'corner_card',
				'label'       => __( 'Thẻ nổi góc dưới', 'nt-tao-anh-noi-dung-wordpress' ),
				'description' => __( 'Thẻ bo góc nổi ở góc dưới-trái kiểu lower-third truyền hình, có vạch màu nhấn. Giữ được nhiều phần ảnh gốc.', 'nt-tao-anh-noi-dung-wordpress' ),
				'layout'      => 'corner_card',
				'shows_title' => true,
			),
		);

		/** Allows extra overlay templates as long as they reuse a supported layout. */
		$filtered = apply_filters( 'nt_content_images_overlay_templates', $templates );
		return $this->sanitize_templates( is_array( $filtered ) ? $filtered : $templates );
	}

	/** @return array<string, mixed>|null */
	public function get( string $id ): ?array {
		$templates = $this->get_all();
		$id        = sanitize_key( $id );
		return $templates[ $id ] ?? null;
	}

	public function exists( string $id ): bool {
		return null !== $this->get( $id );
	}

	/** @return array<int, array<string, mixed>> Public list for REST/JS. */
	public function get_public(): array {
		$items = array();
		foreach ( $this->get_all() as $template ) {
			$items[] = array(
				'id'          => $template['id'],
				'label'       => $template['label'],
				'description' => $template['description'],
				'shows_title' => $template['shows_title'],
			);
		}
		return $items;
	}

	/**
	 * @param array<string, mixed> $templates Raw template map.
	 * @return array<string, array<string, mixed>>
	 */
	private function sanitize_templates( array $templates ): array {
		$clean = array();
		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$id     = sanitize_key( (string) ( $template['id'] ?? '' ) );
			$layout = sanitize_key( (string) ( $template['layout'] ?? '' ) );
			if ( '' === $id || ! in_array( $layout, self::SUPPORTED_LAYOUTS, true ) ) {
				continue;
			}
			$clean[ $id ] = array(
				'id'          => $id,
				'label'       => sanitize_text_field( (string) ( $template['label'] ?? $id ) ),
				'description' => sanitize_text_field( (string) ( $template['description'] ?? '' ) ),
				'layout'      => $layout,
				'shows_title' => ! empty( $template['shows_title'] ),
			);
		}
		return $clean;
	}
}
