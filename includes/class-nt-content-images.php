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
	private ?NT_Content_Images_Audit_Repository $audit_repository = null;
	private ?NT_Content_Images_Content_Scanner $audit_scanner = null;
	private ?NT_Content_Images_Audit_Query $audit_query = null;
	private ?NT_Content_Images_Audit_Job_Store $audit_job_store = null;
	private ?NT_Content_Images_Audit_Batch_Runner $audit_runner = null;

	/**
	 * Registers hooks and initializes plugin modules.
	 */
	public function run(): void {
		$this->initialize_audit_services();

		$rest_controller = new NT_Content_Images_Audit_REST_Controller(
			$this->get_audit_runner(),
			$this->get_audit_repository(),
			$this->get_audit_query()
		);
		$admin           = new NT_Content_Images_Audit_Admin();
		$exporter        = new NT_Content_Images_Audit_Exporter( $this->get_audit_repository() );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );
		$admin->register();
		$exporter->register();

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
	 * Applies schema upgrades on authenticated admin requests only.
	 */
	public function maybe_upgrade_database(): void {
		NT_Content_Images_Audit_Migrator::maybe_upgrade();
		update_option( 'nt_content_images_version', NT_CONTENT_IMAGES_VERSION, false );
	}

	/**
	 * Adds the top-level plugin menu.
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
	 * Renders a concise audit dashboard.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$summary   = $this->get_audit_repository()->get_summary();
		$audit_url = admin_url( 'admin.php?page=nt-content-images-audit' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'NT – Tạo ảnh cho nội dung WordPress', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Sprint Audit 2 đã sẵn sàng: quét nội dung theo batch, theo dõi tiến độ, lọc kết quả và xuất CSV. Chức năng này chưa tạo hoặc chèn ảnh.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<p><strong><?php echo esc_html__( 'Tổng bài đã audit:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $audit_url ); ?>"><?php echo esc_html__( 'Mở chức năng kiểm tra bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Returns the read-only audit scanner.
	 */
	public function get_audit_scanner(): NT_Content_Images_Content_Scanner {
		$this->initialize_audit_services();

		return $this->audit_scanner;
	}

	/**
	 * Returns the audit repository.
	 */
	public function get_audit_repository(): NT_Content_Images_Audit_Repository {
		$this->initialize_audit_services();

		return $this->audit_repository;
	}

	/**
	 * Returns the audit query service.
	 */
	public function get_audit_query(): NT_Content_Images_Audit_Query {
		$this->initialize_audit_services();

		return $this->audit_query;
	}

	/**
	 * Returns the resumable batch runner.
	 */
	public function get_audit_runner(): NT_Content_Images_Audit_Batch_Runner {
		$this->initialize_audit_services();

		return $this->audit_runner;
	}

	/**
	 * Builds audit services once with explicit dependencies.
	 */
	private function initialize_audit_services(): void {
		if ( null !== $this->audit_runner ) {
			return;
		}

		$this->audit_repository = new NT_Content_Images_Audit_Repository();
		$this->audit_query      = new NT_Content_Images_Audit_Query();
		$this->audit_job_store  = new NT_Content_Images_Audit_Job_Store();
		$this->audit_scanner    = new NT_Content_Images_Content_Scanner(
			new NT_Content_Images_Image_Detector(),
			new NT_Content_Images_Content_Metrics_Analyzer(),
			new NT_Content_Images_Priority_Calculator(),
			$this->audit_repository
		);
		$this->audit_runner     = new NT_Content_Images_Audit_Batch_Runner(
			$this->audit_query,
			$this->audit_scanner,
			$this->audit_repository,
			$this->audit_job_store
		);
	}
}
