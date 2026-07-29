<?php
/**
 * Removes credentials and tokens from diagnostic payloads.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Secret_Redactor {
	private const REDACTED = '[REDACTED]';

	/**
	 * @param mixed $value Value to sanitize.
	 * @return mixed
	 */
	public static function redact( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$key_string = strtolower( (string) $key );
				if ( self::is_sensitive_key( $key_string ) ) {
					$clean[ $key ] = self::REDACTED;
					continue;
				}
				$clean[ $key ] = self::redact( $item );
			}
			return $clean;
		}

		if ( is_object( $value ) ) {
			return self::redact( get_object_vars( $value ) );
		}

		if ( is_string( $value ) ) {
			return self::redact_string( $value );
		}

		return $value;
	}

	public static function redact_message( string $message ): string {
		return sanitize_text_field( self::redact_string( $message ) );
	}

	private static function is_sensitive_key( string $key ): bool {
		foreach ( array( 'api_key', 'apikey', 'authorization', 'access_token', 'refresh_token', 'client_secret', 'secret', 'password', 'code_verifier' ) as $needle ) {
			if ( str_contains( $key, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function redact_string( string $value ): string {
		$patterns = array(
			'/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i',
			'/\bsk-(?:or-v1-)?[A-Za-z0-9_-]{8,}\b/i',
			'/\b(?:access|refresh)_token["\'\s:=]+[A-Za-z0-9._~+\/-]{8,}/i',
			'/\bclient_secret["\'\s:=]+[^\s,;&]+/i',
		);

		return (string) preg_replace( $patterns, self::REDACTED, $value );
	}
}
