# Dashboard 모듈 인덱스

**갱신:** 2026-07-21

## SSOT

| 문서 | 설명 |
|------|------|
| [01_대시보드설계.md](01_대시보드설계.md) | StatsAggregator, Redis, KPI 파이프라인, poll/WS, API·SQL SSOT |

## 보조 (고객 포털)

| 문서 | 설명 |
|------|------|
| [01_고객대시보드.md](01_고객대시보드.md) | Customer portal `/customer/dashboard` — **운영 통계 SSOT 아님** |

## 교차 참조

- [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) — PHP Admin UI·Chart.js·partial 구현 SSOT
- [07_ADMIN/_ADMIN_INDEX.md](../07_ADMIN/_ADMIN_INDEX.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)
- [06_FRONTEND/_FRONTEND_INDEX.md](../06_FRONTEND/_FRONTEND_INDEX.md) — React V2.0 DashboardPage

## STEP

| STEP | 내용 |
|------|------|
| 7 | 08_DASHBOARD SSOT + Admin UI 연동 |
| 8 | QA TC-DASH-* |

## 구현 체크리스트

- [ ] StatsAggregator cron + Redis TTL
- [ ] `/api/v1/admin/stats/*` ↔ 07_ADMIN §6
- [ ] V1.0 Chart.js poll intervals (5/10/30/60s)
- [ ] V1.5 `/admin` WS `stats:delta` (설계만 → 구현 STEP 8)