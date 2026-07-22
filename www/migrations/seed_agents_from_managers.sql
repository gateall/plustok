-- Cafe24 phpMyAdmin: Frontend 로그인용 agents 계정 준비
-- 전제: migrations/V1.5.0__agents_ai_ops.sql 실행 완료
-- 목적: Admin(managers)과 Frontend(agents) 동일 ID/비밀번호

INSERT INTO agents (
    id, login_id, password_hash, name, role, status,
    failed_login_count, created_at, updated_at
)
SELECT
    UUID(),
    m.login_id,
    m.password,
    COALESCE(NULLIF(TRIM(m.name), ''), m.login_id),
    CASE
        WHEN m.role IN ('super', 'admin') THEN 'admin'
        WHEN m.role = 'manager' THEN 'operator'
        ELSE 'agent'
    END,
    'offline',
    0,
    NOW(3),
    NOW(3)
FROM managers m
WHERE m.status = 1
  AND m.login_id IS NOT NULL
  AND TRIM(m.login_id) <> ''
  AND NOT EXISTS (
    SELECT 1 FROM agents a
    WHERE a.login_id = m.login_id AND a.deleted_at IS NULL
  );

-- 확인
-- SELECT login_id, name, role FROM agents ORDER BY login_id;
