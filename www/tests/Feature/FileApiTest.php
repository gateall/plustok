<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class FileApiTest extends ApiTestCase
{
    public function test_upload_requires_file(): void
    {
        $token = $this->loginAdmin();
        $res = $this->api('POST', '/files/upload', null, $token);
        $this->assertSame(400, $res->http);
        $this->assertSame('VALIDATION_ERROR', $res->body['error']['code']);
    }
}
