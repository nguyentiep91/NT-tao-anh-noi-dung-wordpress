<?php
/**
 * Normalizes portable brand settings without coupling them to one website.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Brand_Profile {
	/**
	 * Returns brand defaults derived from native WordPress settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$custom_logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );

		return array(
			'brand_name'         => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'website'            => esc_url_raw( home_url( '/' ) ),
			'logo_attachment_id' => $custom_logo_id,
			'primary_color'      => '#0f172a',
			'secondary_color'    => '#d4a017',
			'accent_color'       => '#ffffff',
			'font_family'        => 'system-ui',
			'template_family'    => 'corporate',
			'logo_required'      => 0 < $custom_logo_id,
			'website_required'   => true,
			'overlay_enabled'    => true,
		);
	}

	/**
	 * Sanitizes one brand profile payload.
	 *
	 * @param array<string, mixed> $raw Raw brand settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$profile = wp_parse_args( $raw, self::defaults() );

		$profile['brand_name']         = sanitize_text_field( (string) $profile['brand_name'] );
		$profile['website']            = esc_url_raw( (string) $profile['website'] );
		$profile['logo_attachment_id'] = absint( $profile['logo_attachment_id'] );
		$profile['primary_color']      = self::sanitize_color( (string) $profile['primary_color'], '#0f172a' );
		$profile['secondary_color']    = self::sanitize_color( (string) $profile['secondary_color'], '#d4a017' );
		$profile['accent_color']       = self::sanitize_color( (string) $profile['accent_color'], '#ffffff' );
		$profile['font_family']        = sanitize_text_field( (string) $profile['font_family'] );
		$profile['template_family']    = sanitize_key( (string) $profile['template_family'] );
		$profile['logo_required']      = ! empty( $profile['logo_required'] );
		$profile['website_required']   = ! empty( $profile['website_required'] );
		$profile['overlay_enabled']    = ! empty( $profile['overlay_enabled'] );

		if ( '' === $profile['brand_name'] ) {
			$profile['brand_name'] = sanitize_text_field( (string) get_bloginfo( 'name' ) );
		}

		if ( '' === $profile['template_family'] ) {
			$profile['template_family'] = 'corporate';
		}

		return $profile;
	}

	/**
	 * Returns a validated hexadecimal color or fallback.
	 */
	private static function sanitize_color( string $color, string $fallback ): string {
		$sanitized = sanitize_hex_color( $color );
		return is_string( $sanitized ) && '' !== $sanitized ? $sanitized : $fallback;
	}
}
