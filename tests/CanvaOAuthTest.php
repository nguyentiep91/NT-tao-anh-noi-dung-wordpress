<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CanvaOAuthTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
		$GLOBALS['ntci_test_transients'] = array();
		$GLOBALS['ntci_test_http_post'] = null;
	}

	public function test_public_settings_never_expose_client_secret_or_tokens(): void {
		$settings = new NT_Content_Images_Canva_Settings();
		$result = $settings->save_credentials(
			array(
				'client_id' => 'canva-client-id',
				'client_secret' => 'canva-client-secret-value',
			)
		);
		$settings->save_tokens(
			array(
				'access_token' => 'access-token-value',
				'refresh_token' => 'refresh-token-value',
				'expires_in' => 3600,
			)
		);

		self::assertIsArray( $result );
		$public = $settings->get_public();
		self::assertTrue( $public['credentials_configured'] );
		self::assertTrue( $public['connected'] );
		self::assertArrayNotHasKey( 'client_secret', $public );
		self::assertArrayNotHasKey( 'access_token', $public );
		self::assertArrayNotHasKey( 'refresh_token', $public );
	}

	public function test_callback_rejects_invalid_state(): void {
		$settings = $this->configuredSettings();
		$oauth = new NT_Content_Images_Canva_OAuth( $settings );
		$result = $oauth->handle_callback( 'authorization-code', 'invalid-state', 7 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_canva_oauth_state_invalid', $result->get_error_code() );
	}

	public function test_authorization_code_flow_saves_tokens(): void {
		$settings = $this->configuredSettings();
		$oauth = new NT_Content_Images_Canva_OAuth( $settings );
		$url = $oauth->get_authorization_url( 7 );
		self::assertIsString( $url );

		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
		$state = (string) ( $query['state'] ?? '' );
		self::assertNotSame( '', $state );

		$GLOBALS['ntci_test_http_post'] = static function ( string $endpoint, array $args ): array {
			self::assertStringContainsString( '/oauth/token', $endpoint );
			self::assertSame( 'authorization_code', $args['body']['grant_type'] );
			return array(
				'response' => array( 'code' => 200 ),
				'body' => json_encode(
					array(
						'access_token' => 'access-token-value',
						'refresh_token' => 'refresh-token-value',
						'expires_in' => 3600,
						'scope' => 'asset:read asset:write',
					)
				),
			);
		};

		$result = $oauth->handle_callback( 'authorization-code', $state, 7 );
		self::assertTrue( $result );
		self::assertTrue( $settings->is_connected() );
	}

	private function configuredSettings(): NT_Content_Images_Canva_Settings {
		$settings = new NT_Content_Images_Canva_Settings();
		$result = $settings->save_credentials(
			array(
				'client_id' => 'canva-client-id',
				'client_secret' => 'canva-client-secret-value',
			)
		);
		self::assertIsArray( $result );
		return $settings;
	}
}
