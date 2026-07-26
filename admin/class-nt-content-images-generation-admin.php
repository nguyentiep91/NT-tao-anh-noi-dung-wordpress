<?php
/**
 * Admin screen for AI featured-image generation.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Admin {
	private NT_Content_Images_Generation_Settings $settings;

	public function __construct( NT_Content_Images_Generation_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nt_content_images_save_generation_settings', array( $this, 'handle_save_settings' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'nt-content-images',
			__( 'Tạo ảnh AI', 'nt-tao-anh-noi-dung-wordpress' ),
			__( 'Tạo ảnh AI', 'nt-tao-anh-noi-dung-wordpress' ),
			'manage_options',
			'nt-content-images-generation',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'nt-content-images-generation' !== $page ) {
			return;
		}
		wp_enqueue_style( 'nt-content-images-generation', NT_CONTENT_IMAGES_URL . 'admin/assets/generation-admin.css', array(), NT_CONTENT_IMAGES_VERSION );
		wp_enqueue_script( 'nt-content-images-generation', NT_CONTENT_IMAGES_URL . 'admin/assets/generation-admin.js', array( 'wp-api-fetch' ), NT_CONTENT_IMAGES_VERSION, true );
		wp_localize_script(
			'nt-content-images-generation',
			'NTContentImagesGeneration',
			array(
				'root'  => '/nt-content-images/v1',
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'labels'=> array(
					'confirmGenerate' => __( 'Tạo một ảnh AI có thể phát sinh chi phí API. Tiếp tục?', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmApprove'  => __( 'Đặt ảnh này làm ảnh đại diện cho bài viết?', 'nt-tao-anh-noi-dung-wordpress' ),
					'networkError'    => __( 'Không thể kết nối tới WordPress REST API.', 'nt-tao-anh-noi-dung-wordpress' ),
				)
			)
		);
	}

	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		check_admin_referer( 'nt_content_images_save_generation_settings' );
		$raw    = isset( $_POST['generation_settings'] ) && is_array( $_POST['generation_settings'] ) ? wp_unslash( $_POST['generation_settings'] ) : array();
		$result = $this->settings->save( $raw );
		$args   = is_wp_error( $result ) ? array( 'ntci_error' => $result->get_error_message() ) : array( 'ntci_status' => 'saved' );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=nt-content-images-generation' ) ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$config = $this->settings->get_public();
		$status = isset( $_GET['ntci_status'] ) ? sanitize_key( wp_unslash( $_GET['ntci_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error  = isset( $_GET['ntci_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ntci_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap ntci-generation" id="ntci-generation-app">
			<h1><?php echo esc_html__( 'Tạo ảnh đại diện bằng AI', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Plugin phân tích nội dung và Image Brief, tạo một ảnh nền không chữ, lưu vào Media Library rồi chờ anh duyệt trước khi đặt làm ảnh đại diện.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Mỗi lần bấm Tạo ảnh sẽ gửi một yêu cầu tới OpenAI và có thể phát sinh chi phí. MVP không tự chạy hàng loạt và không ghi đè ảnh đại diện hiện có.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div>
			<?php if ( $status ) : ?><div class="notice notice-success inline"><p><?php echo esc_html__( 'Đã lưu cấu hình tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<section class="ntci-generation-panel">
				<h2><?php echo esc_html__( 'Cấu hình OpenAI Images', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><strong><?php echo esc_html__( 'Trạng thái API key:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( $config['configured'] ? __( 'Đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'Chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> — <code><?php echo esc_html( (string) $config['key_source'] ); ?></code></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_generation_settings">
					<?php wp_nonce_field( 'nt_content_images_save_generation_settings' ); ?>
					<div class="ntci-generation-grid">
						<label><span><?php echo esc_html__( 'OpenAI API key', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="password" name="generation_settings[api_key]" value="" autocomplete="new-password" placeholder="Để trống để giữ key hiện tại" <?php disabled( 'wp-config' === $config['key_source'] ); ?>></label>
						<label><span><?php echo esc_html__( 'Model', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="generation_settings[model]"><option value="gpt-image-1-mini" <?php selected( $config['model'], 'gpt-image-1-mini' ); ?>>gpt-image-1-mini</option><option value="gpt-image-1" <?php selected( $config['model'], 'gpt-image-1' ); ?>>gpt-image-1</option></select></label>
						<label><span><?php echo esc_html__( 'Chất lượng', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="generation_settings[quality]"><?php foreach ( array( 'low', 'medium', 'high', 'auto' ) as $quality ) : ?><option value="<?php echo esc_attr( $quality ); ?>" <?php selected( $config['quality'], $quality ); ?>><?php echo esc_html( $quality ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html__( 'Timeout giây', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="number" min="60" max="240" name="generation_settings[timeout]" value="<?php echo esc_attr( (string) $config['timeout'] ); ?>"></label>
					</div>
					<?php if ( 'database' === $config['key_source'] ) : ?><label><input type="checkbox" name="generation_settings[clear_api_key]" value="1"> <?php echo esc_html__( 'Xóa API key đang lưu trong database', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><?php endif; ?>
					<p class="description"><?php echo esc_html__( 'Khuyến nghị production: khai báo NT_CONTENT_IMAGES_OPENAI_API_KEY trong wp-config.php. API key không bao giờ được gửi xuống JavaScript.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					<?php submit_button( __( 'Lưu cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
				</form>
			</section>

			<section class="ntci-generation-panel">
				<div class="ntci-generation-heading"><div><h2><?php echo esc_html__( 'Bài viết thiếu ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2><p><?php echo esc_html__( 'Danh sách được lấy từ kết quả audit, sắp xếp theo mức ưu tiên.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><button type="button" class="button" id="ntci-generation-refresh"><?php echo esc_html__( 'Làm mới', 'nt-tao-anh-noi-dung-wordpress' ); ?></button></div>
				<div id="ntci-generation-feedback" class="ntci-generation-feedback" aria-live="polite"></div>
				<div class="ntci-generation-table-wrap"><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Loại', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Số từ', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ưu tiên', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Thao tác', 'nt-tao-anh-noi-dung-wordpress' ); ?></th></tr></thead><tbody id="ntci-generation-candidates"><tr><td colspan="5"><?php echo esc_html__( 'Đang tải…', 'nt-tao-anh-noi-dung-wordpress' ); ?></td></tr></tbody></table></div>
			</section>

			<section class="ntci-generation-panel">
				<h2><?php echo esc_html__( 'Ảnh đã tạo', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<div class="ntci-generation-gallery" id="ntci-generation-gallery"></div>
			</section>
		</div>
		<?php
	}
}
