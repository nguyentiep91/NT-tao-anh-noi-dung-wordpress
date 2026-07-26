<?php
/**
 * Core plugin coordinator.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images {
	/**
	 * Registers hooks and initializes plugin modules.
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Loads translation files.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'nt-tao-anh-noi-dung-wordpress',
			false,
			dirname( plugin_basename( NT_CONTENT_IMAGES_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Adds the initial administrative menu.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'NT – Tạo ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ),
			__( 'NT – Tạo ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ),
			'manage_options',
			'nt-content-images',
			array( $this, 'render_dashboard' ),
			'dashicons-format-image',
			58
		);
	}

	/**
	 * Renders a safe placeholder dashboard for the scaffold release.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'NT – Tạo ảnh cho nội dung WordPress', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Bộ khung plugin đã được kích hoạt. Chưa có thao tác tạo hoặc chèn ảnh nào được thực hiện.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
		</div>
		<?php
	}
}
