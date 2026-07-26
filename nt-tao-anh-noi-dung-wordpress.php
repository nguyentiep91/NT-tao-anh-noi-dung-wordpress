<?php
/**
 * Plugin Name:       NT – Tạo ảnh cho nội dung WordPress
 * Plugin URI:        https://nguyentiep.vn
 * Description:       Phân tích nội dung bài viết, tạo ảnh bằng AI, chèn chữ và nhận diện thương hiệu, tối ưu ảnh và quản lý quy trình duyệt ảnh trong WordPress.
 * Version:           0.1.0
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

define( 'NT_CONTENT_IMAGES_VERSION', '0.1.0' );
define( 'NT_CONTENT_IMAGES_FILE', __FILE__ );
define( 'NT_CONTENT_IMAGES_PATH', plugin_dir_path( __FILE__ ) );
define( 'NT_CONTENT_IMAGES_URL', plugin_dir_url( __FILE__ ) );

require_once NT_CONTENT_IMAGES_PATH . 'includes/class-nt-content-images-activator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/class-nt-content-images-deactivator.php';
require_once NT_CONTENT_IMAGES_PATH . 'includes/class-nt-content-images.php';

register_activation_hook(
	__FILE__,
	array( 'NT_Content_Images_Activator', 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( 'NT_Content_Images_Deactivator', 'deactivate' )
);

/**
 * Starts the plugin after all active plugins have loaded.
 */
function nt_content_images_run(): void {
	$plugin = new NT_Content_Images();
	$plugin->run();
}

add_action( 'plugins_loaded', 'nt_content_images_run' );
