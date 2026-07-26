<?php
/**
 * WordPress admin UI for image brief planning.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brief_Admin {
	/**
	 * Registers menu and asset hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the brief submenu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'nt-content-images',
			__( 'Kế hoạch hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ),
			__( 'Kế hoạch hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ),
			'manage_options',
			'nt-content-images-briefs',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Loads assets only on the brief screen.
	 */
	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'nt-content-images-briefs' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'nt-content-images-brief-admin',
			NT_CONTENT_IMAGES_URL . 'admin/assets/brief-admin.css',
			array(),
			NT_CONTENT_IMAGES_VERSION
		);
		wp_enqueue_script(
			'nt-content-images-brief-admin',
			NT_CONTENT_IMAGES_URL . 'admin/assets/brief-admin.js',
			array( 'wp-api-fetch' ),
			NT_CONTENT_IMAGES_VERSION,
			true
		);
		wp_localize_script(
			'nt-content-images-brief-admin',
			'NTContentImagesBriefs',
			array(
				'root'  => '/nt-content-images/v1',
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'labels'=> array(
					'networkError' => __( 'Không thể kết nối tới WordPress REST API.', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmApprove' => __( 'Duyệt kế hoạch hình ảnh này?', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmReject' => __( 'Từ chối kế hoạch hình ảnh này?', 'nt-tao-anh-noi-dung-wordpress' ),
					'empty' => __( 'Chưa có kế hoạch hình ảnh phù hợp.', 'nt-tao-anh-noi-dung-wordpress' ),
				),
			)
		);
	}

	/**
	 * Renders the image brief page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		?>
		<div class="wrap ntci-briefs" id="ntci-brief-app">
			<h1><?php echo esc_html__( 'Kế hoạch hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<div class="notice notice-info inline">
				<p><?php echo esc_html__( 'Module này tạo kế hoạch hình ảnh bằng quy tắc nội bộ. Chưa gọi AI, chưa tạo tệp ảnh và chưa thay đổi bài viết.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			</div>

			<section class="ntci-brief-panel" aria-labelledby="ntci-brief-candidates-title">
				<div class="ntci-brief-heading">
					<div>
						<h2 id="ntci-brief-candidates-title"><?php echo esc_html__( 'Bài ưu tiên để tạo brief', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
						<p><?php echo esc_html__( 'Mặc định lấy các bài ưu tiên rất cao, đã audit thành công và chưa có ảnh nội dung.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					</div>
					<button type="button" class="button" id="ntci-brief-refresh-candidates"><?php echo esc_html__( 'Tải lại', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div class="ntci-brief-toolbar">
					<label><input type="checkbox" id="ntci-brief-select-all"> <?php echo esc_html__( 'Chọn tất cả đang hiển thị', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					<button type="button" class="button button-primary" id="ntci-brief-generate-selected"><?php echo esc_html__( 'Tạo brief cho bài đã chọn', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div class="ntci-brief-table-wrap">
					<table class="widefat striped" id="ntci-brief-candidates-table">
						<thead><tr><th class="check-column"></th><th><?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Số từ', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Điểm ưu tiên', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Brief hiện tại', 'nt-tao-anh-noi-dung-wordpress' ); ?></th></tr></thead>
						<tbody></tbody>
					</table>
				</div>
				<div id="ntci-brief-feedback" class="ntci-brief-feedback" aria-live="polite"></div>
			</section>

			<section aria-labelledby="ntci-brief-summary-title">
				<h2 id="ntci-brief-summary-title"><?php echo esc_html__( 'Tổng quan brief', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<div class="ntci-brief-summary" id="ntci-brief-summary"></div>
			</section>

			<section class="ntci-brief-panel" aria-labelledby="ntci-brief-list-title">
				<div class="ntci-brief-heading">
					<h2 id="ntci-brief-list-title"><?php echo esc_html__( 'Danh sách kế hoạch hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
					<button type="button" class="button" id="ntci-brief-refresh-list"><?php echo esc_html__( 'Làm mới danh sách', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div class="ntci-brief-filters">
					<input type="search" id="ntci-brief-search" placeholder="<?php echo esc_attr__( 'Tìm theo tiêu đề hoặc chủ đề…', 'nt-tao-anh-noi-dung-wordpress' ); ?>">
					<select id="ntci-brief-status-filter">
						<option value=""><?php echo esc_html__( 'Tất cả trạng thái', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						<option value="draft"><?php echo esc_html__( 'Nháp', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						<option value="pending_review"><?php echo esc_html__( 'Chờ duyệt', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						<option value="approved"><?php echo esc_html__( 'Đã duyệt', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						<option value="rejected"><?php echo esc_html__( 'Từ chối', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						<option value="outdated"><?php echo esc_html__( 'Lỗi thời', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
					</select>
					<select id="ntci-brief-type-filter">
						<option value=""><?php echo esc_html__( 'Tất cả loại nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></option>
						<option value="legal_update">legal_update</option>
						<option value="legal_explainer">legal_explainer</option>
						<option value="how_to">how_to</option>
						<option value="comparison">comparison</option>
						<option value="course">course</option>
						<option value="service">service</option>
						<option value="event">event</option>
						<option value="general_education">general_education</option>
					</select>
					<button type="button" class="button" id="ntci-brief-apply-filters"><?php echo esc_html__( 'Áp dụng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div class="ntci-brief-table-wrap">
					<table class="widefat striped" id="ntci-brief-list-table">
						<thead><tr><th><?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Chủ đề', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Loại', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ảnh nội dung cần tạo', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Kiểm tra', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Trạng thái', 'nt-tao-anh-noi-dung-wordpress' ); ?></th></tr></thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="ntci-brief-pagination" id="ntci-brief-pagination"></div>
			</section>

			<section class="ntci-brief-panel" id="ntci-brief-detail-panel" hidden aria-labelledby="ntci-brief-detail-title">
				<div class="ntci-brief-heading">
					<h2 id="ntci-brief-detail-title"><?php echo esc_html__( 'Chi tiết kế hoạch hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
					<button type="button" class="button-link" id="ntci-brief-detail-close"><?php echo esc_html__( 'Đóng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div id="ntci-brief-detail"></div>
				<div class="ntci-brief-detail-actions" id="ntci-brief-detail-actions"></div>
			</section>
		</div>
		<?php
	}
}
