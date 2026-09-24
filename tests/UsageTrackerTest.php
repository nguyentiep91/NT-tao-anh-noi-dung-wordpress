<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UsageTrackerTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
	}

	public function test_starts_empty_and_counts_per_provider(): void {
		self::assertSame( 0, NT_Content_Images_Usage_Tracker::get_today()['total'] );

		NT_Content_Images_Usage_Tracker::increment( 'openrouter' );
		NT_Content_Images_Usage_Tracker::increment( 'openrouter' );
		NT_Content_Images_Usage_Tracker::increment( 'cloudflare' );

		$usage = NT_Content_Images_Usage_Tracker::get_today();
		self::assertSame( 3, $usage['total'] );
		self::assertSame( 2, $usage['providers']['openrouter'] );
		self::assertSame( 1, $usage['providers']['cloudflare'] );
	}

	public function test_limit_enforcement_and_remaining(): void {
		self::assertFalse( NT_Content_Images_Usage_Tracker::is_exhausted( 2 ) );
		self::assertSame( 2, NT_Content_Images_Usage_Tracker::remaining( 2 ) );

		NT_Content_Images_Usage_Tracker::increment( 'openai' );
		NT_Content_Images_Usage_Tracker::increment( 'openai' );

		self::assertTrue( NT_Content_Images_Usage_Tracker::is_exhausted( 2 ) );
		self::assertSame( 0, NT_Content_Images_Usage_Tracker::remaining( 2 ) );
		self::assertFalse( NT_Content_Images_Usage_Tracker::is_exhausted( 0 ), 'Giới hạn 0 nghĩa là không giới hạn.' );

		$error = NT_Content_Images_Usage_Tracker::limit_error( 2 );
		self::assertSame( 'ntci_daily_limit_reached', $error->get_error_code() );
	}

	public function test_stale_previous_day_resets(): void {
		$GLOBALS['ntci_test_options']['nt_content_images_daily_usage'] = array(
			'date'      => '2000-01-01',
			'total'     => 99,
			'providers' => array( 'openai' => 99 ),
		);
		self::assertSame( 0, NT_Content_Images_Usage_Tracker::get_today()['total'] );
	}
}
