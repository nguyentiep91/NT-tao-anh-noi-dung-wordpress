<?php
/**
 * Admin UI for free stock sources and affordable AI providers.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Sources_Admin {
	private NT_Content_Images_Source_Settings $settings;
	private NT_Content_Images_Generation_Settings $generation_settings;

	public function __construct( NT_Content_Images_Source_Settings $settings, NT_Content_Images_Generation_Settings $generation_settings ) {
		$this->settings = $settings;
		$this->generation_settings = $generation_settings;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 24 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nt_content_images_save_source_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_nt_content_images_save_affordable_ai_settings', array( $this, 'handle_save_affordable_ai_settings' ) );
	}

	public function register_menu(): void {
		add_submenu_page( 'nt-content-images', __( 'Tìm & Tạo ảnh', 'nt-tao-anh-noi-dung-wordpress' ), __( 'Tìm & Tạo ảnh', 'nt-tao-anh-noi-dung-wordpress' ), 'manage_options', 'nt-content-images-sources', array( $this, 'render_page' ) );
	}

	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'nt-content-images-sources' !== $page ) {
			return;
		}
		wp_enqueue_style( 'nt-content-images-sources', NT_CONTENT_IMAGES_URL . 'admin/assets/sources-admin.css', array(), NT_CONTENT_IMAGES_VERSION );
		wp_enqueue_script( 'nt-content-images-sources', NT_CONTENT_IMAGES_URL . 'admin/assets/sources-admin.js', array( 'wp-api-fetch' ), NT_CONTENT_IMAGES_VERSION, true );
		wp_localize_script(
			'nt-content-images-sources',
			'NTContentImagesSources',
			array(
				'root'           => '/nt-content-images/v1',
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'generationUrl'  => admin_url( 'admin.php?page=nt-content-images-generation' ),
				'labels'         => array(
					'networkError'   => __( 'Không thể kết nối tới WordPress REST API.', 'nt-tao-anh-noi-dung-wordpress' ),
					'importConfirm'  => __( 'Nhập ảnh này vào Media Library và đưa vào danh sách chờ duyệt?', 'nt-tao-anh-noi-dung-wordpress' ),
					'licenseConfirm' => __( 'Ảnh CC BY/CC BY-SA yêu cầu kiểm tra nguồn và giấy phép trước khi nhập.', 'nt-tao-anh-noi-dung-wordpress' ),
				),
			)
		);
	}

	public function handle_save_settings(): void {
		$this->assert_permission( 'nt_content_images_save_source_settings' );
		$raw = isset( $_POST['source_settings'] ) && is_array( $_POST['source_settings'] ) ? wp_unslash( $_POST['source_settings'] ) : array();
		$this->redirect_result( $this->settings->save( $raw ), 'sources_saved' );
	}

	public function handle_save_affordable_ai_settings(): void {
		$this->assert_permission( 'nt_content_images_save_affordable_ai_settings' );
		$raw = isset( $_POST['generation_settings'] ) && is_array( $_POST['generation_settings'] ) ? wp_unslash( $_POST['generation_settings'] ) : array();
		$this->redirect_result( $this->generation_settings->save( $raw ), 'affordable_ai_saved' );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$config = $this->settings->get_public();
		$generation = $this->generation_settings->get_public();
		$status = isset( $_GET['ntci_status'] ) ? sanitize_key( wp_unslash( $_GET['ntci_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['ntci_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ntci_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap ntci-sources" id="ntci-sources-app">
			<h1><?php echo esc_html__( 'Tìm ảnh miễn phí & tạo ảnh chi phí thấp', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Tìm Pexels hoặc Openverse trước. Khi không có ảnh phù hợp, chuyển sang Cloudflare, fal.ai, OpenRouter hoặc OpenAI.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'Plugin không tự chọn ảnh đầu tiên, không tự dùng giấy phép NC/ND và không tự chuyển sang dịch vụ trả phí.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div>
			<?php if ( $status ) : ?><div class="notice notice-success inline"><p><?php echo esc_html__( 'Đã lưu cấu hình nguồn ảnh.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<section class="ntci-sources-panel">
				<h2><?php echo esc_html__( 'Cấu hình kho ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><strong>Pexels:</strong> <?php echo esc_html( $config['providers']['pexels']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> — <code><?php echo esc_html( $config['providers']['pexels']['key_source'] ); ?></code> &nbsp; <strong>Openverse:</strong> <?php echo esc_html__( 'dùng được không cần token', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_source_settings">
					<?php wp_nonce_field( 'nt_content_images_save_source_settings' ); ?>
					<div class="ntci-sources-form-grid">
						<label><span><?php echo esc_html__( 'Chế độ nguồn ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="source_settings[mode]"><?php foreach ( array( 'stock_first' => 'Tìm ảnh miễn phí trước', 'manual' => 'Chọn thủ công', 'ai_first' => 'AI trước', 'stock_only' => 'Chỉ kho ảnh', 'ai_only' => 'Chỉ AI' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $config['mode'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html__( 'Kho ảnh mặc định', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="source_settings[default_stock_provider]"><option value="pexels" <?php selected( $config['default_stock_provider'], 'pexels' ); ?>>Pexels</option><option value="openverse" <?php selected( $config['default_stock_provider'], 'openverse' ); ?>>Openverse</option></select></label>
						<label><span>Pexels API key</span><input type="password" name="source_settings[pexels_api_key]" autocomplete="new-password" placeholder="Để trống để giữ key" <?php disabled( 'wp-config' === $config['providers']['pexels']['key_source'] ); ?>></label>
						<label><span>Openverse API token</span><input type="password" name="source_settings[openverse_api_key]" autocomplete="new-password" placeholder="Không bắt buộc" <?php disabled( 'wp-config' === $config['providers']['openverse']['key_source'] ); ?>></label>
					</div>
					<p class="description"><?php echo esc_html__( 'Production nên khai báo credential trong wp-config.php.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					<?php submit_button( __( 'Lưu cấu hình nguồn ảnh', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
				</form>
			</section>

			<section class="ntci-sources-panel">
				<h2><?php echo esc_html__( 'AI chi phí thấp', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><strong>Cloudflare:</strong> <?php echo esc_html( $generation['providers']['cloudflare']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> &nbsp; <strong>fal.ai:</strong> <?php echo esc_html( $generation['providers']['fal']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_affordable_ai_settings">
					<?php wp_nonce_field( 'nt_content_images_save_affordable_ai_settings' ); ?>
					<div class="ntci-sources-form-grid">
						<label><span><?php echo esc_html__( 'Provider AI đang sử dụng', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="generation_settings[provider]"><option value="cloudflare" <?php selected( $generation['provider'], 'cloudflare' ); ?>>Cloudflare Workers AI</option><option value="fal" <?php selected( $generation['provider'], 'fal' ); ?>>fal.ai FLUX Schnell</option><option value="openrouter" <?php selected( $generation['provider'], 'openrouter' ); ?>>OpenRouter</option><option value="openai" <?php selected( $generation['provider'], 'openai' ); ?>>OpenAI</option></select></label>
						<label><span>Cloudflare Account ID</span><input type="text" name="generation_settings[cloudflare_account_id]" autocomplete="off" placeholder="Để trống để giữ giá trị" <?php disabled( 'wp-config' === $generation['providers']['cloudflare']['account_source'] ); ?>></label>
						<label><span>Cloudflare Workers AI token</span><input type="password" name="generation_settings[cloudflare_api_key]" autocomplete="new-password" placeholder="Để trống để giữ token" <?php disabled( 'wp-config' === $generation['providers']['cloudflare']['key_source'] ); ?>></label>
						<label><span>fal.ai API key</span><input type="password" name="generation_settings[fal_api_key]" autocomplete="new-password" placeholder="Để trống để giữ key" <?php disabled( 'wp-config' === $generation['providers']['fal']['key_source'] ); ?>></label>
						<label><span><?php echo esc_html__( 'Giới hạn Cloudflare/ngày', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="number" min="1" max="500" name="generation_settings[cloudflare_daily_limit]" value="<?php echo esc_attr( (string) $generation['cloudflare_daily_limit'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Timeout giây', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="number" min="60" max="300" name="generation_settings[timeout]" value="<?php echo esc_attr( (string) $generation['timeout'] ); ?>"></label>
						<input type="hidden" name="generation_settings[openai_model]" value="<?php echo esc_attr( (string) $generation['openai_model'] ); ?>">
						<input type="hidden" name="generation_settings[openrouter_model]" value="<?php echo esc_attr( (string) $generation['openrouter_model'] ); ?>">
						<input type="hidden" name="generation_settings[quality]" value="<?php echo esc_attr( (string) $generation['quality'] ); ?>">
					</div>
					<p class="description"><?php echo esc_html__( 'Plugin không tự chuyển sang fal.ai hoặc provider trả phí khi Cloudflare lỗi hay đạt giới hạn.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					<?php submit_button( __( 'Lưu cấu hình AI giá rẻ', 'nt-tao-anh-noi-dung-wordpress' ), 'secondary' ); ?>
				</form>
			</section>

			<section class="ntci-sources-panel">
				<h2><?php echo esc_html__( 'Tìm ảnh theo nội dung bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<div class="ntci-sources-search-row">
					<label><span><?php echo esc_html__( 'Bài viết', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select id="ntci-source-post"><option value=""><?php echo esc_html__( 'Đang tải bài thiếu ảnh…', 'nt-tao-anh-noi-dung-wordpress' ); ?></option></select></label>
					<label><span><?php echo esc_html__( 'Nguồn', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select id="ntci-stock-provider"><option value="pexels" <?php selected( $config['default_stock_provider'], 'pexels' ); ?>>Pexels</option><option value="openverse" <?php selected( $config['default_stock_provider'], 'openverse' ); ?>>Openverse</option></select></label>
					<label class="ntci-source-query"><span><?php echo esc_html__( 'Từ khóa tùy chỉnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input id="ntci-stock-query" type="text" placeholder="Để trống để plugin tự đề xuất"></label>
					<button type="button" class="button button-primary" id="ntci-stock-search"><?php echo esc_html__( 'Tìm ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></button>
				</div>
				<div id="ntci-sources-feedback" class="ntci-sources-feedback" aria-live="polite"></div>
				<div id="ntci-stock-results" class="ntci-stock-grid"></div>
			</section>

			<section class="ntci-sources-panel">
				<div class="ntci-sources-heading"><h2><?php echo esc_html__( 'Ảnh kho đã nhập', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nt-content-images-generation' ) ); ?>"><?php echo esc_html__( 'Mở danh sách chờ duyệt', 'nt-tao-anh-noi-dung-wordpress' ); ?></a></div>
				<div id="ntci-imported-assets" class="ntci-stock-grid"></div>
			</section>
		</div>
		<?php
	}

	private function assert_permission( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** @param mixed $result */
	private function redirect_result( $result, string $status ): void {
		$args = is_wp_error( $result ) ? array( 'ntci_error' => $result->get_error_message() ) : array( 'ntci_status' => $status );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=nt-content-images-sources' ) ) );
		exit;
	}
}
