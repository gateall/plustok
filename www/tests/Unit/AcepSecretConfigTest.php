<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AcepSecretConfigTest extends TestCase
{
    public function test_rejects_placeholder_and_weak_jwt_secrets(): void
    {
        $this->assertTrue(acep_is_rejected_jwt_secret(''));
        $this->assertTrue(acep_is_rejected_jwt_secret('CHANGE_ME_IN_acep.local.php'));
        $this->assertTrue(acep_is_rejected_jwt_secret('short'));
        $this->assertTrue(acep_is_rejected_jwt_secret('prefix-change_me-suffix-' . str_repeat('a', 32)));
        $this->assertFalse(acep_is_rejected_jwt_secret(bin2hex(random_bytes(32))));
    }

    public function test_testing_mode_uses_configured_secret(): void
    {
        $this->assertGreaterThanOrEqual(32, strlen(acep_jwt_secret()));
    }
}
