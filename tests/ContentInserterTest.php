<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContentInserterTest extends TestCase {
	private const FIGURE = "\n[FIGURE]\n";

	private function blockContent(): string {
		return implode(
			"\n",
			array(
				'<!-- wp:paragraph --><p>Đoạn mở đầu thứ nhất.</p><!-- /wp:paragraph -->',
				'<!-- wp:paragraph --><p>Đoạn thứ hai nói về bối cảnh.</p><!-- /wp:paragraph -->',
				'<!-- wp:paragraph --><p>Đoạn thứ ba kết thúc phần mở đầu.</p><!-- /wp:paragraph -->',
				'<!-- wp:heading --><h2 class="wp-block-heading">Hồ sơ cần chuẩn bị</h2><!-- /wp:heading -->',
				'<!-- wp:paragraph --><p>Nội dung mục một.</p><!-- /wp:paragraph -->',
				'<!-- wp:heading --><h2 class="wp-block-heading">Trình tự thực hiện</h2><!-- /wp:heading -->',
				'<!-- wp:paragraph --><p>Nội dung mục hai.</p><!-- /wp:paragraph -->',
			)
		);
	}

	public function test_block_content_detection(): void {
		self::assertTrue( NT_Content_Images_Content_Inserter::is_block_content( $this->blockContent() ) );
		self::assertFalse( NT_Content_Images_Content_Inserter::is_block_content( '<p>Classic content</p>' ) );
	}

	public function test_inserts_after_third_paragraph_in_block_content(): void {
		$result = NT_Content_Images_Content_Inserter::inject_placement(
			$this->blockContent(),
			array( 'type' => 'after_intro', 'paragraph_index' => 3 ),
			self::FIGURE
		);

		self::assertIsString( $result );
		$figure_pos  = strpos( $result, '[FIGURE]' );
		$third_close = strpos( $result, 'kết thúc phần mở đầu' );
		$heading_pos = strpos( $result, 'Hồ sơ cần chuẩn bị' );
		self::assertGreaterThan( $third_close, $figure_pos, 'Ảnh phải nằm sau đoạn mở đầu thứ ba.' );
		self::assertLessThan( $heading_pos, $figure_pos, 'Ảnh phải nằm trước heading đầu tiên.' );
	}

	public function test_inserts_before_matching_vietnamese_heading(): void {
		$result = NT_Content_Images_Content_Inserter::inject_placement(
			$this->blockContent(),
			array( 'type' => 'before_heading', 'heading_text' => 'Trình tự thực hiện' ),
			self::FIGURE
		);

		self::assertIsString( $result );
		$figure_pos  = strpos( $result, '[FIGURE]' );
		$heading_pos = strpos( $result, '<!-- wp:heading --><h2 class="wp-block-heading">Trình tự thực hiện' );
		self::assertLessThan( $heading_pos, $figure_pos );
		$first_section = strpos( $result, 'Nội dung mục một' );
		self::assertGreaterThan( $first_section, $figure_pos, 'Ảnh phải nằm sau nội dung mục một.' );
	}

	public function test_classic_content_paragraph_and_heading_anchors(): void {
		$classic = '<p>Một.</p><p>Hai.</p><p>Ba.</p><h2>Lưu ý quan trọng</h2><p>Bốn.</p>';

		$after_intro = NT_Content_Images_Content_Inserter::inject_placement( $classic, array( 'type' => 'after_intro', 'paragraph_index' => 3 ), self::FIGURE );
		self::assertIsString( $after_intro );
		self::assertLessThan( strpos( $after_intro, '<h2>' ), strpos( $after_intro, '[FIGURE]' ) );
		self::assertGreaterThan( strpos( $after_intro, 'Ba.' ), strpos( $after_intro, '[FIGURE]' ) );

		$before_heading = NT_Content_Images_Content_Inserter::inject_placement( $classic, array( 'type' => 'before_heading', 'heading_text' => 'Lưu ý quan trọng' ), self::FIGURE );
		self::assertIsString( $before_heading );
		self::assertLessThan( strpos( $before_heading, '<h2>' ), strpos( $before_heading, '[FIGURE]' ) );
	}

	public function test_falls_back_to_last_paragraph_when_intro_is_short(): void {
		$short  = '<!-- wp:paragraph --><p>Chỉ một đoạn.</p><!-- /wp:paragraph -->';
		$result = NT_Content_Images_Content_Inserter::inject_placement( $short, array( 'type' => 'after_intro', 'paragraph_index' => 3 ), self::FIGURE );

		self::assertIsString( $result );
		self::assertStringContainsString( '[FIGURE]', $result );
	}

	public function test_returns_null_when_anchor_is_missing(): void {
		self::assertNull(
			NT_Content_Images_Content_Inserter::inject_placement(
				$this->blockContent(),
				array( 'type' => 'before_heading', 'heading_text' => 'Mục không tồn tại' ),
				self::FIGURE
			)
		);
		self::assertNull(
			NT_Content_Images_Content_Inserter::inject_placement( 'Nội dung không có đoạn văn nào.', array( 'type' => 'after_intro', 'paragraph_index' => 3 ), self::FIGURE )
		);
		self::assertNull(
			NT_Content_Images_Content_Inserter::inject_placement( $this->blockContent(), array( 'type' => 'manual_review_required' ), self::FIGURE )
		);
	}
}
