# Phase 3 배포 체크리스트 (P0)

**대상:** `https://plustok.mycafe24.com` (Cafe24 웹호스팅)  
**BUG-003:** chat-server 보류 (별도 Node 호스팅 예정)

---

## A. DB 마이그레이션 (phpMyAdmin 또는 SSH)

### A-1. 사전 백업
- [ ] phpMyAdmin → 전체 DB Export (Structure + Data)

### A-2. 마이그레이션 실행

SSH 가능 시:
```bash
cd /path/to/www
php migrations/migrate.php
php migrations/phase3_chat_rooms.php   # migrate.php에 포함 안 되면 별도
```

phpMyAdmin 수동 import:
1. `migrations/V3.0.1__phase3_crm.sql` — Import
2. `chat_rooms` ALTER (아래 SQL):

```sql
ALTER TABLE chat_rooms
  ADD COLUMN IF NOT EXISTS legacy_consult_id BIGINT NULL,
  ADD COLUMN IF NOT EXISTS crm_save_status ENUM('pending','saved','failed') NULL,
  ADD COLUMN IF NOT EXISTS crm_saved_at DATETIME NULL;
```

### A-3. 검증
```bash
php scripts/validate_production.php
```

기대: Phase 3 테이블 `consults`, `schedules`, `customer_bridge` 등 **OK**

| 테이블 | 용도 |
|--------|------|
| `consults` | CRM 상담 저장 |
| `schedules` | 후속 일정 |
| `customer_bridge` | ACEP↔legacy 고객 |
| `chat_rooms.crm_save_status` | 종료 CRM 상태 |

---

## B. FTP/SFTP 업로드 목록

### B-1. API 코어 (필수)
```
api/v1/bootstrap.php
api/v1/router.php
api/v1/index.php
includes/AcepHttpResponse.php
includes/api_envelope.php
includes/middleware/JwtMiddleware.php
includes/middleware/CorsMiddleware.php
```

### B-2. Phase 3 CRM
```
includes/services/CrmCloseService.php
includes/services/CrmAiPipeline.php
includes/repositories/ConsultRepository.php
includes/repositories/ScheduleRepository.php
includes/repositories/CustomerBridgeRepository.php
includes/util/CrmSchema.php
includes/services/ChatService.php          # CRM 연동 포함 최신본
```

### B-3. Phase 3 Admin API
```
includes/services/AdminStatsService.php
includes/services/AdminAgentService.php
includes/services/AdminMonitorService.php
includes/services/AdminConsultService.php
includes/services/AdminPromptService.php      ← P1 신규
includes/services/AdminFailoverService.php    ← P1 신규
```

### B-4. Frontend (React dist)
```
frontend/index.html
frontend/assets/*
frontend/.htaccess
```
(`npm run build` 후 `dist/*` 업로드 — 소스 `/frontend/src/` 업로드 금지)

### B-5. 업로드 금지
```
.env, config/database.php, logs/, node_modules/, .git/
```

---

## C. 프로덕션 스모크 테스트

Admin JWT 로그인 후:

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  https://plustok.mycafe24.com/api/v1/admin/stats/overview

curl -s -H "Authorization: Bearer $TOKEN" \
  https://plustok.mycafe24.com/api/v1/admin/prompts

curl -s -H "Authorization: Bearer $TOKEN" \
  https://plustok.mycafe24.com/api/v1/admin/failover-logs
```

| # | Endpoint | 기대 |
|---|----------|------|
| 1 | `GET /api/v1/health` | 200 |
| 2 | `GET /admin/stats/overview` | 200 + KPI JSON |
| 3 | `GET /admin/prompts` | 200 (admin JWT) |
| 4 | `GET /admin/failover-logs` | 200 + summary |
| 5 | `POST /consults/close` | 200/422 (테스트 room) |

브라우저:
- [ ] `https://plustok.mycafe24.com/frontend/login`
- [ ] admin 계정 → `/frontend/admin/dashboard`

---

## D. JWT / 설정 동기화

| 항목 | 위치 |
|------|------|
| `ACEP_JWT_SECRET` | `config/acep.local.php` |
| Admin 로그인 | `agents.role` = admin 또는 operator |

---

## E. 완료 기준

- [ ] validate_production.php PASS
- [ ] Admin stats API 200
- [ ] CRM close → consults 행 생성 (테스트 room)
- [ ] Frontend Admin Dashboard 렌더
- [ ] chat-server — **별도 Node 호스팅 준비 후** 진행
