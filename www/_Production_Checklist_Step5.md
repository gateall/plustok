# Production Checklist — Phase 2 Step 5

**Target:** `https://plustok.mycafe24.com`  
**Date:** 2026-07-21

---

## A. 인프라 / DB

| # | Check | Status | Owner |
|---|-------|--------|-------|
| A-01 | PHP >= 8.2 (ACEP router) | ☐ | Ops |
| A-02 | PDO MySQL extension enabled | ☐ | Ops |
| A-03 | `config/database.php` configured | ☐ | Ops |
| A-04 | `config/acep.local.php` JWT secret set | ☐ | Ops |
| A-05 | `ACEP_REDIS_URL` (optional WS bridge) | ☐ | Ops |
| A-06 | `php migrations/migrate.php --check` PASS | ☐ | Backend |
| A-07 | `php migrations/migrate.php` applied | ☐ | Backend |
| A-08 | `php scripts/validate_production.php` PASS | ☐ | Backend |
| A-09 | DB backup before migration | ☐ | Ops |

---

## B. API (ACEP Router)

| # | Check | Status |
|---|-------|--------|
| B-01 | `GET /api/v1/system/health` → 200 | ☐ |
| B-02 | `GET /api/v1/health` → version 1.5 | ☐ |
| B-03 | `POST /api/v1/auth/login` → accessToken | ☐ |
| B-04 | `GET /api/v1/auth/me` JWT OK | ☐ |
| B-05 | Chat CRUD (rooms/messages) | ☐ |
| B-06 | AI recommendations GET | ☐ |
| B-07 | Dashboard stats GET | ☐ |
| B-08 | Legacy `health.php` still OK (fallback) | ☑ |

---

## C. WebSocket (chat-server)

| # | Check | Status |
|---|-------|--------|
| C-01 | Node.js 18+ installed | ☐ |
| C-02 | `chat-server` PM2/systemd running | ☐ |
| C-03 | Port 3001 / Nginx WSS proxy | ☐ |
| C-04 | `JWT_SECRET` = PHP `ACEP_JWT_SECRET` | ☐ |
| C-05 | `GET :3001/health` → healthy | ☐ |
| C-06 | Redis pub/sub (ai:update) | ☐ |

---

## D. Frontend

| # | Check | Status |
|---|-------|--------|
| D-01 | `npm run build` with `VITE_BASE_PATH=/frontend/` | ☐ |
| D-02 | `dist/` uploaded to `/www/frontend/` | ☐ |
| D-03 | `VITE_API_BASE=/api/v1` | ☐ |
| D-04 | `VITE_WS_URL` = production WSS | ☐ |
| D-05 | Login → ChatScreen 3-panel | ☐ |
| D-06 | No console errors | ☐ |

---

## E. E2E 시나리오 (08_TEST/03)

| # | Scenario | Status |
|---|----------|--------|
| E-01 | E2E-01 Happy Path (접수→채팅→AI→종료) | ☐ |
| E-02 | Room 전환 (E2E-02) | ☐ |
| E-03 | JWT refresh / reconnect | ☐ |
| E-04 | AI 추천 2초 이내 | ☐ |
| E-05 | Dashboard KPI 반영 | ☐ |

---

## F. 보안 / 성능

| # | Check | Status |
|---|-------|--------|
| F-01 | HTTPS only | ☐ |
| F-02 | JWT secret not default | ☐ |
| F-03 | Admin password changed from seed | ☐ |
| F-04 | API p95 < 500ms (health/login) | ☐ |
| F-05 | WS reconnect < 30s | ☐ |

---

## Legend

- ☑ Done / verified
- ☐ Pending
- ⛔ Blocked

**Current gate:** A-06~B-07, C-01~C-06, D-01~D-06 pending before production approval.
