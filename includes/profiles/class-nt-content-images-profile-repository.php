<?php
/**
 * Persists portable site and brand profiles in WordPress options.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Profile_Repository {
	private const SITE_OPTION = 'nt_content_images_site_profile';
	private const BRAND_OPTION = 'nt_content_images_brand_profile';
	private const VERSION_OPTION = 'nt_content_images_profile_version';

	private NT_Content_Images_Post_Type_Registry $post_types;
	private NT_Content_Images_Rule_Pack_Registry $rule_packs;
	private NT_Content_Images_Profile_Validator $validator;

	public function __construct(
		NT_Content_Images_Post_Type_Registry $post_types,
		NT_Content_Images_Rule_Pack_Registry $rule_packs,
		NT_Content_Images_Profile_Validator $validator
	) {
		$this->post_types = $post_types;
		$this->rule_packs = $rule_packs;
		$this->validator  = $validator;
	}

	/** @return array<string, mixed> */
	public function get_site_profile(): array {
		$raw = get_option( self::SITE_OPTION, array() );
		return NT_Content_Images_Site_Profile::sanitize( is_array( $raw ) ? $raw : array(), $this->post_types->get_slugs(), $this->rule_packs->get_ids() );
	}

	/** @return array<string, mixed> */
	public function get_brand_profile(): array {
		$raw = get_option( self::BRAND_OPTION, array() );
		return NT_Content_Images_Brand_Profile::sanitize( is_array( $raw ) ? $raw : array() );
	}

	public function get_version(): int {
		return max( 1, absint( get_option( self::VERSION_OPTION, 1 ) ) );
	}

	/** @return array<string, mixed> */
	public function get_context(): array {
		$site  = $this->get_site_profile();
		$brand = $this->get_brand_profile();
		return array(
			'version'      => $this->get_version(),
			'profile_hash' => $this->hash( $site ),
			'brand_hash'   => $this->hash( $brand ),
			'site'         => $site,
			'brand'        => $brand,
			'validation'   => $this->validator->validate( $site, $brand ),
		);
	}

	/**
	 * @param array<string, mixed> $site_raw Raw site profile.
	 * @param array<string, mixed> $brand_raw Raw brand profile.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save( array $site_raw, array $brand_raw ) {
		$site       = NT_Content_Images_Site_Profile::sanitize( $site_raw, $this->post_types->get_slugs(), $this->rule_packs->get_ids() );
		$brand      = NT_Content_Images_Brand_Profile::sanitize( $brand_raw );
		$validation = $this->validator->validate( $site, $brand );

		if ( 'invalid' === $validation['status'] ) {
			return new WP_Error( 'ntci_profile_invalid', __( 'Hồ sơ website không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ), $validation );
		}

		$old_context = $this->get_context();
		$new_hash    = $this->hash( $site );
		$new_brand   = $this->hash( $brand );
		$changed     = $old_context['profile_hash'] !== $new_hash || $old_context['brand_hash'] !== $new_brand;

		update_option( self::SITE_OPTION, $site, false );
		update_option( self::BRAND_OPTION, $brand, false );
		if ( $changed ) {
			update_option( self::VERSION_OPTION, $this->get_version() + 1, false );
		}

		$context = $this->get_context();
		if ( $changed ) {
			do_action( 'nt_content_images_profile_updated', $context, $old_context );
		}
		return $context;
	}

	public function export_json(): string {
		$context = $this->get_context();
		$payload = array(
			'schema_version' => '1.0',
			'plugin_version' => NT_CONTENT_IMAGES_VERSION,
			'exported_at'    => current_time( 'mysql', true ),
			'site_profile'   => $context['site'],
			'brand_profile'  => $context['brand'],
		);
		return (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/** @return array<string, mixed>|WP_Error */
	public function import_json( string $json ) {
		if ( strlen( $json ) > 200000 ) {
			return new WP_Error( 'ntci_profile_import_too_large', __( 'Tệp cấu hình vượt quá giới hạn cho phép.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! is_array( $data['site_profile'] ?? null ) || ! is_array( $data['brand_profile'] ?? null ) ) {
			return new WP_Error( 'ntci_profile_import_invalid', __( 'JSON cấu hình không đúng cấu trúc.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return $this->save( $data['site_profile'], $data['brand_profile'] );
	}

	/** @param array<string, mixed> $value Profile value. */
	private function hash( array $value ): string {
		$normalized = $this->canonicalize( $value );
		return hash( 'sha256', (string) wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/** @return mixed */
	private function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_is_list( $value ) ) {
			return array_map( array( $this, 'canonicalize' ), $value );
		}
		ksort( $value );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->canonicalize( $item );
		}
		return $value;
	}
}
