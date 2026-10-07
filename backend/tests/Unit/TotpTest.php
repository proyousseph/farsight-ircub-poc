<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_generated_secret_verifies_current_code(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::currentCode($secret);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }

    public function test_otpauth_uri_contains_secret_and_issuer(): void
    {
        $uri = Totp::otpAuthUri('ABCDEFGHIJKLMNOP', 'user@ircub.test', 'IRCUB');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=ABCDEFGHIJKLMNOP', $uri);
        $this->assertStringContainsString('issuer=IRCUB', $uri);
    }
}
