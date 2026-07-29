<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AffordableSourcesTest extends TestCase {
	private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z4WQAAAAASUVORK5CYII=';

	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
		$GLOBALS['ntci_test_transients'] = array();
		$GLOBALS['ntci_test_http_post'] = null;
		$GLOBALS['ntci_test_http_get'] = null;
	}

	public function test_affordable_credentials_are_not_exposed_publicly(): void {
		$generation = new NT_Content_Images_Generation_Settings();
		$result = $generation->save(
			array(
				'provider' => 'cloudflare',
				'openai_model' => 'gpt-image-1-mini',
				'openrouter_model' => '',
				'quality' => 'medium',
				'timeout' => 120,
				'cloudflare_api_key' => 'cloudflare-secret-token',
				'cloudflare_account_id' => 'account123',
				'fal_api_key' => 'fal-secret-token',
			)
		);
		self::assertIsArray( $result );
		$encoded = json_encode( $result );
		self::assertStringNotContainsString( 'cloudflare-secret-token', (string) $encoded );
		self::assertStringNotContainsString( 'fal-secret-token', (string) $encoded );
		self::assertTrue( $result['providers']['cloudflare']['configured'] );
		self::assertTrue( $result['providers']['fal']['configured'] );

		$sources = new NT_Content_Images_Source_Settings();
		$public = $sources->save(
			array(
				'mode' => 'stock_first',
				'default_stock_provider' => 'pexels',
				'pexels_api_key' => 'pexels-secret-key',
			)
		);
		self::assertIsArray( $public );
		self::assertStringNotContainsString( 'pexels-secret-key', (string) json_encode( $public ) );
	}

	public function test_cloudflare_returns_valid_raster_bytes(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		$settings->save(
			array(
				'provider' => 'cloudflare',
				'openai_model' => 'gpt-image-1-mini',
				'quality' => 'medium',
				'timeout' => 120,
				'cloudflare_api_key' => 'cloudflare-secret-token',
				'cloudflare_account_id' => 'account123',
			)
		);
		$GLOBALS['ntci_test_http_post'] = static function ( string $url, array $args ): array {
			self::assertStringContainsString( '/accounts/account123/ai/run/@cf/black-forest-labs/flux-1-schnell', $url );
			self::assertStringStartsWith( 'Bearer ', $args['headers']['Authorization'] );
			return array(
				'response' => array( 'code' => 200 ),
				'body' => json_encode( array( 'success' => true, 'result' => array( 'image' => self::PNG_BASE64 ) ) ),
			);
		};
		$provider = new NT_Content_Images_Cloudflare_Image_Provider( $settings );
		$result = $provider->generate( array( 'prompt' => str_repeat( 'professional office ', 200 ) ) );
		self::assertIsArray( $result );
		self::assertSame( 'image/png', $result['mime_type'] );
		self::assertSame( 'png', $result['extension'] );
		self::assertSame( 'cloudflare', $result['provider'] );
	}

	public function test_pexels_search_normalizes_attribution_and_quota(): void {
		$sources = new NT_Content_Images_Source_Settings();
		$sources->save( array( 'mode' => 'stock_first', 'default_stock_provider' => 'pexels', 'pexels_api_key' => 'pexels-secret-key' ) );
		$GLOBALS['ntci_test_http_get'] = static function ( string $url, array $args ): array {
			self::assertStringContainsString( 'query=professional', $url );
			self::assertSame( 'pexels-secret-key', $args['headers']['Authorization'] );
			return array(
				'response' => array( 'code' => 200 ),
				'headers' => array( 'X-Ratelimit-Limit' => '200', 'X-Ratelimit-Remaining' => '199', 'X-Ratelimit-Reset' => '12345' ),
				'body' => json_encode(
					array(
						'total_results' => 1,
						'photos' => array(
							array(
								'id' => 42,
								'width' => 1920,
								'height' => 1280,
								'url' => 'https://www.pexels.com/photo/example-42/',
								'photographer' => 'Jane Doe',
								'photographer_url' => 'https://www.pexels.com/@jane',
								'alt' => 'Professional office',
								'src' => array( 'large2x' => 'https://images.pexels.com/photos/42/example.jpeg', 'medium' => 'https://images.pexels.com/photos/42/medium.jpeg' ),
							),
						),
					)
				),
			);
		};
		$provider = new NT_Content_Images_Pexels_Stock_Provider( $sources );
		$result = $provider->search( array( 'query' => 'professional office', 'per_page' => 12, 'page' => 1 ) );
		self::assertIsArray( $result );
		self::assertSame( 'Photo by Jane Doe on Pexels', $result['items'][0]['attribution'] );
		self::assertSame( 199, $result['quota']['remaining'] );
	}

	public function test_openverse_filters_mature_and_non_allowed_licenses(): void {
		$sources = new NT_Content_Images_Source_Settings();
		$GLOBALS['ntci_test_http_get'] = static function (): array {
			return array(
				'response' => array( 'code' => 200 ),
				'body' => json_encode(
					array(
						'result_count' => 3,
						'results' => array(
							array( 'id' => '11111111-1111-1111-1111-111111111111', 'license' => 'cc0', 'url' => 'https://upload.example.org/a.jpg', 'foreign_landing_url' => 'https://source.example.org/a', 'thumbnail' => 'https://thumb.example.org/a.jpg', 'title' => 'Allowed', 'filetype' => 'jpg', 'mature' => false ),
							array( 'id' => '22222222-2222-2222-2222-222222222222', 'license' => 'by-nc', 'url' => 'https://upload.example.org/b.jpg', 'foreign_landing_url' => 'https://source.example.org/b', 'title' => 'Blocked NC', 'filetype' => 'jpg', 'mature' => false ),
							array( 'id' => '33333333-3333-3333-3333-333333333333', 'license' => 'by', 'url' => 'https://upload.example.org/c.jpg', 'foreign_landing_url' => 'https://source.example.org/c', 'title' => 'Mature', 'filetype' => 'jpg', 'mature' => true ),
						),
					)
				),
			);
		};
		$provider = new NT_Content_Images_Openverse_Stock_Provider( $sources );
		$result = $provider->search( array( 'query' => 'professional education', 'per_page' => 12, 'page' => 1 ) );
		self::assertIsArray( $result );
		self::assertCount( 1, $result['items'] );
		self::assertSame( 'cc0', $result['items'][0]['license_code'] );
	}

	public function test_remote_downloader_rejects_untrusted_pexels_host(): void {
		$downloader = new NT_Content_Images_Remote_Image_Downloader();
		$result = $downloader->download( 'https://evil.example/image.jpg', 'pexels' );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_remote_url_rejected', $result->get_error_code() );
	}
}
