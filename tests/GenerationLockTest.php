<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GenerationLockTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ntci_test_options'] = array();
	}

	public function test_prevents_duplicate_request_for_same_post(): void {
		$lock = new NT_Content_Images_Generation_Lock();

		self::assertTrue( $lock->acquire( 123 ) );
		self::assertFalse( $lock->acquire( 123 ) );

		$lock->release( 123 );
		self::assertTrue( $lock->acquire( 123 ) );
	}

	public function test_expired_lock_can_be_reacquired(): void {
		$GLOBALS['ntci_test_options']['nt_content_images_generation_lock_456'] = time() - 1200;
		$lock = new NT_Content_Images_Generation_Lock();

		self::assertTrue( $lock->acquire( 456 ) );
	}

	public function test_invalid_post_id_cannot_be_locked(): void {
		$lock = new NT_Content_Images_Generation_Lock();
		self::assertFalse( $lock->acquire( 0 ) );
	}
}
