<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/acep.php';

final class JwtHelper
{
    public static function encode(array $payload, int $ttlSeconds): string
    {
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $header = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $body = self::b64url(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig = self::b64url(hash_hmac('sha256', "{$header}.{$body}", acep_jwt_secret(), true));

        return "{$header}.{$body}.{$sig}";
    }

    /** @return array<string,mixed>|null */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "{$header}.{$body}", acep_jwt_secret(), true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode(self::b64urlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['exp']) && (int)$payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad < 4) {
            $data .= str_repeat('=', $pad);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'), true);
    }
}
