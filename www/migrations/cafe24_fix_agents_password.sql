-- Cafe24 phpMyAdmin: agents.password_hash 재동기화
-- 증상: Admin(managers) 로그인은 되지만 Frontend(agents) 로그인 실패
-- 원인: seed 시 managers.password 가 bcrypt 해시가 아니거나 agents 행이 잘못 생성됨
--
-- 1) managers 와 동일 bcrypt 해시로 agents.password_hash 동기화 (권장)
UPDATE agents a
INNER JOIN managers m ON m.login_id = a.login_id AND m.status = 1
SET a.password_hash = m.password,
    a.updated_at = NOW(3)
WHERE a.deleted_at IS NULL
  AND m.password IS NOT NULL
  AND TRIM(m.password) <> '';

-- 확인: 해시가 $2y$ 로 시작하는 bcrypt 여야 함
-- SELECT a.login_id, LEFT(a.password_hash, 7) AS hash_prefix FROM agents a LIMIT 20;

-- 2) 특정 계정만 수동 bcrypt 설정 (아래 해시는 예시 — 실제 비밀번호에 맞게 교체)
-- PHP: echo password_hash('YOUR_PASSWORD', PASSWORD_BCRYPT);
-- UPDATE agents SET password_hash = '$2y$10$...' WHERE login_id = 'your_id' AND deleted_at IS NULL;
