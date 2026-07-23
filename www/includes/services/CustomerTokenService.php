<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/JwtHelper.php';

final class CustomerTokenService
{
    /** @return array{accessToken:string,expiresIn:int} */
    public function issue(string $customerId, string $name = ''): array
    {
        $payload = [
            'sub'  => $customerId,
            'role' => 'customer',
            'name' => $name,
        ];
        $access = JwtHelper::encode($payload, ACEP_CUSTOMER_JWT_TTL);

        return [
            'accessToken' => $access,
            'expiresIn'   => ACEP_CUSTOMER_JWT_TTL,
        ];
    }
}
