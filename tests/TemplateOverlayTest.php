<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TemplateOverlayTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
	}

	public function test_registry_ships_nine_supported_templates(): void {
		$registry  = new NT_Content_Images_Template_Registry();
		$templates = $registry->get_all();

		self::assertCount( 9, $templates );
		self::assertArrayHasKey( NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE, $templates );
		foreach ( array( 'top_gradient', 'right_panel', 'bottom_bar', 'corner_card' ) as $new_id ) {
			self::assertArrayHasKey( $new_id, $templates );
			self::assertTrue( $templates[ $new_id ]['shows_title'] );
		}
		foreach ( $templates as $template ) {
			self::assertContains( $template['layout'], NT_Content_Images_Template_Registry::SUPPORTED_LAYOUTS );
			self::assertNotSame( '', $template['label'] );
		}
		self::assertFalse( $templates['minimal_badge']['shows_title'] );
		self::assertTrue( $templates['bottom_gradient']['shows_title'] );
	}

	public function test_mix_mode_rotates_templates_per_slot_and_post(): void {
		$pool = array( 'a/one', 'b/two', 'c/three' );

		// Cùng bài: các slot khác nhau ra mẫu khác nhau.
		$featured = NT_Content_Images_Template_Settings::pick_from_pool( $pool, 100, 0 );
		$slot1    = NT_Content_Images_Template_Settings::pick_from_pool( $pool, 100, 1 );
		$slot2    = NT_Content_Images_Template_Settings::pick_from_pool( $pool, 100, 2 );
		self::assertCount( 3, array_unique( array( $featured, $slot1, $slot2 ) ) );

		// Bài kế nhau dịch vòng: featured của post 101 khác post 100.
		self::assertNotSame( $featured, NT_Content_Images_Template_Settings::pick_from_pool( $pool, 101, 0 ) );

		// Deterministic: gọi lại vẫn ra đúng mẫu cũ.
		self::assertSame( $featured, NT_Content_Images_Template_Settings::pick_from_pool( $pool, 100, 0 ) );

		// Pool rỗng rơi về mẫu mặc định.
		self::assertSame( NT_Content_Images_Template_Registry::DEFAULT_TEMPLATE, NT_Content_Images_Template_Settings::pick_from_pool( array(), 100, 0 ) );
	}

	public function test_settings_mix_mode_and_pool_sanitized(): void {
		$settings = new NT_Content_Images_Template_Settings( new NT_Content_Images_Template_Registry() );

		// Mặc định: fixed, pool = 8 mẫu có tiêu đề (không gồm minimal_badge).
		$config = $settings->get();
		self::assertSame( 'fixed', $config['template_mode'] );
		self::assertCount( 8, $config['mix_templates'] );
		self::assertNotContains( 'minimal_badge', $config['mix_templates'] );

		$saved = $settings->save(
			array(
				'default_template' => 'bottom_gradient',
				'template_mode'    => 'mix',
				'mix_templates'    => array( 'corner_card', 'left_panel', 'khong_ton_tai', 'corner_card' ),
			)
		);
		self::assertSame( 'mix', $saved['template_mode'] );
		self::assertSame( array( 'corner_card', 'left_panel' ), $saved['mix_templates'] );

		// pick_template dùng đúng pool đã lưu.
		self::assertContains( $settings->pick_template( 7, 0, false ), array( 'corner_card', 'left_panel' ) );
	}

	public function test_registry_public_shape_has_no_layout_internals(): void {
		$registry = new NT_Content_Images_Template_Registry();
		$public   = $registry->get_public();

		self::assertNotEmpty( $public );
		foreach ( $public as $item ) {
			self::assertArrayHasKey( 'id', $item );
			self::assertArrayHasKey( 'label', $item );
			self::assertArrayHasKey( 'description', $item );
			self::assertArrayHasKey( 'shows_title', $item );
			self::assertArrayNotHasKey( 'layout', $item );
		}
	}

	public function test_settings_default_to_bottom_gradient_with_all_marks_visible(): void {
		$settings = new NT_Content_Images_Template_Settings( new NT_Content_Images_Template_Registry() );
		$config   = $settings->get();

		self::assertSame( 'bottom_gradient', $config['default_template'] );
		self::assertSame( 'bottom_gradient', $config['content_template'] );
		self::assertTrue( $config['show_category'] );
		self::assertTrue( $config['show_site_name'] );
		self::assertTrue( $config['show_logo'] );
		self::assertTrue( $config['auto_overlay'] );
	}

	public function test_settings_reject_unknown_template(): void {
		$settings = new NT_Content_Images_Template_Settings( new NT_Content_Images_Template_Registry() );
		$result   = $settings->save( array( 'default_template' => 'does_not_exist' ) );

		self::assertInstanceOf( WP_Error::class, $result );
	}

	public function test_settings_save_and_reload_choices(): void {
		$settings = new NT_Content_Images_Template_Settings( new NT_Content_Images_Template_Registry() );
		$result   = $settings->save(
			array(
				'default_template' => 'left_panel',
				'content_template' => 'minimal_badge',
				'show_category'    => '1',
				'show_site_name'   => '',
				'show_logo'        => '1',
				'auto_overlay'     => '',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'left_panel', $result['default_template'] );
		self::assertSame( 'minimal_badge', $result['content_template'] );
		self::assertTrue( $result['show_category'] );
		self::assertFalse( $result['show_site_name'] );
		self::assertTrue( $result['show_logo'] );
		self::assertFalse( $result['auto_overlay'] );
	}

	public function test_hex_to_rgb_accepts_short_and_long_forms(): void {
		self::assertSame( array( 255, 255, 255 ), NT_Content_Images_Overlay_Renderer::hex_to_rgb( '#ffffff' ) );
		self::assertSame( array( 15, 23, 42 ), NT_Content_Images_Overlay_Renderer::hex_to_rgb( '0f172a' ) );
		self::assertSame( array( 255, 0, 0 ), NT_Content_Images_Overlay_Renderer::hex_to_rgb( '#f00' ) );
		self::assertSame( array( 1, 2, 3 ), NT_Content_Images_Overlay_Renderer::hex_to_rgb( 'not-a-color', array( 1, 2, 3 ) ) );
	}

	public function test_text_color_flips_for_light_backgrounds(): void {
		self::assertSame( array( 255, 255, 255 ), NT_Content_Images_Overlay_Renderer::pick_text_color( array( 15, 23, 42 ) ) );
		self::assertSame( array( 17, 24, 39 ), NT_Content_Images_Overlay_Renderer::pick_text_color( array( 250, 240, 200 ) ) );
	}

	public function test_bundled_fonts_are_present_and_readable(): void {
		$renderer = new NT_Content_Images_Overlay_Renderer();
		$status   = $renderer->get_font_status();

		self::assertTrue( $status['ready'], 'Thiếu font: ' . implode( ', ', $status['missing'] ) );
	}

	public function test_render_draws_vietnamese_title_on_each_template(): void {
		if ( ! NT_Content_Images_Overlay_Renderer::is_supported() ) {
			self::markTestSkipped( 'GD/FreeType không khả dụng trong môi trường test.' );
		}
		$source = tempnam( sys_get_temp_dir(), 'ntci' ) . '.png';
		$image  = imagecreatetruecolor( 640, 360 );
		imagefilledrectangle( $image, 0, 0, 640, 360, (int) imagecolorallocate( $image, 90, 120, 160 ) );
		imagepng( $image, $source );
		imagedestroy( $image );

		$registry = new NT_Content_Images_Template_Registry();
		$renderer = new NT_Content_Images_Overlay_Renderer();
		$data     = array(
			'title'         => 'Hướng dẫn đăng ký kinh doanh hộ cá thể năm 2026 đầy đủ nhất',
			'badge'         => 'Doanh nghiệp',
			'brand_name'    => 'Nguyễn Tiệp',
			'website_host'  => 'nguyentiep.vn',
			'logo_file'     => '',
			'colors'        => array(
				'primary'   => '#0f172a',
				'secondary' => '#d4a017',
				'accent'    => '#ffffff',
			),
			'target_width'  => 1280,
			'target_height' => 720,
		);

		foreach ( $registry->get_all() as $template ) {
			$result = $renderer->render( $source, $template, $data );
			self::assertIsArray( $result, 'Render lỗi ở mẫu ' . $template['id'] );
			self::assertNotSame( '', $result['bytes'] );
			self::assertContains( $result['mime'], array( 'image/webp', 'image/png' ) );
			self::assertSame( 1280, $result['width'] );
			self::assertSame( 720, $result['height'] );
			$info = getimagesizefromstring( $result['bytes'] );
			self::assertIsArray( $info );
			self::assertSame( 1280, $info[0] );
			self::assertSame( 720, $info[1] );
		}
		unlink( $source );
	}

	public function test_render_fails_cleanly_when_source_is_missing(): void {
		if ( ! NT_Content_Images_Overlay_Renderer::is_supported() ) {
			self::markTestSkipped( 'GD/FreeType không khả dụng trong môi trường test.' );
		}
		$registry = new NT_Content_Images_Template_Registry();
		$renderer = new NT_Content_Images_Overlay_Renderer();
		$result   = $renderer->render( '/duong-dan/khong-ton-tai.png', (array) $registry->get( 'bottom_gradient' ), array() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'ntci_overlay_source_missing', $result->get_error_code() );
	}
}
