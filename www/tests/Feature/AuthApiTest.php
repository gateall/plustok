<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class AuthApiTest extends ApiTestCase
{
    public function test_login_success(): void
    {
        $res = $this->api('POST', '/auth/login', [
            'loginId'  => 'admin',
            'password' => 'Admin123!',
        ]);
        $this->assertSame(200, $res->http);
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('accessToken', $res->body['data']);
    }

    public function test_login_with_username_alias(): void
    {
        $res = $this->api('POST', '/auth/login', [
            'username' => 'admin',
            'password' => 'Admin123!',
        ]);
        $this->assertSame(200, $res->http);
        $this->assertTrue($res->isSuccess());
    }

    public function test_login_invalid_credentials(): void
    {
        $res = $this->api('POST', '/auth/login', [
            'loginId'  => 'admin',
            'password' => 'wrong',
        ]);
        $this->assertSame(401, $res->http);
        $this->assertFalse($res->isSuccess());
    }

    public function test_auth_me_requires_token(): void
    {
        $res = $this->api('GET', '/auth/me');
        $this->assertSame(401, $res->http);
    }

    public function test_auth_me_success(): void
    {
        $token = $this->loginAdmin();
        $res = $this->api('GET', '/auth/me', null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertSame('admin', $res->body['data']['loginId']);
    }

    public function test_logout_success(): void
    {
        $token = $this->loginAdmin();
        $res = $this->api('POST', '/auth/logout', null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertTrue($res->body['data']['loggedOut']);
    }
}
