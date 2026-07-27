# CLAUDE — ADMIN REACT FINAL INTEGRATION REVIEW

**Priority:** P0  
**Owner:** Claude Code  
**Mode:** **READ-ONLY REVIEW FIRST**  
**Date:** 2026-07-27  
**Status:** WAIT — Cursor Phase 1 submitted; implementation not started

---

## Trigger

Execute this review **after**:

1. Cursor completes `feature/cursor-admin-core` (consults, customers, contracts, sites + shared components)
2. Codex completes `feature/codex-admin-ops` (dashboard, products, stats, users, settings) — *when assigned*
3. Cursor performs final `App.tsx` route integration

**Do not review until both branches have commits.**

---

## Repository

| Item | Value |
|------|-------|
| Base | `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_MERGE_MAIN\www` |
| Cursor branch | `feature/cursor-admin-core` |
| Codex branch | `feature/codex-admin-ops` |
| Baseline SHA | `d1ca12f97bc358c50d92b31201c95cb359d61dca` |

---

## Review Scope (16 items)

1. PHP 기능 누락 (consults, customers, contracts, sites, users, settings, products, stats)
2. React 화면 기능 완성도
3. API 계약 일치 (request params, response envelope `{ success, data, error }`)
4. Route 충돌 (`App.tsx`, static before dynamic, `new` before `:id`)
5. 공통 컴포넌트 중복 (`admin-ui` vs `common` vs per-domain)
6. CSS 중복·회귀 (mobile+PC 동시 노출, 769px breakpoint)
7. Mobile First 준수 (360, 768)
8. PC 화면 회귀 (769, 1024, 1440)
9. JWT 인증 (`loginId`, Bearer header)
10. 역할 권한 (`admin`, `operator`, `agent`)
11. 민감정보 노출 (API keys, PII in console)
12. Mock 데이터 운영 노출
13. **Contracts 복구 기능 회귀** (list API, empty state, error UI, schema guard)
14. PHP 관리자 기존 기능 영향 (legacy paths preserved)
15. 병합 충돌 가능성 (file ownership matrix)
16. 배포 파일 범위 (dist only vs PHP backend)

---

## Required Widths

| px | Check |
|----|-------|
| 360 | Mobile cards, no overflow |
| 768 | Mobile layout boundary |
| 769 | PC table appears, mobile hidden |
| 1024 | Sidebar + content |
| 1440 | Max-width shell |

---

## Required Commands

```powershell
cd frontend
npm run build
```

PHP changed files:

```powershell
php -l <each-changed-file>
```

Tests (if env allows):

```powershell
npm run test:ci
php vendor/bin/phpunit tests/Feature/AdminContractApiTest.php
php vendor/bin/phpunit tests/Feature/AuthApiTest.php
```

---

## File Ownership Matrix (conflict detection)

### Cursor-owned patterns

```
AdminConsult*  Consult*
AdminCustomer* Customer*
AdminContract* Contract*
AdminSite*     Site*
components/admin-ui/* (1차)
App.tsx (final integration)
config/adminNav.ts
```

### Codex-owned patterns

```
AdminDashboard*  Dashboard*
AdminProduct*    Product*
AdminStats*      Stats*
AdminUser*       User*
AdminSetting*    Setting*
```

### Shared — Cursor integrates, Codex reports only

```
App.tsx routes (Codex submits route request list)
layouts/*
types/* (coordinate)
services/api/* (coordinate)
```

---

## Known Phase 1 Blockers (pre-review baseline)

From Cursor investigation @ `d1ca12f`:

| Blocker | Impact |
|---------|--------|
| No `/admin/sites` REST | Sites React BLOCK |
| No customer write API | Customer form/detail BLOCK |
| No consult reply REST | Email/SMS in React BLOCK |
| Production `V3.1.0` migration | Contracts prod BLOCK |

Claude should flag any **mock data used to bypass these** as **Critical FAIL**.

---

## Submit Format

### PASS items

- Bullet list with file evidence

### FAIL items

| Severity | Item | File | Owner |
|----------|------|------|-------|
| Critical/High/Medium/Low | ... | ... | Cursor / Codex |

### Additional sections

- 파일별 충돌 목록
- 수정 소유자 지정
- 권장 병합 순서
- 배포 가능 파일 목록 (FTP scope)
- 최종 권고

---

## Prohibited

- FTP · DB 수정 · 운영 변경
- PM 승인 없는 대규모 재작성
- 자동 병합

---

## Final Verdict (pick one)

| Verdict | Meaning |
|---------|---------|
| **INTEGRATION PASS** | Safe to merge + deploy (PM approval) |
| **FIX REQUIRED — CURSOR** | Core screens need Cursor fixes |
| **FIX REQUIRED — CODEX** | Ops screens need Codex fixes |
| **BLOCK — ARCHITECTURE CONFLICT** | Route/component ownership broken |

---

## References

- Cursor Phase 1: `16_AI_ASSIGNMENT/CURSOR_ADMIN_CORE_REACT_WORK.md`
- Contracts fix: `17_RELEASES/Release_3.4/CONTRACT_LIST_API_FIX_REPORT.md`
- Contract API handoff: `16_AI_ASSIGNMENT/DEEPSEEK_CONTRACT_API_FIX_WORK.md`
