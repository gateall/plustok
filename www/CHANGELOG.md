# Changelog

All notable changes to PlusTok ACEP (AI Customer Engagement Platform) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Docker Compose full-stack staging environment
- WebSocket Chat Server on VPS (V1.5)
- Redis rate limiting for production Docker path
- Admin Dashboard ACEP React migration

---

## [1.0.0-doc] — 2026-07-21

### Documentation Milestone (ACEP STEP 1~8)

**Scope:** Enterprise specification documentation — not production code release.

#### Added
- STEP 1: `00_PROJECT_MASTER.md`, UI/UX design (`02_UIUX/`)
- STEP 2: DB design, API design, system architecture (`03_SYSTEM/`)
- STEP 3: AI Prompt, Failover, Router integration (`04_AI/`)
- STEP 4: Chat Server, Backend Chat API, WebSocket protocol (`05_CHAT/`)
- STEP 5: Frontend React architecture and component specs (`06_FRONTEND/`)
- STEP 6: Admin & Dashboard specification (documented in roadmap)
- STEP 7: QA & release gate specification (referenced in `08_TEST/`)
- STEP 8: Release & deployment SSOT (`09_RELEASE/`)

#### Documentation Deliverables (STEP 8)
- `09_RELEASE/01_배포_아키텍처_및_환경.md`
- `09_RELEASE/02_Docker_및_Nginx_구성.md`
- `09_RELEASE/03_FTP_Cafe24_배포_가이드.md`
- `09_RELEASE/04_CHANGELOG_및_버전관리.md`
- `09_RELEASE/05_릴리스_런북.md`
- `_작업지시서/08_STEP8_작업지시서_Release_Deploy.md`

---

## PLUS톡 V2.0 — Production Summary (Legacy Product)

> **Host:** `https://plustok.mycafe24.com`  
> **Stack:** PHP 8.4, MariaDB (Cafe24), FTP deployment  
> **Config SSOT:** `config/app.php`, `config/ai.php`

### Features (Operational)
- **통합 CRM Admin** — 상담 접수, 상태 관리, 상품/견적 연동
- **멀티 AI Failover** — Claude → GPT → Gemini → Grok (`includes/ai.php`)
- **Embed Widget** — 고객 문의 폼 (`embed/embed.js`, `embed/form.php`)
- **REST API v1** — `api/v1/consult.php` 등 상담 API
- **Admin AI Tools** — AI 요약, AI 답변, AI 분석 (`admin/consults/ai_*.php`)
- **Mail Notification** — 상담 접수 알림 (`ADMIN_NOTIFY_EMAIL` in `config/app.php`)
- **File Upload** — 사업자등록증, 견적서 등 (`uploads/`, 10MB limit)

### Configuration Reference
| Constant | Value | File |
|----------|-------|------|
| `BASE_URL` | `https://plustok.mycafe24.com` | `config/app.php` |
| `MAIL_FROM` | `noreply@plustok.mycafe24.com` | `config/app.php` |
| `APP_NAME` | PlusTok 통합 CRM | `config/app.php` |

### Known Limitations (V2.0 → ACEP V1.0 Gap)
- WebSocket real-time chat: polling-based (Chat Server planned in Docker path)
- Redis: not available on Cafe24 shared hosting
- React 3-panel UI: documented in STEP 5, deployment via Docker or static FTP
- 14-table ERD: partial migration from legacy schema

---

## Version Tag Convention

| Tag Pattern | Meaning |
|-------------|---------|
| `v1.0.0-mvp` | First ACEP MVP production release (target 2026-08-31) |
| `v1.0.0-doc` | Documentation milestone (STEP 1~8 complete) |
| `v1.5.0` | AI Failover + WebSocket staging |
| `v2.0.0` | PLUS톡 V2.0 feature parity + ACEP Enterprise |

See [09_RELEASE/04_CHANGELOG_및_버전관리.md](09_RELEASE/04_CHANGELOG_및_버전관리.md) for full release process.

---

[Unreleased]: https://github.com/plustok/acep/compare/v1.0.0-doc...HEAD
[1.0.0-doc]: https://github.com/plustok/acep/releases/tag/v1.0.0-doc
