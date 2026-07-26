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
		self::assertArrayNotHasKey( 'openrouter_api_key', $result );
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
