# BUG-003 — chat-server PM2/Nginx 진단 보고서

**일시:** 2026-07-21  
**환경:** `https://plustok.mycafe24.com` (Cafe24 공유호스팅)  
**상태:** ⏳ **미해결** — 인프라 제약 확인, 배포 산출물 준비 완료

---

## 1. 프로덕션 curl 진단

| # | 요청 | 기대 | 실제 |
|---|------|------|------|
| 1 | `GET :3001/health` | 200 JSON | **연결 실패** (curl exit 7) |
| 2 | `GET /socket.io/?EIO=4&transport=polling` | Socket.io handshake | **404** |
| 3 | `GET /api/v1/health` | 200 ACEP JSON | **200 OK** ✅ |

**판정:** PHP API는 정상. WebSocket/chat-server는 **미기동·미프록시** 상태.

---

## 2. 근본 원인

Cafe24 **웹호스팅(공유)** 은 다음을 지원하지 않습니다.

1. Node.js 장기 프로세스 (PM2/systemd)
2. 사용자 정의 포트(3001) 외부 개방
3. Nginx `location /socket.io/` 리버스 프록시 설정

현재 `frontend/.env.production`의 `VITE_WS_URL=wss://plustok.mycafe24.com:3001` 은 **Cafe24 PATH A에서 동작 불가**합니다.

아키텍처 문서(`09_DEVELOPMENT/03_배포운영.md`)도 Cafe24 V1.0은 `No Node WS (V1.0 poll)` 로 명시되어 있습니다.

---

## 3. 코드 정합성 (로컬 — ✅)

| 항목 | 상태 |
|------|------|
| chat-server `path: '/socket.io'` | ✅ `server.ts` |
| Frontend `auth: { token }` (Bearer 없음) | ✅ `useSocket.tsx` ↔ `auth.middleware.ts` |
| REST SoT (`BACKEND_URL` → PHP) | ✅ `backend.client.ts` |
| Health endpoint | ✅ `GET /health` |
| Redis pub/sub | 선택 (REDIS_URL 없으면 경고만) |

---

## 4. 해결 방안

### Option A — Hybrid (권장)

```
[Browser] ──HTTPS──► Cafe24 (PHP API + React /frontend/)
     │
     └──WSS──────► VPS Nginx :443 ──► PM2 chat-server :3001
                      /socket.io/
```

- PHP·DB·React는 Cafe24 유지
- chat-server만 VPS에서 PM2 + Nginx
- `VITE_WS_URL=wss://ws.your-vps-domain.com` (또는 동일 apex + `/socket.io/` 프록시 VPS)

### Option B — Full Docker PATH B

`09_RELEASE/02_Docker_및_Nginx_구성.md` — acep-nginx + acep-chat-server

### Option C — WS 없이 운영

채팅 UI는 REST로 메시지 송수신 가능하나 **실시간 room:update / typing / read** 는 동작하지 않음.

### Option D — Cafe24 Node.js 호스팅 (2026-07-22 검토)

**상품:** [개발언어 호스팅 — Node.js](https://hosting.cafe24.com/?controller=new_product_page&page=language)

| 항목 | Cafe24 Node.js 호스팅 | ACEP chat-server 요구 |
|------|----------------------|------------------------|
| 상시 구동 | ✅ 웹 콘솔 시작/중지/재시작 | ✅ (PM2 불필요) |
| 배포 | Git Push (FTP ❌) | 별도 Git 저장소 필요 |
| Node 버전 | **v14 / v12** | 코드는 Node 18+ 권장, **v12 불가** (redis v4) |
| 진입점 | **`web.js` 필수** | 현재 `dist/server.js` → 래퍼 필요 |
| 포트 | `process.env.PORT` (할당) | `CHAT_SERVER_PORT=3001` 고정 ❌ → env PORT 사용 |
| 도메인 | `*.cafe24app.com` (별도) | `plustok.mycafe24.com`과 **크로스 도메인** |
| SSL/WSS | Cafe24 제공 (상품별) | `VITE_WS_URL` = Node 앱 도메인 |

**판정:** Cafe24 **웹호스팅과 별도 상품**으로 chat-server 운영 **가능(조건부)**.

**필수 사전 작업 (코드)**

1. `web.js` — `process.env.PORT`로 listen, `dist/server.js` 기동
2. 로컬 `npm run build` → `dist/` + `node_modules/` Git 포함 또는 post-receive 빌드
3. `tsconfig` target ES2020 (Node 14 호환)
4. `.env`: `BACKEND_URL=https://plustok.mycafe24.com/api/v1`, `CORS_ALLOWED_ORIGINS=https://plustok.mycafe24.com`
5. `JWT_SECRET` = PHP `acep.local.php` 동일
6. Frontend: `VITE_WS_URL=wss://{앱명}.cafe24app.com` → 재빌드

**Node v14 제약:** socket.io 4.x + redis 4.x는 v14에서 동작. v12 선택 시 **불가**.

**대안:** Node 24 필요 시 → Cafe24 **개발언어 VPS** (별도 상품, 월 33,000원~)

---

## 5. 준비된 배포 산출물

| 파일 | 용도 |
|------|------|
| `chat-server/ecosystem.config.cjs` | PM2 |
| `chat-server/.env.production.example` | 프로덕션 env 템플릿 |
| `chat-server/deploy/nginx-socketio.conf` | Nginx WS 프록시 |
| `chat-server/DEPLOY.md` | 운영 런북 |

---

## 6. Operator 체크리스트

- [ ] VPS(또는 서버호스팅) 확보 + Node 20 설치
- [ ] `chat-server` → `npm ci && npm run build`
- [ ] `.env`: `JWT_SECRET` = `acep.local.php` `ACEP_JWT_SECRET`
- [ ] `pm2 start ecosystem.config.cjs --env production`
- [ ] Nginx `deploy/nginx-socketio.conf` 적용 + SSL
- [ ] `curl` socket.io polling handshake 200 확인
- [ ] `frontend/.env.production` `VITE_WS_URL` 수정 → `npm run build` → dist 업로드
- [ ] 로그인 → 채팅방 → DevTools Network WS `101 Switching Protocols` 확인

---

## 7. BUG-006 연동

`VITE_WS_URL` 올바른 형식:

```env
# ✅ Socket.io origin (path /socket.io 는 클라이언트 기본값)
VITE_WS_URL=wss://ws.example.com

# ❌ Cafe24에서 동작 안 함
VITE_WS_URL=wss://plustok.mycafe24.com:3001

# ❌ URL에 /socket.io 접미사 불필요 (별도 path 옵션 없을 때)
VITE_WS_URL=wss://plustok.mycafe24.com/socket.io
```

---

## 8. 결론

| BUG | 결과 |
|-----|------|
| BUG-003 | **웹호스팅 단독 불가** — Cafe24 **Node.js 호스팅(Option D)** 또는 VPS Hybrid 검토 |
| BUG-006 | WS URL을 Nginx/VPS 도메인으로 변경 후 frontend 재빌드 필요 |

**다음 액션:** Cafe24 Node.js 호스팅 신청 → `web.js` + Git 배포 → `VITE_WS_URL` 재빌드. (Node 20 필요 시 개발언어 VPS)
