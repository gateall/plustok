-- Cafe24 phpMyAdmin: Frontend 로그인용 agents 계정 준비
-- 1) migrations/V1.5.0__agents_ai_ops.sql 실행 후
-- 2) managers 계정을 agents로 복사 (Admin과 Frontend 동일 ID/비밀번호)

INSERT INTO agents (
    id, login_id, password_hash, name, role, status,
    failed_login_count, created_at, updated_at
)
SELECT
    UUID(),
    m.login_id,
    m.password,
    COALESCE(m.name, m.login_id),
    'admin',
    'offline',
    0,
    NOW(3),
    NOW(3)
FROM managers m
WHERE m.status = 1
  AND NOT EXISTS (
    SELECT 1 FROM agents a WHERE a.login_id = m.login_id AND a.deleted_at IS NULL
  )
LIMIT 20;

-- 확인: SELECT login_id, role FROM agents;
