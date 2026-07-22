# PlusTok V3.0 — Chat / Backend 문서 인덱스

**Version:** 3.0 | **갱신:** 2026-07-21  
**문서 위치:** `www/05_CHAT/`

---

## SSOT (STEP 4)

> 단일 진실 원천: **[01_WebSocket설계.md](01_WebSocket설계.md)**, **[02_실시간동기화.md](02_실시간동기화.md)**

| # | 문서 | 설명 |
|---|------|------|
| 1 | [01_WebSocket설계.md](01_WebSocket설계.md) | Socket.io 4, JWT, 이벤트, Nginx wss |
| 2 | [02_실시간동기화.md](02_실시간동기화.md) | REST+WS, optimistic UI, offline queue |

## Supplementary (Legacy)

| # | 문서 | 비고 |
|---|------|------|
| L1 | [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md) | → SSOT 01 |
| L2 | [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md) | → SSOT 02 |
| L3 | [03_AI_Router_Service_구현명세.md](03_AI_Router_Service_구현명세.md) | → 04_AI |
| L4 | [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md) | → SSOT 01 |

## 관련 문서

- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §12
- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) chat_*
- [06_CRM/_CRM_INDEX.md](../06_CRM/_CRM_INDEX.md)
- [07_ADMIN/_ADMIN_INDEX.md](../07_ADMIN/_ADMIN_INDEX.md)
- [08_DASHBOARD/_DASHBOARD_INDEX.md](../08_DASHBOARD/_DASHBOARD_INDEX.md)

## STEP 로드맵

| STEP | 내용 |
|------|------|
| 4 | **05_CHAT SSOT 01/02** |
| 5–6 | CRM + Admin |
| 7–8 | Dashboard + QA |

## 구현 체크리스트

- [ ] SSOT 01 WebSocket: JWT handshake, room join, reconnect
- [ ] SSOT 02 동기화: optimistic send, offline queue flush
- [ ] Nginx `location /socket.io/` proxy + wss
- [ ] Chat API contract ↔ [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)
- [ ] E2E: TC-WS-01 ~ TC-WS-05 (상세는 01_WebSocket설계.md)

**STEP 4 SSOT:** `01_WebSocket설계.md` + `02_실시간동기화.md`