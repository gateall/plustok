# DEPLOY_REPORT — Phase 3: Deployment & Verification

**Project:** PlusTok ACEP V3.0  
**Date:** 2026-07-22 (KST)  
**Verifier:** Cursor Agent (Phase 3 automated + remote checks)  
**Git remote:** https://github.com/gateall/plustok.git  

---

## Executive Summary

| Overall | **PARTIAL PASS** |
|---------|------------------|
| Git commit & push | ✅ PASS |
| Render Chat Server (infra) | ⚠️ PARTIAL — `/health` 200 but **pre-integration build still running** |
| Cafe24 Backend API | ✅ PASS |
| Cafe24 Frontend | ⚠️ PARTIAL — **built `dist` deployed**; SPA rewrite (`.htaccess`) **missing** |
| Socket.io handshake | ✅ PASS |
| Full E2E (login → chat → DB → broadcast) | ❌ BLOCKED — no production credentials; Render not on latest build |

**Bottom line:** Code is committed and pushed to `main`. Render has **not** picked up commit `922a2594` yet (health response still legacy format). Cafe24 frontend serves the **correct production bundle** at `/frontend/`, but deep links like `/frontend/login` return **404** until `.htaccess` is uploaded. Operator must trigger Render redeploy, verify env vars, upload `.htaccess`, and run manual E2E with real accounts.

---

## 1. Git Status & Commit

| Item | Result |
|------|--------|
| Repository root | `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\` |
| Commit | `922a2594` — *Unified auth and Render chat-server integration for Phase 3 deploy.* |
| Push | ✅ `2b0c1725..922a2594  main -> main` |
| Prior commit | `2b0c1725` — Add Dockerfile for Render deployment |

**Included in `922a2594` (35 files):**
- Unified auth: `config/acep.users.php`, `admin/index.php`, `api/v1/auth.php`, `AuthService.php`
- Chat-server: `auth.ts`, enhanced `server.ts`, `redis.adapter.ts`, `pingBackend()`
- Frontend: production build `index-HOZ3f0Ns.js`, `useSocket.tsx`, `.env.production`

---

## 2. Render Auto Deploy (Step 4)

| Check | Result | Evidence |
|-------|--------|----------|
| GitHub push received | ✅ Assumed (push exit 0) | `main -> main` |
| Render rebuild triggered | ⚠️ **Not confirmed** | Health still returns **legacy JSON** |
| New build live | ❌ **No** | Missing `backend`, `jwt`, `redis` fields |

### Health polling (post-push)

| Time (KST) | URL | HTTP | Response |
|------------|-----|------|----------|
| ~14:20 | `GET /health` | 200 | `{"status":"healthy","uptimeSec":169.78...}` |
| ~14:34 | `GET /health` | 200 | `{"status":"healthy","uptimeSec":1066.42...}` |
| ~14:36 | `GET /health` | 200 | `{"status":"healthy","uptimeSec":1179.06...}` |
| ~14:37 | `GET /health` | 200 | `{"status":"healthy","uptimeSec":1241.39...}` |

**Interpretation:** `uptimeSec` increased continuously (~20+ min) without response-shape change → **same container instance**, not redeployed with `922a2594`. Expected new format (from `chat-server/src/server.ts`):

```json
{
  "status": "healthy|degraded",
  "uptimeSec": 0,
  "backend": { "url": "...", "reachable": true, "latencyMs": 180 },
  "jwt": { "configured": true },
  "redis": { "adapter": false, "pubsub": false }
}
```

**Operator action:** Render Dashboard → **Manual Deploy** → verify build log shows commit `922a2594`. Confirm Root Directory `www/chat-server`, Dockerfile Path `Dockerfile`.

---

## 3. Environment Variables (Step 5)

| Variable | Required | Remote verify? | Notes |
|----------|----------|----------------|-------|
| `JWT_SECRET` | ✅ | ⚠️ Indirect | Must match Cafe24 `ACEP_JWT_SECRET`. Legacy health does not expose `jwt.configured`. |
| `BACKEND_URL` | ✅ | ⚠️ Indirect | Expected: `https://plustok.mycafe24.com/api/v1`. New `/health` would show `backend.url`. |
| `CORS_ALLOWED_ORIGINS` | ✅ | ❌ Dashboard only | Work order says `CORS_ORIGIN`; **code uses `CORS_ALLOWED_ORIGINS`**. Value: `https://plustok.mycafe24.com` |
| `PORT` | ✅ | ✅ Auto | Render injects at runtime. Server reads `process.env.PORT`. |
| `NODE_ENV` | Recommended | ❌ Dashboard | Dockerfile sets `production`. |
| `REDIS_URL` | Optional | ⚠️ Via new health | If unset: in-memory adapter, pub/sub disabled. |

**Cannot fully verify without Render Dashboard or post-deploy enhanced `/health`.**

---

## 4. Health Check (Step 6)

### Render Chat Server

```
GET https://plustok.onrender.com/health
HTTP 200 OK
Body: {"status":"healthy","uptimeSec":1241.39920213}
```

✅ **200 OK** — service reachable  
⚠️ **Legacy payload** — deploy pending

### Cafe24 PHP Backend

```
GET https://plustok.mycafe24.com/api/v1/health
HTTP 200 OK
Body: {"success":true,"data":{"status":"ok","timestamp":"2026-07-22T14:34:55+09:00","version":"1.5"},...}
```

✅ **200 OK**

---

## 5. Socket.io Connection (Step 7)

### Polling handshake (unauthenticated transport layer)

```
GET https://plustok.onrender.com/socket.io/?EIO=4&transport=polling
HTTP 200
Body: 0{"sid":"Lc3kP0Ax__xQXWvaAAAC","upgrades":["websocket"],"pingInterval":25000,"pingTimeout":20000,...}
```

✅ Socket.io engine accepts connections.

### Authenticated flow (not executed)

| Step | Status | Reason |
|------|--------|--------|
| Frontend Login → JWT | ❌ Not tested | Production credentials not available to agent |
| Socket connect + JWT | ❌ Not tested | Requires valid token |
| `room:join` | ❌ Not tested | Requires room + token |
| `message:send` → REST | ❌ Not tested | Requires active chat session |
| `message:receive` broadcast | ❌ Not tested | Requires two clients |

**Manual test script (operator):**

1. Login at `https://plustok.mycafe24.com/frontend/` (root, not `/login` until `.htaccess` fixed)
2. DevTools → Network → WS → confirm `wss://plustok.onrender.com/socket.io`
3. Emit `room:join` with valid `roomId`
4. Emit `message:send`; verify `POST /api/v1/chats/{roomId}/messages` 201
5. Confirm `message:receive` on second browser/tab

---

## 6. Browser Console Errors (Step 8)

| Page | Load | Console |
|------|------|---------|
| `https://plustok.mycafe24.com/frontend/` | ✅ 200, built JS | ⚠️ Not automated (browser MCP unavailable) |
| `https://plustok.mycafe24.com/frontend/login` | ❌ 404 Apache | N/A — server-side 404 before React loads |
| `https://plustok.mycafe24.com/admin/` | ✅ 200 | ⚠️ Manual check recommended |

### Frontend deployment status (updated since initial handoff)

**Before (handoff):** `index.html` referenced dev entry `/src/main.tsx`  
**Now (verified 14:34 KST):**

```html
<script type="module" crossorigin src="/frontend/assets/index-HOZ3f0Ns.js"></script>
<link rel="stylesheet" crossorigin href="/frontend/assets/index-CwFaCoof.css">
```

| Asset | HTTP |
|-------|------|
| `/frontend/assets/index-HOZ3f0Ns.js` | 200 |
| `/frontend/assets/index-CwFaCoof.css` | 200 |

✅ Production bundle deployed.  
⚠️ `/frontend/login` → **404** — upload `frontend/dist/.htaccess` for SPA rewrite.

---

## 7. Render Logs (Step 9)

| Item | Status |
|------|--------|
| Dashboard log access | ❌ Not available to agent |
| Error / Warning / Unhandled Exception | ⚠️ **Manual review required** |

**Operator:** Render Dashboard → Service `plustok` → Logs → filter after deploy trigger. Look for:
- `[startup] Backend OK` / `Backend unreachable`
- `[startup] JWT_SECRET not set`
- `[redis] REDIS_URL not set`
- Docker build failures for commit `922a2594`

---

## 8. Redis Pub/Sub (Step 10)

| Check | Result |
|-------|--------|
| `REDIS_URL` configured | ⚠️ Unknown (legacy health) |
| `redis.adapter` | ⚠️ Pending new deploy |
| `redis.pubsub` | ⚠️ Pending new deploy |

**Expected without Redis:** both `false` in enhanced health — acceptable for single Render instance.

**Operator (optional):** Set `REDIS_URL` on Render + Redis Cloud; redeploy; confirm health shows `redis.adapter: true`.

---

## 9. Final E2E Matrix (Step 11)

| Scenario | Result | Notes |
|----------|--------|-------|
| Admin Login (`/admin/`) | ✅ Page loads | Form present; credential login not attempted |
| Customer / Agent Frontend Login | ⚠️ Partial | App loads at `/frontend/`; `/frontend/login` 404 |
| Chat creation | ❌ Blocked | Needs authenticated session |
| Message send → DB | ❌ Blocked | Needs chat + JWT |
| Cross-browser realtime | ❌ Blocked | Needs WS on latest Render build |
| Reconnect + history | ❌ Blocked | Manual |
| Logout | ❌ Blocked | Manual |
| JWT expiry | ❌ Blocked | Manual |

---

## 10. Unified Login (Frontend ↔ Admin)

| Surface | Auth | SSOT |
|---------|------|------|
| Admin `/admin/` | `AcepUserManager::authenticate()` | `agents.login_id` + `password_hash` |
| Frontend `/api/v1/auth/login` | `AuthService::login()` | same `agents` row |

Same ID/password on both surfaces when the account is in `agents`. Admin legacy `managers` fallback works on Admin only — migrate to `agents` for Frontend parity.

Until `frontend/dist/.htaccess` is on Cafe24, use `/frontend/` not `/frontend/login` (Apache 404).

---

## 11. Operator Action Items

### P0 — Required before full E2E

1. **Render manual redeploy** — confirm commit `922a2594` live; `/health` shows `backend` + `jwt` fields.
2. **Render env vars** — `JWT_SECRET`, `BACKEND_URL`, `CORS_ALLOWED_ORIGINS` (see §3).
3. **Cafe24 FTP** — upload `www/frontend/dist/.htaccess` (SPA rewrite for `/frontend/login`, etc.).
4. **Cafe24 FTP** — upload unified auth PHP if not yet on server:
   - `config/acep.users.php`
   - `admin/index.php`
   - `api/v1/auth.php`, `api/v1/router.php`
   - `includes/auth.php`, `AuthService.php`

### P1 — Verification

5. Manual E2E with production agent account (see §5 script).
6. Render Logs review post-deploy.
7. Optional: `REDIS_URL` for multi-instance / PHP→WS bridge.
8. Confirm Frontend login with same `agents.login_id` as Admin (§10).

---

## 12. Test Evidence Summary

```
# Git
922a2594 Unified auth and Render chat-server integration for Phase 3 deploy.
Push: 2b0c1725..922a2594 main -> main

# Render
curl https://plustok.onrender.com/health → 200 {"status":"healthy","uptimeSec":...}  [LEGACY]
curl https://plustok.onrender.com/socket.io/?EIO=4&transport=polling → 200 handshake OK

# Cafe24 Backend
curl https://plustok.mycafe24.com/api/v1/health → 200 {"success":true,"data":{"status":"ok",...}}

# Cafe24 Frontend
curl https://plustok.mycafe24.com/frontend/ → 200 (built assets index-HOZ3f0Ns.js)
curl https://plustok.mycafe24.com/frontend/login → 404 (missing .htaccess rewrite)
curl https://plustok.mycafe24.com/admin/ → 200
```

---

## 13. Sign-off

| Phase | Verdict |
|-------|---------|
| Phase 3 — Git & Push | ✅ Complete |
| Phase 3 — Render Deploy | ⚠️ Pending operator redeploy |
| Phase 3 — Cafe24 Frontend | ⚠️ Dist OK; `.htaccess` needed |
| Phase 3 — E2E | ❌ Blocked (credentials + deploy) |

**Next checkpoint:** After Render redeploy + `.htaccess` upload, re-run `/health` and manual E2E; update this report with PASS/FAIL per scenario.

---

*Generated: 2026-07-22T14:38+09:00*
