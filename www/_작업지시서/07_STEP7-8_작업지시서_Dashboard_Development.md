# STEP 7–8 작업지시서 — Dashboard & Development

**작성일:** 2026-07-21  
**목표 릴리스:** V1.0 2026-08

## 1. 목표

- STEP 7: [08_DASHBOARD/01_대시보드설계.md](../08_DASHBOARD/01_대시보드설계.md) 기준 Stats UI
- STEP 8: [09_DEVELOPMENT/02_테스트시나리오.md](../09_DEVELOPMENT/02_테스트시나리오.md) QA Gate

## 2. SSOT

| 문서 | 용도 |
|------|------|
| 01_대시보드설계.md | KPI architecture, poll→WS |
| 01_개발WBS.md | STEP 1–14 |
| 03_배포운영.md | Cafe24/Docker/runbook |

## 3. Work Packages

| WP | 내용 | 담당 | DoD |
| --- | --- | --- | --- |
| WP7.1 | StatsAggregator + Redis | BE | cache TTL matrix |
| WP7.2 | operator vs admin view | FE | RBAC hide KPI |
| WP7.3 | Chart.js bundle | FE | 3 charts |
| WP8.1 | API test matrix CI | QA | 70% coverage gate |
| WP8.2 | E2E-01~05 | QA | playwright/cypress |
| WP8.3 | Cafe24 FTP dry-run | Ops | rollback doc |
| WP8.4 | Smoke on staging | Ops | 6 checks |

### WP7.1 — StatsAggregator + Redis

담당 BE. DoD: cache TTL matrix.

### WP7.2 — operator vs admin view

담당 FE. DoD: RBAC hide KPI.

### WP7.3 — Chart.js bundle

담당 FE. DoD: 3 charts.

### WP8.1 — API test matrix CI

담당 QA. DoD: 70% coverage gate.

### WP8.2 — E2E-01~05

담당 QA. DoD: playwright/cypress.

### WP8.3 — Cafe24 FTP dry-run

담당 Ops. DoD: rollback doc.

### WP8.4 — Smoke on staging

담당 Ops. DoD: 6 checks.

## 4. QA Gate (V1.0)

- [ ] E2E-01 CRM webhook end-to-end
- [ ] E2E-03 Admin KPI poll
- [ ] TC-WS reconnect gap
- [ ] 03_배포운영 smoke green
- [ ] CHANGELOG v1.0.0-rc

## 5. 배포 연계

- [09_RELEASE/03_FTP_Cafe24_배포_가이드.md](../09_RELEASE/03_FTP_Cafe24_배포_가이드.md)
- [09_RELEASE/05_릴리스_런북.md](../09_RELEASE/05_릴리스_런북.md)

## 6. RACI (요약)

| 활동 | PM | QA | Ops |
|------|:--:|:--:|:---:|
| QA Gate | A | R | C |
| Prod cutover | A | C | R |

## 7. 참조

- [08_DASHBOARD/_DASHBOARD_INDEX.md](../08_DASHBOARD/_DASHBOARD_INDEX.md)
## 8. E2E 매핑

| ID | 시나리오 | WP |
| --- | --- | --- |
| E2E-01 | CRM webhook | WP5.x regression |
| E2E-02 | AI failover | WP8.2 |
| E2E-03 | Admin KPI | WP7.x |
| E2E-04 | Failover widget | WP7.3 |
| E2E-05 | RBAC 403 | WP7.2 |

## 9. 환경 변수 (배포 전)

- `PLUS_TOK_CRM_WEBHOOK_URL` — staging/prod 분리 확인
- `JWT_SECRET` — staging/prod 분리 확인
- `REDIS_URL` — staging/prod 분리 확인
- `DB_HOST` — staging/prod 분리 확인

## 10. Definition of Done (STEP 8)

- [ ] 02_테스트시나리오 API matrix CI green
- [ ] 03_배포운영 smoke 6/6
- [ ] Dashboard operator view QA
- [ ] WBS STEP 8 마일스톤 체크
- [ ] PM sign-off V1.0 gate

## 11. 커뮤니케이션

| 역할 | 채널 | 주기 |
|------|------|------|
| PM | standup | daily |
| QA | test report | weekly |
| Ops | deploy window | bi-weekly |

### Sprint checkpoint 1

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 2

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 3

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 4

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 5

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 6

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 7

- Demo KPI dashboard
- Regression subset
- Risk review

### Sprint checkpoint 8

- Demo KPI dashboard
- Regression subset
- Risk review

