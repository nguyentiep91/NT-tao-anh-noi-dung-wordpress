<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CaptionWriterTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options']   = array();
		$GLOBALS['ntci_test_http_post'] = null;
	}

	private function writer(): NT_Content_Images_Caption_Writer {
		update_option( 'nt_content_images_openrouter_api_key', 'sk-or-v1-test-key-abcdefghijk' );
		return new NT_Content_Images_Caption_Writer( new NT_Content_Images_Generation_Settings() );
	}

	public function test_parse_response_handles_plain_json_and_code_fences(): void {
		$plain = NT_Content_Images_Caption_Writer::parse_response( '{"alt":"Chuyên viên rà soát hồ sơ đấu thầu tại văn phòng","caption":"Một bộ hồ sơ chuẩn giúp nhà thầu tự tin hơn khi nộp trên hệ thống."}' );
		self::assertNotNull( $plain );
		self::assertSame( 'Chuyên viên rà soát hồ sơ đấu thầu tại văn phòng', $plain['alt_text'] );
		self::assertSame( $plain['alt_text'], $plain['title'] );

		$fenced = NT_Content_Images_Caption_Writer::parse_response( "```json\n{\"alt\": \"Bàn làm việc với tài liệu\", \"caption\": \"Chuẩn bị kỹ từng bước trước khi dự thầu.\"}\n```" );
		self::assertNotNull( $fenced );
		self::assertSame( 'Bàn làm việc với tài liệu', $fenced['alt_text'] );
	}

	public function test_parse_response_rejects_garbage_and_incomplete_json(): void {
		self::assertNull( NT_Content_Images_Caption_Writer::parse_response( '' ) );
		self::assertNull( NT_Content_Images_Caption_Writer::parse_response( 'Xin lỗi, tôi không thể giúp.' ) );
		self::assertNull( NT_Content_Images_Caption_Writer::parse_response( '{"alt":"chỉ có alt"}' ) );
		self::assertNull( NT_Content_Images_Caption_Writer::parse_response( '{"alt":"","caption":"caption trống alt"}' ) );
	}

	public function test_parse_response_strips_tags_and_clamps_length(): void {
		$long   = str_repeat( 'mô tả rất dài ', 40 );
		$result = NT_Content_Images_Caption_Writer::parse_response( wp_json_encode( array( 'alt' => '<b>Ảnh có tag</b>', 'caption' => $long ) ) );
		self::assertNotNull( $result );
		self::assertStringNotContainsString( '<b>', $result['alt_text'] );
		self::assertLessThanOrEqual( NT_Content_Images_Caption_Writer::CAPTION_MAX, mb_strlen( $result['caption'] ) );
	}

	public function test_write_returns_parsed_meta_from_http_response(): void {
		$writer = $this->writer();
		$GLOBALS['ntci_test_http_post'] = static function ( string $url, array $args ): array {
			$body = json_decode( (string) $args['body'], true );
			// Yêu cầu gửi đi phải chứa model caption và ngữ cảnh bài viết.
			if ( 'google/gemini-2.5-flash-lite' !== ( $body['model'] ?? '' ) || false === strpos( (string) $args['body'], 'quy trình nộp thầu' ) ) {
				return array( 'response' => array( 'code' => 400 ), 'body' => '{}' );
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'choices' => array(
							array( 'message' => array( 'content' => '{"alt":"Kỹ sư kiểm tra hồ sơ trên máy tính","caption":"Từng bước nộp thầu đúng chuẩn giúp tiết kiệm thời gian xử lý."}' ) ),
						),
					)
				),
			);
		};

		$meta = $writer->write( array( 'post_title' => 'Hướng dẫn quy trình nộp thầu', 'heading' => 'Bước chuẩn bị', 'scene' => 'a professional checking documents' ) );
		self::assertNotNull( $meta );
		self::assertSame( 'Kỹ sư kiểm tra hồ sơ trên máy tính', $meta['alt_text'] );
		self::assertSame( 'Từng bước nộp thầu đúng chuẩn giúp tiết kiệm thời gian xử lý.', $meta['caption'] );
	}

	public function test_write_returns_null_on_http_error_and_when_disabled(): void {
		$writer = $this->writer();
		$GLOBALS['ntci_test_http_post'] = static fn (): array => array( 'response' => array( 'code' => 500 ), 'body' => '{"error":{"message":"boom"}}' );
		self::assertNull( $writer->write( array( 'post_title' => 'Bài test' ) ) );

		// Tắt ai_captions → không gọi HTTP.
		( new NT_Content_Images_Generation_Settings() )->save( array( 'ai_captions' => '0' ) );
		$GLOBALS['ntci_test_http_post'] = static function (): array {
			throw new RuntimeException( 'Không được gọi HTTP khi ai_captions tắt.' );
		};
		self::assertNull( $writer->write( array( 'post_title' => 'Bài test' ) ) );
	}

	public function test_write_retries_once_on_transient_429(): void {
		$writer = $this->writer();
		$calls  = 0;
		$GLOBALS['ntci_test_http_post'] = static function () use ( &$calls ): array {
			$calls++;
			if ( 1 === $calls ) {
				return array( 'response' => array( 'code' => 429 ), 'body' => '{"error":{"message":"rate limited"}}' );
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => '{"alt":"Ảnh sau retry","caption":"Caption sau retry thành công."}' ) ) ) ) ),
			);
		};

		$meta = $writer->write( array( 'post_title' => 'Bài test retry' ) );
		self::assertNotNull( $meta );
		self::assertSame( 'Ảnh sau retry', $meta['alt_text'] );
		self::assertSame( 2, $calls );
	}

	public function test_settings_defaults_and_validation(): void {
		$settings = new NT_Content_Images_Generation_Settings();
		self::assertTrue( $settings->get()['ai_captions'] );
		self::assertSame( 'google/gemini-2.5-flash-lite', $settings->get()['caption_model'] );

		$invalid = $settings->save( array( 'caption_model' => 'model sai định dạng' ) );
		self::assertInstanceOf( WP_Error::class, $invalid );
		self::assertSame( 'ntci_generation_caption_model_invalid', $invalid->get_error_code() );

		$settings->save( array( 'caption_model' => 'openai/gpt-5-mini' ) );
		self::assertSame( 'openai/gpt-5-mini', $settings->get()['caption_model'] );
	}
}
