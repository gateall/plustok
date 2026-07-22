# ACEP — 개발 WBS (Work Breakdown Structure)

**프로젝트:** PlusTok V3.0  
**Version:** 3.0 · **작성일:** 2026-07-21  
**목표 Go-Live:** 2026-08 (9주)  
**SSOT:** `09_DEVELOPMENT/01_개발WBS.md`

---

## 1. 목적 (Purpose)

STEP 1~8 전체 구현의 작업 분해, 일정, 의존성, 마일스톤, RACI를 정의한다.

---

## 2. 범위 (Scope)

- DB DDL 14+ tables
- REST API ~40 endpoints
- Socket.io Chat Server
- React Customer/Agent UI
- PHP Admin + CRM bridge
- CI/CD + Cafe24/Docker 배포

---

## 3. WBS 계층

```
Level 1: Phase (STEP)
Level 2: Category (Backend / Frontend / DevOps / QA)
Level 3: Task (구현 단위, 예상일, 담당, 의존성)
```

---

## 4. Phase 상세

### [Phase 1] Platform Setup — STEP 1~2 (2주)

| WP | Task | 담당 | 일수 | 선행 | 산출물 |
|----|------|------|------|------|--------|
| 1.1 | 14개 테이블 DDL + Index + FK | BE | 2 | — | `03_SYSTEM/01_DB설계.md` migration |
| 1.2 | Express/API Gateway, JWT 미들웨어 | BE | 3 | 1.1 | `03_SYSTEM/02_API설계.md` §Auth |
| 1.3 | Auth·Customer 기초 API 10종 | BE | 3 | 1.2 | register/login/get/update |
| 1.4 | React 프로젝트, MUI, 라우팅 | FE | 2 | — | `06_FRONTEND/01` |
| 1.5 | UI/UX SSOT 문서 | FE | 2 | — | `02_UIUX/` |

### [Phase 2] Chat System — STEP 3~4 (3주)

| WP | Task | 담당 | 일수 | 선행 | 산출물 |
|----|------|------|------|------|--------|
| 2.1 | Socket.io 서버, 15+ 이벤트 | BE | 5 | 1.3 | `05_CHAT/01_WebSocket설계.md` |
| 2.2 | 실시간 동기화·읽음·오프라인 큐 | FE+BE | 4 | 2.1 | `05_CHAT/02_실시간동기화.md` |
| 2.3 | AI Router + Failover 5-chain | BE | 4 | 1.3 | `04_AI/*` |
| 2.4 | 3-panel Chat UI (Agent) | FE | 5 | 2.1 | `06_FRONTEND/04` |
| 2.5 | Chat REST API 15종 | BE | 4 | 2.1 | rooms/messages/read/close |

### [Phase 3] CRM Integration — STEP 5 (2주)

| WP | Task | 담당 | 일수 | 선행 | 산출물 |
|----|------|------|------|------|--------|
| 3.1 | ConsultCloseService + AI summarize/analyze | BE | 4 | 2.5 | `06_CRM/01_CRM통합.md` |
| 3.2 | schedules 후속 일정 자동 생성 | BE | 2 | 3.1 | schedules DDL |
| 3.3 | CRM API + legacy admin/consults 어댑터 | BE | 3 | 3.1 | POST consults/close |
| 3.4 | Customer Widget React | FE | 4 | 2.4 | embed widget |

### [Phase 4] Admin & Customer — STEP 6~7 (2주)

| WP | Task | 담당 | 일수 | 선행 | 산출물 |
|----|------|------|------|------|--------|
| 4.1 | Admin Dashboard 5 Blocks + stats API | BE+FE | 5 | 3.3 | `07_ADMIN/01` |
| 4.2 | 상담원·설정 관리 | BE | 3 | 4.1 | `07_ADMIN/02`, `03` |
| 4.3 | Customer Dashboard 포털 | FE | 3 | 3.4 | `08_DASHBOARD/01_고객대시보드.md` |
| 4.4 | PHP Admin shell + Chart.js | BE | 2 | 4.1 | `admin/index.php` |

### [Phase 5] QA & Release — STEP 8 (2주)

| WP | Task | 담당 | 일수 | 선행 | 산출물 |
|----|------|------|------|------|--------|
| 5.1 | Unit/Integration CI (PHPUnit, Vitest) | QA | 3 | ALL | `09_DEVELOPMENT/02` |
| 5.2 | E2E-01~05 수동 + sign-off | QA | 3 | 5.1 | QA gate |
| 5.3 | Cafe24 FTP + Docker PATH B | Ops | 4 | 5.2 | `09_DEVELOPMENT/03` |
| 5.4 | Load test 1000 WS (staging) | QA+Ops | 2 | 2.1 | perf report |
| 5.5 | Production cutover + hypercare | ALL | 3 | 5.3 | runbook |



### Phase 1 — Platform Setup (2주)

| Task | 산출물 | 일수 | 의존 |
| --- | --- | --- | --- |
| 1.1 DB Schema | 03_SYSTEM/01_DB설계 DDL | 2 | — |
| 1.2 API Gateway | JWT, error handler | 3 | 1.1 |
| 1.3 Auth API | register/login/logout | 3 | 1.2 |
| 1.4 Frontend init | React + routing | 2 | — |

### Phase 2 — Chat System (3주)

| Task | 산출물 | 일수 | 의존 |
| --- | --- | --- | --- |
| 2.1 WS Server | 05_CHAT/01_WebSocket설계 | 5 | 1.3 |
| 2.2 Chat UI | 3-panel Agent screen | 5 | 2.1 |
| 2.3 AI Router | 04_AI/* | 4 | 1.3 |
| 2.4 Chat API | 15 endpoints | 4 | 2.1 |

### Phase 3 — CRM (2주)

| Task | 산출물 | 일수 | 의존 |
| --- | --- | --- | --- |
| 3.1 Close workflow | 06_CRM/01 | 4 | 2.4 |
| 3.2 CRM API | consults/close | 3 | 3.1 |

### Phase 4 — Admin & Deploy (2주)

| Task | 산출물 | 일수 | 의존 |
| --- | --- | --- | --- |
| 4.1 Admin UI | 07_ADMIN/* | 5 | 3.2 |
| 4.2 Customer Dash | 08_DASHBOARD/* | 3 | 2.2 |
| 4.3 DevOps | Docker/FTP | 4 | 4.1 |
| 4.4 QA Sign-off | 02_테스트시나리오 | 3 | ALL |

---

## 5. 일정 (9주)

| Week | Milestone |
|------|-----------|
| W2 | DB + API Gateway Ready |
| W5 | Chat + AI Integration Complete |
| W7 | CRM + Admin + Customer Dashboard |
| W9 | Production Go-Live |

---

## 6. 팀 구성

| 팀 | 인원 | 역할 |
|----|------|------|
| Backend | 4 | API, CRM, AI, DevOps |
| Frontend | 3 | Chat, Admin, Customer |
| QA | 2 | Automation, E2E, Performance |

---

## 7. 위험 및 완화

| Risk | Impact | Mitigation |
| --- | --- | --- |
| AI API 지연 | Chat SLA | Mock AI early |
| 1000 WS conn | Scale | W5 load test |
| WS stability | UX | Reconnect + IndexedDB queue |
| Cafe24 제약 | No Redis/Node | V1.0 poll fallback |

---

## 8~11. DoD · 추적 · 보고 · Future

- **DoD:** 문서 SSOT + PR merge + QA TC pass + audit log
- **추적:** Jira epic STEP-N, sprint 2주
- **보고:** Weekly stakeholder demo
- **Future:** STEP 14 AI Ops Center V2.5

---

## 8. STEP 1~14 마일스톤 (문서·구현 로드맵)

| STEP | 명칭 | 문서 SSOT | 구현 마일스톤 | 목표일 |
|------|------|-----------|---------------|--------|
| 1 | Project Setup + UI/UX | `02_UIUX/` | 폴더 구조, 컴포넌트 가이드 | W1 |
| 2 | DB + API Architecture | `03_SYSTEM/` | DDL 14 tables, OpenAPI 30 endpoints | W2 |
| 3 | AI Strategy & Engine | `04_AI/` | ai_call(), Failover, Prompt CRUD | W3 |
| 4 | WebSocket + Real-time | `05_CHAT/01`, `02` | Chat Server, dual-path sync | W5 |
| 5 | CRM Integration | `06_CRM/01` | ConsultCloseService, legacy bridge | W6 |
| 6 | Admin Dashboard | `07_ADMIN/01~03` | KPI, stats API, PHP Admin | W7 |
| 7 | Real-time Stats Dashboard | `08_DASHBOARD/01` | StatsAggregator, poll→WS 설계 | W7 |
| 8 | WBS + QA + Deploy | `09_DEVELOPMENT/01~03` | CI, E2E sign-off, Cafe24 deploy | W9 |
| 9–13 | (reserved) | — | 멀티테넌트, SSO, Public API | TBD |
| 14 | AI Ops Center V2.5 | `08_DASHBOARD` ext | ML metrics, token cost, A/B prompt | V2.5 |

---

## 9. RACI 매트릭스 (Phase별)

| Activity | PM | BE Lead | FE Lead | QA | Ops |
|----------|:--:|:-------:|:-------:|:--:|:---:|
| DB Schema sign-off | A | R | C | I | I |
| API contract freeze | A | R | C | C | I |
| Chat Server deploy | I | R | C | C | A |
| AI Failover config | C | R | I | C | I |
| CRM close workflow | A | R | C | C | I |
| Admin Dashboard | C | R | R | C | I |
| E2E sign-off | A | C | C | R | I |
| Production deploy | A | C | I | C | R |

> **R**=Responsible, **A**=Accountable, **C**=Consulted, **I**=Informed

---

## 10. Sprint 상세 (2주 스프린트 × 5)

### Sprint 1 (W1–W2): Foundation

| Story | Points | Owner | Acceptance |
|-------|--------|-------|------------|
| DB migration 14 tables | 8 | BE | phpMyAdmin apply, FK valid |
| JWT auth middleware | 5 | BE | login 201, expired 401 |
| React scaffold + routing | 5 | FE | /login, /chat routes |
| UI/UX doc review | 3 | FE+PM | 02_UIUX sign-off |

### Sprint 2 (W3–W4): AI + Backend Core

| Story | Points | Owner | Acceptance |
|-------|--------|-------|------------|
| ai_call() + Failover chain | 13 | BE | FO-001~003 pass |
| Prompt loader + 12 roles | 8 | BE | JSON schema validate |
| Customer API 10 endpoints | 8 | BE | Postman collection green |
| Vitest component setup | 5 | FE | 3 components pass |

### Sprint 3 (W5–W6): Real-time Chat

| Story | Points | Owner | Acceptance |
|-------|--------|-------|------------|
| Socket.io server + Redis | 13 | BE | TC-WS-P01~05 |
| useSocket + optimistic UI | 13 | FE | IT-20~27 pass |
| Chat REST 15 endpoints | 8 | BE | API-005~013 |
| Agent 3-panel UI | 13 | FE | IT-50~54 desktop |

### Sprint 4 (W7–W8): CRM + Admin

| Story | Points | Owner | Acceptance |
|-------|--------|-------|------------|
| ConsultCloseService | 13 | BE | E2E-01 Step 10 |
| Admin stats API 4 endpoints | 8 | BE | A-01~04 |
| Dashboard 5 Blocks PHP | 13 | BE+FE | TC-DASH-01~05 |
| CRM legacy adapter | 8 | BE | admin/consults sync |

### Sprint 5 (W9–W10): QA + Release

| Story | Points | Owner | Acceptance |
|-------|--------|-------|------------|
| PHPUnit + Vitest CI | 8 | QA | coverage ≥ 80% |
| E2E-01~05 staging | 13 | QA | sign-off §15 |
| Cafe24 FTP deploy | 8 | Ops | SMK-01~07 |
| Hypercare 72h | 5 | ALL | 0 P0 bugs |

---

## 11. Critical Path (의존성)

```
1.1 DB ──► 1.2 API Gateway ──► 1.3 Auth
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
              2.3 AI Router    2.1 WS Server   1.4 React init
                    │               │
                    │               ├──► 2.5 Chat API ──► 3.1 CRM Close
                    │               │                           │
                    │               └──► 2.2 Sync + 2.4 UI ─────┤
                    │                                           ▼
                    └──────────────────────────────────► 4.1 Admin Dashboard
                                                                    │
                                                                    ▼
                                                              5.2 E2E ──► 5.3 Deploy
```

**Critical path duration:** W1 → W9 (9 weeks). Slack: 3 days on Phase 3 CRM.

---

## 12. 리소스 캘린더 (FTE)

| Week | BE | FE | QA | Ops | Notes |
|------|----|----|----|----|-------|
| W1–W2 | 4.0 | 2.0 | 0.5 | 0 | DB/API focus |
| W3–W4 | 4.0 | 2.5 | 1.0 | 0 | AI integration |
| W5–W6 | 3.5 | 3.0 | 1.0 | 0.5 | Chat peak |
| W7–W8 | 3.0 | 2.0 | 1.5 | 0.5 | Admin + CRM |
| W9–W10 | 2.0 | 1.0 | 2.0 | 1.0 | QA + deploy |

---

## 13. Phase별 Acceptance Criteria

### Phase 1 (STEP 1–2)

- [ ] 14 tables created on staging DB
- [ ] 30 REST endpoints documented in OpenAPI
- [ ] Auth login/logout/refresh integration test pass
- [ ] React app boots with MUI theme

### Phase 2 (STEP 3–4)

- [ ] ai_call() Failover FO-001~003 manual pass
- [ ] WebSocket TC-WS-P01~10 integration pass
- [ ] Agent ChatScreen IT-01~72 P0 subset pass
- [ ] Message round-trip < 1s on staging

### Phase 3 (STEP 5)

- [ ] PUT /chats/{id}/close triggers CRM write
- [ ] consults table row created/updated
- [ ] schedules auto-insert when contract_probability ≥ 80
- [ ] E2E-01 Step 10 CRM verify pass

### Phase 4 (STEP 6–7)

- [ ] Admin Dashboard 4 KPI cards load < 2s
- [ ] Stats API A-01~04 return valid JSON
- [ ] Operator RBAC: no export, no write
- [ ] StatsAggregator Redis cache hit ≥ 70%

### Phase 5 (STEP 8)

- [ ] CI green on develop branch
- [ ] E2E sign-off 4 roles signed
- [ ] SMK-01~07 post-deploy pass
- [ ] KNOWN_ISSUES.md reviewed

---

## 14. 위험 레지스터 (확장)

| ID | Risk | Prob | Impact | Mitigation | Owner | Status |
|----|------|------|--------|------------|-------|--------|
| R-01 | AI API rate limit | M | H | Failover + mock CI | BE | Open |
| R-02 | Cafe24 no Redis/Node | H | M | V1.0 poll fallback | Ops | Mitigated |
| R-03 | Legacy CRM schema drift | M | H | Field mapping doc §5 | BE | Open |
| R-04 | WS 1000 conn load fail | L | H | Staging load test W5 | QA | Open |
| R-05 | PII leak in logs | L | H | PII-01~05 audit | BE | Open |
| R-06 | Admin stats query slow | M | M | Redis cache + indexes | BE | Open |
| R-07 | FTP partial upload | M | H | Manifest + MD5 verify | Ops | Open |
| R-08 | Key person unavailable | L | M | Pair programming | PM | Open |

---

## 15. 커뮤니케이션 · 보고

| Meeting | Frequency | Attendees | Output |
|---------|-----------|-----------|--------|
| Daily standup | Daily 09:30 | Dev team | Blockers |
| Sprint review | Bi-weekly Fri | ALL + PM | Demo |
| Stakeholder demo | Weekly Wed | PM + leads | Status slide |
| Go/No-Go | W9 Mon | QA + Ops + PM | Release decision |

---

## 16. 문서·코드 Traceability

| WBS Task | Doc SSOT | Code Path | Test ID |
|----------|----------|-----------|---------|
| 2.1 WS Server | 05_CHAT/01 | chat-server/ | TC-WS-P* |
| 2.3 AI Router | 04_AI/03 | includes/ai.php | FO-001~007 |
| 3.1 CRM Close | 06_CRM/01 | ConsultCloseService.php | E2E-01-S10 |
| 4.1 Admin Dash | 07_ADMIN/01 | admin/index.php | TC-DASH-* |
| 5.2 E2E | 09_DEV/02 | — | E2E-01~05 |
| 5.3 Deploy | 09_DEV/03 | — | SMK-01~07 |

---

## 17. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-07-21 | STEP 8 WBS 초판 — Phase 1~5, 9주 일정 |
| 1.1.0 | 2026-07-21 | STEP 1~14 마일스톤, RACI, Sprint, Critical path 추가 |

---

**문서 끝 — PM은 본 WBS를 Jira Epic/Story 생성의 기준으로 사용한다.**

