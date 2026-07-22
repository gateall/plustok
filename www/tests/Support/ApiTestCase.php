<?php
declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Tests\Traits\WithDatabase;

abstract class ApiTestCase extends TestCase
{
    use WithDatabase;

    protected array $admin = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/includes/api_envelope.php';
        \acep_test_mode(true);
        \JwtMiddleware::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        \acep_set_request_json(null);

        $pdo = $this->ensureSchema();
        $this->admin = $this->seedAdmin($pdo);
    }

    /** @param array<string,mixed>|null $json */
    protected function api(
        string $method,
        string $uri,
        ?array $json = null,
        ?string $token = null,
        array $query = [],
    ): \AcepHttpResponse {
        \JwtMiddleware::reset();
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/api/v1' . $uri;
        $_SERVER['PATH_INFO'] = $uri;
        $_GET = $query;
        \acep_set_request_json($json);
        $_SERVER['HTTP_AUTHORIZATION'] = $token ? 'Bearer ' . $token : '';

        require_once dirname(__DIR__, 2) . '/includes/api_envelope.php';
        \acep_test_mode(true);
        acep_bootstrap(true);

        require_once dirname(__DIR__, 2) . '/api/v1/router.php';

        try {
            acep_route($method, $uri);
            $this->fail('Expected AcepHttpResponse');
        } catch (\AcepHttpResponse $e) {
            return $e;
        }
    }

    protected function adminToken(): string
    {
        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';
        return \JwtHelper::encode([
            'sub'  => $this->admin['id'],
            'role' => 'admin',
            'name' => 'Test Admin',
        ], 3600);
    }

    protected function loginAdmin(): string
    {
        $res = $this->api('POST', '/auth/login', [
            'loginId'  => $this->admin['loginId'],
            'password' => $this->admin['password'],
        ]);
        $this->assertTrue($res->isSuccess());
        return (string)$res->body['data']['accessToken'];
    }
}
