# BUG: Frontend Login 500 — `/api/v1/auth/login`

**증상:** Frontend `#/login` → 「서버 오류가 발생했습니다」  
**API:** `POST /api/v1/auth/login` → HTTP 500, `error.code: MSG_SEND_FAILED`

---

## 원인 (코드 추적)

`api/v1/index.php` catch 블록이 **모든 uncaught Throwable**을 `MSG_SEND_FAILED`로 마스킹합니다.

로그인 흐름 (`AuthService::login`):

```
POST /auth/login
  → AgentRepository::findByLoginId()     ← agents 테이블 SELECT
  → password_verify()
  → incrementFailedLogin / updateLoginSuccess
  → AuditService::agentAction()          ← audit_logs INSERT
  → JwtHelper::encode()                  ← JWT 발급
```

### 재현 패턴별 원인

| 패턴 | 원인 | HTTP (수정 후) |
|------|------|----------------|
| 존재하지 않는 ID도 500 | `agents` 테이블 없음 / `deleted_at` 컬럼 없음 | 503 `AUTH_DB_ERROR` |
| Admin OK, Frontend 500 | Admin=`managers` legacy, Frontend=`agents` only | 401 또는 503 |
| 올바른 ID/비번인데 500 | `audit_logs` 없음 (V1.5 미적용) | 수정: audit 실패 시 로그인 허용 |
| JWT 설정 문제 | `acep.local.php` JWT_SECRET | 500 `AUTH_TOKEN_ERROR` |

---

## 진단

```bash
curl -s https://plustok.mycafe24.com/api/v1/system/health
```

**기대 (코드 배포 후):**
```json
{
  "components": {
    "database": { "status": "up" },
    "agents": { "status": "up" },
    "audit_logs": { "status": "up" }
  },
  "hint": null
}
```

`agents.status: down` → **V1.5.0 마이그레이션 필요**

---

## 해결 (Cafe24 phpMyAdmin)

### 1. 마이그레이션
```
migrations/V1.5.0__agents_ai_ops.sql
```

### 2. Admin 계정 → agents 복사
```
migrations/seed_agents_from_managers.sql
```

### 3. FTP 업로드
- `includes/services/AuthService.php`
- `api/v1/router.php`
- `config/acep.users.php` (없으면)

### 4. JWT (Render 연동 시)
- `config/acep.local.php` → `ACEP_JWT_SECRET` 설정

---

## 코드 수정 (3882a2cb+)

- `AuthService`: PDO/Audit/JWT 단계별 명확한 에러 코드
- `audit_logs` 없어도 로그인 성공 (`safeAudit`)
- `/system/health`: `agents`, `audit_logs` 컴포넌트 추가

---

*2026-07-22*
