# 버그 리스트 — Phase 2 Step 5

**Updated:** 2026-07-21

| ID | Severity | Component | Summary | Status |
|----|----------|-----------|---------|--------|
| BUG-001 | **P0** | API Router | `/api/v1/system/health`, `/api/v1/health` → HTTP 500 on production | Open |
| BUG-002 | **P0** | Frontend Deploy | `/frontend/` serves dev `index.html` (`/src/main.tsx`), no built assets | Open |
| BUG-003 | **P1** | WebSocket | chat-server not on Cafe24 — :3001 blocked, /socket.io 404; needs VPS Hybrid | **Confirmed — infra** |
| BUG-004 | **P1** | E2E Blocker | ACEP tables migration not confirmed on production | Open |
| BUG-005 | **P2** | Local Dev | PHP 8.5 CLI missing `pdo_mysql` — migrate/tests blocked locally | Open |
| BUG-006 | **P2** | Config | `VITE_WS_URL` defaults to localhost — production `.env` required | Open |

---

## BUG-001 — ACEP Router HTTP 500

**재현**
```
GET https://plustok.mycafe24.com/api/v1/system/health → 500
GET https://plustok.mycafe24.com/api/v1/health → 500
GET https://plustok.mycafe24.com/api/v1/health.php → 200 (legacy OK)
```

**원인 추정**
1. ACEP `index.php` / bootstrap 파일 미배포 또는 불완전 배포
2. Cafe24 PHP < 8.2 (constructor property promotion)
3. bootstrap require fatal (missing `config/acep.local.php` 등)

**수정**
- [x] `index.php` bootstrap catch → `BOOTSTRAP_ERROR` JSON (배포 후 원인 확인 가능)
- [ ] Cafe24 PHP 버전 확인 (8.2+)
- [ ] 최신 `api/v1/*`, `includes/*` FTP/SFTP 배포
- [ ] 배포 후 `GET /system/health` 재검증

---

## BUG-002 — Frontend dist 미배포

**재현**
```
GET https://plustok.mycafe24.com/frontend/ → HTML with <script src="/src/main.tsx">
GET .../assets/index-*.js → 404
```

**수정**
- [x] `vite.config.ts` — `VITE_BASE_PATH` 지원
- [x] `.env.production.example` — `/frontend/` base
- [ ] `npm run build` → `dist/*` → `/www/frontend/` 업로드

---

## BUG-003 — WebSocket 미운영

**조치**
- [ ] `chat-server` 빌드 & PM2
- [ ] Nginx `location /socket.io/` → proxy 3001
- [ ] `JWT_SECRET` 동기화

---

## BUG-004 — Migration 미확인

**조치**
```bash
php migrations/migrate.php --check
php scripts/validate_production.php
```

---

## BUG-005 — 로컬 PDO driver

**조치:** PHP.ini `extension=pdo_mysql` 활성화 또는 Cafe24 SSH에서만 migration 실행

---

## BUG-006 — WS URL

**조치:** Production `.env`:
```
VITE_WS_URL=wss://plustok.mycafe24.com/socket.io
```
(Nginx proxy 경로에 맞게 조정)

---

## 우선순위 작업 순서

1. BUG-001 → BUG-004 (API + DB)
2. BUG-002 (Frontend dist)
3. BUG-003 + BUG-006 (WebSocket)
4. E2E-01 수동 재실행
