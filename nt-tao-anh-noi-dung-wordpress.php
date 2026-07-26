<?php
/**
 * Plugin Name:       NT – Tạo ảnh cho nội dung WordPress
 * Plugin URI:        https://nguyentiep.vn
 * Description:       Phân tích nội dung WordPress, tạo ảnh bằng AI, duyệt ảnh và quản lý quy trình hình ảnh an toàn trong WordPress.
 * Version:           0.6.0
 * Author:            Nguyễn Tiệp
 * Author URI:        https://nguyentiep.vn
 * Text Domain:       nt-tao-anh-noi-dung-wordpress
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NT_CONTENT_IMAGES_VERSION', '0.6.0' );
define( 'NT_CONTENT_IMAGES_DB_VERSION', '1.3.0' );
define( 'NT_CONTENT_IMAGES_FILE', __FILE__ );
define( 'NT_CONTENT_IMAGES_PATH', plugin_dir_path( __FILE__ ) );
define( 'NT_CONTENT_IMAGES_URL', plugin_dir_url( __FILE__ ) );

require_once NT_CONTENT_IMAGES_PATH . 'includes/core/class-nt-content-images-post-type-registry.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/core/class-nt-content-images-content-type-mapper.php';

require_once NT_CONTENT_IMAGES_PATH . 'includes/rules/interface-nt-content-images-rule-pack.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/rules/class-nt-content-images-generic-rule-pack.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/rules/class-nt-content-images-education-rule-pack.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/rules/class-nt-content-images-legal-rule-pack.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/rules/class-nt-content-images-procurement-rule-pack.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/rules/class-nt-content-images-rule-pack-registry.php';

require_once NT_CONTENT_IMAGES_PATH . 'includes/profiles/class-nt-content-images-site-profile.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/profiles/class-nt-content-images-brand-profile.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/profiles/class-nt-content-images-profile-validator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/profiles/class-nt-content-images-profile-repository.php';

require_once NT_CONTENT_IMAGES_PATH . 'includes/seo/interface-nt-content-images-seo-adapter.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/seo/class-nt-content-images-wordpress-seo-adapter.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/seo/class-nt-content-images-yoast-seo-adapter.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/seo/class-nt-content-images-rank-math-seo-adapter.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/seo/class-nt-content-images-seo-adapter-manager.php';

require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-migrator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-repository.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-image-detector.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-content-metrics-analyzer.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-priority-calculator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-content-scanner.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-query.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-job-store.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-batch-runner.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-rest-controller.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/audit/class-nt-content-images-audit-exporter.php';

require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-brief-migrator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-brief-repository.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-brief-source-builder.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-intent-classifier.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-visual-strategy-resolver.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-placement-planner.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-restriction-builder.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-brief-validator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-brief-generator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/brief/class-nt-content-images-brief-rest-controller.php';

require_once NT_CONTENT_IMAGES_PATH . 'includes/generation/class-nt-content-images-generation-settings.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/generation/class-nt-content-images-generation-migrator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/generation/class-nt-content-images-generation-repository.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/generation/class-nt-content-images-featured-prompt-builder.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/providers/interface-nt-content-images-image-provider.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/providers/class-nt-content-images-openai-image-provider.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/media/class-nt-content-images-media-manager.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/generation/class-nt-content-images-featured-image-generator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/generation/class-nt-content-images-generation-rest-controller.php';

require_once NT_CONTENT_IMAGES_PATH . 'admin/class-nt-content-images-audit-admin.php';
require_once NT_CONTENT_IMAGES_PATH . 'admin/class-nt-content-images-brief-admin.php';
require_once NT_CONTENT_IMAGES_PATH . 'admin/class-nt-content-images-settings-admin.php';
require_once NT_CONTENT_IMAGES_PATH . 'admin/class-nt-content-images-generation-admin.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/class-nt-content-images-activator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/class-nt-content-images-deactivator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/class-nt-content-images.php';

register_activation_hook( __FILE__, array( 'NT_Content_Images_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NT_Content_Images_Deactivator', 'deactivate' ) );

function nt_content_images_run(): void {
	$plugin = new NT_Content_Images();
	$plugin->run();
}

add_action( 'plugins_loaded', 'nt_content_images_run' );
