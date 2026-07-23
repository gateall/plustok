<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/acep.php';

final class PiiEncryptor
{
    public static function encrypt(string $plain): string
    {
        $key = acep_pii_key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('PII encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Invalid PII ciphertext');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', acep_pii_key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('PII decryption failed');
        }
        return $plain;
    }

    /** agents.email — 암호화 저장 우선, 레거시 평문(수동 SQL) 폴백 */
    public static function decryptEmail(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }
        try {
            return self::decrypt($stored);
        } catch (Throwable) {
            if (filter_var($stored, FILTER_VALIDATE_EMAIL)) {
                return $stored;
            }
            return null;
        }
    }

    public static function phoneHash(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        return hash('sha256', $digits);
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) >= 10) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3, 4) . '-****';
        }
        return '****';
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '****';
        }
        $local = $parts[0];
        $shown = strlen($local) > 2 ? substr($local, 0, 2) : $local;
        return $shown . '@****.' . (explode('.', $parts[1])[1] ?? 'com');
    }

    public static function maskAddress(string $address): string
    {
        $trim = trim($address);
        if ($trim === '') {
            return '';
        }
        $words = preg_split('/\s+/u', $trim) ?: [];
        if (count($words) <= 2) {
            return $words[0] . ' ****';
        }
        return implode(' ', array_slice($words, 0, 2)) . ' ****';
    }
}
