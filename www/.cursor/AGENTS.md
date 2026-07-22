# PlusTok ACEP — Cursor Agent Rules

**Purpose:** Define how Cursor agents behave when working in `www/`  
**Version:** 1.0 · **Updated:** 2026-07-21

> Companion: [RULES.md](RULES.md) · Auto-apply: `.cursor/rules/plustok-enterprise.mdc`

---

## Agent Roles

### Role: Code Implementer

```markdown
- Always open INDEX.md + 00_PROJECT_MASTER.md first
- Read full STEP SSOT before writing code
- Follow Cross References to DB/API/UI docs
- Match existing naming conventions in target folder
- Use ai_call() for all AI provider access
- Write or update tests per 09_DEVELOPMENT/02
- Mark document checklists when items are done
```

### Role: Quality Reviewer

Review checklist:

1. **Doc compliance** — implementation matches STEP SSOT
2. **Schema/API alignment** — fields match `03_SYSTEM/`
3. **Security** — no hardcoded secrets; prepared SQL; XSS/CSRF on admin
4. **AI failover** — uses `ai_call()`, logs to `ai_failover_log`
5. **Tests** — relevant TC from `09_DEVELOPMENT/02` covered
6. **Scope** — no unrelated changes

### Role: Documentation Keeper

```markdown
- Code change → update affected SSOT if behavior diverges
- New endpoints → update 03_SYSTEM/02_API설계.md
- New tables/columns → update 03_SYSTEM/01_DB설계.md
- Keep _XXX_INDEX.md current
- Fix broken Cross Reference links immediately
```

---

## Session Workflow

### On Session Start

1. Read `INDEX.md` (navigation)
2. Read `00_PROJECT_MASTER.md` (constitution)
3. Identify active STEP from user task
4. Open STEP SSOT + folder index
5. Check `_검증리포트_문서품질.md` for quality gate status

### During Implementation

```
For each sub-task:
  → Read relevant STEP section
  → Check DB (01_DB설계) + API (02_API설계)
  → Implement minimal diff
  → Run applicable tests
  → Verify against document checklist
```

### Before PR / Commit

- [ ] STEP doc checklist items addressed
- [ ] No API keys or secrets in diff
- [ ] Tests pass (PHPUnit / Vitest as applicable)
- [ ] Cross References still valid
- [ ] Korean UI copy preserved where specified

---

## STEP-Specific Agent Focus

| STEP | Primary Docs | Agent Priority |
|------|-------------|----------------|
| 1–2 | UIUX, SYSTEM | DB DDL, API skeleton, auth |
| 3 | 04_AI | ai_call(), failover, prompts |
| 4 | 05_CHAT | Socket.io events, sync |
| 5 | 06_CRM, 06_FRONTEND | CRM close, React ChatScreen |
| 6 | 07_ADMIN | Dashboard KPI, agent mgmt |
| 7 | 08_DASHBOARD | Customer portal |
| 8 | 09_DEVELOPMENT | WBS phases, QA, deploy |

---

## Checkpoints

### After Each STEP Phase (WBS)

1. Phase deliverables match WBS table in `09_DEVELOPMENT/01_개발WBS.md`
2. Integration tests for phase pass
3. SSOT docs reflect actual implementation

### Pre-Deploy (STEP 8)

Reference: `09_DEVELOPMENT/02_테스트시나리오.md`

1. All P0/P1 test cases pass
2. Failover chain tested (≥1 forced failure per provider skip)
3. WebSocket load target met (WBS 5.4)
4. Cafe24/Docker deploy checklist in `09_DEVELOPMENT/03_배포운영.md`

---

## Escalation

When documentation is ambiguous:

1. Check `00_PROJECT_MASTER.md` Appendix B cross-refs
2. Check folder `_XXX_INDEX.md` for SSOT vs archive distinction
3. Prefer SSOT over legacy/archive files
4. Do not invent APIs or schema — ask or flag gap in doc

---

*PlusTok ACEP Enterprise · Agent Rules v1.0*
