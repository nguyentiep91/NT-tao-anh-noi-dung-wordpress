<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MediaWebpTest extends TestCase {
	private function pngBytes(): string {
		// Hoa văn giả nhiễu như ảnh chụp: PNG nén kém, WebP lossy nén tốt.
		$image = imagecreatetruecolor( 320, 180 );
		for ( $x = 0; $x < 320; $x++ ) {
			for ( $y = 0; $y < 180; $y++ ) {
				$r = ( $x * 13 + $y * 7 + ( $x * $y ) % 31 ) % 256;
				$g = ( $x * 5 + $y * 17 + ( $x ^ $y ) ) % 256;
				$b = ( $x * 3 + $y * 11 + ( $x + $y * 2 ) % 53 ) % 256;
				imagesetpixel( $image, $x, $y, (int) imagecolorallocate( $image, $r, $g, $b ) );
			}
		}
		ob_start();
		imagepng( $image );
		imagedestroy( $image );
		return (string) ob_get_clean();
	}

	public function test_converts_png_bytes_to_smaller_webp(): void {
		if ( ! function_exists( 'imagewebp' ) ) {
			self::markTestSkipped( 'GD WebP không khả dụng.' );
		}
		$png    = $this->pngBytes();
		$result = NT_Content_Images_Media_Manager::convert_bytes_to_webp( $png, 'image/png', 82 );

		self::assertIsArray( $result );
		self::assertSame( 'image/webp', $result['mime'] );
		self::assertNotSame( '', $result['bytes'] );
		$info = getimagesizefromstring( $result['bytes'] );
		self::assertIsArray( $info );
		self::assertSame( 'image/webp', $info['mime'] );
		self::assertSame( 320, $info[0] );
		self::assertLessThan( strlen( $png ), strlen( $result['bytes'] ) );
	}

	public function test_returns_null_for_webp_input_and_garbage(): void {
		self::assertNull( NT_Content_Images_Media_Manager::convert_bytes_to_webp( 'anything', 'image/webp' ) );
		self::assertNull( NT_Content_Images_Media_Manager::convert_bytes_to_webp( '', 'image/png' ) );
		if ( function_exists( 'imagewebp' ) ) {
			self::assertNull( NT_Content_Images_Media_Manager::convert_bytes_to_webp( 'not-an-image', 'image/png' ) );
		}
	}
}
