<?php
declare(strict_types=1);

/** PHPUnit 등 CLI 테스트에서 acep_success/acep_error exit 대신 throw */
final class AcepHttpResponse extends RuntimeException
{
    public array $body;
    public int $http;

    public function __construct(array $body, int $http)
    {
        $this->body = $body;
        $this->http = $http;
        parent::__construct((string)($body['error']['message'] ?? 'HTTP ' . $http), $http);
    }

    public function isSuccess(): bool
    {
        return ($this->body['success'] ?? false) === true;
    }
}
