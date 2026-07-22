# Phase 2 Step 5 — E2E 테스트 보고서

**프로젝트:** PlusTok V3.0 ACEP  
**환경:** Staging/Production `https://plustok.mycafe24.com`  
**검증일:** 2026-07-21  
**검증자:** Cursor Agent (자동 + 원격 스모크)

---

## 1. 요약

| 구분 | 결과 |
|------|------|
| **로컬 Frontend (Vitest + Build)** | ✅ PASS (17/17 tests, build OK) |
| **로컬 Backend Migration** | ⚠️ BLOCKED (CLI PHP 8.5 — PDO MySQL driver 미설치) |
| **Production Legacy API** | ✅ PASS (`/api/v1/health.php` → 200, `db:true`) |
| **Production ACEP Router API** | ❌ FAIL (`/api/v1/system/health` → **HTTP 500**) |
| **Production Frontend** | ❌ FAIL (dev `index.html`만 배포, `dist`/`assets` 미배포) |
| **WebSocket (chat-server)** | ⏳ 미검증 (Cafe24 외부 포트 3001 접근 불가) |
| **실시간 채팅 / AI / Dashboard E2E** | ⏳ ACEP API 미배포로 차단 |

**판정:** **조건부 보류 (Conditional Hold)** — 배포·인프라 이슈 해결 후 재검증 필요

---

## 2. 검증 항목별 결과

### 2.1 Cafe24 DB Migration

| # | 항목 | 결과 | 비고 |
|---|------|------|------|
| 1 | `migrate.php --check` | ⏳ | 로컬 CLI: PDO driver 없음. **서버 SSH에서 실행 필요** |
| 2 | `V1.0.0` ~ `V1.5.3` DDL | ⏳ | `health.php` DB 연결 OK → DB 자체는 동작 |
| 3 | ACEP 14+ 테이블 | ⏳ | `scripts/validate_production.php` 서버 실행 필요 |
| 4 | Seed (admin) | ⏳ | 수동 확인 필요 |

**레거시 확인:** `GET /api/v1/health.php` → `{"db":true}` ✅

---

### 2.2 API 전체 점검

| Endpoint | Method | 결과 | HTTP |
|----------|--------|------|------|
| `/api/v1/health.php` (legacy) | GET | ✅ PASS | 200 |
| `/api/v1/system/health` (ACEP) | GET | ❌ FAIL | 500 |
| `/api/v1/health` (V1.5) | GET | ❌ FAIL | 500 |
| `/api/v1/auth/login` | POST | ⏳ | 미실행 (자격증명 보호) |
| `/api/v1/chats/rooms` | GET | ⏳ | Router 500으로 차단 |
| `/api/v1/dashboard/stats` | GET | ⏳ | Router 500으로 차단 |

**원인 추정 (BUG-001):**
- ACEP `api/v1/index.php` + bootstrap 미배포 또는 PHP 8.2+ 미충족
- bootstrap fatal → 기존 500 (수정: `BOOTSTRAP_ERROR` JSON 반환 추가)

**서버 실행 스크립트:** `php scripts/e2e_smoke.php --base=https://plustok.mycafe24.com/api/v1`

---

### 2.3 WebSocket 연결

| # | 항목 | 결과 |
|---|------|------|
| 1 | chat-server `:3001/health` | ⏳ Cafe24에서 Node 프로세스·포트 개방 확인 필요 |
| 2 | JWT `auth.token` (Bearer 없음) | ✅ 코드 일치 (`useSocket.tsx` ↔ chat-server) |
| 3 | `room:join`, `message:receive` | ⏳ API+WS 배포 후 수동 E2E |

---

### 2.4 React Frontend 연결

| # | 항목 | 결과 |
|---|------|------|
| 1 | `npm run test` | ✅ 17 PASS |
| 2 | `npm run build` | ✅ PASS |
| 3 | Production `/frontend/` | ❌ dev HTML (`/src/main.tsx` 참조) |
| 4 | Production assets | ❌ 404 |

**조치:** `frontend/.env.production.example` → `VITE_BASE_PATH=/frontend/` 로 빌드 후 `dist/*` 업로드

---

### 2.5 JWT 인증

| # | 항목 | 결과 |
|---|------|------|
| 1 | HS256 Secret 동기화 | ⏳ `acep.local.php` ↔ chat-server `.env` |
| 2 | Login → accessToken | ⏳ Router 배포 후 |
| 3 | Protected routes 401 | ⏳ |

---

### 2.6~2.8 실시간 채팅 / AI / Dashboard

ACEP Router 및 Frontend dist 배포 완료 전까지 **E2E-01 (08_TEST/03)** 시나리오 실행 불가.

로컬 단위 테스트:
- `ChatScreen.test.tsx` — 3-panel, room 선택, 메시지 전송 mock ✅
- `useSocket.test.tsx` — connect/emit/on ✅

---

## 3. 로컬 실행 결과

```text
npm run test  → 6 files, 17 tests PASS
npm run build → ✓ built in ~3s
```

```text
php migrations/migrate.php --check → FAIL (could not find driver — 로컬 PHP PDO mysql)
```

---

## 4. 재검증 절차 (Cafe24 SSH)

```bash
# 1. Migration
php migrations/migrate.php --check
php migrations/migrate.php
php migrations/migrate.php --seed

# 2. Validation
php scripts/validate_production.php

# 3. API Smoke
php scripts/e2e_smoke.php --base=https://plustok.mycafe24.com/api/v1

# 4. Frontend (로컬 빌드 후 FTP)
cd frontend && cp .env.production.example .env.production
npm run build
# dist/* → /www/frontend/

# 5. chat-server (PM2/systemd)
cd chat-server && npm run build && npm start
```

---

## 5. Sign-off

| 역할 | 상태 | 서명/일자 |
|------|------|-----------|
| QA | ⏳ 재검증 대기 | |
| Backend | ⏳ BUG-001 배포 | |
| Frontend | ⏳ BUG-002 dist 배포 | |
| Ops | ⏳ WS/Redis | |

**운영 배포 승인:** ❌ **보류** (버그리스트 P0 해결 후)

---

*Phase 2 Step 5 E2E 보고서 · 2026-07-21*
