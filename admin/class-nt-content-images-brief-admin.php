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
	private NT_Content_Images_Plan_Settings $plan_settings;

	public function __construct( ?NT_Content_Images_Plan_Settings $plan_settings = null ) {
		$this->plan_settings = $plan_settings ?? new NT_Content_Images_Plan_Settings();
	}

	/**
	 * Registers menu and asset hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nt_content_images_save_plan_settings', array( $this, 'handle_save_plan_settings' ) );
	}

	/** Saves the configurable image-count rules. */
	public function handle_save_plan_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		check_admin_referer( 'nt_content_images_save_plan_settings' );
		$raw    = isset( $_POST['plan_settings'] ) && is_array( $_POST['plan_settings'] ) ? wp_unslash( $_POST['plan_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = $this->plan_settings->save( $raw );
		$url    = admin_url( 'admin.php?page=nt-content-images-briefs' );
		$url    = is_wp_error( $result ) ? add_query_arg( 'ntci_error', rawurlencode( $result->get_error_message() ), $url ) : add_query_arg( 'ntci_status', 'plan_saved', $url );
		wp_safe_redirect( $url );
		exit;
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
			<?php
			$rules       = $this->plan_settings->get();
			$plan_status = isset( $_GET['ntci_status'] ) ? sanitize_key( wp_unslash( $_GET['ntci_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$plan_error  = isset( $_GET['ntci_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ntci_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<?php if ( 'plan_saved' === $plan_status ) : ?><div class="notice notice-success inline"><p><?php echo esc_html__( 'Đã lưu quy tắc kế hoạch ảnh. Quy tắc mới áp dụng khi tạo kế hoạch tiếp theo; kế hoạch cũ giữ nguyên cho tới khi tạo lại.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><?php endif; ?>
			<?php if ( '' !== $plan_error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $plan_error ); ?></p></div><?php endif; ?>

			<section class="ntci-brief-panel" aria-labelledby="ntci-plan-rules-title">
				<h2 id="ntci-plan-rules-title"><?php echo esc_html__( 'Quy tắc số ảnh trong bài', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><?php echo esc_html__( 'Tự quyết định bài dài bao nhiêu thì có mấy ảnh minh hoạ. Đặt 0 để không tạo ảnh cho nhóm đó.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_plan_settings">
					<?php wp_nonce_field( 'nt_content_images_save_plan_settings' ); ?>
					<table class="form-table" role="presentation" style="max-width:640px;">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Bài ngắn', 'nt-tao-anh-noi-dung-wordpress' ); ?></th>
							<td><?php echo esc_html__( 'Dưới', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[threshold_small]" value="<?php echo esc_attr( (string) $rules['threshold_small'] ); ?>" min="100" max="20000" style="width:90px;"> <?php echo esc_html__( 'từ →', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[count_small]" value="<?php echo esc_attr( (string) $rules['count_small'] ); ?>" min="0" max="5" style="width:60px;"> <?php echo esc_html__( 'ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Bài trung bình', 'nt-tao-anh-noi-dung-wordpress' ); ?></th>
							<td><?php echo esc_html__( 'Đến', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[threshold_large]" value="<?php echo esc_attr( (string) $rules['threshold_large'] ); ?>" min="200" max="50000" style="width:90px;"> <?php echo esc_html__( 'từ →', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[count_medium]" value="<?php echo esc_attr( (string) $rules['count_medium'] ); ?>" min="0" max="5" style="width:60px;"> <?php echo esc_html__( 'ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Bài dài', 'nt-tao-anh-noi-dung-wordpress' ); ?></th>
							<td><?php echo esc_html__( 'Trên ngưỡng trên →', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[count_large]" value="<?php echo esc_attr( (string) $rules['count_large'] ); ?>" min="0" max="5" style="width:60px;"> <?php echo esc_html__( 'ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Giới hạn', 'nt-tao-anh-noi-dung-wordpress' ); ?></th>
							<td>
								<?php echo esc_html__( 'Tối đa mỗi bài', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[max_images]" value="<?php echo esc_attr( (string) $rules['max_images'] ); ?>" min="0" max="5" style="width:60px;"> <?php echo esc_html__( 'ảnh · Bài khóa học/dịch vụ tối đa', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" name="plan_settings[special_max]" value="<?php echo esc_attr( (string) $rules['special_max'] ); ?>" min="0" max="5" style="width:60px;"> <?php echo esc_html__( 'ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Lưu quy tắc kế hoạch ảnh', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
				</form>
			</section>

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
