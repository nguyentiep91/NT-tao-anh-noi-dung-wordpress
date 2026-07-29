<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlanOverridesTest extends TestCase {
	private const POST = 123;

	protected function setUp(): void {
		$GLOBALS['ntci_test_postmeta'] = array();
	}

	private static function slot( int $index, string $heading ): array {
		return array(
			'index'     => $index,
			'purpose'   => 'Minh họa mục ' . $heading,
			'placement' => array(
				'type'         => 'before_heading',
				'heading_text' => $heading,
				'safety'       => 'safe_candidate',
			),
		);
	}

	public function test_apply_without_overrides_keeps_plan_and_defaults(): void {
		$plan = NT_Content_Images_Plan_Overrides::apply(
			array( self::slot( 1, 'Mục A' ), self::slot( 2, 'Mục B' ) ),
			array( 'slots' => array(), 'extra' => array() )
		);
		$this->assertCount( 2, $plan );
		$this->assertTrue( $plan[0]['enabled'] );
		$this->assertFalse( $plan[0]['user_modified'] );
		$this->assertFalse( $plan[0]['is_extra'] );
		$this->assertSame( '', $plan[0]['custom_scene'] );
		$this->assertSame( 'Mục A', $plan[0]['placement']['heading_text'] );
	}

	public function test_apply_heading_override_replaces_placement(): void {
		$overrides = array(
			'slots' => array( 2 => array( 'enabled' => true, 'heading_text' => 'Mục C', 'custom_scene' => '' ) ),
			'extra' => array(),
		);
		$plan = NT_Content_Images_Plan_Overrides::apply( array( self::slot( 1, 'Mục A' ), self::slot( 2, 'Mục B' ) ), $overrides );
		$this->assertSame( 'Mục A', $plan[0]['placement']['heading_text'] );
		$this->assertSame( 'Mục C', $plan[1]['placement']['heading_text'] );
		$this->assertSame( 'before_heading', $plan[1]['placement']['type'] );
		$this->assertSame( 'safe_candidate', $plan[1]['placement']['safety'] );
		$this->assertTrue( $plan[1]['user_modified'] );
	}

	public function test_apply_disable_and_custom_scene(): void {
		$overrides = array(
			'slots' => array(
				1 => array( 'enabled' => false, 'heading_text' => '', 'custom_scene' => '' ),
				2 => array( 'enabled' => true, 'heading_text' => '', 'custom_scene' => 'Hai kỹ sư trao đổi trước bản vẽ' ),
			),
			'extra' => array(),
		);
		$plan = NT_Content_Images_Plan_Overrides::apply( array( self::slot( 1, 'Mục A' ), self::slot( 2, 'Mục B' ) ), $overrides );
		$this->assertFalse( $plan[0]['enabled'] );
		$this->assertSame( 'Hai kỹ sư trao đổi trước bản vẽ', $plan[1]['custom_scene'] );
		$this->assertSame( 'Mục B', $plan[1]['placement']['heading_text'], 'Scene override không được đổi vị trí chèn' );
	}

	public function test_apply_appends_extra_slots_after_auto_slots(): void {
		$overrides = array(
			'slots' => array(),
			'extra' => array( 101 => array( 'index' => 101, 'heading_text' => 'Mục thêm tay', 'enabled' => true, 'custom_scene' => '' ) ),
		);
		$plan = NT_Content_Images_Plan_Overrides::apply( array( self::slot( 1, 'Mục A' ) ), $overrides );
		$this->assertCount( 2, $plan );
		$this->assertSame( 101, $plan[1]['index'] );
		$this->assertTrue( $plan[1]['is_extra'] );
		$this->assertTrue( $plan[1]['user_modified'] );
		$this->assertSame( 'Mục thêm tay', $plan[1]['placement']['heading_text'] );
		$this->assertStringContainsString( 'Mục thêm tay', $plan[1]['purpose'] );
	}

	public function test_update_slot_roundtrip_with_vietnamese_text(): void {
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 1, array( 'custom_scene' => 'Cảnh "đấu thầu" — hồ sơ & máy tính' ) );
		$data = NT_Content_Images_Plan_Overrides::get( self::POST );
		$this->assertSame( 'Cảnh "đấu thầu" — hồ sơ & máy tính', $data['slots'][1]['custom_scene'] );
		$this->assertTrue( NT_Content_Images_Plan_Overrides::has_overrides( self::POST ) );
	}

	public function test_update_slot_merges_patches(): void {
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 2, array( 'custom_scene' => 'Cảnh riêng' ) );
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 2, array( 'heading_text' => 'Mục C' ) );
		$data = NT_Content_Images_Plan_Overrides::get( self::POST );
		$this->assertSame( 'Cảnh riêng', $data['slots'][2]['custom_scene'] );
		$this->assertSame( 'Mục C', $data['slots'][2]['heading_text'] );
	}

	public function test_update_slot_back_to_default_removes_meta(): void {
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 1, array( 'enabled' => false ) );
		$this->assertTrue( NT_Content_Images_Plan_Overrides::has_overrides( self::POST ) );
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 1, array( 'enabled' => true ) );
		$this->assertFalse( NT_Content_Images_Plan_Overrides::has_overrides( self::POST ) );
		$this->assertSame( '', get_post_meta( self::POST, NT_Content_Images_Plan_Overrides::META, true ) );
	}

	public function test_add_extra_assigns_sequential_indexes(): void {
		$first  = NT_Content_Images_Plan_Overrides::add_extra( self::POST, 'Mục X', 2 );
		$second = NT_Content_Images_Plan_Overrides::add_extra( self::POST, 'Mục Y', 3 );
		$this->assertSame( 101, $first );
		$this->assertSame( 102, $second );
		$data = NT_Content_Images_Plan_Overrides::get( self::POST );
		$this->assertSame( 'Mục X', $data['extra'][101]['heading_text'] );
		$this->assertSame( 'Mục Y', $data['extra'][102]['heading_text'] );
	}

	public function test_add_extra_validation(): void {
		$empty = NT_Content_Images_Plan_Overrides::add_extra( self::POST, '  ', 1 );
		$this->assertTrue( is_wp_error( $empty ) );
		$this->assertSame( 'ntci_plan_heading_empty', $empty->get_error_code() );

		$capped = NT_Content_Images_Plan_Overrides::add_extra( self::POST, 'Mục X', NT_Content_Images_Plan_Overrides::HARD_CAP );
		$this->assertTrue( is_wp_error( $capped ) );
		$this->assertSame( 'ntci_plan_cap_reached', $capped->get_error_code() );
	}

	public function test_remove_extra_and_reset(): void {
		NT_Content_Images_Plan_Overrides::add_extra( self::POST, 'Mục X', 1 );
		NT_Content_Images_Plan_Overrides::remove_extra( self::POST, 101 );
		$this->assertFalse( NT_Content_Images_Plan_Overrides::has_overrides( self::POST ) );

		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 1, array( 'enabled' => false ) );
		NT_Content_Images_Plan_Overrides::add_extra( self::POST, 'Mục X', 1 );
		NT_Content_Images_Plan_Overrides::reset( self::POST );
		$this->assertFalse( NT_Content_Images_Plan_Overrides::has_overrides( self::POST ) );
	}

	public function test_disabled_indexes_covers_slots_and_extras(): void {
		$this->assertSame( array(), NT_Content_Images_Plan_Overrides::disabled_indexes( self::POST ) );

		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 2, array( 'enabled' => false ) );
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 3, array( 'custom_scene' => 'Cảnh riêng, vẫn bật' ) );
		NT_Content_Images_Plan_Overrides::add_extra( self::POST, 'Mục X', 1 );
		NT_Content_Images_Plan_Overrides::update_slot( self::POST, 101, array( 'enabled' => false ) );

		$this->assertSame( array( 2 => true, 101 => true ), NT_Content_Images_Plan_Overrides::disabled_indexes( self::POST ) );
	}

	public function test_list_headings_parses_h2_h3_dedupes_and_ignores_h4(): void {
		$content = '<h2 class="wp-block-heading">Bảo lãnh dự thầu là gì?</h2>'
			. '<p>...</p><h3><strong>Điều kiện</strong>   áp dụng</h3>'
			. '<h2>Bảo lãnh dự thầu là gì?</h2>'
			. '<h4>Không lấy mục này</h4>'
			. '<h3></h3>';
		$headings = NT_Content_Images_Plan_Overrides::list_headings( $content );
		$this->assertSame(
			array(
				array( 'text' => 'Bảo lãnh dự thầu là gì?', 'level' => 2 ),
				array( 'text' => 'Điều kiện áp dụng', 'level' => 3 ),
			),
			$headings
		);
	}
}
