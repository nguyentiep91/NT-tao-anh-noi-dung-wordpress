<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GenerationSettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
	}

	public function test_defaults_to_openai_without_exposing_key(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		$config = $settings->get_public();

		self::assertSame( 'openai', $config['provider'] );
		self::assertSame( 'gpt-image-1-mini', $config['model'] );
		self::assertFalse( $config['configured'] );
		self::assertArrayNotHasKey( 'api_key', $config );
	}

	public function test_saves_openrouter_provider_model_and_key_safely(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		$result = $settings->save(
			array(
				'provider' => 'openrouter',
				'openai_model' => 'gpt-image-1-mini',
				'openrouter_model' => 'google/gemini-2.5-flash-image',
				'quality' => 'medium',
				'timeout' => 180,
				'openrouter_api_key' => 'sk-or-v1-abcdefghijklmnopqrstuvwxyz',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'openrouter', $result['provider'] );
		self::assertSame( 'google/gemini-2.5-flash-image', $result['model'] );
		self::assertTrue( $result['configured'] );
		self::assertTrue( $result['ready'] );
		self::assertArrayNotHasKey( 'openrouter_api_key', $result );
	}

	public function test_rejects_cloudflare_global_api_key_with_clear_message(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		$result = $settings->save(
			array(
				'provider'           => 'cloudflare',
				'cloudflare_api_key' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a', // 37 hex chars = Global API Key format.
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_generation_cloudflare_global_key', $result->get_error_code() );
		self::assertSame( '', get_option( 'nt_content_images_cloudflare_api_token', '' ) );
	}

	public function test_saves_openrouter_key_before_a_model_is_selected(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		$result = $settings->save(
			array(
				'provider' => 'openrouter',
				'openrouter_model' => '',
				'openrouter_api_key' => 'sk-or-v1-first-time-configuration-key',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'openrouter', $result['provider'] );
		self::assertTrue( $result['configured'] );
		self::assertFalse( $result['providers']['openrouter']['model_configured'] );
		self::assertFalse( $result['ready'] );
		self::assertSame( 'database', $result['providers']['openrouter']['key_source'] );
	}

	public function test_rejects_invalid_openrouter_model(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		$result = $settings->save(
			array(
				'provider' => 'openrouter',
				'openrouter_model' => 'invalid model with spaces',
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_generation_openrouter_model_missing', $result->get_error_code() );
	}
}
