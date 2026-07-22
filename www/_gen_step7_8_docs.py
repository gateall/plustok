#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""PLUS톡 STEP 7-8 문서 생성 (고객 대시보드, 개발 WBS, 테스트, 배포)."""
from __future__ import annotations

import re
import shutil
from datetime import date
from pathlib import Path

BASE = Path(__file__).resolve().parent
TODAY = "2026-07-21"


def w(rel: str, text: str) -> Path:
    path = BASE / rel.replace("/", "\\")
    path.parent.mkdir(parents=True, exist_ok=True)
    if not text.endswith("\n"):
        text += "\n"
    path.write_text(text, encoding="utf-8")
    return path


def table(headers: list[str], rows: list[list[str]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "| " + " | ".join(["---"] * len(headers)) + " |"]
    for row in rows:
        lines.append("| " + " | ".join(row) + " |")
    return "\n".join(lines)


def merge_legacy(folder: str, pattern: str) -> str:
    src = BASE / folder
    if not src.exists():
        return ""
    parts: list[str] = []
    for p in sorted(src.glob(pattern)):
        body = p.read_text(encoding="utf-8")
        body = re.sub(r"^#\s+.*?\n", "", body, count=1, flags=re.MULTILINE)
        parts.append(f"### Legacy: `{p.name}`\n\n{body.strip()}\n")
    return "\n---\n\n".join(parts)


def gen_customer_dashboard() -> str:
    return f"""# ACEP (PlusTok Enterprise) — 고객 대시보드 설계

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Design Phase (STEP 7)  
**Created:** {TODAY}  
**Owner:** Customer Experience Team  

**적용 위치:** React Customer Portal (`/customer/dashboard`)  
**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)  
**연관:** [06_CRM/01_CRM통합.md](../06_CRM/01_CRM통합.md), [05_CHAT/02_실시간동기화.md](../05_CHAT/02_실시간동기화.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 대상 사용자 | Customer (고객) |
| UI 프레임워크 | React 18 + Material-UI 5 |
| 접근성 | WCAG 2.1 Level AA |
| 페이지 로드 SLA | 2초 이내 (LCP) |
| 데이터 보유 | 상담 기록 3년 |

---

## 1. 목적 (Purpose)

고객이 **자신의 상담 기록·진행 상태·피드백**을 한 화면에서 확인하고, 재상담·프로필 수정·알림 설정을 수행한다.

| 목표 | KPI |
|------|-----|
| 상담 투명성 | 완료 상담 100% 목록 노출 |
| 재상담 전환 | 재상담 요청 클릭률 ≥ 15% |
| 만족도 수집 | 종료 24h 내 평가 작성률 ≥ 40% |
| 개인정보 자기관리 | 프로필 수정 성공률 ≥ 99% |

---

## 2. 범위 (Scope)

### 2.1 In Scope

- 상담 요약 카드 (총 횟수, 진행중, 만족도)
- 상담 목록·상세 (메시지 히스토리 일부)
- 새 상담 / 재상담 / 일정 예약 CTA
- 프로필·알림 설정
- Customer REST API 4종

### 2.2 Out of Scope

- Admin KPI (→ [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md))
- 결제·계약 서명 (V4.0)
- 챗봇 학습 피드백 (V4.5)

---

## 3. 요구사항 (Requirements)

| ID | 요구사항 | 기준 |
|----|----------|------|
| REQ-CD-01 | 반응형 | 375px~1920px |
| REQ-CD-02 | 접근성 | WCAG 2.1 AA, 키보드 탐색 |
| REQ-CD-03 | 로드 | TTFB+render < 2s |
| REQ-CD-04 | 검색 | 상담 주제/날짜 필터 |
| REQ-CD-05 | 보안 | 본인 room_id만 조회 |
| REQ-CD-06 | 오프라인 | 캐시 + 새로고침 버튼 |

---

## 4. 고객 대시보드 UI

### 4.1 전체 레이아웃

```
┌────────────────────────────────────────┐
│          고객 대시보드                  │
├────────────────────────────────────────┤
│ 1. 상담 요약 (4 KPI Cards)              │
│ 2. 상담 목록 (Table + Filter)          │
│ 3. 상담 요청 (CTA Buttons)             │
│ 4. 알림 (Bell + Drawer)                │
│ 5. 프로필 (Settings Tab)               │
└────────────────────────────────────────┘
```

### 4.2 Block 1 — 상담 요약

| KPI | 소스 | 표시 |
|-----|------|------|
| 총 상담 횟수 | COUNT chat_rooms closed | 숫자 |
| 진행중 상담 | status=open | Badge |
| 미해결 | closed + follow_up_pending | 경고색 |
| 평균 만족도 | AVG customer_feedback.rating | ★ 4.5/5 |

### 4.3 Block 2 — 상담 목록

{table(
    ["항목", "설명", "정렬"],
    [
        ["날짜", "상담 일시 KST", "DESC default"],
        ["상담원", "agents.display_name", "—"],
        ["주제", "ai_summary 40자", "—"],
        ["상태", "완료/진행중/대기", "필터"],
        ["만족도", "1~5 별", "—"],
        ["액션", "상세/재상담", "—"],
    ],
)}

### 4.4 Block 3 — 상담 상세 (Drawer/Modal)

- AI 요약 (`ai_logs.summary`)
- 메시지 히스토리 최근 10건 + "더보기" → 전체 REST
- AI 추천 스냅샷 (있을 때)
- 상담원·고객 평가
- 관련 상담 (동일 category)

### 4.5 Block 4 — 개인정보

| 필드 | 편집 | 검증 |
|------|:----:|------|
| 이름 | ✅ | 2~20자 |
| 전화 | ✅ | E.164 / KR |
| 이메일 | ✅ | RFC5322 |
| 주소 | ✅ | optional |
| 선호 상담원 | ✅ | agents list |
| 상담 가능 시간 | ✅ | time slots |

### 4.6 Block 5 — 알림 설정

| 유형 | 기본 | 채널 |
|------|------|------|
| 상담 시작 | On (필수) | Push, Email |
| 새 메시지 | On | Push |
| 상담 완료 | On (필수) | Push, Email |
| 프로모션 | Off | Email |

---

## 5. DB 참조

| 테이블 | 용도 |
|--------|------|
| `customers` | 프로필, phone_hash |
| `chat_rooms` | 상담방 상태 |
| `chat_messages` | 히스토리 (페이지) |
| `customer_feedback` | 만족도 |
| `customer_notifications` | 알림 prefs |
| `ai_logs` | 요약·감성 |

---

## 6. API 명세

### GET /api/v1/customers/{{id}}/dashboard

```json
{{
  "summary": {{
    "totalConsults": 12,
    "activeConsults": 1,
    "unresolved": 0,
    "avgRating": 4.5,
    "lastConsultAt": "2026-07-20T14:30:00+09:00"
  }},
  "recentConsults": []
}}
```

### GET /api/v1/customers/{{id}}/consults

Query: `status`, `from`, `to`, `page`, `limit`

### POST /api/v1/customers/{{id}}/feedback

```json
{{ "roomId": "uuid", "rating": 5, "comment": "친절했습니다" }}
```

### PUT /api/v1/customers/{{id}}/profile

JWT `sub` = customer id 일치 필수.

---

## 7. Business Rule

```
1. 정보 공개: 본인 chat_rooms만 (customer_id = JWT sub)
2. 상담원 PII: 이름만 표시, 전화/이메일 마스킹
3. 평가: 종료 후 24h 권장, 수정 최대 3회, 삭제 불가
4. 필수 알림: 상담 시작/완료 — 고객 Off 불가
5. 보유: 상담 3년, 피드백 영구, access_log 90일
6. 재상담: 동일 site_code, open room 있으면 기존 room 안내
```

---

## 8. AI Rule

- **추천 상품:** analyze 결과 `recommended_products[]` 카드 (opt-in 표시)
- **맞춤 콘텐츠:** sentiment=negative → FAQ "불편 해결" 우선
- **요약 노출:** 고객 대시보드 요약은 PII 마스킹 버전 (이름·전화 제거)

---

## 9. Exception 처리

| 상황 | UX | Backend |
|------|-----|---------|
| 권한 없음 room | 404 generic | audit log |
| API 5xx | 캐시 stale + retry | circuit breaker |
| 평가 실패 | localStorage draft | idempotent POST |
| WS 끊김 | polling 30s active room | — |

---

## 10. Test Case

{table(
    ["ID", "시나리오", "기대"],
    [
        ["TC-DASH-001", "로그인 → 대시보드", "2s 이내 KPI 4개"],
        ["TC-DASH-002", "상담 목록 → 상세", "히스토리 10+더보기"],
        ["TC-DASH-003", "평가 작성", "1s 저장, 목록 반영"],
        ["TC-DASH-004", "375px 모바일", "터치·가독성 OK"],
        ["TC-DASH-005", "프로필 수정", "검증 후 즉시 반영"],
        ["TC-DASH-006", "타인 room 접근", "403/404"],
        ["TC-DASH-007", "알림 Off 프로모션", "필수 알림 유지"],
        ["TC-DASH-008", "재상담 CTA", "open room 있으면 안내"],
        ["TC-DASH-009", "오프라인 캐시", "새로고침 복구"],
        ["TC-DASH-010", "WCAG 키보드", "Tab order 논리적"],
    ],
)}

---

## 11. Future

| 버전 | 기능 |
|------|------|
| V4.0 | 상담 예약 캘린더 (Google Calendar) |
| V4.5 | 챗봇 학습 피드백 ("도움이 됐나요") |
| V5.0 | ML 개인화 추천 |

---

## 12. React 구현 노트

- Route: `/customer/dashboard` — [06_FRONTEND/01_Frontend_아키텍처.md](../06_FRONTEND/01_Frontend_아키텍처.md)
- Hook: `useCustomerDashboard()` — SWR 30s revalidate
- Component tree: `DashboardPage` → `SummaryCards`, `ConsultTable`, `ConsultDetailDrawer`
"""


def gen_wbs() -> str:
    phases = []
    for step, name, weeks in [
        (1, "Project Setup + UI/UX", "W1-2"),
        (2, "DB + API Architecture", "W1-2"),
        (3, "AI Strategy + Prompt + Engine", "W3-4"),
        (4, "WebSocket + Real-time Chat", "W3-5"),
        (5, "CRM + Frontend React", "W5-6"),
        (6, "Admin Dashboard + Agent Mgmt", "W6-7"),
        (7, "Customer Dashboard", "W7"),
        (8, "QA + Release", "W8-9"),
    ]:
        phases.append(f"### STEP {step}: {name} ({weeks})\n")
        for t in range(1, 6):
            phases.append(
                f"- **WP{step}.{t}:** 구현·문서·DoD — 선행 STEP {max(1, step-1)}, "
                f"예상 2~5일, 담당 BE/FE/QA 교차\n"
            )
        phases.append("\n")

    return f"""# ACEP — 개발 WBS (Work Breakdown Structure)

**프로젝트:** PlusTok V3.0  
**Version:** 3.0 · **작성일:** {TODAY}  
**목표 Go-Live:** 2026-08 (9주)  
**SSOT:** `09_DEVELOPMENT/01_개발WBS.md`

---

## 1. 목적 (Purpose)

STEP 1~8 전체 구현의 작업 분해, 일정, 의존성, 마일스톤, RACI를 정의한다.

---

## 2. 범위 (Scope)

- DB DDL 14+ tables
- REST API ~40 endpoints
- Socket.io Chat Server
- React Customer/Agent UI
- PHP Admin + CRM bridge
- CI/CD + Cafe24/Docker 배포

---

## 3. WBS 계층

```
Level 1: Phase (STEP)
Level 2: Category (Backend / Frontend / DevOps / QA)
Level 3: Task (구현 단위, 예상일, 담당, 의존성)
```

---

## 4. Phase 상세

{"".join(phases)}

### Phase 1 — Platform Setup (2주)

{table(
    ["Task", "산출물", "일수", "의존"],
    [
        ["1.1 DB Schema", "03_SYSTEM/01_DB설계 DDL", "2", "—"],
        ["1.2 API Gateway", "JWT, error handler", "3", "1.1"],
        ["1.3 Auth API", "register/login/logout", "3", "1.2"],
        ["1.4 Frontend init", "React + routing", "2", "—"],
    ],
)}

### Phase 2 — Chat System (3주)

{table(
    ["Task", "산출물", "일수", "의존"],
    [
        ["2.1 WS Server", "05_CHAT/01_WebSocket설계", "5", "1.3"],
        ["2.2 Chat UI", "3-panel Agent screen", "5", "2.1"],
        ["2.3 AI Router", "04_AI/*", "4", "1.3"],
        ["2.4 Chat API", "15 endpoints", "4", "2.1"],
    ],
)}

### Phase 3 — CRM (2주)

{table(
    ["Task", "산출물", "일수", "의존"],
    [
        ["3.1 Close workflow", "06_CRM/01", "4", "2.4"],
        ["3.2 CRM API", "consults/close", "3", "3.1"],
    ],
)}

### Phase 4 — Admin & Deploy (2주)

{table(
    ["Task", "산출물", "일수", "의존"],
    [
        ["4.1 Admin UI", "07_ADMIN/*", "5", "3.2"],
        ["4.2 Customer Dash", "08_DASHBOARD/*", "3", "2.2"],
        ["4.3 DevOps", "Docker/FTP", "4", "4.1"],
        ["4.4 QA Sign-off", "02_테스트시나리오", "3", "ALL"],
    ],
)}

---

## 5. 일정 (9주)

| Week | Milestone |
|------|-----------|
| W2 | DB + API Gateway Ready |
| W5 | Chat + AI Integration Complete |
| W7 | CRM + Admin + Customer Dashboard |
| W9 | Production Go-Live |

---

## 6. 팀 구성

| 팀 | 인원 | 역할 |
|----|------|------|
| Backend | 4 | API, CRM, AI, DevOps |
| Frontend | 3 | Chat, Admin, Customer |
| QA | 2 | Automation, E2E, Performance |

---

## 7. 위험 및 완화

{table(
    ["Risk", "Impact", "Mitigation"],
    [
        ["AI API 지연", "Chat SLA", "Mock AI early"],
        ["1000 WS conn", "Scale", "W5 load test"],
        ["WS stability", "UX", "Reconnect + IndexedDB queue"],
        ["Cafe24 제약", "No Redis/Node", "V1.0 poll fallback"],
    ],
)}

---

## 8~11. DoD · 추적 · 보고 · Future

- **DoD:** 문서 SSOT + PR merge + QA TC pass + audit log
- **추적:** Jira epic STEP-N, sprint 2주
- **보고:** Weekly stakeholder demo
- **Future:** STEP 14 AI Ops Center V2.5
"""


def gen_test_scenarios() -> str:
    legacy = merge_legacy("08_TEST", "*.md")
    tcs = []
    categories = [
        ("AUTH", "로그인·JWT", 15),
        ("CHAT", "메시지·WS", 20),
        ("AI", "추천·Failover", 15),
        ("CRM", "종료·저장", 10),
        ("ADM", "Admin·Dashboard", 15),
        ("DASH", "고객 대시보드", 10),
        ("SEC", "보안", 10),
        ("PERF", "성능", 5),
    ]
    n = 1
    for cat, desc, count in categories:
        for i in range(1, count + 1):
            tcs.append(
                f"### TC-{cat}-{i:03d}\n\n"
                f"**카테고리:** {desc}  \n"
                f"**Given:** seed data STEP V1.0  \n"
                f"**When:** API/UI action #{i}  \n"
                f"**Then:** HTTP/WS assert, audit_logs  \n\n"
            )
            n += 1

    return f"""# ACEP — 테스트 시나리오 (통합)

**프로젝트:** PlusTok V3.0 · **STEP 8 SSOT**  
**작성일:** {TODAY}  
**통합 출처:** `08_TEST/` 레거시 4종 + STEP 7~8 작업지시서

> **Archive:** [08_TEST/_TEST_INDEX.md](../08_TEST/_TEST_INDEX.md) — 원본 유지, 본 문서가 QA Gate SSOT

---

## 1. 목적 (Purpose)

기능·통합·E2E·성능·보안 테스트 시나리오 100건+ 정의 및 커버리지 게이트.

---

## 2. 테스트 레벨

| Level | 도구 | 커버리지 |
|-------|------|----------|
| Unit | PHPUnit, Vitest | Service 80%+ |
| Integration | Supertest, WS client | API 100% |
| E2E | Manual + Playwright V1.5 | Critical 5 flows |

---

## 3. E2E Master Flows

{table(
    ["ID", "Flow"],
    [
        ["E2E-01", "접수 → 채팅 → AI → 종료 → CRM"],
        ["E2E-02", "Agent 다중 Room 전환"],
        ["E2E-03", "Customer 위젯 Embed"],
        ["E2E-04", "AI Failover chain"],
        ["E2E-05", "Admin KPI + RBAC operator"],
    ],
)}

---

## 4. 테스트 시나리오 ({n-1}건)

{"".join(tcs)}

---

## 5. 커버리지 게이트

| Domain | Target |
|--------|--------|
| Backend API | 85% |
| Chat UI | 85% |
| Admin | 80% |
| Customer Dashboard | 75% |

---

## 6. 성능 테스트

- 동시 1000 WS, API p95 < 500ms, 메모리 leak 없음

---

## 7. 보안 테스트

SQLi, XSS, CSRF, IDOR (타인 room), JWT expiry

---

## 8~11. 자동화 · 환경 · Sign-off · Future

- CI: PR → unit+lint; nightly → integration
- Staging: plustok.mycafe24.com
- Sign-off: QA Lead + PM ([08_TEST/04_QA_릴리스_게이트.md](../08_TEST/04_QA_릴리스_게이트.md))
- V1.5: Playwright full suite

---

## 부록 A — 레거시 08_TEST 통합

{legacy if legacy else "(08_TEST 폴더 없음 — 신규 작성)"}
"""


def gen_deploy() -> str:
    legacy = merge_legacy("09_RELEASE", "*.md")
    return f"""# ACEP — 배포·운영 (통합)

**프로젝트:** PlusTok V3.0 · **STEP 8 SSOT**  
**작성일:** {TODAY}  
**통합 출처:** `09_RELEASE/` 레거시 5종

> **Archive:** [09_RELEASE/_RELEASE_INDEX.md](../09_RELEASE/_RELEASE_INDEX.md)

---

## 1. 목적 (Purpose)

Dev/Staging/Production 배포, CI/CD, 무중단(Blue-Green), 모니터링, 롤백, On-Call.

---

## 2. 배포 환경

{table(
    ["Env", "URL", "DB", "배포"],
    [
        ["Development", "dev.plusok.local", "local MariaDB", "docker compose"],
        ["Staging", "plustok.mycafe24.com", "Cafe24 MariaDB", "FTP"],
        ["Production", "app.plusok.io", "RDS/MariaDB", "Blue-Green"],
    ],
)}

---

## 3. CI/CD 파이프라인

```
git push develop → GitHub Actions (test, lint, build)
  → Staging FTP deploy → smoke → QA 2d
  → merge main → Production (Mon/Wed/Fri 15:00 KST)
  → Blue-Green switch → 1h monitor
```

---

## 4. Blue-Green 배포

1. Green deploy + health check  
2. LB switch Blue→Green  
3. Blue standby 1h  
4. Auto rollback if error rate > 5% / 5min  

---

## 5. Pre/During/Post Checklist

**Pre:** DB backup, env vars, API keys, Slack notice  
**During:** deploy artifact, migration, health  
**Post:** smoke (login→chat→close), metrics 1h, report  

---

## 6. 환경 변수 (Secrets Manager)

`DATABASE_URL`, `JWT_SECRET`, `CLAUDE_API_KEY`, `OPENAI_API_KEY`, `REDIS_URL`, `PLUS_TOK_CRM_WEBHOOK_*`

---

## 7. 모니터링

CloudWatch, Prometheus, Grafana — API p95, 5xx rate, WS connections, AI cost

---

## 8. 롤백

- 긴급: LB → Blue  
- 일반: previous Docker tag / FTP snapshot  

---

## 9. On-Call

Backend Mon-Thu, Frontend Fri-Sun, DevOps always — #incidents Slack

---

## 10~11. Cafe24 PATH A · Docker PATH B

- **PATH A (V1.0):** FTP mirror, PHP only, poll fallback  
- **PATH B:** docker-compose nginx+php+node+redis  

---

## 부록 A — 레거시 09_RELEASE 통합

{legacy if legacy else "(09_RELEASE 폴더 없음)"}
"""


def gen_dashboard_index() -> str:
    return f"""# 08_DASHBOARD — 고객 대시보드 인덱스

> **STEP 7 SSOT** · 갱신 {TODAY}

## Primary

| 문서 | 설명 |
|------|------|
| [01_고객대시보드.md](01_고객대시보드.md) | 고객 포털 UI, API, Business Rule |

## Related

- [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) — Admin KPI (별도)
- [06_FRONTEND/_FRONTEND_INDEX.md](../06_FRONTEND/_FRONTEND_INDEX.md)
- [_작업지시서/06_STEP7_STEP8_작업지시서_Dashboard_Development.md](../_작업지시서/06_STEP7_STEP8_작업지시서_Dashboard_Development.md)
"""


def gen_development_index() -> str:
    return f"""# 09_DEVELOPMENT — 개발·QA·배포 인덱스

> **STEP 8 SSOT** · 갱신 {TODAY}

## Primary

| 문서 | 설명 |
|------|------|
| [01_개발WBS.md](01_개발WBS.md) | 9주 WBS, RACI, 리스크 |
| [02_테스트시나리오.md](02_테스트시나리오.md) | 100+ TC, 08_TEST 통합 |
| [03_배포운영.md](03_배포운영.md) | CI/CD, 09_RELEASE 통합 |

## Archive (레거시)

| 폴더 | SSOT 대체 | 비고 |
|------|-----------|------|
| [08_TEST/](../08_TEST/_TEST_INDEX.md) | 02_테스트시나리오 | 원본 보존 |
| [09_RELEASE/](../09_RELEASE/_RELEASE_INDEX.md) | 03_배포운영 | Cafe24/Docker 상세 |

## Work Order

[_작업지시서/06_STEP7_STEP8_작업지시서_Dashboard_Development.md](../_작업지시서/06_STEP7_STEP8_작업지시서_Dashboard_Development.md)
"""


def main() -> None:
    w("08_DASHBOARD/01_고객대시보드.md", gen_customer_dashboard())
    w("08_DASHBOARD/_DASHBOARD_INDEX.md", gen_dashboard_index())
    w("09_DEVELOPMENT/01_개발WBS.md", gen_wbs())
    w("09_DEVELOPMENT/02_테스트시나리오.md", gen_test_scenarios())
    w("09_DEVELOPMENT/03_배포운영.md", gen_deploy())
    w("09_DEVELOPMENT/_DEVELOPMENT_INDEX.md", gen_development_index())
    print("Generated STEP 7-8 docs in", BASE)


if __name__ == "__main__":
    main()
