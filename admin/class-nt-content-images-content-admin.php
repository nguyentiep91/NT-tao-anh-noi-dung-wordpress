<?php
/**
 * Admin screen for planned in-article images: plan, generate, review, insert, undo.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Content_Admin {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		add_submenu_page( 'nt-content-images', __( 'Ảnh trong bài', 'nt-tao-anh-noi-dung-wordpress' ), __( 'Ảnh trong bài', 'nt-tao-anh-noi-dung-wordpress' ), 'manage_options', 'nt-content-images-content', array( $this, 'render_page' ) );
	}

	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'nt-content-images-content' !== $page ) {
			return;
		}
		wp_enqueue_style( 'nt-content-images-content', NT_CONTENT_IMAGES_URL . 'admin/assets/content-admin.css', array(), NT_CONTENT_IMAGES_VERSION );
		wp_enqueue_script( 'nt-content-images-content', NT_CONTENT_IMAGES_URL . 'admin/assets/content-admin.js', array( 'wp-api-fetch' ), NT_CONTENT_IMAGES_VERSION, true );
		wp_localize_script(
			'nt-content-images-content',
			'NTContentImagesContent',
			array(
				'root'   => '/nt-content-images/v1',
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'labels' => array(
					'confirmGenerate' => __( 'Tạo toàn bộ ảnh theo kế hoạch cho bài này? Mỗi ảnh là một yêu cầu API có thể phát sinh chi phí.', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmInsert'   => __( 'Chèn các ảnh đã duyệt vào nội dung bài viết? Plugin sẽ lưu bản gốc để hoàn tác.', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmRollback' => __( 'Khôi phục nội dung bài về trạng thái trước khi chèn ảnh?', 'nt-tao-anh-noi-dung-wordpress' ),
				'confirmQueue'    => __( 'Bắt đầu chạy hàng loạt? Mỗi ảnh là một yêu cầu API có thể phát sinh chi phí. Đợt chạy tự dừng khi chạm giới hạn ảnh/ngày.', 'nt-tao-anh-noi-dung-wordpress' ),
				'confirmQueueAutoInsert' => __( 'Bắt đầu chạy hàng loạt VỚI TỰ ĐỘNG CHÈN? Ảnh tạo xong sẽ được duyệt và chèn thẳng vào từng bài theo kế hoạch (bài chưa có ảnh đại diện sẽ được đặt luôn). Mỗi bài đều có bản lưu để hoàn tác. Mỗi ảnh là một yêu cầu API có thể phát sinh chi phí.', 'nt-tao-anh-noi-dung-wordpress' ),
				'confirmQueueCancel' => __( 'Hủy đợt chạy hàng loạt hiện tại?', 'nt-tao-anh-noi-dung-wordpress' ),
				'confirmCleanup'  => __( 'Dọn Media Library? Mọi ảnh do plugin tạo mà KHÔNG được chèn vào bài và KHÔNG làm ảnh đại diện sẽ bị xoá vĩnh viễn — gồm cả ảnh đang chờ duyệt. Ảnh đang dùng và ảnh anh tự tải lên được giữ nguyên.', 'nt-tao-anh-noi-dung-wordpress' ),
					'networkError'    => __( 'Không thể kết nối tới WordPress REST API.', 'nt-tao-anh-noi-dung-wordpress' ),
				),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		?>
		<div class="wrap ntci-content" id="ntci-content-app">
			<h1><?php echo esc_html__( 'Ảnh minh hoạ trong bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Plugin lập kế hoạch theo độ dài bài (2000 từ ≈ 2 ảnh), tạo ảnh cho từng phần nội dung, rồi chèn vào vị trí an toàn sau khi được duyệt. Luôn có bản lưu để hoàn tác.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Quy trình: Xem kế hoạch → Tạo bộ ảnh → Duyệt từng ảnh → Chèn vào bài. Plugin không tự chèn khi chưa duyệt và không đụng tới bài có shortcode được bảo vệ.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div>
			<div id="ntci-content-feedback" class="ntci-content-feedback" aria-live="polite"></div>

			<section class="ntci-content-panel">
				<h2><?php echo esc_html__( 'Chạy hàng loạt', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><?php echo esc_html__( 'Xử lý nhiều bài liên tiếp, mỗi bước một ảnh. Khi bật "Tự động duyệt & chèn", ảnh tạo xong sẽ được chèn thẳng vào từng bài theo kế hoạch và đặt ảnh đại diện — mỗi bài vẫn có bản lưu để hoàn tác. Giữ tab này mở trong khi chạy; có thể tạm dừng hoặc hủy bất kỳ lúc nào. Đợt chạy tự tạm dừng khi chạm giới hạn ảnh/ngày.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
				<div class="ntci-queue-controls">
					<label><input type="checkbox" id="ntci-queue-featured" checked> <?php echo esc_html__( 'Ảnh đại diện (bài đang thiếu)', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					<label><input type="checkbox" id="ntci-queue-content" checked> <?php echo esc_html__( 'Ảnh trong bài (theo kế hoạch)', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					<label><input type="checkbox" id="ntci-queue-autoinsert" checked> <?php echo esc_html__( 'Tự động duyệt & chèn vào bài sau khi tạo xong mỗi bài', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					<label><?php echo esc_html__( 'Số bài tối đa', 'nt-tao-anh-noi-dung-wordpress' ); ?> <input type="number" id="ntci-queue-limit" min="1" max="50" value="10"></label>
					<button type="button" class="button button-primary" id="ntci-queue-start"><?php echo esc_html__( 'Bắt đầu chạy', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					<button type="button" class="button" id="ntci-queue-pause" hidden><?php echo esc_html__( 'Tạm dừng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					<button type="button" class="button" id="ntci-queue-resume" hidden><?php echo esc_html__( 'Tiếp tục', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					<button type="button" class="button" id="ntci-queue-cancel" hidden><?php echo esc_html__( 'Hủy đợt chạy', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div class="ntci-queue-progress" id="ntci-queue-progress" hidden>
					<div class="ntci-queue-bar"><span id="ntci-queue-bar-fill"></span></div>
					<p id="ntci-queue-summary"></p>
					<ul id="ntci-queue-items" class="ntci-queue-items"></ul>
				</div>
				<p class="description" id="ntci-queue-usage"></p>
			</section>

			<section class="ntci-content-panel">
				<div class="ntci-content-heading">
					<div>
						<h2><?php echo esc_html__( 'Dọn ảnh không dùng', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
						<p><?php echo esc_html__( 'Chỉ giữ ảnh đã chèn vào bài hoặc đang làm ảnh đại diện. Ảnh trung gian (bản gốc chưa chèn chữ, ảnh bị từ chối, ảnh thừa sau khi chèn) được tự dọn ngay trong quy trình; nút này quét và xoá toàn bộ phần còn sót.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					</div>
					<button type="button" class="button" id="ntci-cleanup-run"><?php echo esc_html__( 'Dọn ảnh không dùng', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<p class="description" id="ntci-cleanup-result"></p>
			</section>

			<section class="ntci-content-panel">
				<div class="ntci-content-heading"><div><h2><?php echo esc_html__( 'Chọn bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2><p><?php echo esc_html__( 'Danh sách từ kết quả audit, ưu tiên bài dài.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><button type="button" class="button" id="ntci-content-refresh"><?php echo esc_html__( 'Làm mới', 'nt-tao-anh-noi-dung-wordpress' ); ?></button></div>
				<div class="ntci-content-table-wrap"><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Số từ', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Kế hoạch', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ảnh hiện có', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th></th></tr></thead><tbody id="ntci-content-candidates"><tr><td colspan="5"><?php echo esc_html__( 'Đang tải…', 'nt-tao-anh-noi-dung-wordpress' ); ?></td></tr></tbody></table></div>
			</section>

			<section class="ntci-content-panel" id="ntci-content-detail" hidden>
				<div class="ntci-content-heading"><div><h2 id="ntci-content-detail-title"></h2><p id="ntci-content-detail-meta"></p></div>
					<div class="ntci-content-actions-bar">
						<button type="button" class="button button-primary" id="ntci-content-generate"><?php echo esc_html__( 'Tạo bộ ảnh theo kế hoạch', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
						<button type="button" class="button" id="ntci-content-insert"><?php echo esc_html__( 'Chèn ảnh đã duyệt vào bài', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
						<button type="button" class="button" id="ntci-content-rollback" hidden><?php echo esc_html__( 'Hoàn tác chèn ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
					</div>
				</div>
				<div id="ntci-content-slots" class="ntci-content-slots"></div>
			</section>
		</div>
		<?php
	}
}
