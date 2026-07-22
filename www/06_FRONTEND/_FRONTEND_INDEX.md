# PlusTok V3.0 — Frontend React 문서 인덱스

**프로젝트:** PlusTok Enterprise (ACEP)  
**Version:** 3.0  
**Status:** STEP 5 Complete  
**적용 위치:** `www/06_FRONTEND/`

---

## 문서 목록

| # | 문서 | 설명 | 대상 |
|---|------|------|------|
| 1 | [01_Frontend_아키텍처.md](01_Frontend_아키텍처.md) | React 18 + Vite + Tailwind — 폴더 구조, Auth, api/socket client, env, build, proxy | Frontend Dev, DevOps |
| 2 | [02_React_컴포넌트_구현명세.md](02_React_컴포넌트_구현명세.md) | UI_COMPONENTS_GUIDE 11개 + ChatScreen — Props, TSX snippets, a11y | Frontend Dev |
| 3 | [03_Hooks_및_상태관리.md](03_Hooks_및_상태관리.md) | useSocket, useChatRooms, useMessages, useAiRecommendations, useTyping, useReadReceipt — React Query + Zustand | Frontend Dev |
| 4 | [04_ChatScreen_통합_구현가이드.md](04_ChatScreen_통합_구현가이드.md) | 3패널/태블릿/모바일 composition, event wiring, integration test checklist | Frontend Dev, QA |

---

## 상위·연관 문서

| 문서 | 경로 |
|------|------|
| MASTER (PART 4, 10) | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| UI Components (11) | [02_UIUX/UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md) |
| 상담채팅화면 | [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) |
| REST API (30) | [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) |
| 시스템 아키텍처 (frontend/) | [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §10 |
| WebSocket 프로토콜 | [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md) |
| Chat Backend (STEP 4) | [05_CHAT/_CHAT_INDEX.md](../05_CHAT/_CHAT_INDEX.md) |
| STEP 5 작업지시서 | [_작업지시서/05_STEP5_작업지시서_Frontend_React.md](../_작업지시서/05_STEP5_작업지시서_Frontend_React.md) |

---

## 구현 소스 (목표)

| 컴포넌트 | 경로 | 상태 |
|----------|------|:----:|
| React SPA | `www/frontend/` | 📋 문서화 완료 |
| Chat Components (11) | `www/frontend/src/components/chat/` | 📋 문서화 완료 |
| Hooks (6) | `www/frontend/src/hooks/` | 📋 문서화 완료 |
| Services | `www/frontend/src/services/` | 📋 문서화 완료 |
| ChatScreen Page | `www/frontend/src/pages/ChatScreen.tsx` | 📋 문서화 완료 |

> **Note:** STEP 5는 **문서 only**. `frontend/` 실제 코드 scaffold는 개발 스프린트에서 본 문서 SSOT로 진행.

---

## STEP 로드맵

| STEP | 산출물 | 상태 |
|------|--------|:----:|
| STEP 3 | 04_AI/* | ✅ 완료 |
| STEP 4 | 05_CHAT/* | ✅ 완료 |
| **STEP 5** | **06_FRONTEND/* (본 폴더)** | **✅ 문서 완료** |
| STEP 6 | Admin, Dashboard | 예정 |
| STEP 7 | 테스트, QA | 예정 |
| STEP 8 | 릴리스, 배포 | 예정 |

---

## MVP V1.0 Frontend 범위

| In Scope | Out of Scope |
|----------|--------------|
| ChatScreen (Agent) 3패널 + mobile tabs | Admin Dashboard |
| 11 UI components | Customer widget (별도 STEP) |
| useSocket + 5 domain hooks | E2E Playwright (V1.5) |
| JWT auth + protected routes | i18n multi-language |
| Optimistic message send | message:send WS (V1.5) |
| AI panel pending/processing/failed | Video/voice UI (V3.0) |

---

## 문서 읽기 순서 (권장)

```
1. 01_Frontend_아키텍처.md     — 스택, 폴더, auth, clients
2. 02_React_컴포넌트_구현명세.md — 11 components + ChatScreen tree
3. 03_Hooks_및_상태관리.md     — data layer, types
4. 04_ChatScreen_통합_구현가이드.md — wire-up + test checklist
```

---

## Quick Reference

| Topic | Document | Section |
|-------|----------|---------|
| VITE_API_BASE | 01_Frontend_아키텍처 | §7 |
| VITE_WS_URL | 01_Frontend_아키텍처 | §7 |
| MessageBubble props | 02_React_컴포넌트 | §1 |
| useSocket reconnect | 03_Hooks | §5 |
| Mobile tabs | 04_ChatScreen | §4 |
| Integration tests | 04_ChatScreen | §11 |

---

**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)
