# Cafe24 — agents.email 등록 & 비밀번호 찾기 진단

**목적:** 비밀번호 재설정 메일 미수신 원인 확인 (phpMyAdmin 진단 SQL + 1회용 등록 스크립트)

## 1. 진단 SQL (phpMyAdmin)

```sql
-- 1) 대상 계정 email 컬럼 존재 여부 (NULL이면 메일 발송 안 됨)
SELECT id, login_id, name,
       CASE WHEN email IS NULL OR email = '' THEN 'EMPTY' ELSE 'SET' END AS email_status,
       reset_token_hash IS NOT NULL AS has_reset_token,
       reset_token_expires_at
FROM agents
WHERE login_id = 'admin'   -- ← 실제 login_id 로 변경
  AND deleted_at IS NULL;

-- 2) 비밀번호 찾기 요청 후 토큰이 생성됐는지 (생성됐는데 메일 없음 → mail() 문제)
SELECT login_id, reset_token_hash, reset_token_expires_at, updated_at
FROM agents
WHERE login_id = 'admin'
  AND deleted_at IS NULL;

-- 3) reset_token 컬럼 자체가 없으면 마이그레이션 필요
SHOW COLUMNS FROM agents LIKE 'reset_token%';
```

| email_status | reset_token | 해석 |
|---|---|---|
| EMPTY | — | email 미등록 → `set_agent_email_once.php` 실행 필요 |
| SET | NULL | email 있으나 요청 전, 또는 login_id/email 불일치로 early return |
| SET | NOT NULL | 토큰 생성됨 → Cafe24 `mail()` 발송 실패 가능성 |

## 2. email 등록 (1회용 스크립트)

**파일:** `admin/set_agent_email_once.php` (FTP 업로드)

```
/admin/set_agent_email_once.php?key=plustok-set-email-20260722&login_id=admin&email=실제@이메일.com
```

성공 시 `OK: email registered` 출력 → **즉시 파일 삭제**

> phpMyAdmin에서 `UPDATE agents SET email='...'` 하지 마세요. PiiEncryptor 암호화 필수.

## 3. reset_token 컬럼 없을 때

`migrations/cafe24_add_password_reset.sql` 실행

## 4. mail() 한계 (Cafe24)

토큰은 생성됐는데 메일이 스팸·받은편지함 모두 없으면:

- Cafe24 공유호스팅 `mail()` 은 외부 수신(네이버·Gmail 등) 차단/지연이 잦음
- `config/app.php` 의 `MAIL_FROM` 이 `noreply@본인도메인.mycafe24.com` 형태인지 확인
- 장기 해결: SMTP(네이버/구글/Gmail API 등) 연동 검토 — 현재 코드는 PHP `mail()` 만 사용
