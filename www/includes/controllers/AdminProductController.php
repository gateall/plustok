<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class AdminProductController
{
    public function __construct(private AdminProductService $products)
    {
    }

    public function route(string $method, string $uri): bool
    {
        $readRoles = ['admin', 'operator'];
        $writeRoles = ['admin'];

        if ($method === 'GET' && $uri === '/admin/products') {
            JwtMiddleware::requireRole($readRoles);
            acep_success($this->products->list($_GET));
        }
        if ($method === 'POST' && $uri === '/admin/products') {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->products->create((string)$claims['sub'], acep_read_json()), 201);
        }
        if ($method === 'GET' && preg_match('#^/admin/products/(\d+)$#', $uri, $m)) {
            JwtMiddleware::requireRole($readRoles);
            acep_success($this->products->get((int)$m[1]));
        }
        if ($method === 'PATCH' && preg_match('#^/admin/products/(\d+)$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->products->update((string)$claims['sub'], (int)$m[1], acep_read_json()));
        }
        if ($method === 'DELETE' && preg_match('#^/admin/products/(\d+)$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->products->delete((string)$claims['sub'], (int)$m[1]));
        }
        if ($method === 'POST' && preg_match('#^/admin/products/(\d+)/toggle$#', $uri, $m)) {
            $claims = JwtMiddleware::requireRole($writeRoles);
            acep_success($this->products->toggle((string)$claims['sub'], (int)$m[1]));
        }

        return false;
    }
}
