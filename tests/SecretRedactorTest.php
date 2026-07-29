<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecretRedactorTest extends TestCase {
	public function test_redacts_nested_credentials(): void {
		$input = array(
			'provider' => 'openrouter',
			'api_key' => 'sk-or-v1-super-secret-value',
			'nested' => array(
				'access_token' => 'token-value',
				'message' => 'Authorization: Bearer abc.def.ghi',
			),
		);

		$result = NT_Content_Images_Secret_Redactor::redact( $input );

		self::assertSame( '[REDACTED]', $result['api_key'] );
		self::assertSame( '[REDACTED]', $result['nested']['access_token'] );
		self::assertStringNotContainsString( 'abc.def.ghi', $result['nested']['message'] );
	}

	public function test_redacts_secret_patterns_inside_message(): void {
		$message = 'Provider failed with sk-or-v1-abcdefghijklmnopqrstuvwxyz and client_secret=my-secret';
		$clean = NT_Content_Images_Secret_Redactor::redact_message( $message );

		self::assertStringNotContainsString( 'abcdefghijklmnopqrstuvwxyz', $clean );
		self::assertStringNotContainsString( 'my-secret', $clean );
		self::assertStringContainsString( '[REDACTED]', $clean );
	}
}
