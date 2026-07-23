# 작업지시서: 채팅 화면 "WebSocket 재연결 중..." 해결 (2026-07-22)

**상태:** 원인 가설 확정 · **Render 대시보드 작업 필요** (Claude/Codex는 Render 접근 권한 없음)
**증상:** 로그인 성공 후 `/frontend/#/chat`에서 상단에 "WebSocket 재연결 중..." 배너가 계속 표시됨

---

## 1. 확인된 사실

### 1-1. Render의 chat-server 자체는 살아있음
```
GET https://plustok.onrender.com/socket.io/?EIO=4&transport=polling
→ 200, 정상 socket.io 핸드셰이크 (sid 발급됨)
```
서버가 죽어있거나 슬립 상태인 건 아님.

### 1-2. 그런데 `/health` 응답이 로컬 소스코드와 다름 — **배포 코드가 구버전**

**실제 라이브 응답:**
```json
{"status":"healthy","uptimeSec":206.05}
```

**로컬 `chat-server/src/server.ts:18-38`에 있는 현재 코드가 원래 반환해야 하는 형태:**
```json
{
  "status": "healthy",
  "uptimeSec": ...,
  "backend": { "url": "...", "reachable": true, "latencyMs": ..., "error": null },
  "jwt": { "configured": true },
  "redis": { "adapter": false, "pubsub": false }
}
```
`backend`/`jwt`/`redis` 필드가 라이브 응답에 아예 없음 → **Render에 배포된 코드가 최신 `chat-server/src` 기준보다 오래된 빌드**라는 뜻. 이 상태로는 `jwt.configured`, `backend.reachable` 값을 원격으로 확인할 방법이 없음.

### 1-3. JWT 시크릿 로테이션 흔적

`config/acep.local.php:13`:
```php
define('ACEP_JWT_SECRET', 'plustok_jwt_secret_2026_07_22_enterprise_production_key_change_me_periodically');
```
값 안에 **오늘 날짜(2026_07_22)**가 박혀있음 — 최근에 새로 생성/교체된 것으로 보임.

`chat-server/.env.production.example:6`:
```
# MUST match config/acep.local.php ACEP_JWT_SECRET (256-bit+)
JWT_SECRET=CHANGE_ME_SAME_AS_acep.local.php
```
Render의 실제 `JWT_SECRET` 환경변수가 **이 새 값으로 갱신 안 됐을 가능성이 높음** — PHP가 새 시크릿으로 서명한 JWT를 chat-server가 옛 시크릿으로 검증 실패(`jwt.verify()` 실패) → `authMiddleware`가 매번 `UNAUTHORIZED`로 거부 → 브라우저가 계속 재연결 시도 → "WebSocket 재연결 중..." 무한 반복.

(`chat-server/src/middleware/auth.middleware.ts:14-42`, `chat-server/src/auth.ts:16-30` 참고)

---

## 2. 조치 (Render 대시보드에서, 접근 권한 보유자)

> 로컬 검증 스크립트: `chat-server/scripts/verify-render.ps1`  
> Blueprint 참고: `chat-server/render.yaml`

### STEP 1. 최신 코드 재배포
- Render 서비스가 `www/chat-server`의 **현재 소스**를 기준으로 재배포됐는지 확인 (Git 연동이면 최신 커밋 push/재배포 트리거, 수동 업로드면 다시 업로드)

### STEP 2. 환경 변수 확인/수정
Render 대시보드 → 해당 서비스 → Environment:

| 변수 | 값 |
|------|-----|
| `JWT_SECRET` | `plustok_jwt_secret_2026_07_22_enterprise_production_key_change_me_periodically` (acep.local.php와 **정확히 동일**해야 함) |
| `BACKEND_URL` | `https://plustok.mycafe24.com/api/v1` |
| `CORS_ALLOWED_ORIGINS` | `https://plustok.mycafe24.com` |

수정 후 서비스 재시작(redeploy).

### STEP 3. 검증
```bash
curl -s https://plustok.onrender.com/health
```
기대 결과:
```json
{"status":"healthy","backend":{"reachable":true,...},"jwt":{"configured":true},...}
```
- `jwt.configured: false`면 Render에 `JWT_SECRET` 자체가 비어있다는 뜻
- `backend.reachable: false`면 `BACKEND_URL`이 Cafe24 서버에 못 닿는다는 뜻 (URL 오타 등)

### STEP 4. 브라우저 확인
`https://plustok.mycafe24.com/frontend/#/chat` 새로고침 → "WebSocket 재연결 중..." 배너가 사라지고 정상 연결되는지 확인

---

## 3. 참고 — 배포 구조 (`chat-server/DEPLOY.md`)

- 현재 운영: **Render (Hybrid)** — `www/chat-server`를 Dockerfile로 배포, PHP(Cafe24)는 REST API만 담당
- `frontend/.env.production`: `VITE_WS_URL=wss://plustok.onrender.com` (변경 불필요)

---

## 4. 건드리지 않은 영역

- 로그인/통합 인증 (이미 별도로 해결됨)
- Cafe24 PHP 백엔드 코드

---

## 5. 완료 기준

- [ ] Render에 최신 `chat-server` 소스 재배포
- [ ] `JWT_SECRET` = acep.local.php의 `ACEP_JWT_SECRET`과 동일하게 설정
- [ ] `/health` 응답에 `backend`/`jwt`/`redis` 필드 정상 노출, `jwt.configured:true`
- [ ] `/frontend/#/chat`에서 "WebSocket 재연결 중..." 배너 사라짐 확인
