<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    public function test_success_payload_structure(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/api_envelope.php';
        acep_test_mode(true);
        try {
            acep_success(['foo' => 'bar']);
            $this->fail('expected exception');
        } catch (\AcepHttpResponse $e) {
            $this->assertTrue($e->isSuccess());
            $this->assertSame('bar', $e->body['data']['foo']);
        }
    }
}
