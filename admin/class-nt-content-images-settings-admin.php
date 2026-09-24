<?php
/**
 * Portable site and brand profile settings screen.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Settings_Admin {
	private NT_Content_Images_Profile_Repository $profiles;
	private NT_Content_Images_Post_Type_Registry $post_types;
	private NT_Content_Images_Rule_Pack_Registry $rule_packs;

	public function __construct(
		NT_Content_Images_Profile_Repository $profiles,
		NT_Content_Images_Post_Type_Registry $post_types,
		NT_Content_Images_Rule_Pack_Registry $rule_packs
	) {
		$this->profiles  = $profiles;
		$this->post_types = $post_types;
		$this->rule_packs = $rule_packs;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nt_content_images_save_profile', array( $this, 'handle_save' ) );
		add_action( 'admin_post_nt_content_images_import_profile', array( $this, 'handle_import' ) );
		add_action( 'admin_post_nt_content_images_export_profile', array( $this, 'handle_export' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'nt-content-images',
			__( 'Cấu hình website', 'nt-tao-anh-noi-dung-wordpress' ),
			__( 'Cấu hình website', 'nt-tao-anh-noi-dung-wordpress' ),
			'manage_options',
			'nt-content-images-settings',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'nt-content-images-settings' !== $page ) {
			return;
		}
		wp_enqueue_style(
			'nt-content-images-settings',
			NT_CONTENT_IMAGES_URL . 'admin/assets/settings-admin.css',
			array(),
			NT_CONTENT_IMAGES_VERSION
		);
	}

	public function handle_save(): void {
		$this->assert_permission( 'nt_content_images_save_profile' );
		$site  = isset( $_POST['site_profile'] ) && is_array( $_POST['site_profile'] ) ? wp_unslash( $_POST['site_profile'] ) : array();
		$brand = isset( $_POST['brand_profile'] ) && is_array( $_POST['brand_profile'] ) ? wp_unslash( $_POST['brand_profile'] ) : array();
		$result = $this->profiles->save( $site, $brand );
		$this->redirect_with_result( $result, 'saved' );
	}

	public function handle_import(): void {
		$this->assert_permission( 'nt_content_images_import_profile' );
		$json   = isset( $_POST['profile_json'] ) ? (string) wp_unslash( $_POST['profile_json'] ) : '';
		$result = $this->profiles->import_json( $json );
		$this->redirect_with_result( $result, 'imported' );
	}

	public function handle_export(): void {
		$this->assert_permission( 'nt_content_images_export_profile' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nt-content-images-profile-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo $this->profiles->export_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$context       = $this->profiles->get_context();
		$site          = $context['site'];
		$brand         = $context['brand'];
		$post_labels   = $this->post_types->get_labels();
		$rule_metadata = $this->rule_packs->get_metadata();
		$status        = isset( $_GET['ntci_status'] ) ? sanitize_key( wp_unslash( $_GET['ntci_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error         = isset( $_GET['ntci_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ntci_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap ntci-settings">
			<h1><?php echo esc_html__( 'Cấu hình website và thương hiệu', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Cấu hình này giúp plugin hoạt động trên nhiều website mà không sửa mã nguồn. Không lưu API key trong hồ sơ export.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<?php if ( $status ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html( 'imported' === $status ? __( 'Đã nhập cấu hình.', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'Đã lưu cấu hình.', 'nt-tao-anh-noi-dung-wordpress' ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="ntci-settings-meta">
				<span><strong><?php echo esc_html__( 'Profile version:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( (string) $context['version'] ); ?></span>
				<span><strong><?php echo esc_html__( 'Profile hash:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <code><?php echo esc_html( substr( (string) $context['profile_hash'], 0, 12 ) ); ?></code></span>
				<span><strong><?php echo esc_html__( 'Validation:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( (string) $context['validation']['status'] ); ?></span>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nt_content_images_save_profile">
				<?php wp_nonce_field( 'nt_content_images_save_profile' ); ?>

				<section class="ntci-settings-panel">
					<h2><?php echo esc_html__( 'Site Profile', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
					<div class="ntci-settings-grid">
						<label><span><?php echo esc_html__( 'Tên website', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="site_profile[site_name]" value="<?php echo esc_attr( (string) $site['site_name'] ); ?>" required></label>
						<label><span><?php echo esc_html__( 'Tên miền', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="site_profile[domain]" value="<?php echo esc_attr( (string) $site['domain'] ); ?>" required></label>
						<label><span><?php echo esc_html__( 'Ngôn ngữ/locale', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="site_profile[language]" value="<?php echo esc_attr( (string) $site['language'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Lĩnh vực, cách nhau bằng dấu phẩy', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="site_profile[industries_text]" value="<?php echo esc_attr( implode( ', ', (array) $site['industries'] ) ); ?>"></label>
					</div>

					<h3><?php echo esc_html__( 'Post type được phép xử lý', 'nt-tao-anh-noi-dung-wordpress' ); ?></h3>
					<div class="ntci-check-grid">
						<?php foreach ( $post_labels as $slug => $label ) : ?>
							<label><input type="checkbox" name="site_profile[enabled_post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $site['enabled_post_types'], true ) ); ?>> <?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></label>
						<?php endforeach; ?>
					</div>

					<h3><?php echo esc_html__( 'Ánh xạ loại nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></h3>
					<div class="ntci-settings-grid">
						<?php foreach ( $post_labels as $slug => $label ) : ?>
							<label><span><?php echo esc_html( $label . ' (' . $slug . ')' ); ?></span><input type="text" name="site_profile[post_type_mapping][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( (string) ( $site['post_type_mapping'][ $slug ] ?? '' ) ); ?>" placeholder="generic_content"></label>
						<?php endforeach; ?>
					</div>

					<h3><?php echo esc_html__( 'Rule Pack đang bật', 'nt-tao-anh-noi-dung-wordpress' ); ?></h3>
					<div class="ntci-check-grid">
						<?php foreach ( $rule_metadata as $pack ) : ?>
							<label><input type="checkbox" name="site_profile[active_rule_packs][]" value="<?php echo esc_attr( $pack['id'] ); ?>" <?php checked( in_array( $pack['id'], (array) $site['active_rule_packs'], true ) ); ?> <?php disabled( 'generic' === $pack['id'] ); ?>> <?php echo esc_html( $pack['label'] ); ?> <code><?php echo esc_html( $pack['id'] ); ?></code><?php if ( 'generic' === $pack['id'] ) : ?><input type="hidden" name="site_profile[active_rule_packs][]" value="generic"><?php endif; ?></label>
						<?php endforeach; ?>
					</div>

					<div class="ntci-settings-grid">
						<label><span><?php echo esc_html__( 'Shortcode cần bảo vệ, mỗi dòng một tag', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><textarea name="site_profile[protected_shortcodes_text]" rows="7"><?php echo esc_textarea( implode( "\n", (array) $site['protected_shortcodes'] ) ); ?></textarea></label>
						<label><span><?php echo esc_html__( 'Heading không được chọn làm anchor, mỗi dòng một cụm từ', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><textarea name="site_profile[blocked_heading_terms_text]" rows="7"><?php echo esc_textarea( implode( "\n", (array) $site['blocked_heading_terms'] ) ); ?></textarea></label>
						<label><span><?php echo esc_html__( 'Chính sách page builder', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><select name="site_profile[builder_policy]"><option value="safe_only" <?php selected( $site['builder_policy'], 'safe_only' ); ?>>safe_only</option><option value="manual_for_builders" <?php selected( $site['builder_policy'], 'manual_for_builders' ); ?>>manual_for_builders</option><option value="media_only" <?php selected( $site['builder_policy'], 'media_only' ); ?>>media_only</option></select></label>
					</div>
				</section>

				<section class="ntci-settings-panel">
					<h2><?php echo esc_html__( 'Brand Profile', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
					<div class="ntci-settings-grid">
						<label><span><?php echo esc_html__( 'Tên thương hiệu', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="brand_profile[brand_name]" value="<?php echo esc_attr( (string) $brand['brand_name'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Website thương hiệu', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="url" name="brand_profile[website]" value="<?php echo esc_attr( (string) $brand['website'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'ID logo trong Media Library', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="number" min="0" name="brand_profile[logo_attachment_id]" value="<?php echo esc_attr( (string) $brand['logo_attachment_id'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Template family', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="brand_profile[template_family]" value="<?php echo esc_attr( (string) $brand['template_family'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Màu chính', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="color" name="brand_profile[primary_color]" value="<?php echo esc_attr( (string) $brand['primary_color'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Màu phụ', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="color" name="brand_profile[secondary_color]" value="<?php echo esc_attr( (string) $brand['secondary_color'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Màu nhấn', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="color" name="brand_profile[accent_color]" value="<?php echo esc_attr( (string) $brand['accent_color'] ); ?>"></label>
						<label><span><?php echo esc_html__( 'Font family', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><input type="text" name="brand_profile[font_family]" value="<?php echo esc_attr( (string) $brand['font_family'] ); ?>"></label>
					</div>
					<div class="ntci-check-grid">
						<label><input type="checkbox" name="brand_profile[logo_required]" value="1" <?php checked( ! empty( $brand['logo_required'] ) ); ?>> <?php echo esc_html__( 'Render logo bằng plugin', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
						<label><input type="checkbox" name="brand_profile[website_required]" value="1" <?php checked( ! empty( $brand['website_required'] ) ); ?>> <?php echo esc_html__( 'Render website bằng plugin', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
						<label><input type="checkbox" name="brand_profile[overlay_enabled]" value="1" <?php checked( ! empty( $brand['overlay_enabled'] ) ); ?>> <?php echo esc_html__( 'Bật lớp chữ thương hiệu', 'nt-tao-anh-noi-dung-wordpress' ); ?></label>
					</div>
				</section>

				<?php submit_button( __( 'Lưu cấu hình', 'nt-tao-anh-noi-dung-wordpress' ) ); ?>
			</form>

			<section class="ntci-settings-panel">
				<h2><?php echo esc_html__( 'Import / Export', 'nt-tao-anh-noi-dung-wordpress' ); ?></h2>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nt_content_images_export_profile' ), 'nt_content_images_export_profile' ) ); ?>"><?php echo esc_html__( 'Tải profile JSON', 'nt-tao-anh-noi-dung-wordpress' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nt_content_images_import_profile">
					<?php wp_nonce_field( 'nt_content_images_import_profile' ); ?>
					<label class="ntci-json-import"><span><?php echo esc_html__( 'Dán JSON cấu hình', 'nt-tao-anh-noi-dung-wordpress' ); ?></span><textarea name="profile_json" rows="12" required></textarea></label>
					<?php submit_button( __( 'Nhập cấu hình', 'nt-tao-anh-noi-dung-wordpress' ), 'secondary' ); ?>
				</form>
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

	/** @param array<string, mixed>|WP_Error $result Save result. */
	private function redirect_with_result( $result, string $success ): void {
		$url = admin_url( 'admin.php?page=nt-content-images-settings' );
		if ( is_wp_error( $result ) ) {
			$url = add_query_arg( 'ntci_error', rawurlencode( $result->get_error_message() ), $url );
		} else {
			$url = add_query_arg( 'ntci_status', $success, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
