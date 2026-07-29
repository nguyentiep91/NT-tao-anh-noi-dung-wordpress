<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OpenRouterTextModelsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options']    = array();
		$GLOBALS['ntci_test_transients'] = array();
		$GLOBALS['ntci_test_http_get']   = null;
	}

	public function test_normalize_keeps_text_models_and_drops_image_only_and_invalid(): void {
		$rows = array(
			array(
				'id'           => 'google/gemini-2.5-flash-lite',
				'name'         => 'Gemini 2.5 Flash Lite',
				'architecture' => array( 'output_modalities' => array( 'text' ) ),
				'pricing'      => array( 'prompt' => '0.0000001' ),
			),
			array(
				'id'           => 'black-forest-labs/flux.2-flex',
				'name'         => 'FLUX.2 Flex',
				'architecture' => array( 'output_modalities' => array( 'image' ) ),
				'pricing'      => array( 'prompt' => '0.000001' ),
			),
			array(
				'id'           => 'openai/gpt-5.2',
				'name'         => 'GPT-5.2',
				'architecture' => array( 'output_modalities' => array( 'text', 'image' ) ),
				'pricing'      => array( 'prompt' => '0.00000125' ),
			),
			array( 'id' => 'id không hợp lệ', 'architecture' => array( 'output_modalities' => array( 'text' ) ) ),
			'not-an-array',
		);

		$models = NT_Content_Images_OpenRouter_Image_Provider::normalize_text_models( $rows );
		$ids    = array_column( $models, 'id' );

		self::assertContains( 'google/gemini-2.5-flash-lite', $ids );
		self::assertContains( 'openai/gpt-5.2', $ids ); // Xuất cả text lẫn image vẫn dùng được cho caption.
		self::assertNotContains( 'black-forest-labs/flux.2-flex', $ids );
		self::assertSame( 2, count( $models ) );
		// Sắp theo tên: Gemini trước GPT.
		self::assertSame( 'google/gemini-2.5-flash-lite', $models[0]['id'] );
		self::assertSame( '~$0.1/1M', $models[0]['price_label'] );
	}

	public function test_price_label_formats(): void {
		self::assertSame( 'miễn phí', NT_Content_Images_OpenRouter_Image_Provider::format_price_per_million( '0' ) );
		self::assertSame( '~$1.25/1M', NT_Content_Images_OpenRouter_Image_Provider::format_price_per_million( '0.00000125' ) );
		self::assertSame( '~$15/1M', NT_Content_Images_OpenRouter_Image_Provider::format_price_per_million( '0.000015' ) );
		self::assertSame( '', NT_Content_Images_OpenRouter_Image_Provider::format_price_per_million( 'abc' ) );
		self::assertSame( '', NT_Content_Images_OpenRouter_Image_Provider::format_price_per_million( '' ) );
	}

	public function test_list_text_models_fetches_filters_and_caches(): void {
		update_option( 'nt_content_images_openrouter_api_key', 'sk-or-v1-test-key-abcdefghijk' );
		$provider = new NT_Content_Images_OpenRouter_Image_Provider( new NT_Content_Images_Generation_Settings() );
		$calls    = 0;
		$GLOBALS['ntci_test_http_get'] = static function ( string $url ) use ( &$calls ): array {
			$calls++;
			if ( false === strpos( $url, 'openrouter.ai/api/v1/models' ) ) {
				return array( 'response' => array( 'code' => 404 ), 'body' => '{}' );
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'data' => array(
							array( 'id' => 'openai/gpt-5-mini', 'name' => 'GPT-5 Mini', 'architecture' => array( 'output_modalities' => array( 'text' ) ), 'pricing' => array( 'prompt' => '0.00000025' ) ),
						),
					)
				),
			);
		};

		$first = $provider->list_text_models();
		self::assertIsArray( $first );
		self::assertSame( 'openai/gpt-5-mini', $first[0]['id'] );

		$second = $provider->list_text_models();
		self::assertIsArray( $second );
		self::assertSame( 1, $calls, 'Lần hai phải dùng cache transient, không gọi HTTP.' );
	}
}
