# STEP 6 작업지시서 — Admin + Dashboard

> **프로젝트**: PlusTok ACEP  
> **STEP**: 6 · Admin + Dashboard  
> **작성일**: 2026-07-21  
> **선행 STEP**: STEP 5 Frontend (Customer/Agent — Admin out of scope)  
> **SSOT 문서**: [`07_ADMIN/`](../07_ADMIN/_ADMIN_INDEX.md)

---

## 1. 목표

PlusTok ACEP **관리자 콘솔** 및 **AI 운영 센터 Dashboard**를 설계·구현한다.

| 산출물 | 문서 |
|--------|------|
| UI/UX 설계 | [07_ADMIN/01_관리자화면_UIUX_설계.md](../07_ADMIN/01_관리자화면_UIUX_설계.md) |
| Dashboard 구현 | [07_ADMIN/02_Admin_Dashboard_구현명세.md](../07_ADMIN/02_Admin_Dashboard_구현명세.md) |
| PHP 모듈 | [07_ADMIN/03_Admin_모듈_구현명세.md](../07_ADMIN/03_Admin_모듈_구현명세.md) |
| API·권한 | [07_ADMIN/04_Admin_API_및_권한_명세.md](../07_ADMIN/04_Admin_API_및_권한_명세.md) |

---

## 2. 범위

### 2.1 In Scope

- [ ] Admin 공통 레이아웃 (sidebar, header, RBAC menu)
- [ ] Dashboard Home (KPI 4 + Chart 3 + Widget 2)
- [ ] Consult List ACEP 통합 (`chat_rooms`)
- [ ] 기존 `ai.php` ACEP 필드 확장 (failover_threshold 등)
- [ ] Prompt editor (`ai_prompts` CRUD)
- [ ] Failover log viewer
- [ ] Live chat monitor (read-only, polling)
- [ ] Agent management + assignment UI
- [ ] Admin Domain REST API 10 endpoints
- [ ] audit_logs 연동

### 2.2 Out of Scope

- React Admin SPA 구현 (V2.0 — API-first만 STEP 6)
- Mobile Admin (Monitor read-only V1.5)
- WebSocket admin namespace (V1.5)
- STEP 14 AI 운영 센터 ML metrics (설계 preview만)

---

## 3. 선행 조건

| # | 조건 | 확인 |
|---|------|------|
| P-01 | STEP 3 DB: `chat_rooms`, `messages`, `ai_logs`, `agents` | ☐ |
| P-02 | STEP 3 DB: `ai_prompts`, `ai_failover_log`, `audit_logs` migration | ☐ |
| P-03 | STEP 5 Agent Frontend 완료 (Admin 분리 확인) | ☐ |
| P-04 | 기존 `admin/settings/ai.php` 운영 중 | ☐ |
| P-05 | `includes/auth.php` require_role 패턴 | ☐ |

---

## 4. 작업 패키지

### WP-1: Admin Foundation (P0)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W1-01 | `admin_layout_header/footer.php` 통합 | BE/FE | 4h | 03 §4 |
| W1-02 | `admin/config/menu.json` + role filter | BE | 2h | 03 §13 |
| W1-03 | `includes/csrf.php` 전 페이지 적용 | BE | 2h | 03 §12 |
| W1-04 | 403/419 error pages | FE | 2h | 01 §3 |
| W1-05 | Admin CSS baseline (1440px) | FE | 4h | 01 §2.3 |

**완료 기준**: super/admin/operator 로그인 → sidebar 메뉴 RBAC별 다름

---

### WP-2: Consult + AI Legacy (P0)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W2-01 | `consults/index.php` ACEP chat_rooms UNION | BE | 8h | 03 §5 |
| W2-02 | CRM/ACEP badge + filter | FE | 2h | 01 §6.3 |
| W2-03 | `ai_summary/reply/analyze.php` ACEP room_id | BE | 4h | 03 §7 |
| W2-04 | `ai.php` 문서 정합 regression test | QA | 2h | 03 §6 |
| W2-05 | `ai.php` failover_threshold 필드 추가 | BE | 2h | 03 §6.5 |

**완료 기준**: ACEP room에서 AI 요약/분석 동작, ai.php 기존 기능 regression pass

---

### WP-3: Dashboard (P1)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W3-01 | Stats API: overview, sentiment, funnel, agents | BE | 12h | 02 §6, 04 §2 |
| W3-02 | DB indexes for dashboard queries | BE | 2h | 02 §7.3 |
| W3-03 | `admin/index.php` + partials | FE | 6h | 02 §8 |
| W3-04 | `dashboard.js` + Chart.js | FE | 6h | 02 §8.4 |
| W3-05 | Redis cache 30s overview (optional) | BE | 2h | 02 §11 |
| W3-06 | operator read-only (no CSV export) | FE | 1h | 02 §8.5 |

**완료 기준**: Dashboard KPI·차트 로드 < 3s, operator export 버튼 없음

---

### WP-4: Prompt + Failover (P1)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W4-01 | `prompts.php` list + edit | BE/FE | 8h | 03 §9 |
| W4-02 | Prompt API CRUD (A-07) | BE | 6h | 04 §3.3 |
| W4-03 | Activate flow + version history | BE | 4h | 03 §9.4 |
| W4-04 | `failover.php` viewer | BE/FE | 4h | 03 §10 |
| W4-05 | Failover API (A-08) | BE | 2h | 04 §3.4 |
| W4-06 | audit_logs on prompt activate | BE | 2h | 04 §6 |

**완료 기준**: prompt activate → audit row, admin delete prompt → 403

---

### WP-5: Monitor + Agents (P2)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W5-01 | `monitor/index.php` read-only layout | FE | 6h | 01 §6.2, 03 §8 |
| W5-02 | Monitor API rooms/messages (A-06) | BE | 4h | 04 §3.2 |
| W5-03 | `monitor.js` polling 30s | FE | 3h | 03 §8.2 |
| W5-04 | `agents/index.php` + edit | BE/FE | 8h | 03 §11 |
| W5-05 | Assignment API (A-09) | BE | 4h | 04 §3.5 |
| W5-06 | max concurrent validation | BE | 2h | 04 §7.3 |

**완료 기준**: Monitor DOM에 input 없음, assign at max → 422

---

### WP-6: Security + QA (P0-P1)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W6-01 | JWT admin aud middleware | BE | 4h | 04 §5 |
| W6-02 | audit_logs viewer `audit.php` | BE/FE | 4h | 04 §3.6 |
| W6-03 | Rate limiting admin stats | BE | 2h | 04 §8 |
| W6-04 | TC-ADM-* test cases execution | QA | 8h | 01 §10 |
| W6-05 | API-AUTH/PRM/ASG tests | QA | 4h | 04 §10 |

---

## 5. 일정 (권장)

| Week | WP | Milestone |
|------|-----|-----------|
| W1 | WP-1, WP-2 | Admin shell + Consult ACEP |
| W2 | WP-3 | Dashboard live |
| W3 | WP-4 | Prompt + Failover |
| W4 | WP-5, WP-6 | Monitor + Agents + QA sign-off |

---

## 6. Definition of Done

- [ ] All P0/P1 tasks complete
- [ ] [`01_관리자화면_UIUX_설계.md`](../07_ADMIN/01_관리자화면_UIUX_설계.md) §10 test cases pass
- [ ] Admin 10 API endpoints documented in OpenAPI appendix
- [ ] `ai.php` regression pass — no breaking changes
- [ ] audit_logs populated for prompt/key/assignment events
- [ ] operator cannot POST any admin endpoint (403)
- [ ] agent role blocked from `/admin/*` (403)
- [ ] `00_PROJECT_MASTER.md` STEP 6 ✅

---

## 7. 리스크

| Risk | Mitigation |
|------|------------|
| CRM/ACEP duplicate consult rows | migration_map + source filter |
| Dashboard query slow | indexes + Redis cache |
| ai.php key exposure | super-only reveal, server-side strip |
| Prompt bad deploy | activate confirm + version rollback |

---

## 8. 참조 코드

```
admin/settings/ai.php          ← AI 설정 참조 구현 (변경 시 03 §6 동기화)
admin/consults/ai_summary.php  ← AI 요약
admin/consults/ai_reply.php    ← AI 답변
admin/consults/ai_analyze.php  ← AI 분석
includes/auth.php              ← require_role
```

---

## 9. 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 6 작업지시서 초판 |
