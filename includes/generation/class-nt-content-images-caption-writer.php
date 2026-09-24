<?php
/**
 * Writes natural, SEO-friendly Vietnamese alt text and captions with a cheap
 * OpenRouter text model.
 *
 * Failures never block the image pipeline: every caller keeps its template
 * fallback and simply uses it when write() returns null. Text requests are
 * not counted against the daily image limit.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Caption_Writer {
	private const ENDPOINT    = 'https://openrouter.ai/api/v1/chat/completions';
	public const ALT_MAX      = 160;
	public const CAPTION_MAX  = 220;

	private NT_Content_Images_Generation_Settings $settings;
	private NT_Content_Images_Safe_Logger $logger;

	public function __construct( NT_Content_Images_Generation_Settings $settings, ?NT_Content_Images_Safe_Logger $logger = null ) {
		$this->settings = $settings;
		$this->logger   = $logger ?? new NT_Content_Images_Safe_Logger();
	}

	public function is_enabled(): bool {
		$config = $this->settings->get();
		return ! empty( $config['ai_captions'] )
			&& '' !== (string) ( $config['caption_model'] ?? '' )
			&& '' !== $this->settings->get_api_key( 'openrouter' );
	}

	/**
	 * @param array<string, string> $context post_title, heading (optional), scene (image prompt).
	 * @return array{alt_text: string, caption: string, title: string}|null Null → caller dùng fallback mẫu.
	 */
	public function write( array $context ): ?array {
		if ( ! $this->is_enabled() ) {
			return null;
		}
		$config     = $this->settings->get();
		$key        = $this->settings->get_api_key( 'openrouter' );
		$post_title = sanitize_text_field( (string) ( $context['post_title'] ?? '' ) );
		$heading    = sanitize_text_field( (string) ( $context['heading'] ?? '' ) );
		$scene      = self::clamp( sanitize_textarea_field( (string) ( $context['scene'] ?? '' ) ), 600 );
		if ( '' === $post_title ) {
			return null;
		}

		$user_prompt = 'Bài viết: "' . $post_title . '"';
		if ( '' !== $heading ) {
			$user_prompt .= "\nẢnh minh hoạ cho phần: \"" . $heading . '"';
		}
		if ( '' !== $scene ) {
			$user_prompt .= "\nCảnh trong ảnh (mô tả dùng để tạo ảnh): " . $scene;
		}
		$user_prompt .= "\n\nHãy viết cho ảnh này:\n"
			. '1. "alt": alt text SEO tiếng Việt, tối đa 125 ký tự, mô tả đúng những gì thấy trong ảnh, lồng từ khóa chủ đề một cách tự nhiên, không bắt đầu bằng "Hình ảnh" hay "Ảnh".' . "\n"
			. '2. "caption": chú thích 1 câu tiếng Việt hiển thị dưới ảnh, tự nhiên và thu hút người đọc tiếp tục đọc bài, tối đa 180 ký tự, không dùng cụm "Minh họa cho", không markdown, không emoji, không bịa số liệu hay tên riêng.' . "\n"
			. 'Chỉ trả về JSON đúng dạng {"alt": "...", "caption": "..."}.';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'Bạn là biên tập viên SEO tiếng Việt. Luôn trả về đúng một object JSON hợp lệ, không thêm chữ nào khác.',
			),
			array(
				'role'    => 'user',
				'content' => $user_prompt,
			),
		);
		$base = array(
			'model'       => (string) $config['caption_model'],
			'messages'    => $messages,
			'temperature' => 0.7,
			'max_tokens'  => 300,
		);
		// Một số model không nhận response_format — thử có rồi bỏ, giống provider ảnh.
		$attempts = array(
			array_merge( $base, array( 'response_format' => array( 'type' => 'json_object' ) ) ),
			$base,
		);

		// Lỗi tạm thời (429/5xx/mạng) được thử lại một lần sau 2 giây — đủ vượt
		// qua rate limit thoáng qua khi tạo nhiều ảnh liên tiếp trong batch.
		$content = null;
		for ( $round = 0; $round < 2; $round++ ) {
			if ( $round > 0 ) {
				sleep( 2 );
			}
			$result = $this->request_content( $key, $attempts );
			if ( is_string( $result ) ) {
				$content = $result;
				break;
			}
			if ( 'ntci_caption_transient' !== $result->get_error_code() ) {
				return null; // Lỗi cố định — đã log, dùng fallback mẫu.
			}
		}
		if ( null === $content ) {
			return null; // Hết lượt retry — đã log, dùng fallback mẫu.
		}

		$parsed = self::parse_response( $content );
		if ( null === $parsed ) {
			$this->log_failure( 'parse', self::clamp( $content, 120 ) );
		}
		return $parsed;
	}

	/**
	 * One HTTP round through the attempt bodies.
	 *
	 * @param array<int, array<string, mixed>> $attempts Request bodies (có và không có response_format).
	 * @return string|WP_Error Nội dung trả lời, hoặc lỗi mã ntci_caption_transient / ntci_caption_failed.
	 */
	private function request_content( string $key, array $attempts ) {
		foreach ( $attempts as $body ) {
			$response = wp_remote_post(
				self::ENDPOINT,
				array(
					'timeout'     => 45,
					'redirection' => 0,
					'headers'     => array(
						'Authorization' => 'Bearer ' . $key,
						'Content-Type'  => 'application/json',
						'HTTP-Referer'  => home_url( '/' ),
						'X-Title'       => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
					),
					'body'        => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
					'data_format' => 'body',
				)
			);
			if ( is_wp_error( $response ) ) {
				$this->log_failure( 'transport', NT_Content_Images_Secret_Redactor::redact_message( $response->get_error_message() ) );
				return new WP_Error( 'ntci_caption_transient' );
			}
			$status = wp_remote_retrieve_response_code( $response );
			$data   = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $status >= 200 && $status < 300 ) {
				return (string) ( $data['choices'][0]['message']['content'] ?? '' );
			}
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? $data['message'] ?? '' ) : '';
			if ( 400 === $status && false !== stripos( $message, 'response_format' ) ) {
				continue; // Thử body tiếp theo không kèm response_format.
			}
			$this->log_failure( 'http_' . absint( $status ), NT_Content_Images_Secret_Redactor::redact_message( $message ) );
			return new WP_Error( 429 === $status || $status >= 500 ? 'ntci_caption_transient' : 'ntci_caption_failed' );
		}
		return new WP_Error( 'ntci_caption_failed' );
	}

	/**
	 * Parses the model output into sanitized alt/caption. Pure — unit tested.
	 *
	 * @return array{alt_text: string, caption: string, title: string}|null
	 */
	public static function parse_response( string $content ): ?array {
		$content = trim( $content );
		if ( '' === $content ) {
			return null;
		}
		// Bóc code fence ```json ... ``` nếu model bọc vào.
		$content = (string) preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $content );
		$start   = strpos( $content, '{' );
		$end     = strrpos( $content, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return null;
		}
		$data = json_decode( substr( $content, $start, $end - $start + 1 ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$alt     = sanitize_text_field( (string) ( $data['alt'] ?? $data['alt_text'] ?? '' ) );
		$caption = sanitize_text_field( (string) ( $data['caption'] ?? '' ) );
		if ( '' === $alt || '' === $caption ) {
			return null;
		}
		$alt     = self::clamp( $alt, self::ALT_MAX );
		$caption = self::clamp( $caption, self::CAPTION_MAX );
		return array(
			'alt_text' => $alt,
			'caption'  => $caption,
			'title'    => $alt,
		);
	}

	private static function clamp( string $text, int $max ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return trim( mb_substr( $text, 0, $max, 'UTF-8' ) );
		}
		return trim( substr( $text, 0, $max ) );
	}

	private function log_failure( string $reason, string $detail ): void {
		$this->logger->log(
			'warning',
			'caption_writer_fallback',
			array(
				'reason' => $reason,
				'detail' => $detail,
			)
		);
	}
}
