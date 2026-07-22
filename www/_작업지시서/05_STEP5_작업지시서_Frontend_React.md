# STEP 5 작업지시서 — Frontend React

**프로젝트:** PlusTok Enterprise (ACEP)  
**STEP:** 5  
**상태:** ✅ **완료** (2026-07-21) — **문서 산출물**  
**적용 위치:** `www/06_FRONTEND/`, `www/_작업지시서/`

> 본 STEP은 **구현 코드가 아닌 구현 명세 문서**를 산출한다. 실제 `frontend/` 코드 scaffold는 개발 스프린트에서 진행.

---

## 목표

STEP 2~4 API/아키텍처/Chat Backend/WebSocket 명세를 기반으로, **React 18 Frontend SPA** 의 developer-ready 구현 명세를 작성한다. UI_COMPONENTS_GUIDE 11개 컴포넌트, ChatScreen 3패널 composition, hooks(useSocket 포함), 상태관리(React Query + Zustand)를 문서화한다.

---

## 산출물 체크리스트

| # | 산출물 | 목표行 | 상태 | 링크 |
|---|--------|--------|:----:|------|
| 1 | Frontend 아키텍처 | 700+ | ✅ | [06_FRONTEND/01_Frontend_아키텍처.md](../06_FRONTEND/01_Frontend_아키텍처.md) |
| 2 | React 컴포넌트 구현명세 | 900+ | ✅ | [06_FRONTEND/02_React_컴포넌트_구현명세.md](../06_FRONTEND/02_React_컴포넌트_구현명세.md) |
| 3 | Hooks 및 상태관리 | 700+ | ✅ | [06_FRONTEND/03_Hooks_및_상태관리.md](../06_FRONTEND/03_Hooks_및_상태관리.md) |
| 4 | ChatScreen 통합 구현가이드 | 600+ | ✅ | [06_FRONTEND/04_ChatScreen_통합_구현가이드.md](../06_FRONTEND/04_ChatScreen_통합_구현가이드.md) |
| 5 | Frontend 문서 인덱스 | — | ✅ | [06_FRONTEND/_FRONTEND_INDEX.md](../06_FRONTEND/_FRONTEND_INDEX.md) |
| 6 | MASTER PART 10 STEP 5 갱신 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| 7 | MASTER §10.2.1 06_FRONTEND/ 추가 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §10.2.1 |
| 8 | MASTER Appendix B 06_FRONTEND 링크 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §부록 B |

---

## 품질 기준

| 항목 | 기준 | 결과 |
|------|------|:----:|
| UI Components 정합 | [UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md) 11 components props exact | ✅ |
| 화면 레이아웃 정합 | [01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) 3-panel, mobile tabs | ✅ |
| API 정합 | [02_API설계.md](../03_SYSTEM/02_API설계.md) Chat/AI/Auth endpoints | ✅ |
| WS 정합 | [04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md) events + TS types | ✅ |
| 아키텍처 정합 | [03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) frontend/ folder | ✅ |
| React Query + Zustand | server vs client state 분리 | ✅ |
| 한국어 Markdown | substantive, developer-ready | ✅ |
| 상대 링크 (www root) | 06_FRONTEND/*, 02_UIUX/*, 03_SYSTEM/*, 05_CHAT/* | ✅ |
| 코드 파일 미생성 | 문서 only (frontend/ scaffold 없음) | ✅ |

---

## 참조 문서 (입력)

- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 4, 6, 9, 10
- [02_UIUX/UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md)
- [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)
- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §10
- [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md)
- [05_CHAT/_CHAT_INDEX.md](../05_CHAT/_CHAT_INDEX.md)
- [_작업지시서/04_STEP4_작업지시서_Chat_Backend.md](04_STEP4_작업지시서_Chat_Backend.md)

---

## STEP 5 문서 핵심 결정 (ADR 요약)

| 결정 | 선택 | 근거 |
|------|------|------|
| Bundler | Vite 5 | 빠른 HMR, ESM |
| Server State | TanStack Query v5 | rooms/messages/AI cache |
| Client UI State | Zustand | activeRoomId, mobileTab |
| WS Client | Socket.io singleton | STEP 4 protocol SSOT |
| Auth Token | Memory + HttpOnly refresh | MASTER §9.1 |
| Message Send | REST POST + optimistic UI | STEP 4 Source of Truth |
| Layout | 320+800+320 desktop, tabs mobile | UI fig §4 |
| CSS | TailwindCSS + CSS vars | UI_COMPONENTS_GUIDE tokens |

---

## STEP 6 선행 과제 (코드 구현)

- [ ] `frontend/` Vite + React + TS scaffold
- [ ] `npm run build` → dist/ Nginx deploy
- [ ] 11 chat components 구현
- [ ] 6 hooks + ui.store.ts
- [ ] ChatScreen 3 breakpoint layouts
- [ ] MSW mock for local dev without backend
- [ ] Vitest component tests TC-C01~09

---

## 완료 확인

```
STEP 5: Frontend React 구현 명세 — ✅ 2026-07-21
담당: Frontend Platform Team
다음 STEP: STEP 6 Admin, Dashboard
```

---

**상위:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 10.2
