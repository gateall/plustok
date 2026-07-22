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

---

## Phase 2 — Admin Messaging & GO-LIVE

**Date:** 2026-07-22 (KST)  
**Verifier:** Cursor Agent (Phase 2 code complete + remote infra checks)  
**Commits (Phase 2 chain):**

| Commit | Description |
|--------|-------------|
| `d4e07aae` | Add real-time messaging to admin consult detail page (`admin/consults/view.php`) |
| `237988a3` | Document unified Frontend/Admin login credentials in deploy report |
| `922a2594` | Unified auth and Render chat-server integration for Phase 3 deploy |

**Push:** ✅ `237988a3..d4e07aae  main -> main` (verified up-to-date on `origin/main`)

---

### Phase 2 — Completed Items Checklist

| Item | Status | Notes |
|------|--------|-------|
| Backend chat-server (`www/chat-server/`) | ✅ Complete | Dockerfile, auth middleware, message/room handlers |
| WebSocket / Socket.io protocol | ✅ Complete | SSOT events: `room:join`, `message:send`, `message:receive` |
| JWT auth (Frontend + Admin → WS) | ✅ Complete | `auth: { token }` from `$_SESSION['acep_jwt']` / `AuthService` |
| Admin consult detail messaging UI | ✅ Complete | `view.php` — message list, input, Socket.io client (commit `d4e07aae`) |
| REST message persistence | ✅ Complete | `GET/POST /api/v1/chats/{roomId}/messages` via PHP backend |
| Unified auth (agents SSOT) | ✅ Complete | Admin + Frontend share `agents.login_id` |
| Render auto-deploy trigger | ✅ Pushed | Git push received; Render may still run legacy build (see §2) |
| Full E2E (live browser) | ⚠️ **Operator manual** | No production credentials available to agent |

---

### Phase 2 — Test Result Table

| Test | Result | Evidence |
|------|--------|----------|
| Git commit & push (`d4e07aae`) | ✅ PASS | `git push origin main` → *Everything up-to-date* |
| Render `/health` HTTP 200 | ✅ PASS | `curl https://plustok.onrender.com/health` → 200 |
| Render enhanced health payload | ⚠️ BLOCKED | Still legacy: `{"status":"healthy","uptimeSec":…}` — no `backend`/`jwt` fields |
| Socket.io polling handshake | ✅ PASS | `GET …/socket.io/?EIO=4&transport=polling` → 200, sid returned |
| Cafe24 API health | ✅ PASS | `GET https://plustok.mycafe24.com/api/v1/health` → 200 |
| Admin page load (`/admin/`) | ✅ PASS | HTTP 200 |
| Admin consult detail messaging UI (code) | ✅ PASS | `view.php` +254 lines: list, form, Socket.io handlers |
| Admin message input / send (live) | ⚠️ **Operator manual** | Requires agents login + consult with linked `chat_rooms` row |
| WS `room:join` event (live) | ⚠️ **Operator manual** | DevTools → Network → WS frames after login |
| WS `message:send` → REST persist (live) | ⚠️ **Operator manual** | Verify `POST /api/v1/chats/{roomId}/messages` 201 in Network tab |
| WS `message:receive` broadcast (live) | ⚠️ **Operator manual** | Second client/tab must show bubble without refresh |
| DevTools console 0 errors (live) | ⚠️ **Operator manual** | Agent cannot run authenticated browser session |
| Multi-client realtime sync | ⚠️ **Operator manual** | Browser A (Admin) send → Browser B (Frontend) receive |
| Frontend SPA deep links | ❌ FAIL | `/frontend/login` → 404 (`.htaccess` not on Cafe24) |

---

### Phase 2 — GO-LIVE Approval Status

| Gate | Verdict |
|------|---------|
| **Code readiness** | ✅ **APPROVED** — Admin messaging implemented per SSOT |
| **Infra readiness** | ⚠️ **CONDITIONAL** — Render legacy health suggests env/build not fully verified |
| **Production GO-LIVE** | ❌ **NOT APPROVED** — Live E2E not executed; operator must complete manual steps below |

**Honest assessment:** Phase 2 **code is complete and pushed**. Remote infra checks pass for HTTP health and Socket.io handshake. **GO-LIVE cannot be signed off** until an operator runs the manual browser tests with a real `agents` account and confirms zero console errors plus multi-client message sync.

---

### Phase 2 — Operator Manual Test: Admin Messaging

Run in browser after FTP upload of `admin/consults/view.php` (commit `d4e07aae`) to Cafe24 if not auto-synced.

#### Prerequisites

1. Log in at `https://plustok.mycafe24.com/admin/` with an **agents** account (not legacy `managers` — JWT required).
2. Open a consult detail page that has a linked ACEP chat room (`detail_json.room_id` or `chat_rooms.legacy_consult_id`).
3. Confirm the **💬 실시간 상담 메시지** card appears (not the "채팅방이 없습니다" fallback).

#### Test 1 — Message input / send

1. Type a test message in the textarea and click **전송**.
2. Confirm the message bubble appears in the list (agent bubble, right-aligned, blue).
3. Connection status should show **채팅방 입장** or **연결됨**.

#### Test 2 — Network: Socket.io events

Open DevTools → **Network** → filter **WS** (WebSocket):

| Event | Direction | When |
|-------|-----------|------|
| `room:join` | C→S | On socket connect (payload: `{ roomId }`) |
| `room:joined` | S→C | After join succeeds |
| `message:send` | C→S | On form submit (payload: `{ roomId, content }`) |
| `message:receive` | S→C | After chat-server persists via REST and broadcasts |

Also check **Fetch/XHR** for REST persistence:

```
POST /api/v1/chats/{roomId}/messages  →  201 Created
GET  /api/v1/chats/{roomId}/messages  →  200 (initial history load)
```

> **SSOT note:** Do **not** expect `message:new_message` — ACEP uses `message:receive`.

#### Test 3 — DevTools console 0 errors

1. Open DevTools → **Console**.
2. Reload the consult detail page.
3. Send one test message.
4. **Pass criteria:** zero red errors; only expected `[consult-messaging]` logs on server-side errors.

#### Test 4 — Multi-client realtime sync

1. **Browser A:** Admin consult detail — send message *"Phase2 test A→B"*.
2. **Browser B:** Frontend chat screen for the same room (or second Admin tab on same consult).
3. **Pass criteria:** Browser B shows the message within ~2 s without page refresh.
4. Reverse: Browser B sends → Browser A receives via `message:receive`.

#### Failure triage

| Symptom | Likely cause |
|---------|--------------|
| Yellow JWT warning banner | Logged in via legacy `managers` — re-login with `agents` account |
| "채팅방이 없습니다" | Consult has no `chat_rooms` row — use a chat-linked consult |
| Connection error on status | Render not redeployed / `JWT_SECRET` mismatch / CORS |
| Send works but no receive | Check Render logs; verify `BACKEND_URL` points to Cafe24 API |

---

### Phase 2 — Remaining Operator Actions (P0)

1. **Cafe24 FTP** — upload `admin/consults/view.php` from commit `d4e07aae` if not on server.
2. **Render manual redeploy** — confirm latest commit; `/health` should show `backend` + `jwt` fields.
3. **Render env vars** — `JWT_SECRET` (match Cafe24 `ACEP_JWT_SECRET`), `BACKEND_URL`, `CORS_ALLOWED_ORIGINS`.
4. **Cafe24 FTP** — upload `frontend/dist/.htaccess` for SPA deep links.
5. Run **§ Phase 2 — Operator Manual Test** above and update this table with PASS/FAIL.

---

### Phase 2 — Remote Evidence (2026-07-22 ~15:10 KST)

```
# Git
d4e07aae Add real-time messaging to admin consult detail page.
237988a3 Document unified Frontend/Admin login credentials in deploy report.
Push: origin/main up-to-date at d4e07aae

# Render
curl https://plustok.onrender.com/health
  → 200 {"status":"healthy","uptimeSec":6.67…}  [LEGACY — enhanced health pending]
curl https://plustok.onrender.com/socket.io/?EIO=4&transport=polling
  → 200 0{"sid":"…","upgrades":["websocket"],…}

# Cafe24
curl https://plustok.mycafe24.com/api/v1/health → 200
curl https://plustok.mycafe24.com/admin/         → 200
curl https://plustok.mycafe24.com/frontend/login → 404
```

---

*Phase 2 section added: 2026-07-22T15:10+09:00*
