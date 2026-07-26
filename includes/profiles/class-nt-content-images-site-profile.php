<?php
/**
 * Normalizes the portable site profile used by all plugin modules.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Site_Profile {
	/**
	 * Returns portable defaults for a newly installed website.
	 *
	 * @param string[] $available_post_types Public post types.
	 * @return array<string, mixed>
	 */
	public static function defaults( array $available_post_types = array() ): array {
		$defaults = array_values( array_intersect( array( 'post', 'page' ), $available_post_types ) );

		if ( empty( $defaults ) && ! empty( $available_post_types ) ) {
			$defaults[] = (string) reset( $available_post_types );
		}

		$mapping = array();
		foreach ( $defaults as $post_type ) {
			$mapping[ $post_type ] = 'post' === $post_type ? 'article' : ( 'page' === $post_type ? 'page' : 'generic_content' );
		}

		return array(
			'site_name'             => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'domain'                => sanitize_text_field( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ),
			'language'              => sanitize_text_field( (string) get_locale() ),
			'industries'            => array( 'generic' ),
			'active_rule_packs'     => array( 'generic' ),
			'enabled_post_types'    => $defaults,
			'post_type_mapping'     => $mapping,
			'protected_shortcodes'  => array(),
			'blocked_heading_terms' => array( 'contact', 'register', 'faq', 'conclusion' ),
			'builder_policy'        => 'safe_only',
		);
	}

	/**
	 * Sanitizes one site profile payload.
	 *
	 * @param array<string, mixed> $raw                  Raw profile.
	 * @param string[]             $available_post_types Public post types.
	 * @param string[]             $available_rule_packs Registered rule pack IDs.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw, array $available_post_types, array $available_rule_packs ): array {
		$defaults = self::defaults( $available_post_types );
		$profile  = wp_parse_args( $raw, $defaults );

		$profile['site_name'] = sanitize_text_field( (string) $profile['site_name'] );
		$profile['domain']    = self::sanitize_domain( (string) $profile['domain'] );
		$profile['language']  = sanitize_text_field( (string) $profile['language'] );
		$profile['industries'] = self::sanitize_list( (array) $profile['industries'] );
		if ( empty( $profile['industries'] ) ) {
			$profile['industries'] = array( 'generic' );
		}

		$rule_packs = array_values( array_intersect( self::sanitize_list( (array) $profile['active_rule_packs'] ), $available_rule_packs ) );
		if ( in_array( 'generic', $available_rule_packs, true ) && ! in_array( 'generic', $rule_packs, true ) ) {
			array_unshift( $rule_packs, 'generic' );
		}
		$profile['active_rule_packs'] = array_values( array_unique( $rule_packs ) );

		$enabled = array_values( array_intersect( self::sanitize_list( (array) $profile['enabled_post_types'] ), $available_post_types ) );
		if ( empty( $enabled ) ) {
			$enabled = $defaults['enabled_post_types'];
		}
		$profile['enabled_post_types'] = $enabled;

		$raw_mapping = is_array( $profile['post_type_mapping'] ) ? $profile['post_type_mapping'] : array();
		$mapping     = array();
		foreach ( $enabled as $post_type ) {
			$value = sanitize_key( (string) ( $raw_mapping[ $post_type ] ?? '' ) );
			if ( '' === $value ) {
				$value = 'post' === $post_type ? 'article' : ( 'page' === $post_type ? 'page' : 'generic_content' );
			}
			$mapping[ $post_type ] = $value;
		}
		$profile['post_type_mapping'] = $mapping;

		$profile['protected_shortcodes']  = self::sanitize_shortcodes( (array) $profile['protected_shortcodes'] );
		$profile['blocked_heading_terms'] = self::sanitize_text_list( (array) $profile['blocked_heading_terms'] );
		$profile['builder_policy']        = in_array( (string) $profile['builder_policy'], array( 'safe_only', 'manual_for_builders', 'media_only' ), true ) ? (string) $profile['builder_policy'] : 'safe_only';

		return $profile;
	}

	/**
	 * @param array<int, mixed> $values Raw values.
	 * @return string[]
	 */
	private static function sanitize_list( array $values ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $values ) ) ) ) );
	}

	/**
	 * @param array<int, mixed> $values Raw shortcode tags.
	 * @return string[]
	 */
	private static function sanitize_shortcodes( array $values ): array {
		$tags = array();
		foreach ( $values as $value ) {
			$value = trim( (string) $value, " \t\n\r\0\x0B[]/" );
			$value = sanitize_key( preg_replace( '/\s.*$/', '', $value ) ?? $value );
			if ( '' !== $value ) {
				$tags[] = $value;
			}
		}
		return array_values( array_unique( $tags ) );
	}

	/**
	 * @param array<int, mixed> $values Raw text values.
	 * @return string[]
	 */
	private static function sanitize_text_list( array $values ): array {
		$items = array_map(
			static function ( $value ): string {
				return sanitize_text_field( trim( (string) $value ) );
			},
			$values
		);
		return array_values( array_unique( array_filter( $items ) ) );
	}

	/**
	 * Sanitizes a hostname without accepting a path or credentials.
	 */
	private static function sanitize_domain( string $domain ): string {
		$host = wp_parse_url( false === strpos( $domain, '://' ) ? 'https://' . $domain : $domain, PHP_URL_HOST );
		return sanitize_text_field( strtolower( (string) $host ) );
	}
}
