<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class SiteController
{
    public function __construct(private SiteService $sites)
    {
    }

    public function route(string $method, string $uri): bool
    {
        $readRoles = ['admin', 'operator'];
        $writeRoles = ['admin'];

        if ($method === 'GET' && $uri === '/admin/sites') {
            JwtMiddleware::requireRole($readRoles);
            acep_success($this->sites->list($_GET));
        }
        if ($method === 'POST' && $uri === '/admin/sites') {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->sites->create((string)$claims['sub'], acep_read_json()), 201);
        }
        if ($method === 'GET' && preg_match('#^/admin/sites/(\d+)$#', $uri, $m)) {
            JwtMiddleware::requireRole($readRoles);
            acep_success($this->sites->get((int)$m[1]));
        }
        if ($method === 'PATCH' && preg_match('#^/admin/sites/(\d+)$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->sites->update((string)$claims['sub'], (int)$m[1], acep_read_json()));
        }
        if ($method === 'DELETE' && preg_match('#^/admin/sites/(\d+)$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->sites->delete((string)$claims['sub'], (int)$m[1]));
        }
        if ($method === 'POST' && preg_match('#^/admin/sites/(\d+)/regen-key$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->sites->regenerateKey((string)$claims['sub'], (int)$m[1]));
        }
        if ($method === 'POST' && preg_match('#^/admin/sites/(\d+)/toggle$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->sites->toggle((string)$claims['sub'], (int)$m[1]));
        }
        if ($method === 'GET' && preg_match('#^/admin/sites/(\d+)/health$#', $uri, $m)) {
            JwtMiddleware::requireRole($readRoles);
            acep_success($this->sites->health((int)$m[1]));
        }
        if ($method === 'POST' && preg_match('#^/admin/sites/(\d+)/health-check$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->sites->runHealthCheck((string)$claims['sub'], (int)$m[1]));
        }
        if ($method === 'GET' && preg_match('#^/admin/sites/(\d+)/stats$#', $uri, $m)) {
            JwtMiddleware::requireRole($readRoles);
            acep_success($this->sites->stats((int)$m[1]));
        }

        return false;
    }
}
