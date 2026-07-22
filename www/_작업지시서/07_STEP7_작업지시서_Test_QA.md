# STEP 7 작업지시서 — Test & QA

> **프로젝트**: PlusTok ACEP  
> **STEP**: 7 · Testing + QA  
> **작성일**: 2026-07-21  
> **선행 STEP**: STEP 6 Admin + Dashboard  
> **SSOT 문서**: [`08_TEST/`](../08_TEST/_TEST_INDEX.md)

---

## 1. 목표

PlusTok ACEP V1.0 MVP **테스트 전략·명세·E2E 체크리스트·릴리스 게이트**를 문서화하고, QA Sign-off를 통해 STEP 8 배포로 핸드오ff한다.

| 산출물 | 문서 |
|--------|------|
| 테스트 전략 | [08_TEST/01_테스트_전략_및_범위.md](../08_TEST/01_테스트_전략_및_범위.md) |
| 단위·통합 명세 | [08_TEST/02_단위_통합_테스트_명세.md](../08_TEST/02_단위_통합_테스트_명세.md) |
| E2E 체크리스트 | [08_TEST/03_E2E_시나리오_및_체크리스트.md](../08_TEST/03_E2E_시나리오_및_체크리스트.md) |
| 릴리스 게이트 | [08_TEST/04_QA_릴리스_게이트.md](../08_TEST/04_QA_릴리스_게이트.md) |

---

## 2. 범위

### 2.1 In Scope

- [ ] Test Pyramid 정의 (unit / integration / E2E)
- [ ] Coverage targets: 80% unit, 40 API integration
- [ ] V1.0 MVP test scope in/out
- [ ] Environments: local, staging (plustok.mycafe24.com), production smoke
- [ ] QA/Dev/Operator RACI
- [ ] Test data + PII policy
- [ ] Regression policy
- [ ] PHPUnit ChatRoomService, AiRecommendationService specs
- [ ] Node WS handler test specs
- [ ] Vitest 11 React components + hooks
- [ ] API fixtures REST 30 + Admin 10
- [ ] AI Mock CI strategy
- [ ] E2E-01 Master flow checklist
- [ ] TC-001~007 + IT-01~72 + TC-ADM-* merge
- [ ] AI Failover manual FO-001~007
- [ ] Admin Dashboard verification
- [ ] Mobile responsive checklist
- [ ] Security smoke JWT/RBAC/CSRF/PII
- [ ] Performance smoke 1000 WS (staging)
- [ ] Bug severity/priority + Sign-off template
- [ ] Pre-release gate → STEP 8

### 2.2 Out of Scope

- [ ] Playwright 자동 E2E 구현 (V1.5)
- [ ] Production 부하 테스트
- [ ] Penetration test 전문 업체 의뢰
- [ ] STEP 8 FTP deploy 실행 (문서만 handoff)

---

## 3. 선행 조건

| # | 조건 | 확인 |
|---|------|------|
| P-01 | STEP 5 Frontend ChatScreen 구현 또는 mock staging | ☐ |
| P-02 | STEP 6 Admin Dashboard staging deploy | ☐ |
| P-03 | Staging `plustok.mycafe24.com` accessible | ☐ |
| P-04 | Test accounts seeded (agent, admin, operator, super) | ☐ |
| P-05 | `AI_MOCK_ENABLED` CI pipeline config | ☐ |
| P-06 | MASTER PART 8 Quality 기준 합의 | ☐ |

---

## 4. 작업 패키지

### WP-1: Test Strategy Document (P0)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W1-01 | Test pyramid + coverage targets | QA | 4h | 01 §1-2 |
| W1-02 | MVP scope in/out | QA/Arch | 2h | 01 §3 |
| W1-03 | Environment matrix | Operator | 2h | 01 §4 |
| W1-04 | RACI + regression policy | QA Lead | 2h | 01 §5, §8 |
| W1-05 | PII test data policy | QA/Security | 2h | 01 §6 |

**완료 기준**: [01_테스트_전략_및_범위.md](../08_TEST/01_테스트_전략_및_범위.md) review approved

---

### WP-2: Unit & Integration Specs (P0)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W2-01 | PHPUnit ChatRoomService test table | BE | 4h | 02 §2.2 |
| W2-02 | PHPUnit AiRecommendationService | BE | 4h | 02 §2.3 |
| W2-03 | Node WS handler test matrix | Node Dev | 4h | 02 §3 |
| W2-04 | Vitest 11 component matrix | FE | 6h | 02 §4 |
| W2-05 | REST API 30 fixture tables | BE/QA | 8h | 02 §5 |
| W2-06 | Admin API 10 fixture tables | BE/QA | 4h | 02 §6 |
| W2-07 | AI Mock strategy doc + CI guard | BE | 2h | 02 §7 |
| W2-08 | WS integration harness spec | Node Dev | 4h | 02 §8 |

**완료 기준**: Test IDs mapped to implementable PHPUnit/Vitest cases

---

### WP-3: E2E & Manual QA (P0)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W3-01 | E2E-01 happy path checklist | QA | 4h | 03 §1 |
| W3-02 | Merge TC-001~007 | QA | 2h | 03 §2 |
| W3-03 | Merge IT-01~72 | QA | 3h | 03 §3 |
| W3-04 | Merge TC-ADM-* | QA | 3h | 03 §4 |
| W3-05 | AI Failover manual procedure | QA/Operator | 2h | 03 §8 |
| W3-06 | Security smoke checklist | QA | 2h | 03 §7 |
| W3-07 | Mobile responsive checklist | QA | 2h | 03 §6 |
| W3-08 | Performance smoke staging | Operator | 4h | 03 §9 |
| W3-09 | Sign-off template | QA Lead | 1h | 03 §15 |

**완료 기준**: Staging E2E-01 execution log 1회 이상

---

### WP-4: Release Gate (P1)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W4-01 | Pre-release checklist | QA/Release | 2h | 04 §1 |
| W4-02 | CHANGELOG requirements | Dev | 1h | 04 §2 |
| W4-03 | FTP deploy verification steps | Operator | 2h | 04 §3 |
| W4-04 | DB migration verification | BE/Operator | 2h | 04 §4 |
| W4-05 | Rollback criteria | Operator | 2h | 04 §5 |
| W4-06 | KNOWN_ISSUES template | QA | 1h | 04 §6 |

**완료 기준**: Release Manager dry-run gate review

---

### WP-5: Implementation Support (P1 — Dev)

| ID | Task | Owner | Est | Doc Ref |
|----|------|-------|-----|---------|
| W5-01 | Implement PHPUnit P0 tests | BE | 16h | 02 §2 |
| W5-02 | Implement Vitest P0 components | FE | 12h | 02 §4 |
| W5-03 | Postman/Newman collection | QA | 8h | 02 §5-6 |
| W5-04 | CI pipeline test jobs | DevOps | 4h | 02 §12 |
| W5-05 | MockAiProvider CI | BE | 4h | 02 §7 |

---

## 5. 일정 (권장)

| Week | WP | Milestone |
|------|-----|-----------|
| W1 | WP-1, WP-2 | Test docs + specs complete |
| W2 | WP-3, WP-5 | Staging E2E + CI unit tests |
| W3 | WP-4, WP-5 | Release gate + integration 40 APIs |
| W4 | Full regression | QA Sign-off → STEP 8 |

---

## 6. Definition of Done

- [ ] `08_TEST/` 4문서 + `_TEST_INDEX.md` complete
- [ ] `00_PROJECT_MASTER.md` STEP 7 ✅, §10.2.1, Appendix B updated
- [ ] E2E-01 PASS on staging
- [ ] P0/P1 bugs = 0 (or documented waiver)
- [ ] Unit coverage ≥ 80% Service/Repository (or waiver with plan)
- [ ] 40 API integration spec complete (implementation ≥ 78% V1.0)
- [ ] Security smoke PASS
- [ ] Sign-off sheet signed ([03 §15](../08_TEST/03_E2E_시나리오_및_체크리스트.md))
- [ ] Pre-release gate checklist complete ([04 §1](../08_TEST/04_QA_릴리스_게이트.md))
- [ ] STEP 8 handoff package ready

---

## 7. 참조 문서

| STEP | Document |
|------|----------|
| MASTER | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 8 |
| UI TC | [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §10 |
| API | [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) |
| Failover | [04_AI/01_AI전략.md §10](../04_AI/01_AI전략.md) |
| WebSocket | [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md) |
| Frontend IT | [06_FRONTEND/04_ChatScreen_통합_구현가이드.md](../06_FRONTEND/04_ChatScreen_통합_구현가이드.md) §11 |
| Admin TC | [07_ADMIN/01_관리자화면_UIUX_설계.md](../07_ADMIN/01_관리자화면_UIUX_설계.md) §10 |

---

## 8. 리스크

| Risk | Mitigation |
|------|------------|
| Staging unstable | Weekly deploy sync with Dev |
| Real AI cost in CI | AI_MOCK_ENABLED mandatory |
| Manual E2E bottleneck | Prioritize P0 tier, automate V1.5 |
| FTP partial deploy | Manifest + smoke checklist |
| Test account lockout | Seed reset script |

---

## 9. 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 7 작업지시서 초판 |
