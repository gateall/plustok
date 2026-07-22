# PlusTok V3.0 — Cursor Development Rules

**Project:** PlusTok ACEP (AI Customer Engagement Platform)  
**Version:** 1.0 · **Updated:** 2026-07-21  
**Audience:** Cursor AI and human developers

> **Auto-apply rules:** `.cursor/rules/plustok-enterprise.mdc` (always), `plustok-php.mdc`, `plustok-react.mdc` (file-scoped)

---

## 1. Document-First Principle

Every implementation task follows this order:

```
1. INDEX.md          → pick STEP & SSOT path
2. 00_PROJECT_MASTER.md → business context, RBAC, architecture
3. STEP SSOT doc(s)  → e.g. 04_AI/01_AI전략.md
4. Folder _XXX_INDEX.md → local navigation
5. Cross References  → linked DB/API/UI docs
6. Document checklist → bottom of STEP doc
7. Implement
```

**Never** start coding from memory or partial doc reads.

---

## 2. SSOT Document Map

| Domain | Primary SSOT | Index |
|--------|--------------|-------|
| Constitution | `00_PROJECT_MASTER.md` | — |
| Navigation | `INDEX.md` | — |
| UI/UX | `02_UIUX/01_상담채팅화면.fig.md` | `_UIUX_INDEX.md` |
| DB + API | `03_SYSTEM/01_DB설계.md`, `02_API설계.md` | `_SYSTEM_INDEX.md` |
| AI Engine | `04_AI/01~03` | `_AI_INDEX.md` |
| WebSocket/Chat | `05_CHAT/01~02` | `_CHAT_INDEX.md` |
| CRM | `06_CRM/01_CRM통합.md` | `_CRM_INDEX.md` |
| Frontend | `06_FRONTEND/01~04` | `_FRONTEND_INDEX.md` |
| Admin | `07_ADMIN/01~04` | `_ADMIN_INDEX.md` |
| Dashboard | `08_DASHBOARD/01_대시보드설계.md` | `_DASHBOARD_INDEX.md` |
| WBS / QA / Deploy | `09_DEVELOPMENT/01~03` | `_DEVELOPMENT_INDEX.md` |

**Archive folders** (`08_TEST/`, `09_RELEASE/`) are reference-only. SSOT for QA/deploy = `09_DEVELOPMENT/02`, `03`.

---

## 3. Technology Stack

| Layer | Technology | Reference |
|-------|------------|-----------|
| Backend | PHP 8.4, MySQL 8, Redis | `03_SYSTEM/`, `09_DEVELOPMENT/03_배포운영.md` |
| Frontend | React 18, TypeScript 5, Vite 5, TailwindCSS | `06_FRONTEND/01_Frontend_아키텍처.md` |
| Real-time | Socket.io 4.x | `05_CHAT/01_WebSocket설계.md` |
| Admin UI | PHP + Chart.js | `07_ADMIN/01~03` |
| AI | Multi-provider via `ai_call()` | `04_AI/03_AI엔진구현.md` |

---

## 4. AI & Failover Rules

- **Single entry point:** `ai_call()` in `includes/ai.php`
- **Auto failover chain:** Claude → OpenAI → Gemini → Grok → DeepSeek
- **Logging:** `ai_logs` (calls), `ai_failover_log` (transitions)
- **Prompts:** `ai_prompts` table + Rule-002 versioning — `04_AI/02_Prompt설계.md`
- **Config:** `ai_provider_config`, `admin/settings/ai.php`
- **Security:** API keys in DB/env only — **never in source code**
- **PII:** use `ai_mask_pii()` before external calls — `04_AI/03` §6

---

## 5. Coding Standards

### PHP / Backend

- PHP 8.4 minimum
- DB tables/columns: `snake_case`
- Functions: camelCase matching existing `includes/` style
- REST envelope per `03_SYSTEM/02_API설계.md` §5
- Prepared statements; validate JWT on protected routes

### React / Frontend

- React 18 functional components + hooks
- TypeScript strict; camelCase props/state
- Match `UI_COMPONENTS_GUIDE.md` interfaces exactly
- TanStack Query (server), Zustand (UI state)
- Socket.io events per `05_CHAT/01` §4

### Comments & Language

- **Code identifiers:** English
- **Business logic comments:** Korean optional for domain clarity
- **User-facing copy:** Korean (product default)

### File Naming

| Type | Convention | Example |
|------|------------|---------|
| Module folders | `NN_영문` | `04_AI/`, `07_ADMIN/` |
| SSOT docs | `NN_한글설명.md` | `01_AI전략.md` |
| Folder index | `_XXX_INDEX.md` | `_AI_INDEX.md` |
| PHP includes | snake or camel per module | `includes/ai.php` |
| React components | PascalCase `.tsx` | `ChatScreen.tsx` |

---

## 6. Cross Reference Discipline

When doc A references doc B:

1. Verify link target exists
2. Keep both sides consistent on schema/API changes
3. Update `_XXX_INDEX.md` if adding new SSOT files
4. Record significant changes in `CHANGELOG.md`

---

## 7. Testing Requirements

Before marking a task complete:

- Find relevant TCs in `09_DEVELOPMENT/02_테스트시나리오.md`
- Unit: PHPUnit (backend), Vitest (frontend)
- Integration: API + DB per WBS Phase
- E2E: chat flow, failover, CRM close, admin KPI
- Update document checklist items to ✅

---

## 8. Implementation Checklist (every task)

- [ ] INDEX.md + MASTER read for context
- [ ] STEP SSOT purpose & scope understood
- [ ] DB schema columns verified
- [ ] API endpoint/response format verified
- [ ] Cross References read
- [ ] Test cases identified
- [ ] No secrets in code
- [ ] Document checklist updated

---

## 9. Forbidden Actions

- ❌ Implement without reading STEP documentation
- ❌ Use archive docs (`08_TEST/`, `09_RELEASE/`) as primary SSOT
- ❌ Hardcode API keys, JWT secrets, or DB passwords
- ❌ Bypass `ai_call()` or failover logging
- ❌ Break Cross Reference links silently
- ❌ Ignore document completion checklists
- ❌ Unrelated refactors mixed into feature PRs

---

## 10. Quick Reference

| Question | Answer |
|----------|--------|
| Where to start? | `INDEX.md` → `00_PROJECT_MASTER.md` |
| WebSocket events? | `05_CHAT/01_WebSocket설계.md` §4 |
| DB schema? | `03_SYSTEM/01_DB설계.md` §4–5 |
| API format? | `03_SYSTEM/02_API설계.md` §5 |
| WBS schedule? | `09_DEVELOPMENT/01_개발WBS.md` |
| Test cases? | `09_DEVELOPMENT/02_테스트시나리오.md` |
| Deploy? | `09_DEVELOPMENT/03_배포운영.md` |
| Quality gate? | `_검증리포트_문서품질.md` |

---

*PlusTok ACEP Enterprise · Cursor Rules v1.0*
