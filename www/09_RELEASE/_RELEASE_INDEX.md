# PlusTok ACEP — Release & Deployment Index (STEP 8)

> **프로젝트**: PlusTok AI Customer Engagement Platform (ACEP)  
> **STEP**: 8 · Release & Deployment  
> **작성일**: 2026-07-21  
> **Archive:** 본 폴더는 **레거시 원본**입니다. 배포·운영 SSOT는 [`09_DEVELOPMENT/03_배포운영.md`](../09_DEVELOPMENT/03_배포운영.md) (STEP 8 통합).  
> **상위 문서**: [`00_PROJECT_MASTER.md`](../00_PROJECT_MASTER.md) PART 10 Roadmap

---

## 1. 문서 목록

| # | 문서 | 목적 | 대상 독자 | 예상 분량 |
|---|------|------|-----------|-----------|
| 01 | [배포 아키텍처 및 환경](01_배포_아키텍처_및_환경.md) | Cafe24 + Docker 이중 경로, 환경 매트릭스, 컴포넌트 배포 맵, DNS/SSL, Zero-downtime, Rollback 아키텍처 | Architect, Operator, DevOps | ~700 lines |
| 02 | [Docker 및 Nginx 구성](02_Docker_및_Nginx_구성.md) | docker-compose, Dockerfiles, nginx default.conf, healthcheck, local/prod 차이 | DevOps, Backend Dev | ~800 lines |
| 03 | [FTP Cafe24 배포 가이드](03_FTP_Cafe24_배포_가이드.md) | plustok.mycafe24.com FTP/SFTP, 업로드/제외 목록, DB migration, 스모크 테스트 | Operator | ~600 lines |
| 04 | [CHANGELOG 및 버전관리](04_CHANGELOG_및_버전관리.md) | SemVer, Keep a Changelog, Git tag, Release notes | Dev Lead, Release Manager | ~500 lines |
| 05 | [릴리스 런북](05_릴리스_런북.md) | Pre-flight → backup → deploy → migrate → smoke → monitor → sign-off, Hotfix, On-call | Operator, Release Manager | ~600 lines |

---

## 2. 작업지시서

| 문서 | 설명 |
|------|------|
| [`_작업지시서/08_STEP8_작업지시서_Release_Deploy.md`](../_작업지시서/08_STEP8_작업지시서_Release_Deploy.md) | STEP 8 문서 산출 체크리스트 |

---

## 3. 배포 경로 요약

| 경로 | 현재 상태 | 용도 | 참조 |
|------|-----------|------|------|
| **Cafe24 FTP** | ✅ 운영 중 (`plustok.mycafe24.com`) | PLUS톡 V2.0 PHP, Admin, Embed | [03_FTP](03_FTP_Cafe24_배포_가이드.md) |
| **Docker Compose** | 📋 문서화 (향후 Full Stack) | ACEP React + Chat Server + Redis | [02_Docker](02_Docker_및_Nginx_구성.md) |

---

## 4. 환경 매트릭스 (Quick Reference)

| 환경 | URL | 배포 방식 | AI Keys |
|------|-----|-----------|---------|
| **local** | `http://localhost:8080` | Docker Compose dev | Mock / Sandbox |
| **staging** | `https://plustok.mycafe24.com` | Cafe24 FTP | Sandbox |
| **production** | `https://plustok.mycafe24.com` (또는 전용 도메인) | Cafe24 FTP / Docker | Live |

> **Note:** 현재 PLUS톡은 staging=production 동일 호스트. V2.0부터 Docker staging 분리 권장.

---

## 5. 릴리스 파이프라인

```
[STEP 7] QA Sign-off + 08_TEST/04_QA_릴리스_게이트 PASS
    ↓
[STEP 8 §1] Pre-flight checklist (05_릴리스_런북 §2)
    ↓
[STEP 8 §2] Backup (DB + files)
    ↓
[STEP 8 §3] Deploy (FTP 또는 Docker)
    ↓
[STEP 8 §4] DB Migration
    ↓
[STEP 8 §5] Post-deploy Smoke (08_TEST §3.4)
    ↓
[STEP 8 §6] Monitor 24h + Sign-off
```

---

## 6. 선행 STEP 의존성

| STEP | 산출물 | 배포 연계 |
|------|--------|-----------|
| STEP 2 | [03_시스템아키텍처](../03_SYSTEM/03_시스템아키텍처.md) | Docker topology, env vars |
| STEP 3 | [04_AI/](../04_AI/_AI_INDEX.md) | AI keys, Failover config |
| STEP 4 | [05_CHAT/](../05_CHAT/_CHAT_INDEX.md) | Chat Server deploy |
| STEP 5 | [06_FRONTEND/](../06_FRONTEND/_FRONTEND_INDEX.md) | React build → static |
| STEP 6 | [07_ADMIN/](../07_ADMIN/_ADMIN_INDEX.md) | Admin PHP modules |
| STEP 7 | [08_TEST/](../08_TEST/_TEST_INDEX.md) | QA gate, smoke checklist |
| **STEP 8** | **본 폴더** | Release & Deploy SSOT |

---

## 7. 핵심 설정 참조

| 항목 | 값 / 위치 |
|------|-----------|
| BASE_URL | `https://plustok.mycafe24.com` — [`config/app.php`](../config/app.php) |
| PHP | 8.4 (Cafe24 호스팅) |
| MariaDB | Cafe24 제공 DB |
| Mail From | `noreply@plustok.mycafe24.com` |
| Admin Notify | `config/app.php` ADMIN_NOTIFY_EMAIL |

---

## 8. V1.0 Release Milestone

| 날짜 | 마일스톤 | Deliverable |
|------|----------|-------------|
| 2026-08-21 | Alpha | 내부 상담원 테스트 |
| 2026-08-31 | **V1.0 Release** | MVP 프로덕션 — [05_릴리스_런북](05_릴리스_런북.md) §9 |

---

## 9. 관련 문서 Cross-Reference

| Topic | Primary | Release Doc |
|-------|---------|-------------|
| Deployment Topology | [03_시스템아키텍처 §2](../03_SYSTEM/03_시스템아키텍처.md) | [01 §3](01_배포_아키텍처_및_환경.md) |
| Env Variables | [03_시스템아키텍처 §9](../03_SYSTEM/03_시스템아키텍처.md) | [01 §5](01_배포_아키텍처_및_환경.md) |
| QA Pre-release Gate | [08_TEST/04](../08_TEST/04_QA_릴리스_게이트.md) | [05 §2](05_릴리스_런북.md) |
| Smoke Tests | [08_TEST/04 §3.4](../08_TEST/04_QA_릴리스_게이트.md) | [03 §8](03_FTP_Cafe24_배포_가이드.md) |
| CHANGELOG | [04_CHANGELOG](04_CHANGELOG_및_버전관리.md) | `www/CHANGELOG.md` |
| Rollback | [08_TEST/04 §5](../08_TEST/04_QA_릴리스_게이트.md) | [05 §7](05_릴리스_런북.md) |

---

## 10. 문서 읽기 순서 (권장)

```
Operator (Cafe24):
  01_배포_아키텍처_및_환경.md → 03_FTP_Cafe24_배포_가이드.md → 05_릴리스_런북.md

DevOps (Docker):
  01_배포_아키텍처_및_환경.md → 02_Docker_및_Nginx_구성.md → 05_릴리스_런북.md

Release Manager:
  04_CHANGELOG_및_버전관리.md → 05_릴리스_런북.md → 08_TEST/04_QA_릴리스_게이트.md
```

---

## 11. 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 8 초판 — 09_RELEASE/ 5문서 + Index |

---

**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)  
**문서화 로드맵:** STEP 1~8 ✅ **전체 완료**
