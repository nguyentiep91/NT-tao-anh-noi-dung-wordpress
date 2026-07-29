<?php
/**
 * Draws titles, category badges and brand marks on generated images with GD.
 *
 * The renderer never asks AI to draw typography: text is composed server-side
 * with licensed fonts so Vietnamese diacritics stay correct and crisp.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Overlay_Renderer {
	private const NEUTRAL_DARK = array( 11, 18, 32 );

	public static function is_supported(): bool {
		return function_exists( 'imagecreatetruecolor' )
			&& function_exists( 'imagettftext' )
			&& function_exists( 'imagettfbbox' )
			&& ( function_exists( 'imagewebp' ) || function_exists( 'imagepng' ) );
	}

	/** @return array{ready: bool, missing: array<int, string>, files: array<string, string>} */
	public function get_font_status(): array {
		$fonts   = $this->get_fonts();
		$missing = array();
		foreach ( $fonts as $weight => $path ) {
			if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
				$missing[] = $weight;
			}
		}
		return array(
			'ready'   => array() === $missing,
			'missing' => $missing,
			'files'   => $fonts,
		);
	}

	/**
	 * Renders one overlay composition onto a copy of the source image.
	 *
	 * @param string               $source_file Absolute path of the background image.
	 * @param array<string, mixed> $template    Template definition from the registry.
	 * @param array<string, mixed> $data        Overlay content: title, badge, brand_name, website_host, logo_file, colors, target sizes.
	 * @return array{bytes: string, mime: string, width: int, height: int}|WP_Error
	 */
	public function render( string $source_file, array $template, array $data ) {
		if ( ! self::is_supported() ) {
			return new WP_Error( 'ntci_overlay_gd_missing', __( 'Máy chủ chưa bật PHP GD với FreeType nên không thể chèn chữ lên ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$font_status = $this->get_font_status();
		if ( ! $font_status['ready'] ) {
			return new WP_Error( 'ntci_overlay_fonts_missing', __( 'Thiếu file font trong assets/fonts. Hãy kiểm tra lại bản cài plugin.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( '' === $source_file || ! file_exists( $source_file ) || ! is_readable( $source_file ) ) {
			return new WP_Error( 'ntci_overlay_source_missing', __( 'Không tìm thấy file ảnh gốc để chèn chữ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$width  = max( 320, absint( $data['target_width'] ?? 1280 ) );
		$height = max( 180, absint( $data['target_height'] ?? 720 ) );
		$canvas = $this->create_canvas( $source_file, $width, $height );
		if ( is_wp_error( $canvas ) ) {
			return $canvas;
		}

		$layout  = sanitize_key( (string) ( $template['layout'] ?? 'bottom_gradient' ) );
		$content = $this->normalize_content( $data );
		switch ( $layout ) {
			case 'left_panel':
				$this->draw_left_panel( $canvas, $width, $height, $content );
				break;
			case 'top_band':
				$this->draw_top_band( $canvas, $width, $height, $content );
				break;
			case 'center_box':
				$this->draw_center_box( $canvas, $width, $height, $content );
				break;
			case 'minimal_badge':
				$this->draw_minimal_badge( $canvas, $width, $height, $content );
				break;
			case 'top_gradient':
				$this->draw_top_gradient( $canvas, $width, $height, $content );
				break;
			case 'right_panel':
				$this->draw_right_panel( $canvas, $width, $height, $content );
				break;
			case 'bottom_bar':
				$this->draw_bottom_bar( $canvas, $width, $height, $content );
				break;
			case 'corner_card':
				$this->draw_corner_card( $canvas, $width, $height, $content );
				break;
			case 'bottom_gradient':
			default:
				$this->draw_bottom_gradient( $canvas, $width, $height, $content );
				break;
		}

		$bytes = $this->encode( $canvas );
		imagedestroy( $canvas );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}
		return array(
			'bytes'  => $bytes['bytes'],
			'mime'   => $bytes['mime'],
			'width'  => $width,
			'height' => $height,
		);
	}

	/** @return array<string, string> */
	private function get_fonts(): array {
		$base  = NT_CONTENT_IMAGES_PATH . 'assets/fonts/';
		$fonts = array(
			'bold'     => $base . 'BeVietnamPro-Bold.ttf',
			'semibold' => $base . 'BeVietnamPro-SemiBold.ttf',
			'regular'  => $base . 'BeVietnamPro-Regular.ttf',
		);
		/** Allows swapping the bundled fonts for other licensed TTF files. */
		$filtered = apply_filters( 'nt_content_images_overlay_fonts', $fonts );
		if ( is_array( $filtered ) ) {
			foreach ( array( 'bold', 'semibold', 'regular' ) as $weight ) {
				if ( isset( $filtered[ $weight ] ) && is_string( $filtered[ $weight ] ) ) {
					$fonts[ $weight ] = $filtered[ $weight ];
				}
			}
		}
		return $fonts;
	}

	/** @return resource|GdImage|WP_Error */
	private function create_canvas( string $source_file, int $width, int $height ) {
		$info = @getimagesize( $source_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) ) {
			return new WP_Error( 'ntci_overlay_source_invalid', __( 'File ảnh gốc không hợp lệ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$source = $this->create_image_from_file( $source_file, (string) ( $info['mime'] ?? '' ) );
		if ( ! $source ) {
			return new WP_Error( 'ntci_overlay_source_unreadable', __( 'Không thể đọc ảnh gốc. Chỉ hỗ trợ PNG, JPEG, WebP hoặc GIF.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}

		$canvas = imagecreatetruecolor( $width, $height );
		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, false );

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );
		$scale = max( $width / max( 1, $src_w ), $height / max( 1, $src_h ) );
		$fit_w = (int) round( $src_w * $scale );
		$fit_h = (int) round( $src_h * $scale );
		$dst_x = (int) round( ( $width - $fit_w ) / 2 );
		$dst_y = (int) round( ( $height - $fit_h ) / 2 );
		imagecopyresampled( $canvas, $source, $dst_x, $dst_y, 0, 0, $fit_w, $fit_h, $src_w, $src_h );
		imagedestroy( $source );
		return $canvas;
	}

	/** @return resource|GdImage|false */
	private function create_image_from_file( string $file, string $mime ) {
		switch ( $mime ) {
			case 'image/webp':
				return function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			case 'image/png':
				return @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			case 'image/jpeg':
				return @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			case 'image/gif':
				return function_exists( 'imagecreatefromgif' ) ? @imagecreatefromgif( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			default:
				return false;
		}
	}

	/** @return array{bytes: string, mime: string}|WP_Error */
	private function encode( $canvas ) {
		ob_start();
		if ( function_exists( 'imagewebp' ) ) {
			$ok   = imagewebp( $canvas, null, 90 );
			$mime = 'image/webp';
		} else {
			$ok   = imagepng( $canvas, null, 6 );
			$mime = 'image/png';
		}
		$bytes = (string) ob_get_clean();
		if ( ! $ok || '' === $bytes ) {
			return new WP_Error( 'ntci_overlay_encode_failed', __( 'Không thể xuất ảnh sau khi chèn chữ.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		return array( 'bytes' => $bytes, 'mime' => $mime );
	}

	/** @param array<string, mixed> $data @return array<string, mixed> */
	private function normalize_content( array $data ): array {
		$colors = is_array( $data['colors'] ?? null ) ? $data['colors'] : array();
		return array(
			'title'        => trim( (string) ( $data['title'] ?? '' ) ),
			'badge'        => $this->uppercase( trim( (string) ( $data['badge'] ?? '' ) ) ),
			'brand_name'   => trim( (string) ( $data['brand_name'] ?? '' ) ),
			'website_host' => trim( (string) ( $data['website_host'] ?? '' ) ),
			'logo_file'    => (string) ( $data['logo_file'] ?? '' ),
			'primary'      => self::hex_to_rgb( (string) ( $colors['primary'] ?? '' ), array( 15, 23, 42 ) ),
			'secondary'    => self::hex_to_rgb( (string) ( $colors['secondary'] ?? '' ), array( 212, 160, 23 ) ),
			'accent'       => self::hex_to_rgb( (string) ( $colors['accent'] ?? '' ), array( 255, 255, 255 ) ),
		);
	}

	/** @param array<int, int> $fallback @return array<int, int> */
	public static function hex_to_rgb( string $hex, array $fallback = array( 0, 0, 0 ) ): array {
		$hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return $fallback;
		}
		return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Picks a readable text color for a solid background.
	 *
	 * @param array<int, int> $rgb Background color.
	 * @return array<int, int>
	 */
	public static function pick_text_color( array $rgb ): array {
		$luminance = ( 0.2126 * ( $rgb[0] ?? 0 ) + 0.7152 * ( $rgb[1] ?? 0 ) + 0.0722 * ( $rgb[2] ?? 0 ) ) / 255;
		return $luminance > 0.6 ? array( 17, 24, 39 ) : array( 255, 255, 255 );
	}

	private function uppercase( string $text ): string {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
	}

	private function color( $canvas, array $rgb, int $alpha = 0 ): int {
		return (int) imagecolorallocatealpha( $canvas, $rgb[0], $rgb[1], $rgb[2], max( 0, min( 127, $alpha ) ) );
	}

	private function text_width( string $font, int $size, string $text ): int {
		$box = imagettfbbox( $size, 0, $font, $text );
		return is_array( $box ) ? (int) abs( $box[2] - $box[0] ) : 0;
	}

	/** @return array<int, string> */
	private function wrap_lines( string $font, int $size, string $text, int $max_width ): array {
		$words   = preg_split( '/\s+/u', trim( $text ) );
		$words   = is_array( $words ) ? array_values( array_filter( $words, static fn( string $word ): bool => '' !== $word ) ) : array();
		$lines   = array();
		$current = '';
		foreach ( $words as $word ) {
			$candidate = '' === $current ? $word : $current . ' ' . $word;
			if ( '' === $current || $this->text_width( $font, $size, $candidate ) <= $max_width ) {
				$current = $candidate;
				continue;
			}
			$lines[] = $current;
			$current = $word;
		}
		if ( '' !== $current ) {
			$lines[] = $current;
		}
		return $lines;
	}

	/**
	 * Finds the largest font size whose wrapped lines fit the box.
	 *
	 * @return array{size: int, lines: array<int, string>}
	 */
	private function fit_title( string $font, string $text, int $max_width, int $max_lines, int $max_size, int $min_size ): array {
		for ( $size = $max_size; $size >= $min_size; $size -= 2 ) {
			$lines = $this->wrap_lines( $font, $size, $text, $max_width );
			if ( count( $lines ) <= $max_lines ) {
				return array( 'size' => $size, 'lines' => $lines );
			}
		}
		$lines = array_slice( $this->wrap_lines( $font, $min_size, $text, $max_width ), 0, $max_lines );
		$last  = $lines[ $max_lines - 1 ] ?? '';
		while ( '' !== $last && $this->text_width( $font, $min_size, $last . '…' ) > $max_width ) {
			$last = (string) preg_replace( '/\X\z/u', '', $last );
		}
		$lines[ $max_lines - 1 ] = rtrim( $last ) . '…';
		return array( 'size' => $min_size, 'lines' => $lines );
	}

	private function draw_vertical_gradient( $canvas, int $width, int $top, int $bottom, array $rgb, int $alpha_start, int $alpha_end ): void {
		$steps = max( 1, $bottom - $top );
		for ( $y = $top; $y < $bottom; $y++ ) {
			$ratio = ( $y - $top ) / $steps;
			$alpha = (int) round( $alpha_start + ( $alpha_end - $alpha_start ) * $ratio );
			imagefilledrectangle( $canvas, 0, $y, $width, $y + 1, $this->color( $canvas, $rgb, $alpha ) );
		}
	}

	/**
	 * Draws a translucent rounded rectangle without double-blending artifacts.
	 *
	 * The shape is drawn opaque on a scratch image and merged once, because
	 * stacking translucent rectangles and corner ellipses directly on the
	 * canvas darkens every overlapping region.
	 *
	 * @param array<int, int> $rgb Fill color.
	 */
	private function draw_rounded_rect( $canvas, int $x, int $y, int $w, int $h, int $radius, array $rgb, int $alpha = 0 ): void {
		if ( $w < 1 || $h < 1 ) {
			return;
		}
		$radius = max( 0, min( $radius, (int) floor( min( $w, $h ) / 2 ) ) );
		$shape  = imagecreatetruecolor( $w + 1, $h + 1 );
		$magic  = (int) imagecolorallocate( $shape, 1, 2, 3 );
		imagefill( $shape, 0, 0, $magic );
		imagecolortransparent( $shape, $magic );
		$solid = (int) imagecolorallocate( $shape, $rgb[0], $rgb[1], $rgb[2] );
		imagefilledrectangle( $shape, $radius, 0, $w - $radius, $h, $solid );
		imagefilledrectangle( $shape, 0, $radius, $w, $h - $radius, $solid );
		if ( $radius > 0 ) {
			imagefilledellipse( $shape, $radius, $radius, $radius * 2, $radius * 2, $solid );
			imagefilledellipse( $shape, $w - $radius, $radius, $radius * 2, $radius * 2, $solid );
			imagefilledellipse( $shape, $radius, $h - $radius, $radius * 2, $radius * 2, $solid );
			imagefilledellipse( $shape, $w - $radius, $h - $radius, $radius * 2, $radius * 2, $solid );
		}
		$opacity = (int) round( ( 127 - max( 0, min( 127, $alpha ) ) ) / 127 * 100 );
		imagecopymerge( $canvas, $shape, $x, $y, 0, 0, $w + 1, $h + 1, $opacity );
		imagedestroy( $shape );
	}

	/** @return array{w: int, h: int} Rendered badge size, zero when badge text is empty. */
	private function draw_badge( $canvas, array $content, int $x, int $y ): array {
		if ( '' === $content['badge'] ) {
			return array( 'w' => 0, 'h' => 0 );
		}
		$fonts   = $this->get_fonts();
		$size    = 15;
		$pad_x   = 16;
		$pad_y   = 9;
		$text_w  = $this->text_width( $fonts['semibold'], $size, $content['badge'] );
		$badge_w = $text_w + 2 * $pad_x;
		$badge_h = $size + 2 * $pad_y;
		$bg      = $content['secondary'];
		$fg      = self::pick_text_color( $bg );
		$this->draw_rounded_rect( $canvas, $x, $y, $badge_w, $badge_h, 8, $bg, 8 );
		imagettftext( $canvas, $size, 0, $x + $pad_x, $y + $pad_y + $size, $this->color( $canvas, $fg ), $fonts['semibold'], $content['badge'] );
		return array( 'w' => $badge_w, 'h' => $badge_h );
	}

	/** Draws logo + brand name + website host in one line; returns consumed width. */
	private function draw_brand_line( $canvas, array $content, int $x, int $baseline_y, int $text_size, bool $align_right = false, int $right_edge = 0 ): int {
		$fonts = $this->get_fonts();
		$parts = array();
		if ( '' !== $content['brand_name'] ) {
			$parts[] = array( 'text' => $content['brand_name'], 'font' => $fonts['semibold'] );
		}
		if ( '' !== $content['website_host'] ) {
			$parts[] = array( 'text' => $content['website_host'], 'font' => $fonts['regular'] );
		}
		$logo      = $this->load_logo( $content['logo_file'] );
		$logo_h    = 26;
		$gap       = 12;
		$total_w   = 0;
		$logo_w    = 0;
		if ( $logo ) {
			$logo_w  = (int) round( imagesx( $logo ) * ( $logo_h / max( 1, imagesy( $logo ) ) ) );
			$total_w = $logo_w + $gap;
		}
		$measured = array();
		foreach ( $parts as $index => $part ) {
			$w          = $this->text_width( $part['font'], $text_size, $part['text'] );
			$measured[] = $w;
			$total_w   += $w + ( $index < count( $parts ) - 1 ? $gap + 10 : 0 );
		}
		if ( $align_right ) {
			$x = $right_edge - $total_w;
		}
		$cursor = $x;
		$white  = $this->color( $canvas, array( 255, 255, 255 ) );
		$soft   = $this->color( $canvas, array( 255, 255, 255 ), 32 );
		if ( $logo ) {
			imagecopyresampled( $canvas, $logo, $cursor, $baseline_y - $logo_h + 6, 0, 0, $logo_w, $logo_h, imagesx( $logo ), imagesy( $logo ) );
			imagedestroy( $logo );
			$cursor += $logo_w + $gap;
		}
		foreach ( $parts as $index => $part ) {
			$color = 0 === $index && '' !== $content['brand_name'] ? $white : $soft;
			imagettftext( $canvas, $text_size, 0, $cursor, $baseline_y, $color, $part['font'], $part['text'] );
			$cursor += $measured[ $index ];
			if ( $index < count( $parts ) - 1 ) {
				imagefilledellipse( $canvas, $cursor + $gap / 2 + 2, $baseline_y - (int) round( $text_size / 3 ), 4, 4, $soft );
				$cursor += $gap + 10;
			}
		}
		return $total_w;
	}

	/** @return resource|GdImage|false */
	private function load_logo( string $file ) {
		if ( '' === $file || ! file_exists( $file ) || ! is_readable( $file ) ) {
			return false;
		}
		$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) ) {
			return false;
		}
		return $this->create_image_from_file( $file, (string) ( $info['mime'] ?? '' ) );
	}

	private function draw_title_lines( $canvas, array $lines, int $size, string $font, int $x, int $top, int $line_height, int $color, int $width = 0, bool $center = false ): void {
		foreach ( $lines as $index => $line ) {
			$line_x = $x;
			if ( $center && $width > 0 ) {
				$line_x = $x + (int) round( ( $width - $this->text_width( $font, $size, $line ) ) / 2 );
			}
			imagettftext( $canvas, $size, 0, $line_x, $top + $size + $index * $line_height, $color, $font, $line );
		}
	}

	/** Layout 1: dark gradient across the lower third, left-aligned title. */
	private function draw_bottom_gradient( $canvas, int $width, int $height, array $content ): void {
		$fonts  = $this->get_fonts();
		$margin = (int) round( $width * 0.05 );
		$this->draw_vertical_gradient( $canvas, $width, (int) round( $height * 0.40 ), $height, self::NEUTRAL_DARK, 127, 10 );

		$footer_baseline = $height - (int) round( $margin * 0.55 );
		$this->draw_brand_line( $canvas, $content, $margin, $footer_baseline, 15 );

		if ( '' !== $content['title'] ) {
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $width - 2 * $margin, 3, 44, 30 );
			$line_height = (int) round( $fit['size'] * 1.4 );
			$title_h     = count( $fit['lines'] ) * $line_height;
			$title_top   = $footer_baseline - 34 - $title_h;
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $margin, $title_top, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ) );
			$badge_y = $title_top - 52;
		} else {
			$badge_y = $footer_baseline - 80;
		}
		$this->draw_badge( $canvas, $content, $margin, $badge_y );
	}

	/** Layout 2: brand-colored panel on the left half. */
	private function draw_left_panel( $canvas, int $width, int $height, array $content ): void {
		$fonts   = $this->get_fonts();
		$margin  = (int) round( $width * 0.05 );
		$panel_w = (int) round( $width * 0.46 );
		imagefilledrectangle( $canvas, 0, 0, $panel_w, $height, $this->color( $canvas, $content['primary'], 18 ) );
		imagefilledrectangle( $canvas, $panel_w, 0, $panel_w + 6, $height, $this->color( $canvas, $content['secondary'], 8 ) );

		$badge = $this->draw_badge( $canvas, $content, $margin, $margin );
		if ( '' !== $content['title'] ) {
			$max_w       = $panel_w - $margin - 30;
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $max_w, 5, 40, 26 );
			$line_height = (int) round( $fit['size'] * 1.42 );
			$title_h     = count( $fit['lines'] ) * $line_height;
			$title_top   = max( $margin + $badge['h'] + 30, (int) round( ( $height - $title_h ) / 2 ) );
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $margin, $title_top, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ) );
		}
		$this->draw_brand_line( $canvas, $content, $margin, $height - (int) round( $margin * 0.55 ), 15 );
	}

	/** Layout 3: solid brand band on top, title on a soft bottom gradient. */
	private function draw_top_band( $canvas, int $width, int $height, array $content ): void {
		$fonts  = $this->get_fonts();
		$margin = (int) round( $width * 0.05 );
		$band_h = 64;
		imagefilledrectangle( $canvas, 0, 0, $width, $band_h, $this->color( $canvas, $content['primary'], 6 ) );
		$this->draw_brand_line( $canvas, $content, (int) round( $margin / 2 ) + 8, (int) round( $band_h / 2 ) + 8, 16 );
		if ( '' !== $content['badge'] ) {
			$size    = 15;
			$text_w  = $this->text_width( $fonts['semibold'], $size, $content['badge'] );
			$badge_x = $width - $text_w - 32 - (int) round( $margin / 2 );
			$this->draw_badge( $canvas, $content, $badge_x, (int) round( ( $band_h - ( $size + 18 ) ) / 2 ) );
		}
		if ( '' !== $content['title'] ) {
			$this->draw_vertical_gradient( $canvas, $width, (int) round( $height * 0.55 ), $height, self::NEUTRAL_DARK, 127, 12 );
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $width - 2 * $margin, 3, 42, 28 );
			$line_height = (int) round( $fit['size'] * 1.4 );
			$title_h     = count( $fit['lines'] ) * $line_height;
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $margin, $height - $margin - $title_h, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ) );
		}
	}

	/** Layout 4: centered translucent box with centered typography. */
	private function draw_center_box( $canvas, int $width, int $height, array $content ): void {
		$fonts = $this->get_fonts();
		$box_w = (int) round( $width * 0.66 );
		$pad   = 42;
		$fit   = array( 'size' => 0, 'lines' => array() );
		if ( '' !== $content['title'] ) {
			$fit = $this->fit_title( $fonts['bold'], $content['title'], $box_w - 2 * $pad, 3, 42, 28 );
		}
		$line_height = (int) round( max( 1, $fit['size'] ) * 1.42 );
		$title_h     = count( $fit['lines'] ) * $line_height;
		$badge_h     = '' !== $content['badge'] ? 33 : 0;
		$brand_h     = '' !== $content['brand_name'] || '' !== $content['website_host'] ? 34 : 0;
		$box_h       = $pad * 2 + $badge_h + ( $badge_h > 0 ? 22 : 0 ) + $title_h + ( $brand_h > 0 ? 20 : 0 ) + $brand_h;
		$box_x       = (int) round( ( $width - $box_w ) / 2 );
		$box_y       = (int) round( ( $height - $box_h ) / 2 );
		$this->draw_rounded_rect( $canvas, $box_x, $box_y, $box_w, $box_h, 18, self::NEUTRAL_DARK, 28 );

		$cursor_y = $box_y + $pad;
		if ( $badge_h > 0 ) {
			$size    = 15;
			$text_w  = $this->text_width( $fonts['semibold'], $size, $content['badge'] ) + 32;
			$this->draw_badge( $canvas, $content, $box_x + (int) round( ( $box_w - $text_w ) / 2 ), $cursor_y );
			$cursor_y += $badge_h + 22;
		}
		if ( array() !== $fit['lines'] ) {
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $box_x + $pad, $cursor_y, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ), $box_w - 2 * $pad, true );
			$cursor_y += $title_h + 20;
		}
		if ( $brand_h > 0 ) {
			$host = '' !== $content['website_host'] ? $content['website_host'] : $content['brand_name'];
			$size = 15;
			$w    = $this->text_width( $fonts['regular'], $size, $host );
			imagettftext( $canvas, $size, 0, $box_x + (int) round( ( $box_w - $w ) / 2 ), $cursor_y + $size, $this->color( $canvas, array( 255, 255, 255 ), 25 ), $fonts['regular'], $host );
		}
	}

	/** Layout 6: dark gradient across the top, title dưới badge, brand phía phải. */
	private function draw_top_gradient( $canvas, int $width, int $height, array $content ): void {
		$fonts  = $this->get_fonts();
		$margin = (int) round( $width * 0.05 );
		$this->draw_vertical_gradient( $canvas, $width, 0, (int) round( $height * 0.58 ), self::NEUTRAL_DARK, 12, 127 );

		$badge = $this->draw_badge( $canvas, $content, $margin, $margin );
		$this->draw_brand_line( $canvas, $content, 0, $margin + 24, 15, true, $width - $margin );

		if ( '' !== $content['title'] ) {
			$title_top   = $margin + ( $badge['h'] > 0 ? $badge['h'] + 24 : 10 );
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $width - 2 * $margin, 3, 42, 28 );
			$line_height = (int) round( $fit['size'] * 1.4 );
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $margin, $title_top, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ) );
		}
	}

	/** Layout 7: brand-colored panel bên phải (đảo của left_panel). */
	private function draw_right_panel( $canvas, int $width, int $height, array $content ): void {
		$fonts   = $this->get_fonts();
		$margin  = (int) round( $width * 0.05 );
		$panel_w = (int) round( $width * 0.46 );
		$panel_x = $width - $panel_w;
		imagefilledrectangle( $canvas, $panel_x, 0, $width, $height, $this->color( $canvas, $content['primary'], 18 ) );
		imagefilledrectangle( $canvas, $panel_x - 6, 0, $panel_x, $height, $this->color( $canvas, $content['secondary'], 8 ) );

		$text_x = $panel_x + (int) round( $margin * 0.7 );
		$badge  = $this->draw_badge( $canvas, $content, $text_x, $margin );
		if ( '' !== $content['title'] ) {
			$max_w       = $width - $text_x - $margin;
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $max_w, 5, 40, 26 );
			$line_height = (int) round( $fit['size'] * 1.42 );
			$title_h     = count( $fit['lines'] ) * $line_height;
			$title_top   = max( $margin + $badge['h'] + 30, (int) round( ( $height - $title_h ) / 2 ) );
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $text_x, $title_top, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ) );
		}
		// Panel hẹp hơn canvas: bỏ bớt host rồi thu nhỏ chữ khi dòng thương hiệu quá dài để không tràn mép phải.
		$avail = $width - $text_x - (int) round( $margin * 0.4 );
		$size  = 15;
		$brand = $content;
		$full  = $this->text_width( $fonts['semibold'], $size, $content['brand_name'] ) + $this->text_width( $fonts['regular'], $size, $content['website_host'] ) + 70;
		if ( $full > $avail ) {
			$brand['website_host'] = '';
			if ( $this->text_width( $fonts['semibold'], $size, $content['brand_name'] ) + 50 > $avail ) {
				$size = 13;
			}
		}
		$this->draw_brand_line( $canvas, $brand, $text_x, $height - (int) round( $margin * 0.55 ), $size );
	}

	/** Layout 8: thanh màu thương hiệu đặc phía dưới, chữ nằm trên thanh. */
	private function draw_bottom_bar( $canvas, int $width, int $height, array $content ): void {
		$fonts  = $this->get_fonts();
		$margin = (int) round( $width * 0.05 );
		$pad    = 26;

		$fit         = array( 'size' => 0, 'lines' => array() );
		$line_height = 0;
		$title_h     = 0;
		if ( '' !== $content['title'] ) {
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $width - 2 * $margin, 2, 36, 24 );
			$line_height = (int) round( $fit['size'] * 1.38 );
			$title_h     = count( $fit['lines'] ) * $line_height;
		}
		$brand_h = '' !== $content['brand_name'] || '' !== $content['website_host'] || '' !== $content['logo_file'] ? 30 : 0;
		$bar_h   = $pad * 2 + $title_h + ( $brand_h > 0 ? $brand_h + 8 : 0 );
		$bar_top = $height - $bar_h;
		imagefilledrectangle( $canvas, 0, $bar_top, $width, $height, $this->color( $canvas, $content['primary'], 4 ) );
		imagefilledrectangle( $canvas, 0, $bar_top - 5, $width, $bar_top, $this->color( $canvas, $content['secondary'], 6 ) );

		// Badge ngồi vắt lên mép trên của thanh.
		if ( '' !== $content['badge'] ) {
			$this->draw_badge( $canvas, $content, $margin, $bar_top - 48 );
		}

		$text_rgb = self::pick_text_color( $content['primary'] );
		$cursor_y = $bar_top + $pad;
		if ( array() !== $fit['lines'] ) {
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $margin, $cursor_y, $line_height, $this->color( $canvas, $text_rgb ) );
			$cursor_y += $title_h + 8;
		}
		if ( $brand_h > 0 ) {
			// Vẽ brand thủ công theo màu tương phản với thanh (draw_brand_line luôn dùng chữ trắng).
			$host  = '' !== $content['brand_name'] ? $content['brand_name'] : $content['website_host'];
			$extra = '' !== $content['brand_name'] && '' !== $content['website_host'] ? '  ·  ' . $content['website_host'] : '';
			imagettftext( $canvas, 14, 0, $margin, $cursor_y + 14, $this->color( $canvas, $text_rgb, 30 ), $fonts['semibold'], $host . $extra );
		}
	}

	/** Layout 9: thẻ bo góc nổi ở góc dưới-trái (lower third card). */
	private function draw_corner_card( $canvas, int $width, int $height, array $content ): void {
		$fonts  = $this->get_fonts();
		$margin = (int) round( $width * 0.05 );
		$card_w = (int) round( $width * 0.62 );
		$pad    = 28;

		$fit         = array( 'size' => 0, 'lines' => array() );
		$line_height = 0;
		$title_h     = 0;
		if ( '' !== $content['title'] ) {
			$fit         = $this->fit_title( $fonts['bold'], $content['title'], $card_w - 2 * $pad, 3, 34, 24 );
			$line_height = (int) round( $fit['size'] * 1.4 );
			$title_h     = count( $fit['lines'] ) * $line_height;
		}
		$badge_h = '' !== $content['badge'] ? 33 : 0;
		$brand_h = '' !== $content['brand_name'] || '' !== $content['website_host'] || '' !== $content['logo_file'] ? 32 : 0;
		$card_h  = $pad * 2 + $badge_h + ( $badge_h > 0 ? 18 : 0 ) + $title_h + ( $brand_h > 0 ? 16 : 0 ) + $brand_h;
		$card_x  = $margin;
		$card_y  = $height - $margin - $card_h;
		$this->draw_rounded_rect( $canvas, $card_x, $card_y, $card_w, $card_h, 16, self::NEUTRAL_DARK, 22 );
		imagefilledrectangle( $canvas, $card_x, $card_y + 16, $card_x + 5, $card_y + $card_h - 16, $this->color( $canvas, $content['secondary'], 6 ) );

		$cursor_y = $card_y + $pad;
		if ( $badge_h > 0 ) {
			$this->draw_badge( $canvas, $content, $card_x + $pad, $cursor_y );
			$cursor_y += $badge_h + 18;
		}
		if ( array() !== $fit['lines'] ) {
			$this->draw_title_lines( $canvas, $fit['lines'], $fit['size'], $fonts['bold'], $card_x + $pad, $cursor_y, $line_height, $this->color( $canvas, array( 255, 255, 255 ) ) );
			$cursor_y += $title_h + 16;
		}
		if ( $brand_h > 0 ) {
			$this->draw_brand_line( $canvas, $content, $card_x + $pad, $cursor_y + 20, 14 );
		}
	}

	/** Layout 5: corner badge and brand chip only, keeps the photo natural. */
	private function draw_minimal_badge( $canvas, int $width, int $height, array $content ): void {
		$fonts = $this->get_fonts();
		$edge  = 32;
		$this->draw_badge( $canvas, $content, $edge, $edge );
		if ( '' === $content['brand_name'] && '' === $content['website_host'] ) {
			return;
		}
		$text   = '' !== $content['brand_name'] ? $content['brand_name'] : $content['website_host'];
		$size   = 14;
		$pad_x  = 14;
		$pad_y  = 8;
		$text_w = $this->text_width( $fonts['semibold'], $size, $text );
		$chip_w = $text_w + 2 * $pad_x;
		$chip_h = $size + 2 * $pad_y;
		$x      = $width - $edge - $chip_w;
		$y      = $height - $edge - $chip_h;
		$this->draw_rounded_rect( $canvas, $x, $y, $chip_w, $chip_h, 8, self::NEUTRAL_DARK, 40 );
		imagettftext( $canvas, $size, 0, $x + $pad_x, $y + $pad_y + $size, $this->color( $canvas, array( 255, 255, 255 ) ), $fonts['semibold'], $text );
	}
}
