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
	private ?NT_Content_Images_Post_Type_Registry $post_types = null;
	private ?NT_Content_Images_Rule_Pack_Registry $rule_packs = null;
	private ?NT_Content_Images_Profile_Repository $profiles = null;
	private ?NT_Content_Images_SEO_Adapter_Manager $seo = null;
	private ?NT_Content_Images_Content_Type_Mapper $content_mapper = null;
	private ?NT_Content_Images_Audit_Repository $audit_repository = null;
	private ?NT_Content_Images_Content_Scanner $audit_scanner = null;
	private ?NT_Content_Images_Audit_Query $audit_query = null;
	private ?NT_Content_Images_Audit_Job_Store $audit_job_store = null;
	private ?NT_Content_Images_Audit_Batch_Runner $audit_runner = null;
	private ?NT_Content_Images_Brief_Repository $brief_repository = null;
	private ?NT_Content_Images_Plan_Settings $plan_settings = null;
	private ?NT_Content_Images_Brief_Generator $brief_generator = null;
	private ?NT_Content_Images_Generation_Settings $generation_settings = null;
	private ?NT_Content_Images_Generation_Repository $generation_repository = null;
	private ?NT_Content_Images_Image_Provider_Manager $provider_manager = null;
	private ?NT_Content_Images_Media_Manager $media_manager = null;
	private ?NT_Content_Images_Media_Cleanup $media_cleanup = null;
	private ?NT_Content_Images_Featured_Image_Generator $featured_generator = null;
	private ?NT_Content_Images_Content_Image_Generator $content_generator = null;
	private ?NT_Content_Images_Content_Inserter $content_inserter = null;
	private ?NT_Content_Images_Generation_Queue $generation_queue = null;
	private ?NT_Content_Images_Template_Registry $template_registry = null;
	private ?NT_Content_Images_Template_Settings $template_settings = null;
	private ?NT_Content_Images_Overlay_Service $overlay_service = null;
	private ?NT_Content_Images_Canva_Settings $canva_settings = null;
	private ?NT_Content_Images_Canva_OAuth $canva_oauth = null;
	private ?NT_Content_Images_Canva_Design_Service $canva_designs = null;
	private ?NT_Content_Images_Source_Settings $source_settings = null;
	private ?NT_Content_Images_Stock_Provider_Manager $stock_provider_manager = null;
	private ?NT_Content_Images_Remote_Image_Downloader $remote_downloader = null;
	private ?NT_Content_Images_Asset_Repository $asset_repository = null;
	private ?NT_Content_Images_Asset_Service $asset_service = null;

	public function run(): void {
		$this->initialize_platform_services();
		$this->initialize_audit_services();
		$this->initialize_brief_services();
		$this->initialize_generation_services();

		$audit_rest = new NT_Content_Images_Audit_REST_Controller( $this->get_audit_runner(), $this->get_audit_repository(), $this->get_audit_query() );
		$brief_rest = new NT_Content_Images_Brief_REST_Controller( $this->get_brief_generator(), $this->get_brief_repository(), $this->get_audit_repository(), $this->get_profile_repository() );
		$generation_rest = new NT_Content_Images_Generation_REST_Controller(
			$this->get_featured_generator(),
			$this->get_generation_repository(),
			$this->get_generation_settings(),
			$this->get_audit_repository(),
			$this->get_profile_repository(),
			$this->get_provider_manager(),
			$this->get_canva_settings(),
			$this->get_canva_designs()
		);
		$source_rest = new NT_Content_Images_Source_REST_Controller( $this->get_asset_service(), $this->get_asset_repository(), $this->get_source_settings(), $this->get_stock_provider_manager() );
		$template_rest = new NT_Content_Images_Template_REST_Controller( $this->get_overlay_service() );
		$content_rest = new NT_Content_Images_Content_REST_Controller( $this->get_content_generator(), $this->get_content_inserter(), $this->get_audit_repository(), $this->get_profile_repository(), $this->get_media_cleanup(), $this->get_plan_settings() );
		$queue_rest = new NT_Content_Images_Queue_REST_Controller( $this->get_generation_queue() );
		$audit_admin = new NT_Content_Images_Audit_Admin( $this->get_audit_query(), $this->get_post_type_registry() );
		$brief_admin = new NT_Content_Images_Brief_Admin( $this->get_plan_settings() );
		$settings_admin = new NT_Content_Images_Settings_Admin( $this->get_profile_repository(), $this->get_post_type_registry(), $this->get_rule_pack_registry() );
		$generation_admin = new NT_Content_Images_Generation_Admin( $this->get_generation_settings(), $this->get_canva_settings(), $this->get_canva_oauth(), $this->get_template_settings(), $this->get_overlay_service(), $this->get_provider_manager() );
		$sources_admin = new NT_Content_Images_Sources_Admin( $this->get_source_settings(), $this->get_generation_settings() );
		$content_admin = new NT_Content_Images_Content_Admin();
		$exporter = new NT_Content_Images_Audit_Exporter( $this->get_audit_repository() );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'rest_api_init', array( $audit_rest, 'register_routes' ) );
		add_action( 'rest_api_init', array( $brief_rest, 'register_routes' ) );
		add_action( 'rest_api_init', array( $generation_rest, 'register_routes' ) );
		add_action( 'rest_api_init', array( $source_rest, 'register_routes' ) );
		add_action( 'rest_api_init', array( $template_rest, 'register_routes' ) );
		add_action( 'rest_api_init', array( $content_rest, 'register_routes' ) );
		add_action( 'rest_api_init', array( $queue_rest, 'register_routes' ) );
		add_action( 'nt_content_images_profile_updated', array( $this, 'handle_profile_updated' ), 10, 2 );

		$audit_admin->register();
		$brief_admin->register();
		$settings_admin->register();
		$generation_admin->register();
		$sources_admin->register();
		$content_admin->register();
		$exporter->register();
		$this->get_asset_service()->register();
		$this->get_overlay_service()->register();
		$this->get_media_cleanup()->register();
		do_action( 'nt_content_images_loaded', $this );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'nt-tao-anh-noi-dung-wordpress', false, dirname( plugin_basename( NT_CONTENT_IMAGES_FILE ) ) . '/languages/' );
	}

	public function maybe_upgrade_database(): void {
		NT_Content_Images_Audit_Migrator::maybe_upgrade();
		NT_Content_Images_Brief_Migrator::maybe_upgrade();
		NT_Content_Images_Generation_Migrator::maybe_upgrade();
		NT_Content_Images_Asset_Migrator::maybe_upgrade();
		update_option( 'nt_content_images_version', NT_CONTENT_IMAGES_VERSION, false );
	}

	public function register_admin_menu(): void {
		add_menu_page( __( 'NT – Tạo ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ), __( 'NT – Tạo ảnh nội dung', 'nt-tao-anh-noi-dung-wordpress' ), 'manage_options', 'nt-content-images', array( $this, 'render_dashboard' ), 'dashicons-format-image', 58 );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$audit_summary = $this->get_audit_repository()->get_summary();
		$brief_summary = $this->get_brief_repository()->get_summary();
		$profile = $this->get_profile_repository()->get_context();
		$generation = $this->get_generation_settings()->get_public();
		$sources = $this->get_source_settings()->get_public();
		$canva = $this->get_canva_settings()->get_public();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'NT – Tạo ảnh cho nội dung WordPress', 'nt-tao-anh-noi-dung-wordpress' ); ?></h1>
			<p><?php echo esc_html__( 'Phân tích nội dung, tìm ảnh miễn phí hoặc tạo ảnh bằng AI, chỉnh sửa bằng Canva và duyệt trước khi sử dụng.', 'nt-tao-anh-noi-dung-wordpress' ); ?></p>
			<p><strong><?php echo esc_html__( 'Website profile:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( (string) $profile['site']['site_name'] ); ?> — <code><?php echo esc_html( (string) $profile['site']['domain'] ); ?></code></p>
			<p><strong><?php echo esc_html__( 'Chế độ nguồn:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <code><?php echo esc_html( (string) $sources['mode'] ); ?></code> — <strong><?php echo esc_html__( 'AI provider:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <code><?php echo esc_html( (string) $generation['provider'] ); ?></code></p>
			<p><strong>Canva:</strong> <?php echo esc_html( $canva['connected'] ? __( 'Đã kết nối', 'nt-tao-anh-noi-dung-wordpress' ) : __( 'Chưa kết nối', 'nt-tao-anh-noi-dung-wordpress' ) ); ?></p>
			<p><strong><?php echo esc_html__( 'Tổng nội dung đã audit:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( number_format_i18n( $audit_summary['total'] ) ); ?></p>
			<p><strong><?php echo esc_html__( 'Tổng kế hoạch hình ảnh:', 'nt-tao-anh-noi-dung-wordpress' ); ?></strong> <?php echo esc_html( number_format_i18n( $brief_summary['total'] ) ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nt-content-images-settings' ) ); ?>"><?php echo esc_html__( 'Cấu hình website', 'nt-tao-anh-noi-dung-wordpress' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nt-content-images-audit' ) ); ?>"><?php echo esc_html__( 'Kiểm tra nội dung', 'nt-tao-anh-noi-dung-wordpress' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nt-content-images-briefs' ) ); ?>"><?php echo esc_html__( 'Kế hoạch hình ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=nt-content-images-sources' ) ); ?>"><?php echo esc_html__( 'Tìm & Tạo ảnh', 'nt-tao-anh-noi-dung-wordpress' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nt-content-images-generation' ) ); ?>"><?php echo esc_html__( 'Ảnh AI & Canva', 'nt-tao-anh-noi-dung-wordpress' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function handle_profile_updated( array $new_context, array $old_context ): void {
		if ( $new_context['profile_hash'] !== $old_context['profile_hash'] || $new_context['brand_hash'] !== $old_context['brand_hash'] ) {
			$this->get_brief_repository()->mark_all_current_outdated();
		}
	}

	public function get_post_type_registry(): NT_Content_Images_Post_Type_Registry { $this->initialize_platform_services(); return $this->post_types; }
	public function get_rule_pack_registry(): NT_Content_Images_Rule_Pack_Registry { $this->initialize_platform_services(); return $this->rule_packs; }
	public function get_profile_repository(): NT_Content_Images_Profile_Repository { $this->initialize_platform_services(); return $this->profiles; }
	public function get_audit_scanner(): NT_Content_Images_Content_Scanner { $this->initialize_audit_services(); return $this->audit_scanner; }
	public function get_audit_repository(): NT_Content_Images_Audit_Repository { $this->initialize_audit_services(); return $this->audit_repository; }
	public function get_audit_query(): NT_Content_Images_Audit_Query { $this->initialize_audit_services(); return $this->audit_query; }
	public function get_audit_runner(): NT_Content_Images_Audit_Batch_Runner { $this->initialize_audit_services(); return $this->audit_runner; }
	public function get_brief_repository(): NT_Content_Images_Brief_Repository { $this->initialize_brief_services(); return $this->brief_repository; }
	public function get_brief_generator(): NT_Content_Images_Brief_Generator { $this->initialize_brief_services(); return $this->brief_generator; }
	public function get_plan_settings(): NT_Content_Images_Plan_Settings { $this->initialize_brief_services(); return $this->plan_settings; }
	public function get_generation_settings(): NT_Content_Images_Generation_Settings { $this->initialize_generation_services(); return $this->generation_settings; }
	public function get_generation_repository(): NT_Content_Images_Generation_Repository { $this->initialize_generation_services(); return $this->generation_repository; }
	public function get_provider_manager(): NT_Content_Images_Image_Provider_Manager { $this->initialize_generation_services(); return $this->provider_manager; }
	public function get_featured_generator(): NT_Content_Images_Featured_Image_Generator { $this->initialize_generation_services(); return $this->featured_generator; }
	public function get_content_generator(): NT_Content_Images_Content_Image_Generator { $this->initialize_generation_services(); return $this->content_generator; }
	public function get_content_inserter(): NT_Content_Images_Content_Inserter { $this->initialize_generation_services(); return $this->content_inserter; }
	public function get_media_cleanup(): NT_Content_Images_Media_Cleanup { $this->initialize_generation_services(); return $this->media_cleanup; }
	public function get_generation_queue(): NT_Content_Images_Generation_Queue { $this->initialize_generation_services(); return $this->generation_queue; }
	public function get_template_registry(): NT_Content_Images_Template_Registry { $this->initialize_generation_services(); return $this->template_registry; }
	public function get_template_settings(): NT_Content_Images_Template_Settings { $this->initialize_generation_services(); return $this->template_settings; }
	public function get_overlay_service(): NT_Content_Images_Overlay_Service { $this->initialize_generation_services(); return $this->overlay_service; }
	public function get_canva_settings(): NT_Content_Images_Canva_Settings { $this->initialize_generation_services(); return $this->canva_settings; }
	public function get_canva_oauth(): NT_Content_Images_Canva_OAuth { $this->initialize_generation_services(); return $this->canva_oauth; }
	public function get_canva_designs(): NT_Content_Images_Canva_Design_Service { $this->initialize_generation_services(); return $this->canva_designs; }
	public function get_source_settings(): NT_Content_Images_Source_Settings { $this->initialize_generation_services(); return $this->source_settings; }
	public function get_stock_provider_manager(): NT_Content_Images_Stock_Provider_Manager { $this->initialize_generation_services(); return $this->stock_provider_manager; }
	public function get_asset_repository(): NT_Content_Images_Asset_Repository { $this->initialize_generation_services(); return $this->asset_repository; }
	public function get_asset_service(): NT_Content_Images_Asset_Service { $this->initialize_generation_services(); return $this->asset_service; }

	private function initialize_platform_services(): void {
		if ( null !== $this->profiles ) { return; }
		$this->post_types = new NT_Content_Images_Post_Type_Registry();
		$this->rule_packs = new NT_Content_Images_Rule_Pack_Registry();
		$this->profiles = new NT_Content_Images_Profile_Repository( $this->post_types, $this->rule_packs, new NT_Content_Images_Profile_Validator() );
		$this->seo = new NT_Content_Images_SEO_Adapter_Manager();
		$this->content_mapper = new NT_Content_Images_Content_Type_Mapper();
	}

	private function initialize_audit_services(): void {
		if ( null !== $this->audit_runner ) { return; }
		$this->initialize_platform_services();
		$this->audit_repository = new NT_Content_Images_Audit_Repository();
		$this->audit_query = new NT_Content_Images_Audit_Query( $this->profiles, $this->post_types );
		$this->audit_job_store = new NT_Content_Images_Audit_Job_Store();
		$this->audit_scanner = new NT_Content_Images_Content_Scanner( new NT_Content_Images_Image_Detector(), new NT_Content_Images_Content_Metrics_Analyzer(), new NT_Content_Images_Priority_Calculator(), $this->audit_repository, $this->seo );
		$this->audit_runner = new NT_Content_Images_Audit_Batch_Runner( $this->audit_query, $this->audit_scanner, $this->audit_repository, $this->audit_job_store );
	}

	private function initialize_brief_services(): void {
		if ( null !== $this->brief_generator ) { return; }
		$this->initialize_audit_services();
		$this->brief_repository = new NT_Content_Images_Brief_Repository();
		$this->plan_settings = new NT_Content_Images_Plan_Settings();
		$this->brief_generator = new NT_Content_Images_Brief_Generator(
			new NT_Content_Images_Brief_Source_Builder( $this->audit_repository, $this->profiles, $this->seo, $this->content_mapper ),
			new NT_Content_Images_Intent_Classifier( $this->rule_packs, $this->profiles ),
			new NT_Content_Images_Visual_Strategy_Resolver(),
			new NT_Content_Images_Placement_Planner( $this->profiles, $this->rule_packs ),
			new NT_Content_Images_Restriction_Builder( $this->rule_packs, $this->profiles ),
			new NT_Content_Images_Brief_Validator(),
			$this->brief_repository,
			$this->profiles,
			$this->plan_settings
		);
	}

	private function initialize_generation_services(): void {
		if ( null !== $this->featured_generator ) { return; }
		$this->initialize_brief_services();
		$this->generation_settings = new NT_Content_Images_Generation_Settings();
		$this->generation_repository = new NT_Content_Images_Generation_Repository();
		$this->media_manager = new NT_Content_Images_Media_Manager();
		$this->media_cleanup = new NT_Content_Images_Media_Cleanup( $this->generation_repository, $this->generation_settings, new NT_Content_Images_Safe_Logger() );
		$this->source_settings = new NT_Content_Images_Source_Settings();
		$this->remote_downloader = new NT_Content_Images_Remote_Image_Downloader();
		$this->provider_manager = new NT_Content_Images_Image_Provider_Manager(
			$this->generation_settings,
			array(
				new NT_Content_Images_OpenAI_Image_Provider( $this->generation_settings ),
				new NT_Content_Images_OpenRouter_Image_Provider( $this->generation_settings ),
				new NT_Content_Images_Cloudflare_Image_Provider( $this->generation_settings ),
				new NT_Content_Images_Fal_Image_Provider( $this->generation_settings, $this->remote_downloader ),
			)
		);
		$this->featured_generator = new NT_Content_Images_Featured_Image_Generator( $this->brief_generator, $this->brief_repository, new NT_Content_Images_Featured_Prompt_Builder( $this->profiles ), $this->provider_manager, $this->media_manager, $this->generation_repository, $this->generation_settings, $this->profiles, new NT_Content_Images_Generation_Lock(), new NT_Content_Images_Safe_Logger() );
		$this->content_generator = new NT_Content_Images_Content_Image_Generator( $this->brief_generator, $this->brief_repository, new NT_Content_Images_Content_Prompt_Builder( $this->profiles ), $this->provider_manager, $this->media_manager, $this->generation_repository, $this->generation_settings, $this->profiles, new NT_Content_Images_Generation_Lock(), new NT_Content_Images_Safe_Logger() );
		$this->content_inserter = new NT_Content_Images_Content_Inserter( $this->generation_repository, new NT_Content_Images_Safe_Logger() );
		$this->generation_queue = new NT_Content_Images_Generation_Queue( $this->featured_generator, $this->content_generator, $this->audit_repository, $this->profiles, $this->generation_settings, $this->generation_repository, $this->content_inserter );
		$this->template_registry = new NT_Content_Images_Template_Registry();
		$this->template_settings = new NT_Content_Images_Template_Settings( $this->template_registry );
		$this->overlay_service = new NT_Content_Images_Overlay_Service(
			$this->template_registry,
			$this->template_settings,
			new NT_Content_Images_Overlay_Renderer(),
			$this->generation_repository,
			$this->media_manager,
			$this->generation_settings,
			$this->profiles
		);
		$this->canva_settings = new NT_Content_Images_Canva_Settings();
		$this->canva_oauth = new NT_Content_Images_Canva_OAuth( $this->canva_settings );
		$this->canva_designs = new NT_Content_Images_Canva_Design_Service( new NT_Content_Images_Canva_Client( $this->canva_oauth ), $this->generation_repository, $this->media_manager, $this->generation_settings );
		$this->stock_provider_manager = new NT_Content_Images_Stock_Provider_Manager(
			array(
				new NT_Content_Images_Pexels_Stock_Provider( $this->source_settings ),
				new NT_Content_Images_Openverse_Stock_Provider( $this->source_settings ),
			)
		);
		$this->asset_repository = new NT_Content_Images_Asset_Repository();
		$this->asset_service = new NT_Content_Images_Asset_Service(
			$this->stock_provider_manager,
			$this->remote_downloader,
			$this->media_manager,
			$this->generation_repository,
			$this->asset_repository,
			$this->generation_settings,
			$this->source_settings,
			$this->brief_repository,
			$this->brief_generator,
			new NT_Content_Images_Stock_Query_Builder(),
			new NT_Content_Images_Generation_Lock()
		);
	}
}
