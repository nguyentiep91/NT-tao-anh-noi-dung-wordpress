<?php
/**
 * Lightweight WordPress stubs for deterministic unit tests.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WP_DEBUG_LOG', false );

$GLOBALS['ntci_test_options'] = array();
$GLOBALS['ntci_test_transients'] = array();
$GLOBALS['ntci_test_http_post'] = null;
$GLOBALS['ntci_test_http_get'] = null;
$GLOBALS['ntci_test_http_request'] = null;
$GLOBALS['ntci_test_actions'] = array();

final class WP_Error {
	private string $code;
	private string $message;
	private $data;

	public function __construct( string $code = '', string $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}

	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function __( string $text, string $domain = '' ): string { return $text; }
function sanitize_text_field( string $value ): string { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( $value ) ) ?? '' ); }
function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value ) ?? '' ); }
function sanitize_mime_type( string $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9\-\.\+\/]/', '', $value ) ?? '' ); }
function absint( $value ): int { return abs( (int) $value ); }
function esc_url_raw( string $value ): string { return $value; }
function wp_json_encode( $value, int $flags = 0 ) { return json_encode( $value, $flags ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function apply_filters( string $hook, $value, ...$args ) { return $value; }
function do_action( string $hook, ...$args ): void { $GLOBALS['ntci_test_actions'][] = array( $hook, $args ); }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function get_bloginfo( string $show = '' ): string { return 'NT Test'; }
function add_query_arg( array $args, string $url ): string { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }

function get_option( string $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['ntci_test_options'] ) ? $GLOBALS['ntci_test_options'][ $name ] : $default;
}
function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['ntci_test_options'][ $name ] = $value;
	return true;
}
function add_option( string $name, $value, string $deprecated = '', $autoload = 'yes' ): bool {
	if ( array_key_exists( $name, $GLOBALS['ntci_test_options'] ) ) {
		return false;
	}
	$GLOBALS['ntci_test_options'][ $name ] = $value;
	return true;
}
function delete_option( string $name ): bool {
	$exists = array_key_exists( $name, $GLOBALS['ntci_test_options'] );
	unset( $GLOBALS['ntci_test_options'][ $name ] );
	return $exists;
}
function get_transient( string $name ) {
	return $GLOBALS['ntci_test_transients'][ $name ] ?? false;
}
function set_transient( string $name, $value, int $expiration = 0 ): bool {
	$GLOBALS['ntci_test_transients'][ $name ] = $value;
	return true;
}
function delete_transient( string $name ): bool {
	$exists = array_key_exists( $name, $GLOBALS['ntci_test_transients'] );
	unset( $GLOBALS['ntci_test_transients'][ $name ] );
	return $exists;
}

function wp_remote_post( string $url, array $args = array() ) {
	$response = $GLOBALS['ntci_test_http_post'];
	return is_callable( $response ) ? $response( $url, $args ) : $response;
}
function wp_remote_get( string $url, array $args = array() ) {
	$response = $GLOBALS['ntci_test_http_get'];
	return is_callable( $response ) ? $response( $url, $args ) : $response;
}
function wp_remote_request( string $url, array $args = array() ) {
	$response = $GLOBALS['ntci_test_http_request'];
	return is_callable( $response ) ? $response( $url, $args ) : $response;
}
function wp_safe_remote_get( string $url, array $args = array() ) { return wp_remote_get( $url, $args ); }
function wp_remote_retrieve_response_code( $response ): int { return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ): string { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }

require_once dirname( __DIR__ ) . '/includes/security/class-nt-content-images-secret-redactor.php';
require_once dirname( __DIR__ ) . '/includes/logging/class-nt-content-images-safe-logger.php';
require_once dirname( __DIR__ ) . '/includes/generation/class-nt-content-images-generation-settings.php';
require_once dirname( __DIR__ ) . '/includes/generation/class-nt-content-images-generation-lock.php';
require_once dirname( __DIR__ ) . '/includes/providers/interface-nt-content-images-image-provider.php';
require_once dirname( __DIR__ ) . '/includes/providers/class-nt-content-images-openai-image-provider.php';
require_once dirname( __DIR__ ) . '/includes/providers/class-nt-content-images-openrouter-image-provider.php';
require_once dirname( __DIR__ ) . '/includes/providers/class-nt-content-images-image-provider-manager.php';
require_once dirname( __DIR__ ) . '/includes/canva/class-nt-content-images-canva-settings.php';
require_once dirname( __DIR__ ) . '/includes/canva/class-nt-content-images-canva-oauth.php';
