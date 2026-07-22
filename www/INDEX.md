# PlusTok V3.0 — AI Customer Engagement Platform

**Version:** 3.0  
**Status:** Implementation Ready (STEP 1~8 Complete)  
**Last Updated:** 2026-07-21

> **Constitution:** [00_PROJECT_MASTER.md](00_PROJECT_MASTER.md) · **Quality Report:** [_검증리포트_문서품질.md](_검증리포트_문서품질.md) · **Cursor Rules:** [.cursor/RULES.md](.cursor/RULES.md)

---

## 🚀 빠른 시작

### 처음 보는 사람: 30초 안내
1. [00_PROJECT_MASTER.md](00_PROJECT_MASTER.md) 읽기 (5분) — 프로젝트 헌법
2. 관심 STEP 선택 (아래 표)
3. 해당 폴더 INDEX 참조

### 재진입 개발자: 체크포인트
- [ ] API 엔드포인트: [03_SYSTEM/02_API설계.md](03_SYSTEM/02_API설계.md)
- [ ] DB 스키마: [03_SYSTEM/01_DB설계.md](03_SYSTEM/01_DB설계.md)
- [ ] AI 로직: [04_AI/01_AI전략.md](04_AI/01_AI전략.md)
- [ ] WebSocket: [05_CHAT/01_WebSocket설계.md](05_CHAT/01_WebSocket설계.md)
- [ ] QA SSOT: [09_DEVELOPMENT/02_테스트시나리오.md](09_DEVELOPMENT/02_테스트시나리오.md)
- [ ] 배포 SSOT: [09_DEVELOPMENT/03_배포운영.md](09_DEVELOPMENT/03_배포운영.md)

---

## 📂 프로젝트 구조 (STEP 1~8)

### PART 1: 기초 설계 (STEP 1~2)

| STEP | 폴더 | 문서 | 내용 |
|------|------|------|------|
| **1** | (root) | [00_PROJECT_MASTER.md](00_PROJECT_MASTER.md) | 프로젝트 전체 비전, RBAC, 로드맵 |
| **1** | [02_UIUX/](02_UIUX/_UIUX_INDEX.md) | [01_상담채팅화면.fig.md](02_UIUX/01_상담채팅화면.fig.md) | Customer·Agent 채팅 UI |
| **1** | | [UI_COMPONENTS_GUIDE.md](02_UIUX/UI_COMPONENTS_GUIDE.md) | 공통 UI 컴포넌트 |
| **2** | [03_SYSTEM/](03_SYSTEM/_SYSTEM_INDEX.md) | [01_DB설계.md](03_SYSTEM/01_DB설계.md) | 14개 테이블 DDL, ERD |
| **2** | | [02_API설계.md](03_SYSTEM/02_API설계.md) | 30+ REST API |
| **2** | | [03_시스템아키텍처.md](03_SYSTEM/03_시스템아키텍처.md) | 배포·폴더·시퀀스 |

### PART 2: 핵심 기능 (STEP 3~4)

| STEP | 폴더 | 문서 | 내용 |
|------|------|------|------|
| **3** | [04_AI/](04_AI/_AI_INDEX.md) | [01_AI전략.md](04_AI/01_AI전략.md) | Failover 5-chain, 비용 추적 |
| **3** | | [02_Prompt설계.md](04_AI/02_Prompt설계.md) | 12 AI 역할 Prompt |
| **3** | | [03_AI엔진구현.md](04_AI/03_AI엔진구현.md) | `ai_call()` 구현 가이드 |
| **4** | [05_CHAT/](05_CHAT/_CHAT_INDEX.md) | [01_WebSocket설계.md](05_CHAT/01_WebSocket설계.md) | Socket.io, 15+ 이벤트 |
| **4** | | [02_실시간동기화.md](05_CHAT/02_실시간동기화.md) | Optimistic UI, offline queue |

### PART 3: 비즈니스·프론트 (STEP 5~6)

| STEP | 폴더 | 문서 | 내용 |
|------|------|------|------|
| **5** | [06_CRM/](06_CRM/_CRM_INDEX.md) | [01_CRM통합.md](06_CRM/01_CRM통합.md) | 상담→CRM 자동 저장 |
| **5** | [06_FRONTEND/](06_FRONTEND/_FRONTEND_INDEX.md) | [01~04 Frontend docs](06_FRONTEND/_FRONTEND_INDEX.md) | React 아키텍처·ChatScreen |
| **6** | [07_ADMIN/](07_ADMIN/_ADMIN_INDEX.md) | [01_관리자대시보드.md](07_ADMIN/01_관리자대시보드.md) | KPI·Live Monitor |
| **6** | | [02_상담원관리.md](07_ADMIN/02_상담원관리.md) | Agent CRUD·배정 |
| **6** | | [03_설정관리.md](07_ADMIN/03_설정관리.md) | AI/Chat/CRM 설정 |
| **6** | | [04_Admin_API_및_권한_명세.md](07_ADMIN/04_Admin_API_및_권한_명세.md) | Admin REST·RBAC |

### PART 4: 대시보드·배포 (STEP 7~8)

| STEP | 폴더 | 문서 | 내용 |
|------|------|------|------|
| **7** | [08_DASHBOARD/](08_DASHBOARD/_DASHBOARD_INDEX.md) | [01_대시보드설계.md](08_DASHBOARD/01_대시보드설계.md) | 고객·운영 대시보드 |
| **8** | [09_DEVELOPMENT/](09_DEVELOPMENT/_DEVELOPMENT_INDEX.md) | [01_개발WBS.md](09_DEVELOPMENT/01_개발WBS.md) | 9주 Phase 1~4 |
| **8** | | [02_테스트시나리오.md](09_DEVELOPMENT/02_테스트시나리오.md) | 100+ TC (08_TEST 통합) |
| **8** | | [03_배포운영.md](09_DEVELOPMENT/03_배포운영.md) | CI/CD (09_RELEASE 통합) |

### Archive (레거시 참조)

| 폴더 | INDEX | SSOT 대체 |
|------|-------|-----------|
| [08_TEST/](08_TEST/_TEST_INDEX.md) | QA 원본 4종 | `09_DEVELOPMENT/02_테스트시나리오.md` |
| [09_RELEASE/](09_RELEASE/_RELEASE_INDEX.md) | 배포 원본 5종 | `09_DEVELOPMENT/03_배포운영.md` |
| `05_CHAT/` L1~L4 | [Chat Index](05_CHAT/_CHAT_INDEX.md) §Supplementary | SSOT 01/02 |

---

## 📋 전체 SSOT 문서 목록 (~27)

| # | 경로 | STEP |
|---|------|------|
| 1 | `00_PROJECT_MASTER.md` | 1 |
| 2 | `02_UIUX/01_상담채팅화면.fig.md` | 1 |
| 3 | `02_UIUX/UI_COMPONENTS_GUIDE.md` | 1 |
| 4 | `03_SYSTEM/01_DB설계.md` | 2 |
| 5 | `03_SYSTEM/02_API설계.md` | 2 |
| 6 | `03_SYSTEM/03_시스템아키텍처.md` | 2 |
| 7 | `04_AI/01_AI전략.md` | 3 |
| 8 | `04_AI/02_Prompt설계.md` | 3 |
| 9 | `04_AI/03_AI엔진구현.md` | 3 |
| 10 | `05_CHAT/01_WebSocket설계.md` | 4 |
| 11 | `05_CHAT/02_실시간동기화.md` | 4 |
| 12 | `06_CRM/01_CRM통합.md` | 5 |
| 13 | `06_FRONTEND/01_Frontend_아키텍처.md` | 5 |
| 14 | `06_FRONTEND/02_React_컴포넌트_구현명세.md` | 5 |
| 15 | `06_FRONTEND/03_Hooks_및_상태관리.md` | 5 |
| 16 | `06_FRONTEND/04_ChatScreen_통합_구현가이드.md` | 5 |
| 17 | `07_ADMIN/01_관리자대시보드.md` | 6 |
| 18 | `07_ADMIN/02_상담원관리.md` | 6 |
| 19 | `07_ADMIN/03_설정관리.md` | 6 |
| 20 | `07_ADMIN/04_Admin_API_및_권한_명세.md` | 6 |
| 21 | `08_DASHBOARD/01_대시보드설계.md` | 7 |
| 22 | `09_DEVELOPMENT/01_개발WBS.md` | 8 |
| 23 | `09_DEVELOPMENT/02_테스트시나리오.md` | 8 |
| 24 | `09_DEVELOPMENT/03_배포운영.md` | 8 |
| — | 9× `_XXX_INDEX.md` (폴더별) | — |
| — | `_검증리포트_문서품질.md` | QA |

---

## 🔗 문서 간 의존성 (Critical Path)

```
Step 1: 00_PROJECT_MASTER.md
  └─ 전체 비전 · RBAC · 로드맵

Step 2: 01_DB설계 + 02_API설계
  └─ 데이터·API 경계

Step 3: 01_AI전략 → 02_Prompt → 03_AI엔진
  └─ Failover · Prompt · ai_call()

Step 4: 01_WebSocket → 02_실시간동기화
  └─ Socket.io · 메시지 상태

Step 5: 06_CRM + 06_FRONTEND
  └─ CRM 자동화 · React ChatScreen

Step 6: 07_ADMIN 01~04
  └─ 운영·KPI·Agent·설정

Step 7~8: 08_DASHBOARD + 09_DEVELOPMENT
  └─ 고객 대시 · WBS · QA · Deploy
```

### Cross Reference (필수 연결)

| 문서 | 참조 |
|------|------|
| [WebSocket설계](05_CHAT/01_WebSocket설계.md) | DB `chat_*`, API §12, AI §4, [실시간동기화](05_CHAT/02_실시간동기화.md) |
| [CRM통합](06_CRM/01_CRM통합.md) | DB `consults`, API close, [Prompt](04_AI/02_Prompt설계.md) |
| [관리자대시보드](07_ADMIN/01_관리자대시보드.md) | DB KPI, Admin API, [CRM](06_CRM/01_CRM통합.md) |

---

## 🤖 Cursor Rules (AI 개발 규칙)

| 파일 | 용도 |
|------|------|
| [.cursor/RULES.md](.cursor/RULES.md) | 개발 규칙 SSOT (문서 우선, SSOT 경로, 코딩 기준) |
| [.cursor/AGENTS.md](.cursor/AGENTS.md) | Cursor 에이전트 역할·워크플로 |
| [.cursor/rules/plustok-enterprise.mdc](.cursor/rules/plustok-enterprise.mdc) | 자동 적용 핵심 규칙 |
| [.cursor/rules/plustok-php.mdc](.cursor/rules/plustok-php.mdc) | PHP 파일 전용 |
| [.cursor/rules/plustok-react.mdc](.cursor/rules/plustok-react.mdc) | React/TS 파일 전용 |

---

## 💡 Cursor/AI 개발자를 위한 팁

### 문서 활용 방법
1. **한 번에 1 STEP씩** (2~3개 문서)
2. **Cross Reference** 확인 (위 표)
3. **체크리스트** 구현 (각 문서 하단)

### 막혔을 때
| 질문 | 답 |
|------|-----|
| WebSocket 이벤트? | [05_CHAT/01_WebSocket설계.md](05_CHAT/01_WebSocket설계.md) §4 |
| DB 스키마? | [03_SYSTEM/01_DB설계.md](03_SYSTEM/01_DB설계.md) §4 |
| API 응답 형식? | [03_SYSTEM/02_API설계.md](03_SYSTEM/02_API설계.md) §5 |
| 테스트 케이스? | [09_DEVELOPMENT/02_테스트시나리오.md](09_DEVELOPMENT/02_테스트시나리오.md) |

### 주의사항
- **필수 읽기:** MASTER → DB+API → AI전략 → WebSocket
- **구현 전:** Cross Reference 링크·체크리스트 확인
- **금지:** 문서 없이 구현, 일부 문서만 참조

---

## ❓ FAQ

**Q: 어디서 시작?**  
A: [00_PROJECT_MASTER.md](00_PROJECT_MASTER.md) → STEP 선택

**Q: 전체 구현 기간?**  
A: [09_DEVELOPMENT/01_개발WBS.md](09_DEVELOPMENT/01_개발WBS.md) — 9주

**Q: DB는 이미 있나?**  
A: 설계만 완료. [03_SYSTEM/01_DB설계.md](03_SYSTEM/01_DB설계.md) DDL 실행

**Q: 권장 구현 순서?**  
A: DB → API → Backend → Frontend ([WBS Phase 1](09_DEVELOPMENT/01_개발WBS.md))

**Q: 08_TEST / 09_RELEASE vs 09_DEVELOPMENT?**  
A: **SSOT = 09_DEVELOPMENT**. Archive 폴더는 원본 보존용.

**Q: 문서 품질 검증 결과?**  
A: [_검증리포트_문서품질.md](_검증리포트_문서품질.md)

---

## 📋 문서 버전 관리

| 버전 | 날짜 | 변경사항 |
|------|------|---------|
| 3.1 | 2026-07-21 | Cursor Rules (Task 4), Full GO |
| 3.0 | 2026-07-21 | STEP 1~8 완료, INDEX·품질검증 |
| 2.1 | 2026-07-21 | 09_DEVELOPMENT SSOT, archive 분리 |
| 2.0 | 2026-07-21 | CRM + Admin SSOT |
| 1.0 | 2026-07-19 | STEP 1~4 |

---

## 🎯 다음 단계

구현 전 확인:
1. [ ] INDEX.md + MASTER 읽음
2. [ ] Cross Reference 이해
3. [ ] [_검증리포트_문서품질.md](_검증리포트_문서품질.md) **Full GO** 확인
4. [ ] [.cursor/RULES.md](.cursor/RULES.md) + auto-apply `.mdc` 규칙 확인

**모두 OK?** → [09_DEVELOPMENT/01_개발WBS.md](09_DEVELOPMENT/01_개발WBS.md) Phase 1 시작 🚀
