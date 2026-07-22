# STEP 5–6 작업지시서 — CRM & Admin

**작성일:** 2026-07-21  
**대상:** Backend, Frontend, QA, PM

## 1. 배경

PLUS톡 ACEP V3.0에서 STEP 5(CRM)와 STEP 6(Admin)을 동시에 마감한다. CRM은 상담 종료 webhook과 `external_crm_id`를, Admin은 KPI·Chart·Stats API SSOT를 구현한다.

## 2. SSOT 문서

| 영역 | SSOT |
|------|------|
| CRM | [06_CRM/01_CRM통합.md](../06_CRM/01_CRM통합.md) |
| Admin | [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) |

## 3. 범위 (In / Out)

| In Scope | Out of Scope |
|----------|-------------|
| CrmSyncService + outbox | 외부 CRM UI 개편 |
| legacy consults adapter | Multi-tenant |
| Admin KPI 4 + Chart 3 | WS admin namespace (V1.5) |

## 4. 작업 패키지 (WP)

| WP | 내용 | 담당 | DoD | 검증 |
| --- | --- | --- | --- | --- |
| WP5.1 | customers DDL + phone_hash | BE | migration script | EXPLAIN idx_customers_phone_hash |
| WP5.2 | 필드 매핑 consults→chat_rooms | BE | mapping doc §3 | CRM-INT-01~05 pass |
| WP5.3 | CrmSyncService.onRoomClosed | BE | unit tests | audit room.close |
| WP5.4 | crm_outbox worker | BE/Ops | retry 3x | dead-letter alert |
| WP5.5 | Webhook HMAC + masking | BE/Sec | staging 20 calls | no plain PII in payload |
| WP6.1 | GET /admin/stats/summary | BE | OpenAPI snippet | 4 KPI keys |
| WP6.2 | Chart endpoints x3 | BE+FE | Chart.js | CHT-01~03 render |
| WP6.3 | Live widgets x2 | BE | poll matrix | Failover + Active rooms |
| WP6.4 | PHP dashboard shell | FE | React mount | RBAC menu hide |
| WP6.5 | admin/consults adapter | BE | filters 1:1 legacy | export CSV parity |

### WP5.1 상세 — customers DDL + phone_hash

- **담당:** BE
- **DoD:** migration script
- **검증:** EXPLAIN idx_customers_phone_hash

### WP5.2 상세 — 필드 매핑 consults→chat_rooms

- **담당:** BE
- **DoD:** mapping doc §3
- **검증:** CRM-INT-01~05 pass

### WP5.3 상세 — CrmSyncService.onRoomClosed

- **담당:** BE
- **DoD:** unit tests
- **검증:** audit room.close

### WP5.4 상세 — crm_outbox worker

- **담당:** BE/Ops
- **DoD:** retry 3x
- **검증:** dead-letter alert

### WP5.5 상세 — Webhook HMAC + masking

- **담당:** BE/Sec
- **DoD:** staging 20 calls
- **검증:** no plain PII in payload

### WP6.1 상세 — GET /admin/stats/summary

- **담당:** BE
- **DoD:** OpenAPI snippet
- **검증:** 4 KPI keys

### WP6.2 상세 — Chart endpoints x3

- **담당:** BE+FE
- **DoD:** Chart.js
- **검증:** CHT-01~03 render

### WP6.3 상세 — Live widgets x2

- **담당:** BE
- **DoD:** poll matrix
- **검증:** Failover + Active rooms

### WP6.4 상세 — PHP dashboard shell

- **담당:** FE
- **DoD:** React mount
- **검증:** RBAC menu hide

### WP6.5 상세 — admin/consults adapter

- **담당:** BE
- **DoD:** filters 1:1 legacy
- **검증:** export CSV parity

## 5. API · Webhook 체크리스트

- [ ] PATCH /api/v1/admin/rooms/{id}/close → RoomClosed event
- [ ] POST CRM webhook consultation.closed 샘플 20건
- [ ] GET /api/v1/admin/stats/summary KPI 4종
- [ ] legacy admin/consults 필터 site/status/manager
- [ ] RBAC operator 403 on bulk_delete

## 6. 일정 (3주)

| 주 | CRM | Admin |
|---|-----|-------|
| W1 | WP5.1~5.2 | WP6.1 |
| W2 | WP5.3~5.5 | WP6.2~6.3 |
| W3 | WP5.5 QA | WP6.4~6.5 |

## 7. 리스크

| 리스크 | 완화 |
| --- | --- |
| CRM URL 장애 | outbox + backoff |
| PII 유출 | 마스킹 + 암호화 |
| legacy SQL drift | read-model view |

## 8. 참조

- [06_CRM/_CRM_INDEX.md](../06_CRM/_CRM_INDEX.md)
- [07_ADMIN/_ADMIN_INDEX.md](../07_ADMIN/_ADMIN_INDEX.md)
- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §8
