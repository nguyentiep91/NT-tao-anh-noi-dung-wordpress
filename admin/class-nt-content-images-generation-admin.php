<?php
/**
 * Admin screen for AI generation, provider configuration and Canva Connect.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Generation_Admin {
	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Canva_Settings $canva_settings;
	private NT_Content_Images_Canva_OAuth $canva_oauth;
	private NT_Content_Images_Template_Settings $template_settings;
	private NT_Content_Images_Overlay_Service $overlay_service;
	private NT_Content_Images_Image_Provider_Manager $providers;

	public function __construct(
		NT_Content_Images_Generation_Settings $settings,
		NT_Content_Images_Canva_Settings $canva_settings,
		NT_Content_Images_Canva_OAuth $canva_oauth,
		NT_Content_Images_Template_Settings $template_settings,
		NT_Content_Images_Overlay_Service $overlay_service,
		NT_Content_Images_Image_Provider_Manager $providers
	) {
		$this->settings          = $settings;
		$this->canva_settings    = $canva_settings;
		$this->canva_oauth       = $canva_oauth;
		$this->template_settings = $template_settings;
		$this->overlay_service   = $overlay_service;
		$this->providers         = $providers;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nt_content_images_save_generation_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_nt_content_images_save_template_settings', array( $this, 'handle_save_template_settings' ) );
		add_action( 'admin_post_nt_content_images_save_canva_credentials', array( $this, 'handle_save_canva_credentials' ) );
		add_action( 'admin_post_nt_content_images_canva_connect', array( $this, 'handle_canva_connect' ) );
		add_action( 'admin_post_nt_content_images_canva_callback', array( $this, 'handle_canva_callback' ) );
		add_action( 'admin_post_nt_content_images_canva_disconnect', array( $this, 'handle_canva_disconnect' ) );
	}

	public function register_menu(): void {
		add_submenu_page( 'nt-content-images', __( 'Ảnh AI & Canva', 'nt-tao-anh-noi-dung-wordpress' ), __( 'Ảnh AI & Canva', 'nt-tao-anh-noi-dung-wordpress' ), 'manage_options', 'nt-content-images-generation', array( $this, 'render_page' ) );
	}

	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'nt-content-images-generation' !== $page ) {
			return;
		}
		wp_enqueue_style( 'nt-content-images-generation', NT_CONTENT_IMAGES_URL . 'admin/assets/generation-admin.css', array(), NT_CONTENT_IMAGES_VERSION );
		wp_enqueue_script( 'nt-content-images-generation', NT_CONTENT_IMAGES_URL . 'admin/assets/generation-admin.js', array( 'wp-api-fetch' ), NT_CONTENT_IMAGES_VERSION, true );
		$overlay = $this->overlay_service->get_status();
		wp_localize_script(
			'nt-content-images-generation',
			'NTContentImagesGeneration',
			array(
				'root'           => '/nt-content-images/v1',
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'canvaConnected' => $this->canva_settings->is_connected(),
				'overlay'        => array(
					'ready'           => (bool) $overlay['ready'],
					'templates'       => $overlay['templates'],
					'defaultTemplate' => (string) $overlay['settings']['default_template'],
				),
				'labels'         => array(
					'confirmGenerate' => __( 'Tạo một ảnh AI có thể phát sinh chi phí API. Tiếp tục?', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmApprove'  => __( 'Đặt ảnh này làm ảnh đại diện cho bài viết?', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmCanva'    => __( 'Gửi ảnh này sang tài khoản Canva đã kết nối?', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmImport'   => __( 'Xuất thiết kế Canva hiện tại và nhập lại WordPress dưới dạng ảnh mới chờ duyệt?', 'nt-tao-anh-noi-dung-wordpress' ),
					'confirmOverlay'  => __( 'Chèn chữ theo mẫu đã chọn và lưu thành ảnh mới chờ duyệt?', 'nt-tao-anh-noi-dung-wordpress' ),
					'networkError'    => __( 'Không thể kết nối tới WordPress REST API.', 'nt-tao-anh-noi-dung-wordpress' ),
				)
			)
		);
	}

	public function handle_save_settings(): void {
		$this->assert_permission( 'nt_content_images_save_generation_settings' );
		$raw = isset( $_POST['generation_settings'] ) && is_array( $_POST['generation_settings'] ) ? wp_unslash( $_POST['generation_settings'] ) : array();
		$result = $this->settings->save( $raw );
		$this->redirect_result( $result, 'generation_saved' );
	}

	public function handle_save_template_settings(): void {
		$this->assert_permission( 'nt_content_images_save_template_settings' );
		$raw = isset( $_POST['template_settings'] ) && is_array( $_POST['template_settings'] ) ? wp_unslash( $_POST['template_settings'] ) : array();
		$result = $this->template_settings->save( $raw );
		$this->redirect_result( $result, 'template_saved' );
	}

	public function handle_save_canva_credentials(): void {
		$this->assert_permission( 'nt_content_images_save_canva_credentials' );
		$raw = isset( $_POST['canva_settings'] ) && is_array( $_POST['canva_settings'] ) ? wp_unslash( $_POST['canva_settings'] ) : array();
		$result = $this->canva_settings->save_credentials( $raw );
		$this->redirect_result( $result, 'canva_saved' );
	}

	public function handle_canva_connect(): void {
		$this->assert_permission( 'nt_content_images_canva_connect' );
		$url = $this->canva_oauth->get_authorization_url( get_current_user_id() );
		if ( is_wp_error( $url ) ) {
			$this->redirect_result( $url, 'canva_connected' );
		}
		wp_redirect( esc_url_raw( $url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	public function handle_canva_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Hãy đăng nhập lại WordPress để hoàn tất kết nối Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $error ) {
			$this->redirect_result( new WP_Error( 'ntci_canva_oauth_denied', $error ), 'canva_connected' );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = $this->canva_oauth->handle_callback( $code, $state, get_current_user_id() );
		$this->redirect_result( is_wp_error( $result ) ? $result : array(), 'canva_connected' );
	}

	public function handle_canva_disconnect(): void {
		$this->assert_permission( 'nt_content_images_canva_disconnect' );
		$this->canva_settings->clear_tokens();
		$this->redirect_result( array(), 'canva_disconnected' );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$config = $this->settings->get_public();
		$canva = $this->canva_settings->get_public();
		$overlay = $this->overlay_service->get_status();
		$openrouter_models = array();
		if ( ! empty( $config['providers']['openrouter']['configured'] ) ) {
			$models = $this->providers->list_models( 'openrouter' );
			if ( ! is_wp_error( $models ) ) {
				$openrouter_models = $models;
			}
		}
		$current_openrouter_model = (string) $config['openrouter_model'];
		if ( '' !== $current_openrouter_model && ! in_array( $current_openrouter_model, array_column( $openrouter_models, 'id' ), true ) ) {
			array_unshift( $openrouter_models, array( 'id' => $current_openrouter_model, 'name' => $current_openrouter_model ) );
		}
		$status = isset( $_GET['ntci_status'] ) ? sanitize_key( wp_unslash( $_GET['ntci_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['ntci_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ntci_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$connect_url = wp_nonce_url( admin_url( 'admin-post.php?action=nt_content_images_canva_connect' ), 'nt_content_images_canva_connect' );
		$disconnect_url = wp_nonce_url( admin_url( 'admin-post.php?action=nt_content_images_canva_disconnect' ), 'nt_content_images_canva_disconnect' );
		?>
		<div class="wrap ntci-generation" id="ntci-generation-app">
			<h1><?php echo esc_html__( 'Phân tích nội dung và tạo ảnh bằng AI', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Chọn Cloudflare, fal.ai, OpenRouter hoặc OpenAI. Ảnh được lưu vào Media Library và chỉ trở thành ảnh đại diện sau khi quản trị viên duyệt.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Mỗi lần bấm Tạo ảnh gửi đúng một yêu cầu và có thể phát sinh chi phí. Plugin không tự fallback sang provider trả phí và không ghi đè ảnh đại diện hiện có.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div>
			<?php if ( $status ) : ?><div class="notice notice-success inline"><p><?php echo esc_html__( 'Đã cập nhật cấu hình hoặc trạng thái kết nối.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<section class="ntci-generation-panel">
				<h2><?php echo esc_html__( 'Nhà cung cấp tạo ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p>
					<strong>Cloudflare:</strong> <?php echo esc_html( $config['providers']['cloudflare']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> ·
					<strong>fal.ai:</strong> <?php echo esc_html( $config['providers']['fal']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> ·
					<strong>OpenRouter:</strong> <?php echo esc_html( $config['providers']['openrouter']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> ·
					<strong>OpenAI:</strong> <?php echo esc_html( $config['providers']['openai']['configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_generation_settings">
					<?php wp_nonce_field( 'nt_content_images_save_generation_settings' ); ?>
					<div class="ntci-generation-grid">
						<label><span><?php echo esc_html__( 'Provider đang sử dụng', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select id="ntci-provider" name="generation_settings[provider]"><option value="cloudflare" <?php selected( $config['provider'], 'cloudflare' ); ?>>Cloudflare Workers AI</option><option value="fal" <?php selected( $config['provider'], 'fal' ); ?>>fal.ai FLUX Schnell</option><option value="openrouter" <?php selected( $config['provider'], 'openrouter' ); ?>>OpenRouter Images</option><option value="openai" <?php selected( $config['provider'], 'openai' ); ?>>OpenAI Images</option></select></label>
						<label><span>Cloudflare Account ID</span><input type="text" name="generation_settings[cloudflare_account_id]" autocomplete="off" placeholder="Để trống để giữ giá trị" <?php disabled( 'wp-config' === $config['providers']['cloudflare']['account_source'] ); ?>></label>
						<label><span>Cloudflare Workers AI token</span><input type="password" name="generation_settings[cloudflare_api_key]" autocomplete="new-password" placeholder="Để trống để giữ token" <?php disabled( 'wp-config' === $config['providers']['cloudflare']['key_source'] ); ?>></label>
						<label><span>fal.ai API key</span><input type="password" name="generation_settings[fal_api_key]" autocomplete="new-password" placeholder="Để trống để giữ key" <?php disabled( 'wp-config' === $config['providers']['fal']['key_source'] ); ?>></label>
						<label><span><?php echo esc_html__( 'OpenAI API key', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="password" name="generation_settings[openai_api_key]" autocomplete="new-password" placeholder="Để trống để giữ key" <?php disabled( 'wp-config' === $config['providers']['openai']['key_source'] ); ?>></label>
						<label><span><?php echo esc_html__( 'OpenRouter API key', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="password" name="generation_settings[openrouter_api_key]" autocomplete="new-password" placeholder="Để trống để giữ key" <?php disabled( 'wp-config' === $config['providers']['openrouter']['key_source'] ); ?>></label>
						<label><span><?php echo esc_html__( 'OpenAI model', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="generation_settings[openai_model]"><option value="gpt-image-1-mini" <?php selected( $config['openai_model'], 'gpt-image-1-mini' ); ?>>gpt-image-1-mini</option><option value="gpt-image-1" <?php selected( $config['openai_model'], 'gpt-image-1' ); ?>>gpt-image-1</option></select></label>
						<label><span><?php echo esc_html__( 'OpenRouter image model', 'nt-tao-anh-noi-dung-wordpress' ); ?></span>
							<?php if ( array() !== $openrouter_models ) : ?>
								<select id="ntci-openrouter-model-select" name="generation_settings[openrouter_model]">
									<?php foreach ( $openrouter_models as $model ) : ?>
										<option value="<?php echo esc_attr( (string) $model['id'] ); ?>" <?php selected( $current_openrouter_model, $model['id'] ); ?>><?php echo esc_html( (string) $model['name'] ); ?> — <?php echo esc_html( (string) $model['id'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<small><?php echo esc_html( sprintf( /* translators: %d: model count. */ __( 'Đã tải %d model ảnh từ OpenRouter (cache 15 phút).', 'nt-tao-anh-noi-dung-wordpress' ), count( $openrouter_models ) ) ); ?></small>
							<?php else : ?>
								<input id="ntci-openrouter-model" list="ntci-openrouter-models" type="text" name="generation_settings[openrouter_model]" value="<?php echo esc_attr( $current_openrouter_model ); ?>" placeholder="provider/model"><datalist id="ntci-openrouter-models"></datalist>
								<small><?php echo esc_html__( 'Lưu API key OpenRouter rồi tải lại trang để chọn model từ danh sách.', 'nt-tao-anh-noi-dung-wordpress' ); ?></small>
							<?php endif; ?>
						</label>
						<label><span><?php echo esc_html__( 'Chất lượng OpenAI', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="generation_settings[quality]"><?php foreach ( array( 'low', 'medium', 'high', 'auto' ) as $quality ) : ?><option value="<?php echo esc_attr( $quality ); ?>" <?php selected( $config['quality'], $quality ); ?>><?php echo esc_html( $quality ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html__( 'Giới hạn Cloudflare/ngày', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="number" min="1" max="500" name="generation_settings[cloudflare_daily_limit]" value="<?php echo esc_attr( (string) $config['cloudflare_daily_limit'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Timeout giây', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="number" min="60" max="300" name="generation_settings[timeout]" value="<?php echo esc_attr( (string) $config['timeout'] ); ?>"></label>
					</div>
					<?php if ( 'database' === $config['providers']['cloudflare']['key_source'] ) : ?><label><input type="checkbox" name="generation_settings[clear_cloudflare_api_key]" value="1"> <?php echo esc_html__( 'Xóa Cloudflare token trong database', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><br><?php endif; ?>
					<?php if ( 'database' === $config['providers']['fal']['key_source'] ) : ?><label><input type="checkbox" name="generation_settings[clear_fal_api_key]" value="1"> <?php echo esc_html__( 'Xóa fal.ai key trong database', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><br><?php endif; ?>
					<?php if ( 'database' === $config['providers']['openai']['key_source'] ) : ?><label><input type="checkbox" name="generation_settings[clear_openai_api_key]" value="1"> <?php echo esc_html__( 'Xóa OpenAI key trong database', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><br><?php endif; ?>
					<?php if ( 'database' === $config['providers']['openrouter']['key_source'] ) : ?><label><input type="checkbox" name="generation_settings[clear_openrouter_api_key]" value="1"> <?php echo esc_html__( 'Xóa OpenRouter key trong database', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><?php endif; ?>
					<p class="description"><?php echo esc_html__( 'Production nên dùng credential constants trong wp-config.php. fal.ai hiện polling trong request quản trị; chưa dùng cho batch lớn.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					<?php submit_button( __( 'Lưu cấu hình tạo ảnh', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
				</form>
			</section>

			<section class="ntci-generation-panel">
				<h2><?php echo esc_html__( 'Kết nối tài khoản Canva', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><?php echo esc_html__( 'Canva Connect dùng OAuth 2.0 PKCE. Plugin có thể tải ảnh đã tạo hoặc ảnh kho lên Canva, tạo thiết kế 1280×720 và nhập bản PNG trở lại WordPress.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
				<p><strong><?php echo esc_html__( 'Trạng thái:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( $canva['connected'] ? __( 'Đã kết nối', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'Chưa kết nối', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> — <code><?php echo esc_html( (string) $canva['credential_source'] ); ?></code></p>
				<p><strong><?php echo esc_html__( 'Redirect URI cần đăng ký trong Canva Developer Portal:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong><br><code class="ntci-long-code"><?php echo esc_html( (string) $canva['redirect_uri'] ); ?></code></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_canva_credentials">
					<?php wp_nonce_field( 'nt_content_images_save_canva_credentials' ); ?>
					<div class="ntci-generation-grid">
						<label><span>Canva Client ID</span><input type="text" name="canva_settings[client_id]" autocomplete="off" placeholder="Để trống để giữ giá trị" <?php disabled( 'wp-config' === $canva['credential_source'] ); ?>></label>
						<label><span>Canva Client Secret</span><input type="password" name="canva_settings[client_secret]" autocomplete="new-password" placeholder="Để trống để giữ giá trị" <?php disabled( 'wp-config' === $canva['credential_source'] ); ?>></label>
					</div>
					<?php submit_button( __( 'Lưu thông tin Canva', 'nt-tao-anh-noi-dung-wordpress' ), 'secondary' ); ?>
				</form>
				<p><?php if ( $canva['connected'] ) : ?><a class="button" href="<?php echo esc_url( $disconnect_url ); ?>"><?php echo esc_html__( 'Ngắt kết nối Canva', 'nt-tao-anh-noi-dung-wordpress' ); ?></a><?php elseif ( $canva['credentials_configured'] ) : ?><a class="button button-primary" href="<?php echo esc_url( $connect_url ); ?>"><?php echo esc_html__( 'Kết nối tài khoản Canva', 'nt-tao-anh-noi-dung-wordpress' ); ?></a><?php endif; ?></p>
				<p class="description"><?php echo esc_html__( 'LocalWP thường cần Live Link/tunnel hoặc redirect URI được Canva cho phép. Có thể khai báo NT_CONTENT_IMAGES_CANVA_REDIRECT_URI trong wp-config.php.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			</section>

			<section class="ntci-generation-panel">
				<h2><?php echo esc_html__( 'Chèn chữ và thương hiệu theo mẫu', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><?php echo esc_html__( 'Plugin vẽ tiêu đề, nhãn chuyên mục, logo và tên website lên ảnh nền bằng font Be Vietnam Pro. Chữ luôn được render trong WordPress, không nhờ AI viết chữ, nên tiếng Việt hiển thị chính xác.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
				<p>
					<strong>GD/FreeType:</strong> <?php echo esc_html( $overlay['gd_supported'] ? __( 'sẵn sàng', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa bật trên máy chủ', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> ·
					<strong>Font:</strong> <?php echo esc_html( $overlay['fonts_ready'] ? __( 'đầy đủ', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'thiếu file font', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> ·
					<strong><?php echo esc_html__( 'Chèn chữ trong Brand Profile:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( $overlay['overlay_enabled'] ? __( 'đang bật', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'đang tắt', 'nt-tao-anh-noi-dung-wordpress' ) ); ?> ·
					<strong>Logo:</strong> <?php echo esc_html( $overlay['logo_configured'] ? __( 'đã cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'chưa có', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
				</p>
				<?php if ( ! $overlay['ready'] ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html__( 'Chưa thể chèn chữ. Hãy kiểm tra GD/FreeType, file font trong assets/fonts và bật chèn chữ trong Cấu hình website.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_save_template_settings">
					<?php wp_nonce_field( 'nt_content_images_save_template_settings' ); ?>
					<div class="ntci-generation-grid">
						<label><span><?php echo esc_html__( 'Mẫu cho ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="template_settings[default_template]"><?php foreach ( $overlay['templates'] as $template ) : ?><option value="<?php echo esc_attr( (string) $template['id'] ); ?>" <?php selected( $overlay['settings']['default_template'], $template['id'] ); ?>><?php echo esc_html( (string) $template['label'] ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html__( 'Mẫu cho ảnh trong bài', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="template_settings[content_template]"><?php foreach ( $overlay['templates'] as $template ) : ?><option value="<?php echo esc_attr( (string) $template['id'] ); ?>" <?php selected( $overlay['settings']['content_template'], $template['id'] ); ?>><?php echo esc_html( (string) $template['label'] ); ?></option><?php endforeach; ?></select><small><?php echo esc_html__( 'Chữ trên ảnh trong bài là tên mục (H2) tương ứng, không phải tiêu đề bài.', 'nt-tao-anh-noi-dung-wordpress' ); ?></small></label>
					</div>
					<label><input type="checkbox" name="template_settings[show_category]" value="1" <?php checked( ! empty( $overlay['settings']['show_category'] ) ); ?>> <?php echo esc_html__( 'Hiện nhãn chuyên mục', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><br>
					<label><input type="checkbox" name="template_settings[show_site_name]" value="1" <?php checked( ! empty( $overlay['settings']['show_site_name'] ) ); ?>> <?php echo esc_html__( 'Hiện tên thương hiệu và website', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><br>
					<label><input type="checkbox" name="template_settings[show_logo]" value="1" <?php checked( ! empty( $overlay['settings']['show_logo'] ) ); ?>> <?php echo esc_html__( 'Hiện logo (lấy từ Brand Profile)', 'nt-tao-anh-noi-dung-wordpress' ); ?></label><br>
					<label><input type="checkbox" name="template_settings[auto_overlay]" value="1" <?php checked( ! empty( $overlay['settings']['auto_overlay'] ) ); ?>> <strong><?php echo esc_html__( 'Tự động chèn chữ theo mẫu mặc định ngay sau khi tạo ảnh AI', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> — <?php echo esc_html__( 'bản có chữ xuất hiện cùng bản gốc trong danh sách chờ duyệt.', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					<ul class="ntci-template-list">
						<?php foreach ( $overlay['templates'] as $template ) : ?>
							<li><strong><?php echo esc_html( (string) $template['label'] ); ?></strong> — <?php echo esc_html( (string) $template['description'] ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="description"><?php echo esc_html__( 'Chọn mẫu và bấm Xem thử/Chèn chữ ngay trên từng ảnh trong danh sách “Ảnh đã tạo hoặc nhập” bên dưới. Bản chèn chữ được lưu thành ảnh mới chờ duyệt, không ghi đè ảnh gốc.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
					<?php submit_button( __( 'Lưu cấu hình mẫu chữ', 'nt-tao-anh-noi-dung-wordpress' ), 'secondary' ); ?>
				</form>
			</section>

			<section class="ntci-generation-panel">
				<div class="ntci-generation-heading"><div><h2><?php echo esc_html__( 'Nội dung thiếu ảnh đại diện', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2><p><?php echo esc_html__( 'Danh sách lấy từ kết quả audit, sắp xếp theo mức ưu tiên.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p></div><button type="button" class="button" id="ntci-generation-refresh"><?php echo esc_html__( 'Làm mới', 'nt-tao-anh-noi-dung-wordpress' ); ?></button></div>
				<div id="ntci-generation-feedback" class="ntci-generation-feedback" aria-live="polite"></div>
				<div class="ntci-generation-table-wrap"><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Loại', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Số từ', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Ưu tiên', 'nt-tao-anh-noi-dung-wordpress' ); ?></th><th><?php echo esc_html__( 'Thao tác', 'nt-tao-anh-noi-dung-wordpress' ); ?></th></tr></thead><tbody id="ntci-generation-candidates"><tr><td colspan="5"><?php echo esc_html__( 'Đang tải…', 'nt-tao-anh-noi-dung-wordpress' ); ?></td></tr></tbody></table></div>
			</section>

			<section class="ntci-generation-panel"><h2><?php echo esc_html__( 'Ảnh đã tạo hoặc nhập', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2><div class="ntci-generation-gallery" id="ntci-generation-gallery"></div></section>
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
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=nt-content-images-generation' ) ) );
		exit;
	}
}
