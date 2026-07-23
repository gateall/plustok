# chat-server 배포 가이드 (BUG-003)

## Cafe24 공유호스팅 제약

| 항목 | Cafe24 웹호스팅 (PATH A) | VPS / Docker (PATH B) |
|------|--------------------------|------------------------|
| Node.js / PM2 | ❌ 불가 | ✅ |
| 외부 포트 3001 | ❌ 차단 | ✅ (또는 Nginx 443 경유) |
| Nginx `location` 수정 | ❌ 불가 | ✅ |
| Redis pub/sub | ❌ | ✅ |

**결론:** `plustok.mycafe24.com` 공유호스팅만으로는 chat-server를 운영할 수 없습니다.

---

## 배포 경로 선택

### A. Hybrid (권장 — PHP는 Cafe24 유지)

1. 소형 VPS(또는 Cafe24 서버호스팅)에 Node 20 + PM2 설치
2. chat-server 배포 후 `127.0.0.1:3001`에서만 listen
3. VPS Nginx에서 `wss://ws.example.com` 또는 동일 도메인 `/socket.io/` 프록시
4. `frontend/.env.production`:
   ```
   VITE_WS_URL=wss://ws.example.com
   ```
   (Socket.io 클라이언트 기본 path는 `/socket.io` — URL에 `/socket.io` 접미사 불필요)

### B. Full Docker (PATH B)

`09_RELEASE/02_Docker_및_Nginx_구성.md` — acep-chat-server + acep-nginx

### C. Cafe24 only (WS 없음)

실시간 알림 없이 REST 폴링만 사용 (Phase 2 UI는 WS 연결 실패 시 `connect_error` 표시).

---

### D. Render (현재 운영 — Hybrid A 변형)

1. Render Web Service — Root Directory: `www/chat-server`, Dockerfile Path: `Dockerfile`
2. 환경 변수: `JWT_SECRET`, `BACKEND_URL`, `CORS_ALLOWED_ORIGINS` (선택 `REDIS_URL`)
3. `frontend/.env.production`: `VITE_WS_URL=wss://plustok.onrender.com`
4. Render는 `PORT`를 자동 주입 — chat-server는 `process.env.PORT` 우선 사용

**JWT 동기화 (필수):** `JWT_SECRET` = `config/acep.local.php`의 `ACEP_JWT_SECRET`과 **바이트 단위 동일**.

| Render env | 값 |
|------------|-----|
| `JWT_SECRET` | acep.local.php `ACEP_JWT_SECRET` |
| `BACKEND_URL` | `https://plustok.mycafe24.com/api/v1` |
| `CORS_ALLOWED_ORIGINS` | `https://plustok.mycafe24.com` |

재배포 후 검증:

```powershell
cd chat-server
.\scripts\verify-render.ps1
```

구버전 배포 시 `/health`가 `{"status":"healthy","uptimeSec":...}` 만 반환 — **재배포 필요**.

기대 응답:

```json
{"status":"healthy","backend":{"reachable":true},"jwt":{"configured":true},"redis":{...}}
```

---

## VPS PM2 배포 절차

```bash
cd chat-server
npm ci
npm run build
cp .env.production.example .env
# .env 편집: JWT_SECRET, BACKEND_URL, CORS_ALLOWED_ORIGINS

pm2 start ecosystem.config.cjs --env production
pm2 save
pm2 startup
```

검증:

```bash
curl -s http://127.0.0.1:3001/health
# {"status":"healthy","backend":{...},"jwt":{...},"redis":{...}}
```

Nginx 적용 후 (443):

```bash
curl -s "https://YOUR_DOMAIN/socket.io/?EIO=4&transport=polling"
# 0{...} 형태 Socket.io handshake (404면 프록시 미설정)
```

---

## JWT / CORS 동기화

| chat-server `.env` | PHP `config/acep.local.php` |
|--------------------|-----------------------------|
| `JWT_SECRET` | `ACEP_JWT_SECRET` (동일 값) |
| `BACKEND_URL` | `https://plustok.mycafe24.com/api/v1` |
| `CORS_ALLOWED_ORIGINS` | `https://plustok.mycafe24.com` |

---

## Frontend 재빌드

WS URL 변경 후:

```bash
cd frontend
# .env.production: VITE_WS_URL=wss://<nginx-또는-서브도메인>
npm run build
# dist/* → Cafe24 /frontend/
```

---

## 프로덕션 진단 (2026-07-21)

| URL | 결과 |
|-----|------|
| `https://plustok.mycafe24.com:3001/health` | 연결 실패 (포트 미개방) |
| `https://plustok.mycafe24.com/socket.io/?EIO=4&transport=polling` | 404 |
| `https://plustok.mycafe24.com/api/v1/health` | 200 OK |
