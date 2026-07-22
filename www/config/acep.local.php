<?php
declare(strict_types=1);
/**
 * ACEP V3.0 로컬/운영 설정 오버라이드
 * ⚠️ .gitignore에 추가됨 (민감 정보 보호)
 */

// ============================================================================
// 1. JWT Secret (운영용 — 강력한 무작위 값)
// ============================================================================
// 생성 방법: php -r "echo bin2hex(random_bytes(32));"
// 또는 OpenSSL: openssl rand -hex 32
define('ACEP_JWT_SECRET', 'plustok_jwt_secret_2026_07_22_enterprise_production_key_change_me_periodically');

// ============================================================================
// 2. PII 암호화 키 (AES-256-GCM, 32 bytes base64)
// ============================================================================
// 생성 방법: php -r "echo base64_encode(random_bytes(32));"
define('ACEP_PII_KEY', base64_encode(hash('sha256', ACEP_JWT_SECRET . '_pii_salt', true)));

// ============================================================================
// 3. Redis 설정 (WebSocket pub/sub, AI cache)
// ============================================================================
// 로컬 개발: redis://localhost:6379
// Cafe24: redis://localhost:6379 (내부 Redis 사용 가능 여부 확인 필요)
// 프로덕션: Redis Cloud URL 설정 필요
// define('ACEP_REDIS_URL', 'redis://default:password@host:6379');
define('ACEP_REDIS_URL', '');  // 로컬/개발용: 빈 값 = Redis 사용 안 함

// ============================================================================
// 4. 선택적: 추가 설정 오버라이드
// ============================================================================
// define('ACEP_JWT_ACCESS_TTL', 86400);    // 24h
// define('ACEP_LOGIN_MAX_FAIL', 3);
// define('ACEP_LOGIN_LOCK_MINUTES', 30);
// define('ACEP_PASSWORD_COST', 12);

?>
