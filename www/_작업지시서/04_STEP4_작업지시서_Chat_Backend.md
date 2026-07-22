# STEP 4 작업지시서 — Chat Server & Backend

**프로젝트:** PlusTok Enterprise (ACEP)  
**STEP:** 4  
**상태:** ✅ **완료** (2026-07-21) — **문서 산출물**  
**적용 위치:** `www/05_CHAT/`, `www/_작업지시서/`

> 본 STEP은 **구현 코드가 아닌 구현 명세 문서**를 산출한다. 실제 코드 구현은 STEP 5 이전 개발 스프린트에서 진행.

---

## 목표

STEP 2 API/아키텍처 및 STEP 3 AI 엔진 구현 명세를 기반으로, **Chat Server (Node.js)** 와 **PHP Backend Chat API** 및 **AI Router Service** 의 developer-ready 구현 명세를 작성한다.

---

## 산출물 체크리스트

| # | 산출물 | 목표行 | 상태 | 링크 |
|---|--------|--------|:----:|------|
| 1 | Chat Server 구현명세 | 700+ | ✅ | [05_CHAT/01_ChatServer_구현명세.md](../05_CHAT/01_ChatServer_구현명세.md) |
| 2 | Backend Chat API 구현명세 | 800+ | ✅ | [05_CHAT/02_Backend_Chat_API_구현명세.md](../05_CHAT/02_Backend_Chat_API_구현명세.md) |
| 3 | AI Router Service 구현명세 | 600+ | ✅ | [05_CHAT/03_AI_Router_Service_구현명세.md](../05_CHAT/03_AI_Router_Service_구현명세.md) |
| 4 | WebSocket 프로토콜 명세 | 500+ | ✅ | [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md) |
| 5 | Chat 문서 인덱스 | — | ✅ | [05_CHAT/_CHAT_INDEX.md](../05_CHAT/_CHAT_INDEX.md) |
| 6 | MASTER PART 10 STEP 4 갱신 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| 7 | MASTER §10.2.1 05_CHAT/ 추가 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §10.2.1 |
| 8 | MASTER Appendix B 05_CHAT 링크 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §부록 B |

---

## 품질 기준

| 항목 | 기준 | 결과 |
|------|------|:----:|
| API 정합 | [02_API설계.md](../03_SYSTEM/02_API설계.md) Chat/Message/WS §12 | ✅ |
| 아키텍처 정합 | [03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) 레이어 분리 | ✅ |
| AI Router 정합 | [03_AI엔진구현.md](../04_AI/03_AI엔진구현.md) | ✅ |
| UI WS events | [01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §6.2, §7 | ✅ |
| includes/ai.php | ai_call, ai_mask_pii, ai_check_rate_limit 참조 | ✅ |
| PLUS톡 www 구조 | admin/, api/, includes/, embed/ 확장 패턴 | ✅ |
| 한국어 Markdown | substantive | ✅ |
| 상대 링크 (www root) | 05_CHAT/*, 03_SYSTEM/*, 04_AI/* | ✅ |
| 코드 파일 미생성 | 문서 only | ✅ |

---

## 참조 문서 (입력)

- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 4, 6.5, 8
- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) chat_*, ai_*, attachments
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §4~5, §10, §12
- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §3~4, §9~10
- [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §6~7
- [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md)
- [includes/ai.php](../includes/ai.php), [config/app.php](../config/app.php)

---

## STEP 4 문서 핵심 결정 (ADR 요약)

| 결정 | 선택 | 근거 |
|------|------|------|
| Chat Server | Node.js 20 + Socket.io 4 (신규) | WS scale isolation |
| Backend | PHP 8.4 www 확장 | PLUS톡 V2.0 reuse |
| Message persist | REST POST only (V1.0) | Source of Truth = MariaDB |
| Backend↔Chat | Redis Pub/Sub | [03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §3.1 |
| AI async | Redis List + cron (MVP) | Cafe24/hosting 호환 |
| Rate limit | Redis Rule-005 | Session debounce 대체 |

---

## STEP 5 선행 과제 (코드 구현)

- [ ] `chat-server/` Node.js 프로젝트 scaffold
- [ ] `api/v1/` PHP router + Chat/Message controllers
- [ ] `includes/services/` ChatRoomService, MessageService
- [ ] `AiRecommendationService` + `cli/ai_worker.php`
- [ ] Redis pub/sub + Docker compose 통합
- [ ] Nginx `/socket.io/` proxy 설정
- [ ] React `useSocket.ts` (STEP 5)

---

## 완료 확인

```
STEP 4: Chat Server + Backend + AI Router Service 구현 명세 — ✅ 2026-07-21
담당: Real-time Platform Team / Backend Platform Team
다음 STEP: STEP 5 Frontend React
```

---

**상위:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 10.2
