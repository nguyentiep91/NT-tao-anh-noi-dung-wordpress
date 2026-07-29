<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MediaCleanupTest extends TestCase {
	public function test_needles_match_plugin_figure_markup(): void {
		$needles = NT_Content_Images_Media_Cleanup::get_reference_needles(
			18,
			array( '/var/www/uploads/2026/07/ho-so-openrouter.webp', 'ho-so-openrouter-1024x576.webp' )
		);

		// Đúng markup mà Content Inserter sinh ra.
		$figure = '<!-- wp:image {"id":18,"sizeSlug":"large"} --><figure><img class="wp-image-18"/></figure><!-- /wp:image -->';
		$hits   = array_filter( $needles, static fn ( string $needle ): bool => str_contains( $figure, $needle ) );
		self::assertNotEmpty( $hits );

		// Biến thể resize được nhận diện qua tên file trong metadata.
		$resized = '<img src="https://example.test/uploads/ho-so-openrouter-1024x576.webp">';
		$hits    = array_filter( $needles, static fn ( string $needle ): bool => str_contains( $resized, $needle ) );
		self::assertNotEmpty( $hits );
	}

	public function test_needles_do_not_match_other_attachment_ids(): void {
		$needles = NT_Content_Images_Media_Cleanup::get_reference_needles( 18, array() );
		$other   = '<!-- wp:image {"id":181,"sizeSlug":"large"} --><figure><img class="wp-image-181"/></figure>';
		$hits    = array_filter( $needles, static fn ( string $needle ): bool => str_contains( $other, $needle ) );
		self::assertSame( array(), array_values( $hits ) );
	}

	public function test_needles_do_not_match_sibling_files_with_same_prefix(): void {
		// ten-file.webp không được khớp với ten-file-1.webp (file anh em do wp_unique_filename).
		$needles = NT_Content_Images_Media_Cleanup::get_reference_needles( 7, array( '/uploads/thong-bao-moi-thau-template.webp' ) );
		$sibling = '<img src="https://example.test/uploads/thong-bao-moi-thau-template-1.webp" class="wp-image-8">';
		$hits    = array_filter( $needles, static fn ( string $needle ): bool => str_contains( $sibling, $needle ) );
		self::assertSame( array(), array_values( $hits ) );
	}

	public function test_needles_skip_empty_and_duplicate_filenames(): void {
		$needles = NT_Content_Images_Media_Cleanup::get_reference_needles( 7, array( '', 'a.webp', 'a.webp' ) );
		self::assertSame( 1, count( array_filter( $needles, static fn ( string $needle ): bool => 'a.webp' === $needle ) ) );
	}
}
