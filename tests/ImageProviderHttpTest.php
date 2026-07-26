<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageProviderHttpTest extends TestCase {
	private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z1xkAAAAASUVORK5CYII=';

	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
		$GLOBALS['ntci_test_transients'] = array();
		$GLOBALS['ntci_test_http_post'] = null;
		$GLOBALS['ntci_test_http_get'] = null;
	}

	public function test_openai_accepts_valid_raster_bytes(): void {
		$GLOBALS['ntci_test_options']['nt_content_images_openai_api_key'] = 'sk-test-abcdefghijklmnopqrstuvwxyz';
		$GLOBALS['ntci_test_http_post'] = array(
			'response' => array( 'code' => 200 ),
			'body' => json_encode(
				array(
					'created' => 123,
					'data' => array( array( 'b64_json' => self::PNG_BASE64 ) ),
				)
			),
		);

		$provider = new NT_Content_Images_OpenAI_Image_Provider( new NT_Content_Images_Generation_Settings() );
		$result = $provider->generate( array( 'prompt' => 'Professional editorial image' ) );

		self::assertIsArray( $result );
		self::assertSame( 'image/png', $result['mime_type'] );
		self::assertSame( 'png', $result['extension'] );
		self::assertSame( 'openai', $result['provider'] );
	}

	public function test_openai_redacts_secret_from_http_error(): void {
		$GLOBALS['ntci_test_options']['nt_content_images_openai_api_key'] = 'sk-test-abcdefghijklmnopqrstuvwxyz';
		$GLOBALS['ntci_test_http_post'] = array(
			'response' => array( 'code' => 401 ),
			'body' => json_encode( array( 'error' => array( 'message' => 'Invalid Bearer sk-test-abcdefghijklmnopqrstuvwxyz' ) ) ),
		);

		$provider = new NT_Content_Images_OpenAI_Image_Provider( new NT_Content_Images_Generation_Settings() );
		$result = $provider->generate( array( 'prompt' => 'Professional editorial image' ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertStringNotContainsString( 'abcdefghijklmnopqrstuvwxyz', $result->get_error_message() );
	}

	public function test_openrouter_accepts_png_when_declared_mime_matches(): void {
		$this->configureOpenRouter();
		$GLOBALS['ntci_test_http_post'] = array(
			'response' => array( 'code' => 200 ),
			'body' => json_encode(
				array(
					'data' => array(
						array(
							'b64_json' => self::PNG_BASE64,
							'media_type' => 'image/png',
						),
					),
				)
			),
		);

		$provider = new NT_Content_Images_OpenRouter_Image_Provider( new NT_Content_Images_Generation_Settings() );
		$result = $provider->generate( array( 'prompt' => 'Professional editorial image' ) );

		self::assertIsArray( $result );
		self::assertSame( 'image/png', $result['mime_type'] );
		self::assertSame( 'openrouter', $result['provider'] );
	}

	public function test_openrouter_rejects_declared_mime_mismatch(): void {
		$this->configureOpenRouter();
		$GLOBALS['ntci_test_http_post'] = array(
			'response' => array( 'code' => 200 ),
			'body' => json_encode(
				array(
					'data' => array(
						array(
							'b64_json' => self::PNG_BASE64,
							'media_type' => 'image/jpeg',
						),
					),
				)
			),
		);

		$provider = new NT_Content_Images_OpenRouter_Image_Provider( new NT_Content_Images_Generation_Settings() );
		$result = $provider->generate( array( 'prompt' => 'Professional editorial image' ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_openrouter_mime_invalid', $result->get_error_code() );
	}

	public function test_openrouter_rejects_svg(): void {
		$this->configureOpenRouter();
		$GLOBALS['ntci_test_http_post'] = array(
			'response' => array( 'code' => 200 ),
			'body' => json_encode(
				array(
					'data' => array(
						array(
							'b64_json' => base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"></svg>' ),
							'media_type' => 'image/svg+xml',
						),
					),
				)
			),
		);

		$provider = new NT_Content_Images_OpenRouter_Image_Provider( new NT_Content_Images_Generation_Settings() );
		$result = $provider->generate( array( 'prompt' => 'Professional editorial image' ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_openrouter_vector_not_supported', $result->get_error_code() );
	}

	private function configureOpenRouter(): void {
		$GLOBALS['ntci_test_options']['nt_content_images_generation_settings'] = array(
			'provider' => 'openrouter',
			'openrouter_model' => 'google/gemini-2.5-flash-image',
			'quality' => 'medium',
			'timeout' => 180,
		);
		$GLOBALS['ntci_test_options']['nt_content_images_openrouter_api_key'] = 'sk-or-v1-abcdefghijklmnopqrstuvwxyz';
	}
}
