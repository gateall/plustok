# PlusTok ACEP — CHANGELOG 및 버전관리

> **프로젝트**: PlusTok Enterprise (ACEP)
> **Version**: 1.0.0
> **작성일**: 2026-07-21
> **Audience**: Operator, DevOps, Release Manager, Architect
> **상위 문서**: [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)
> **STEP**: 8 — Release & Deployment

## 문서 개요

| 항목 | 내용 |
|------|------|
| 목적 | SemVer, Keep a Changelog, Git tag, Release notes 작성·관리 프로세스 |
| SSOT | [CHANGELOG.md](../CHANGELOG.md) (product) + 본 문서 (process) |
| 대상 | Dev Lead, Release Manager |

---

## 1. Semantic Versioning (SemVer)

ACEP는 [SemVer 2.0.0](https://semver.org/)을 따른다: `MAJOR.MINOR.PATCH`

| Bump | When | ACEP Example |
|------|------|--------------|
| MAJOR | Breaking API/DB change | 2.0.0 — 14-table migration |
| MINOR | New feature, backward compatible | 1.5.0 — WebSocket chat |
| PATCH | Bug fix, no schema change | 1.0.1 — consult API fix |

### 1.1 Pre-release Labels

| Label | Meaning |
|-------|---------|
| `-alpha` | Internal agent test | v1.0.0-alpha |
| `-beta` | Wider staging | v1.0.0-beta |
| `-rc.N` | Release candidate | v1.0.0-rc.1 |
| `-doc` | Documentation milestone | v1.0.0-doc |

---

## 2. Keep a Changelog

형식: [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/)

### 2.1 Categories

| Category | Use For |
|----------|---------|
| Added | New features |
| Changed | Changes in existing functionality |
| Deprecated | Soon-to-be removed |
| Removed | Removed features |
| Fixed | Bug fixes |
| Security | Vulnerability fixes |

### 2.2 CHANGELOG.md Location

- Product changelog: `www/CHANGELOG.md`
- Release process: 본 문서 `09_RELEASE/04_CHANGELOG_및_버전관리.md`

### 2.3 Entry Template

```markdown
## [1.0.0] - 2026-08-31

### Added
- WebSocket chat room join/leave
- AI recommendation panel (AIPanelCard)

### Fixed
- Consult notify mail SPF alignment
```

---

## 3. Git Tag Strategy

### 3.1 Tag Naming

| Pattern | Example |
|---------|---------|
| Release | `v1.0.0-mvp` |
| Hotfix | `v1.0.1-hotfix-consult` |
| Doc | `v1.0.0-doc` |

### 3.2 Tag Commands

```bash
git tag -a v1.0.0-mvp -m "ACEP V1.0 MVP production release"
git push origin v1.0.0-mvp
```

### 3.3 Tag ↔ Deploy Mapping

| Tag | Deploy Target | Path |
|-----|---------------|------|
| v1.0.0-mvp | plustok.mycafe24.com | Cafe24 FTP |
| v1.5.0 | Docker staging | docker compose |
| v2.0.0 | Full parity | Cafe24 + Docker |

---

## 4. Release Notes

### 4.1 Release Notes Contents

Each GitHub/GitLab release or internal notice must include:

1. Version number and date
2. Summary (1-3 sentences)
3. Added / Changed / Fixed bullets from CHANGELOG
4. Migration notes (if DB schema change)
5. Rollback instructions link → [05_릴리스_런북 §7](05_릴리스_런북.md)
6. Known issues

### 4.2 V1.0 Release Notes Draft

```markdown
# ACEP V1.0.0 MVP — 2026-08-31

## Summary
First production MVP of PlusTok ACEP on plustok.mycafe24.com.
Includes consult CRM, AI analyze/reply, embed widget.

## Migration
- Run V1.0.0__mvp_core.sql if fresh install

## Rollback
- See 09_RELEASE/05_릴리스_런북.md §7
```

---

## 5. Release Branch Workflow

```
main (stable)
  └── release/v1.0.0  (freeze, QA only)
        └── hotfix/v1.0.1 (from tag v1.0.0-mvp)
feature/ACEP-* (development)
```

| Branch | Purpose | Merge To |
|--------|---------|----------|
| feature/ACEP-* | Development | main |
| release/v* | QA freeze | main + tag |
| hotfix/v* | Production fix | main + tag |

---

## 6. Version ↔ STEP Documentation Map

| Version | STEP Docs | Code State |
|---------|-----------|------------|
| v1.0.0-doc | STEP 1~8 complete | PLUS톡 V2.0 operational |
| v1.0.0-mvp | STEP 1~8 + code MVP | Target 2026-08-31 |
| v1.5.0 | + WebSocket docs implemented | Chat Server deploy |
| v2.0.0 | Full ACEP parity | Docker + Cafe24 |

---

## 7. PLUS톡 V2.0 Changelog Summary

Legacy product baseline (pre-ACEP documentation):

| Area | V2.0 Feature |
|------|--------------|
| CRM | Consult status workflow (new→installed) |
| AI | Multi-provider failover in includes/ai.php |
| Admin | Dashboard, consults, products, settings |
| API | api/v1/consult.php |
| Embed | embed.js customer widget |
| Config | config/app.php BASE_URL production |

Full history: [CHANGELOG.md](../CHANGELOG.md) § PLUS톡 V2.0

---

## 8. Release Checklist (Versioning)

| # | Item | ☐ |
|---|------|:-:|
| VER-01 | CHANGELOG.md updated with new version section | ☐ |
| VER-02 | SemVer bump justified (major/minor/patch) | ☐ |
| VER-03 | Git tag created and pushed | ☐ |
| VER-04 | Release notes published | ☐ |
| VER-05 | Deploy manifest tagged to version | ☐ |
| VER-06 | MASTER roadmap version updated if needed | ☐ |

---

## 10. Commit & PR Convention (Release)

| Type | Format | Example |
|------|--------|---------|
| Feature | `[ACEP] feat: ...` | `[ACEP] feat: add consult AI summary` |
| Fix | `[ACEP] fix: ...` | `[ACEP] fix: mail notify SPF` |
| Docs | `[ACEP] docs: ...` | `[ACEP] docs: STEP 8 release guide` |
| Release | `[ACEP] release: v1.0.0-mvp` | Version bump commit |

## 11. PR Merge → Release Flow

```
1. feature/ACEP-* → PR → review → merge main
2. release/v1.0.0 branch cut from main
3. QA gate on release branch
4. Tag v1.0.0-mvp on release branch
5. Deploy tag to Cafe24
6. Merge release → main
7. CHANGELOG + release notes published
```

## 12. Version Compatibility Matrix

| Component | v1.0.0-mvp | v1.5.0 | v2.0.0 |
|-----------|:----------:|:------:|:------:|
| PHP Backend | 8.4 | 8.4 | 8.4 |
| MariaDB schema | 5 tables | +agents, failover | 14 tables |
| Chat Server | optional | required | required |
| Redis | optional | recommended | required |
| React Frontend | optional | staging | production |
| Cafe24 PATH A | ✓ primary | ✓ | ✓ parallel |

## 13. Documentation Version Tracking

| Doc Version | Git Tag | Date | Scope |
|-------------|---------|------|-------|
| STEP 1~8 docs | v1.0.0-doc | 2026-07-21 | All specification docs |
| V1.0 MVP code | v1.0.0-mvp | 2026-08-31 (target) | Production code |

## 14. Hotfix Versioning

| Scenario | Version Bump | Example |
|----------|--------------|---------|
| Consult API bug | PATCH | 1.0.0 → 1.0.1 |
| New AI prompt role | MINOR | 1.0.1 → 1.1.0 |
| DB breaking change | MAJOR | 1.x → 2.0.0 |

## 15. Release Approval RACI

| Activity | Dev Lead | QA | Operator | Release Mgr |
|----------|:--------:|:--:|:--------:|:-----------:|
| CHANGELOG update | R | C | I | A |
| QA gate sign-off | C | R | I | A |
| Deploy execution | C | I | R | A |
| Smoke test | C | R | R | A |
| Rollback decision | C | C | R | A |
| Production sign-off | I | C | C | R |

R=Responsible, A=Accountable, C=Consulted, I=Informed

## 16. CHANGELOG Review Checklist

| # | Review Item | ☐ |
|---|-------------|:-:|
| CR-01 | Version header date correct (ISO 8601) | ☐ |
| CR-02 | All user-facing changes listed | ☐ |
| CR-03 | Security fixes in Security section | ☐ |
| CR-04 | Breaking changes clearly marked | ☐ |
| CR-05 | Links to migration docs if schema change | ☐ |
| CR-06 | Unreleased section updated | ☐ |

## 17. Release Notes Examples (Historical)

### v1.0.0-doc (2026-07-21)

- Documentation milestone: STEP 1~8 complete
- Release deployment SSOT in 09_RELEASE/
- No production code changes

### v1.0.0-mvp (planned 2026-08-31)

- MVP consult CRM on plustok.mycafe24.com
- AI analyze/reply/summary in admin
- Embed widget customer intake

## 18. Git Branch Protection Rules

| Rule | main | release/* |
|------|:----:|:---------:|
| Require PR review | ✓ | ✓ |
| Require QA label | ✓ | ✓ |
| Allow force push | ✗ | ✗ |
| Tag only from release branch | — | ✓ |

## 19. Artifact Retention Policy

| Artifact | Retention | Storage |
|----------|-----------|---------|
| Git tags | Permanent | Remote repository |
| Release notes | Permanent | Wiki / GitHub Releases |
| DB backups | 7 days rolling | Encrypted offsite |
| File snapshots | 3 releases | Operator secure store |
| Docker images | 5 versions | Container registry |
| Deploy manifests | 1 year | Release folder |

## 20. SemVer Decision Tree

```
Did DB schema change break backward compatibility?
  YES → MAJOR bump
  NO → Did you add a new feature?
    YES → MINOR bump
    NO → PATCH bump (bug fix only)
```

## 21. PLUS톡 V2.0 → ACEP Migration Version Map

| Legacy | ACEP Version | Notes |
|--------|--------------|-------|
| PLUS톡 V2.0 stable | v2.0.0 baseline | Current production |
| ACEP doc complete | v1.0.0-doc | 2026-07-21 |
| ACEP MVP code | v1.0.0-mvp | Target 2026-08-31 |
| WebSocket add-on | v1.5.0 | Chat Server deploy |
| Full parity | v2.0.0 | 14-table + React UI |

## 22. Automated Changelog Generation (Optional)

```bash
# Conventional commits → changelog draft (future CI)
git log v1.0.0-alpha..HEAD --pretty=format:"- %s (%h)" --no-merges
```

## 23. Release Tag Verification

| # | Verify | Command | ☐ |
|---|--------|---------|:-:|
| TAG-01 | Tag exists locally | `git tag -l v1.0.0-mvp` | ☐ |
| TAG-02 | Tag pushed remote | `git ls-remote --tags origin` | ☐ |
| TAG-03 | Tag points to tested commit | `git show v1.0.0-mvp` | ☐ |
| TAG-04 | Annotated message correct | `git tag -n9 v1.0.0-mvp` | ☐ |

## 24. Cross-Release Dependency Notes

| Release | Requires | Blocks |
|---------|----------|--------|
| v1.0.0-mvp | STEP 1~8 docs | v1.5.0 WS |
| v1.5.0 | Chat Server VPS, Redis | v2.0.0 |
| v2.0.0 | 14-table migration | Enterprise SaaS |

## 25. Release Communication Checklist

| # | Stakeholder | Channel | Timing | ☐ |
|---|-------------|---------|--------|:-:|
| COM-01 | Dev team | Slack #acep-dev | D-3 | ☐ |
| COM-02 | CS agents | Email | D-1 | ☐ |
| COM-03 | Management | Email summary | D+1 | ☐ |
| COM-04 | Customers | None (unless breaking) | — | ☐ |

## 26. Version File Locations

| Location | Purpose |
|----------|---------|
| `www/CHANGELOG.md` | Product changelog (Keep a Changelog) |
| Git tag `v*` | Immutable release pointer |
| `09_RELEASE/04_CHANGELOG_및_버전관리.md` | Process SSOT |
| Release notes (GitHub/GitLab) | Human-readable summary |
| Deploy manifest | Per-release file list |

## 27. FAQ — Versioning

**Q: doc tag vs mvp tag?**  
`v1.0.0-doc` = documentation milestone (2026-07-21). `v1.0.0-mvp` = production code release (target 2026-08-31).

**Q: Hotfix branch from where?**  
Always branch from the production tag (e.g. `v1.0.0-mvp`), not from `main` tip.

**Q: CHANGELOG vs release notes?**  
CHANGELOG is technical and exhaustive; release notes are a stakeholder-facing summary.

**Q: When to bump MAJOR for ACEP?**  
When DB schema or REST API breaks backward compatibility (e.g. 5-table → 14-table migration).

**Q: Can we deploy without a Git tag?**  
No. Every production deploy must map to an annotated tag for rollback traceability.

## 28. Release Checklist Summary (Printable)

| Phase | Key Items | Sign-off ☐ |
|-------|-----------|:----------:|
| Plan | SemVer bump, CHANGELOG draft, QA gate | ☐ |
| Tag | `git tag -a vX.Y.Z`, push origin | ☐ |
| Deploy | Follow [05_릴리스_런북](05_릴리스_런북.md) | ☐ |
| Verify | SMK-01~07, 24h monitor | ☐ |
| Close | Release notes, retrospective | ☐ |

> **Tip:** Archive QA sign-off PDF and smoke test screenshots alongside the Git tag for audit.

**Related:** Product changelog lives at [CHANGELOG.md](../CHANGELOG.md).

---

## 9. 관련 문서

- [CHANGELOG.md](../CHANGELOG.md)
- [05_릴리스_런북.md](05_릴리스_런북.md)
- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 10
- [_RELEASE_INDEX.md](_RELEASE_INDEX.md)

**문서 끝**
