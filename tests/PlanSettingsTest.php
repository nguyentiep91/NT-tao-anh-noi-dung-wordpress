<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlanSettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
	}

	public function test_defaults_match_previous_hardcoded_rules(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		$this->assertSame( 1, $settings->image_count( 800, 'guide' ) );
		$this->assertSame( 2, $settings->image_count( 1500, 'guide' ) );
		$this->assertSame( 2, $settings->image_count( 2999, 'guide' ) );
		$this->assertSame( 2, $settings->image_count( 3000, 'guide' ) );
		$this->assertSame( 3, $settings->image_count( 3001, 'guide' ) );
	}

	public function test_special_types_capped_at_special_max(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		foreach ( array( 'course', 'service', 'event', 'education_event', 'procurement_service' ) as $type ) {
			$this->assertSame( 2, $settings->image_count( 5000, $type ), $type );
		}
		$this->assertSame( 3, $settings->image_count( 5000, 'guide' ) );
	}

	public function test_custom_rules_apply(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		$result = $settings->save(
			array(
				'threshold_small' => 1000,
				'threshold_large' => 2000,
				'count_small'     => 2,
				'count_medium'    => 3,
				'count_large'     => 5,
				'max_images'      => 5,
				'special_max'     => 1,
			)
		);
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 2, $settings->image_count( 500, 'guide' ) );
		$this->assertSame( 3, $settings->image_count( 1500, 'guide' ) );
		$this->assertSame( 5, $settings->image_count( 2500, 'guide' ) );
		$this->assertSame( 1, $settings->image_count( 2500, 'course' ) );
	}

	public function test_zero_counts_allowed(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		$settings->save( array( 'count_small' => 0, 'count_medium' => 0, 'count_large' => 0 ) );
		$this->assertSame( 0, $settings->image_count( 500, 'guide' ) );
		$this->assertSame( 0, $settings->image_count( 5000, 'guide' ) );
	}

	public function test_max_images_caps_every_bucket(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		$settings->save( array( 'count_large' => 5, 'max_images' => 2 ) );
		$this->assertSame( 2, $settings->image_count( 5000, 'guide' ) );
	}

	public function test_hard_cap_is_five(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		$settings->save( array( 'count_large' => 9, 'max_images' => 9 ) );
		$this->assertSame( 5, $settings->image_count( 5000, 'guide' ) );
	}

	public function test_invalid_thresholds_rejected(): void {
		$settings = new NT_Content_Images_Plan_Settings();
		$result = $settings->save( array( 'threshold_small' => 3000, 'threshold_large' => 2000 ) );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'ntci_plan_thresholds_invalid', $result->get_error_code() );
		$this->assertSame( 1, $settings->image_count( 800, 'guide' ) );
	}
}
