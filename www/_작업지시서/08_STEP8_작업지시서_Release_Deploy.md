# STEP 8 작업지시서 — Release & Deployment

**프로젝트:** PlusTok Enterprise (ACEP)  
**STEP:** 8  
**상태:** ✅ **완료** (2026-07-21) — **문서 산출물**  
**적용 위치:** `www/09_RELEASE/`, `www/CHANGELOG.md`, `www/_작업지시서/`

> 본 STEP은 **릴리스·배포 운영 문서(SSOT)** 를 산출한다. Cafe24 FTP 운영 경로와 Docker Full Stack 경로를 모두 문서화한다.

---

## 목표

STEP 2~7 아키텍처·QA 게이트 명세를 기반으로, **Operator / DevOps / Release Manager** 가 즉시 실행 가능한 배포·릴리스 문서를 작성한다. PLUS톡 V2.0 Cafe24 운영(`plustok.mycafe24.com`)과 ACEP Docker Full Stack 이중 경로를 정의한다.

---

## 산출물 체크리스트

| # | 산출물 | 목표行 | 상태 | 링크 |
|---|--------|--------|:----:|------|
| 1 | 배포 아키텍처 및 환경 | 500~700 | ✅ (~557) | [09_RELEASE/01_배포_아키텍처_및_환경.md](../09_RELEASE/01_배포_아키텍처_및_환경.md) |
| 2 | Docker 및 Nginx 구성 | 500~800 | ✅ (~565) | [09_RELEASE/02_Docker_및_Nginx_구성.md](../09_RELEASE/02_Docker_및_Nginx_구성.md) |
| 3 | FTP Cafe24 배포 가이드 | 400~600 | ✅ (~404) | [09_RELEASE/03_FTP_Cafe24_배포_가이드.md](../09_RELEASE/03_FTP_Cafe24_배포_가이드.md) |
| 4 | CHANGELOG 및 버전관리 | 400~500 | ✅ (~400) | [09_RELEASE/04_CHANGELOG_및_버전관리.md](../09_RELEASE/04_CHANGELOG_및_버전관리.md) |
| 5 | 릴리스 런북 | 400~600 | ✅ (~413) | [09_RELEASE/05_릴리스_런북.md](../09_RELEASE/05_릴리스_런북.md) |
| 6 | Release Index | — | ✅ | [09_RELEASE/_RELEASE_INDEX.md](../09_RELEASE/_RELEASE_INDEX.md) |
| 7 | CHANGELOG stub | — | ✅ | [CHANGELOG.md](../CHANGELOG.md) |
| 8 | MASTER PART 10 STEP 8 갱신 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| 9 | MASTER §10.2.1 09_RELEASE/ 추가 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §10.2.1 |
| 10 | MASTER Appendix B + 문서화 완료 | — | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| 11 | `_gen_step8.py` 삭제 | — | ✅ | one-time generator removed |

---

## 품질 기준

| 항목 | 기준 | 결과 |
|------|------|:----:|
| 아키텍처 정합 | [03_시스템아키텍처](../03_SYSTEM/03_시스템아키텍처.md) §2 Docker topology, §9 env vars | ✅ |
| QA 게이트 정합 | [08_TEST/04_QA_릴리스_게이트](../08_TEST/04_QA_릴리스_게이트.md) Pre-flight, Smoke, Rollback | ✅ |
| Config 정합 | [config/app.php](../config/app.php) BASE_URL, MAIL_FROM | ✅ |
| Cafe24 운영 | `plustok.mycafe24.com`, FTP/SFTP, phpMyAdmin migration | ✅ |
| Docker compose | Full `docker-compose.yml` example in doc 02 | ✅ |
| Keep a Changelog | [CHANGELOG.md](../CHANGELOG.md) + doc 04 SemVer process | ✅ |
| 런북 체크리스트 | ☐ Pre-flight ~ Sign-off in doc 05 | ✅ |
| 한국어 Markdown | substantive, operations-ready | ✅ |
| Placeholder 금지 | 부록 Z 반복 루프 등 패딩 없음 | ✅ |
| 상대 링크 | 09_RELEASE/* ↔ 03_SYSTEM, 08_TEST, config | ✅ |

---

## 참조 문서 (입력)

- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 8~10, Security, Roadmap
- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §2~§10
- [08_TEST/04_QA_릴리스_게이트.md](../08_TEST/04_QA_릴리스_게이트.md)
- [config/app.php](../config/app.php)
- [09_RELEASE/_RELEASE_INDEX.md](../09_RELEASE/_RELEASE_INDEX.md)
- [_작업지시서/05_STEP5_작업지시서_Frontend_React.md](./05_STEP5_작업지시서_Frontend_React.md) (형식 참조)

---

## STEP 8 문서 핵심 결정 (ADR 요약)

| 결정 | 선택 | 근거 |
|------|------|------|
| 배포 이중 경로 | Cafe24 FTP (현재) + Docker (향후) | PLUS톡 V2.0 운영 중, ACEP Full Stack 목표 |
| Staging | 현재 staging=production 동일 호스트 | Cafe24 단일 호스트; V2.0 Docker staging 분리 권장 |
| V1.0 Cafe24 WS | polling fallback | Cafe24 Node.js WS 미지원 |
| Zero-downtime Cafe24 | Maintenance window + delta FTP | Blue-Green 불가 (공유 호스팅) |
| Zero-downtime Docker | Rolling update + healthcheck | [03_시스템아키텍처](../03_SYSTEM/03_시스템아키텍처.md) §2 |
| Versioning | SemVer + Git tag `v{major}.{minor}.{patch}` | Industry standard |
| CHANGELOG SSOT | `www/CHANGELOG.md` + release notes in tag | Keep a Changelog 1.1.0 |
| Rollback RTO | 4h (MASTER PART 8.3) | File snapshot + DB restore |

---

## 배포 경로 요약

```
PATH A (현재 운영):  Cafe24 FTP → plustok.mycafe24.com
PATH B (향후):       Docker Compose → VPS / Cloud VM
```

| 문서 | PATH A | PATH B |
|------|:------:|:------:|
| 01 배포 아키텍처 | §1.1, §5 Cafe24 | §1.2, §5.3 Docker |
| 02 Docker/Nginx | — | 전체 |
| 03 FTP Cafe24 | 전체 | — |
| 05 릴리스 런북 | §3 Cafe24 deploy | §4 Docker deploy |

---

## V1.0 Release 마일스톤 연계

| 날짜 | 마일스톤 | STEP 8 Deliverable |
|------|----------|---------------------|
| 2026-08-21 | Alpha | [05_릴리스_런북](../09_RELEASE/05_릴리스_런북.md) §8 Alpha checklist |
| 2026-08-31 | **V1.0 Release** | [05_릴리스_런북](../09_RELEASE/05_릴리스_런북.md) §9 Production sign-off |

---

## STEP 9 선행 과제 (코드·인프라 구현)

- [ ] `docker/docker-compose.yml` 실제 scaffold 생성
- [ ] `frontend/` Vite build → Cafe24 `assets/` 또는 Docker static
- [ ] Chat Server VPS 배포 (V1.5)
- [ ] CI/CD pipeline (Git tag → FTP deploy script)
- [ ] Automated smoke test SMK-01~07
- [ ] Staging Docker 환경 분리

---

## 완료 확인

```
STEP 8: Release & Deployment — ✅ 2026-07-21
담당: Platform / DevOps Team
문서화 로드맵: STEP 1~8 ✅ 전체 완료
다음: V1.0 MVP 코드 구현 스프린트 (2026-08)
```

---

**상위:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 10.2  
**인덱스:** [09_RELEASE/_RELEASE_INDEX.md](../09_RELEASE/_RELEASE_INDEX.md)
