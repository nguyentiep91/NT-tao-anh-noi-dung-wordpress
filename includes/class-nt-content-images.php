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
	 * Read-only audit scanner service.
	 */
	private ?NT_Content_Images_Content_Scanner $audit_scanner = null;

	/**
	 * Registers hooks and initializes plugin modules.
	 */
	public function run(): void {
		$this->audit_scanner = $this->create_audit_scanner();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		/**
		 * Fires after core plugin services have been initialized.
		 *
		 * @param NT_Content_Images $plugin Plugin coordinator.
		 */
		do_action( 'nt_content_images_loaded', $this );
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
	 * Applies audit schema upgrades on authenticated admin requests only.
	 */
	public function maybe_upgrade_database(): void {
		NT_Content_Images_Audit_Migrator::maybe_upgrade();
		update_option( 'nt_content_images_version', NT_CONTENT_IMAGES_VERSION, false );
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
	 * Renders a safe placeholder dashboard for the audit foundation release.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'NT – Tạo ảnh cho nội dung WordPress', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Nền tảng Sprint Audit 1 đã sẵn sàng: bảng audit, bộ quét nội dung, nhận diện ảnh, chỉ số nội dung và chấm điểm ưu tiên. Chưa có thao tác tự động quét hoặc sửa bài viết.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Returns the read-only audit scanner for later controllers and tests.
	 */
	public function get_audit_scanner(): NT_Content_Images_Content_Scanner {
		if ( null === $this->audit_scanner ) {
			$this->audit_scanner = $this->create_audit_scanner();
		}

		return $this->audit_scanner;
	}

	/**
	 * Builds audit services with explicit dependencies.
	 */
	private function create_audit_scanner(): NT_Content_Images_Content_Scanner {
		return new NT_Content_Images_Content_Scanner(
			new NT_Content_Images_Image_Detector(),
			new NT_Content_Images_Content_Metrics_Analyzer(),
			new NT_Content_Images_Priority_Calculator(),
			new NT_Content_Images_Audit_Repository()
		);
	}
}
