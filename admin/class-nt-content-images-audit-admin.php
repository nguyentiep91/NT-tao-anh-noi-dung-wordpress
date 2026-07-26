<?php
/**
 * WordPress admin UI for read-only content audit jobs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Audit_Admin {
	/**
	 * Registers menu and asset hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the audit submenu beneath the plugin menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'nt-content-images',
			__( 'Kiểm tra bài viết', 'nt-tao-anh-noi-dung-wordpress' ),
			__( 'Kiểm tra bài viết', 'nt-tao-anh-noi-dung-wordpress' ),
			'manage_options',
			'nt-content-images-audit',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Loads assets only on the audit screen.
	 */
	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'nt-content-images-audit' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'nt-content-images-audit-admin',
			NT_CONTENT_IMAGES_URL . 'admin/assets/audit-admin.css',
			array(),
			NT_CONTENT_IMAGES_VERSION
		);
		wp_enqueue_script(
			'nt-content-images-audit-admin',
			NT_CONTENT_IMAGES_URL . 'admin/assets/audit-admin.js',
			array( 'wp-api-fetch' ),
			NT_CONTENT_IMAGES_VERSION,
			true
		);
		wp_localize_script(
			'nt-content-images-audit-admin',
			'NTContentImagesAudit',
			array(
				'root'       => '/nt-content-images/v1',
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'exportUrl'  => wp_nonce_url(
					admin_url( 'admin-post.php?action=nt_content_images_audit_export' ),
					'nt_content_images_audit_export'
				),
				'editBase'   => admin_url( 'post.php?action=edit&post=' ),
				'labels'     => array(
					'confirmCancel' => __( 'Dừng tiến trình audit hiện tại?', 'nt-tao-anh-noi-dung-wordpress' ),
					'networkError'  => __( 'Không thể kết nối tới WordPress REST API.', 'nt-tao-anh-noi-dung-wordpress' ),
					'empty'         => __( 'Chưa có dữ liệu audit phù hợp.', 'nt-tao-anh-noi-dung-wordpress' ),
				),
			)
		);
	}

	/**
	 * Renders the audit administration page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		?>
		<div class="wrap ntci-audit" id="ntci-audit-app">
			<h1><?php echo esc_html__( 'Kiểm tra nội dung và hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<div class="notice notice-info inline">
				<p><?php echo esc_html__( 'Chức năng này chỉ đọc và phân tích nội dung. Plugin chưa tạo, sửa hoặc chèn bất kỳ hình ảnh nào.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			</div>

			<section class="ntci-panel" aria-labelledby="ntci-settings-title">
				<h2 id="ntci-settings-title"><?php echo esc_html__( 'Thiết lập lần kiểm tra', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<div class="ntci-form-grid">
					<fieldset>
						<legend><?php echo esc_html__( 'Loại nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></legend>
						<label><input type="checkbox" name="post_types" value="post" checked> <?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
						<label><input type="checkbox" name="post_types" value="page" checked> <?php echo esc_html__( 'Trang', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					</fieldset>
					<fieldset>
						<legend><?php echo esc_html__( 'Trạng thái', 'nt-tao-anh-noi-dung-wordpress' ); ?></legend>
						<label><input type="checkbox" name="post_statuses" value="publish" checked> <?php echo esc_html__( 'Đã xuất bản', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
						<label><input type="checkbox" name="post_statuses" value="draft"> <?php echo esc_html__( 'Bản nháp', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
						<label><input type="checkbox" name="post_statuses" value="pending"> <?php echo esc_html__( 'Chờ duyệt', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
						<label><input type="checkbox" name="post_statuses" value="private"> <?php echo esc_html__( 'Riêng tư', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					</fieldset>
					<label>
						<span><?php echo esc_html__( 'Chế độ quét', 'nt-tao-anh-noi-dung-wordpress' ); ?></span>
						<select id="ntci-scan-mode">
							<option value="changed"><?php echo esc_html__( 'Bài mới và bài đã thay đổi', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
							<option value="new"><?php echo esc_html__( 'Chỉ bài chưa audit', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
							<option value="all"><?php echo esc_html__( 'Quét lại toàn bộ', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php echo esc_html__( 'Số bài mỗi batch', 'nt-tao-anh-noi-dung-wordpress' ); ?></span>
						<select id="ntci-batch-size">
							<option value="10">10</option>
							<option value="20" selected>20</option>
							<option value="30">30</option>
							<option value="50">50</option>
						</select>
					</label>
				</div>
				<div class="ntci-actions">
					<button type="button" class="button button-primary" id="ntci-start"><?php echo esc_html__( 'Bắt đầu kiểm tra', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					<button type="button" class="button" id="ntci-pause" disabled><?php echo esc_html__( 'Tạm dừng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					<button type="button" class="button" id="ntci-resume" disabled><?php echo esc_html__( 'Tiếp tục', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					<button type="button" class="button button-link-delete" id="ntci-cancel" disabled><?php echo esc_html__( 'Hủy', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div id="ntci-feedback" class="ntci-feedback" aria-live="polite"></div>
			</section>

			<section class="ntci-panel" aria-labelledby="ntci-progress-title">
				<h2 id="ntci-progress-title"><?php echo esc_html__( 'Tiến độ', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<div class="ntci-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
					<span id="ntci-progress-bar"></span>
				</div>
				<div class="ntci-progress-meta" id="ntci-progress-meta"><?php echo esc_html__( 'Chưa có tiến trình.', 'nt-tao-anh-noi-dung-wordpress' ); ?></div>
			</section>

			<section aria-labelledby="ntci-summary-title">
				<h2 id="ntci-summary-title"><?php echo esc_html__( 'Tổng quan kết quả', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<div class="ntci-summary-grid" id="ntci-summary"></div>
			</section>

			<section class="ntci-panel" aria-labelledby="ntci-results-title">
				<div class="ntci-section-heading">
					<h2 id="ntci-results-title"><?php echo esc_html__( 'Danh sách bài đã kiểm tra', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
					<a class="button" id="ntci-export" href="#"><?php echo esc_html__( 'Xuất CSV', 'nt-tao-anh-noi-dung-wordpress' ); ?></a>
				</div>
				<div class="ntci-filters">
					<input type="search" id="ntci-search" placeholder="<?php echo esc_attr__( 'Tìm theo tiêu đề…', 'nt-tao-anh-noi-dung-wordpress' ); ?>">
					<select id="ntci-filter-featured"><option value=""><?php echo esc_html__( 'Tất cả ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="missing"><?php echo esc_html__( 'Thiếu ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="present"><?php echo esc_html__( 'Có ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></option></select>
					<select id="ntci-filter-content"><option value=""><?php echo esc_html__( 'Tất cả ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="none"><?php echo esc_html__( 'Không có ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="some"><?php echo esc_html__( 'Có ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></option></select>
					<select id="ntci-filter-priority"><option value=""><?php echo esc_html__( 'Tất cả ưu tiên', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="very_high"><?php echo esc_html__( 'Rất cao', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="high"><?php echo esc_html__( 'Cao', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="medium"><?php echo esc_html__( 'Trung bình', 'nt-tao-anh-noi-dung-wordpress' ); ?></option><option value="low"><?php echo esc_html__( 'Thấp', 'nt-tao-anh-noi-dung-wordpress' ); ?></option></select>
					<button type="button" class="button" id="ntci-apply-filters"><?php echo esc_html__( 'Áp dụng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div class="ntci-table-wrap">
					<table class="widefat striped" id="ntci-results-table">
						<thead><tr><th><?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Loại', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Số từ', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ưu tiên', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Trạng thái', 'nt-tao-anh-noi-dung-wordpress' ); ?></th></tr></thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="ntci-pagination" id="ntci-pagination"></div>
			</section>

			<section class="ntci-panel" id="ntci-detail-panel" hidden aria-labelledby="ntci-detail-title">
				<div class="ntci-section-heading"><h2 id="ntci-detail-title"><?php echo esc_html__( 'Chi tiết bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2><button type="button" class="button-link" id="ntci-detail-close"><?php echo esc_html__( 'Đóng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button></div>
				<div id="ntci-detail"></div>
			</section>
		</div>
		<?php
	}
}
