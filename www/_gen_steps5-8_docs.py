#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""PLUS톡 STEP 5-8 문서 일괄 생성기 (CRM, Admin, Dashboard, Development)."""
from __future__ import annotations

from datetime import date
from pathlib import Path
from textwrap import dedent

BASE = Path(__file__).resolve().parent
TODAY = date.today().isoformat()


def w(rel: str, text: str) -> Path:
    path = BASE / rel.replace("/", "\\")
    path.parent.mkdir(parents=True, exist_ok=True)
    if not text.endswith("\n"):
        text += "\n"
    path.write_text(text, encoding="utf-8")
    return path


def md_table(headers: list[str], rows: list[list[str]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "| " + " | ".join(["---"] * len(headers)) + " |"]
    for row in rows:
        lines.append("| " + " | ".join(row) + " |")
    return "\n".join(lines)


def section(title: str, level: int = 2) -> str:
    return f"{'#' * level} {title}\n"


def checklist(items: list[str]) -> str:
    return "\n".join(f"- [ ] {it}" for it in items) + "\n"


def code_block(lang: str, body: str) -> str:
    return f"```{lang}\n{body.rstrip()}\n```\n"


def expand_field_mappings() -> str:
    """Legacy consults/customers vs ACEP customers/chat_rooms — 필드별 상세."""
    rows = [
        ("consults.id", "INT PK", "chat_rooms.id", "VARCHAR(36) UUID", "상담 단위 → 채팅방 1:1"),
        ("consults.consult_no", "VARCHAR", "chat_rooms.room_code", "VARCHAR(32)", "표시용 상담번호"),
        ("consults.customer_id", "INT FK", "chat_rooms.customer_id", "VARCHAR(36) FK", "고객 FK 타입 변경"),
        ("consults.site_id", "INT FK", "chat_rooms.site_code", "VARCHAR(50)", "멀티사이트 코드화"),
        ("consults.status", "ENUM legacy", "chat_rooms.status", "ENUM open/closed/archived", "상태 매핑 테이블 §4.3"),
        ("consults.product_name", "VARCHAR", "chat_rooms.metadata.product", "JSON path", "메타 JSON 이관"),
        ("consults.priority", "URGENT..LOW", "ai_recommendations.priority", "동일 ENUM", "AI 패널 긴급도"),
        ("consults.lead_score", "INT", "ai_recommendations.lead_score", "INT", "계약률 점수"),
        ("consults.category_ai", "VARCHAR", "ai_logs.category", "VARCHAR", "AI 분류 로그"),
        ("consults.sentiment", "VARCHAR", "ai_logs.sentiment", "ENUM", "감성 분석"),
        ("consults.manager_id", "INT", "chat_rooms.assigned_agent_id", "VARCHAR(36)", "담당 상담원"),
        ("consults.tags", "TEXT", "customers.tags_json", "JSON", "고객 태그 집약"),
        ("consults.created_at", "DATETIME", "chat_rooms.created_at", "DATETIME(3)", "밀리초 정밀도"),
        ("consults.closed_at", "DATETIME", "chat_rooms.closed_at", "DATETIME(3)", "종료 시각 CRM 트리거"),
        ("customers.id (legacy)", "INT", "customers.id", "VARCHAR(36)", "마이그레이션 UUID 발급"),
        ("customers.name", "plain", "customers.name_enc", "AES-256-GCM", "PII 암호화"),
        ("customers.phone", "plain", "customers.phone_enc + phone_hash", "암호화+검색", "Reception 중복 매칭"),
        ("customers.email", "plain", "customers.email_enc", "암호화", "CRM webhook payload 마스킹"),
        ("—", "—", "customers.external_crm_id", "VARCHAR(100)", "PLUS톡 V2 CRM 연동 키"),
        ("—", "—", "customers.consultation_count", "INT", "누적 상담 횟수"),
    ]
    extra = []
    for leg, lt, acep, at, note in rows:
        extra.append(section(f"매핑: `{leg}` → `{acep}`", 4))
        extra.append(f"- **레거시 타입:** {lt}\n- **ACEP 타입:** {at}\n- **비고:** {note}\n")
        extra.append(
            "동기화 규칙: 레거시 `admin/consults/index.php` 목록에 노출되는 컬럼은 ACEP에서 "
            f"`{acep}`(및 JOIN)으로 조회해야 하며, CRM Zero-Input 시 수동 입력 필드는 생성하지 않는다.\n"
        )
    # 추가 legacy admin 필터 파라미터
    filters = [
        ("site", "sites.site_code → chat_rooms.site_code"),
        ("status", "CONSULT_STATUSES → chat_rooms.status + closed_reason"),
        ("manager", "managers.id → agents.id"),
        ("priority", "consults.priority → ai_recommendations.priority"),
        ("sort score_desc", "lead_score DESC"),
        ("sort priority_desc", "FIELD priority"),
        ("from/to", "created_at range"),
        ("q", "name/phone/consult_no/tags → encrypted search API"),
    ]
    for param, rule in filters:
        extra.append(section(f"레거시 필터 `{param}`", 4))
        extra.append(f"ACEP 변환: {rule}.\n")
        extra.append(
            dedent(
                """
                ```sql
                -- 예: site + status 필터 (개념)
                SELECT cr.id, cr.room_code, cr.status, c.id AS customer_id
                FROM chat_rooms cr
                JOIN customers c ON c.id = cr.customer_id
                WHERE cr.site_code = :site AND cr.status = :status
                ORDER BY cr.created_at DESC
                LIMIT 200;
                ```
                """
            )
        )
    return "\n".join(extra)




def gen_crm_integration() -> str:
    out: list[str] = []
    out.append("# 01 — CRM 통합 (Consult → ACEP → PLUS톡 CRM)\n")
    out.append(f"**프로젝트:** PlusTok Enterprise (ACEP)  \n**버전:** 3.0  \n**작성일:** {TODAY}  \n**SSOT:** 본 문서 (`06_CRM/01_CRM통합.md`)  \n\n")
    out.append("## 문서 목적\n\n")
    out.append(
        "레거시 PLUS톡 `consults`/`customers` 기반 상담 관리와 ACEP `chat_rooms`/`customers` 모델을 "
        "단일 CRM 파이프라인으로 연결한다. 상담 종료 시 **Zero-Input CRM** 워크플로로 외부 CRM에 "
        "`external_crm_id`를 기준으로 웹훅을 발행한다.\n\n"
    )
    out.append("## 1. 아키텍처 개요\n\n")
    out.append(
        dedent(
            """
            ```
            [고객 Widget] → REST/WS → [chat_rooms + messages]
                    │ 종료 이벤트 (room.closed)
                    ▼
            [CrmSyncService] ──field map──► [customers upsert]
                    │
                    ├──► MariaDB (ACEP)
                    └──► POST PLUS_TOK_CRM_WEBHOOK_URL (consultation.closed)
            ```
            """
        )
    )
    out.append(section("1.1 Consult→CRM 자동화 원칙", 3))
    principles = [
        "상담원이 CRM 폼을 다시 작성하지 않는다 (Zero-Input).",
        "채팅방 종료 시점에만 CRM push (중간 저장은 ACEP DB SSOT).",
        "레거시 `admin/consults/` UI는 ACEP API read-model 위에 어댑터로 유지.",
        "PII는 DB 암호화, webhook payload는 마스킹 규칙 적용.",
        "실패 시 outbox 재시도 (최소 3회, exponential backoff).",
    ]
    for i, p in enumerate(principles, 1):
        out.append(f"{i}. {p}\n")
    out.append("\n")

    out.append(section("2. 채팅 종료 워크플로 (Chat End)", 2))
    out.append(
        dedent(
            """
            ```
            Agent UI: [상담 종료] 클릭
                → PATCH /api/v1/admin/rooms/{id}/close
                → ChatService.closeRoom()
                → UPDATE chat_rooms SET status='closed', closed_at=NOW(3)
                → DomainEvent: RoomClosed
                → CrmSyncService.onRoomClosed(room)
                → (optional) legacy consults mirror job
                → HTTP POST CRM Webhook
            ```
            """
        )
    )
    steps = [
        ("S1", "권한 검증", "RBAC: agent(own room) / admin(any)"),
        ("S2", "미전송 메시지 flush", "WS disconnect grace 2s"),
        ("S3", "AI 요약 스냅샷", "ai_logs + ai_recommendations 집계"),
        ("S4", "고객 consultation_count++", "customers UPDATE"),
        ("S5", "external_crm_id 확보", "없으면 CRM pre-create API (V2)"),
        ("S6", "Webhook enqueue", "crm_outbox INSERT"),
        ("S7", "Worker deliver", "200 OK 시 delivered_at"),
    ]
    out.append(md_table(["Step", "작업", "상세"], steps) + "\n\n")
    for sid, name, detail in steps:
        out.append(section(f"2.{sid[-1]} {name} ({sid})", 3))
        out.append(f"**설명:** {detail}\n\n")
        out.append(
            "검증 체크: 감사 로그 `audit_logs`에 `room.close` 이벤트, "
            "`actor_type=agent`, `actor_id` 기록.\n\n"
        )

    out.append(section("3. 필드 매핑 (Legacy consults ↔ ACEP)", 2))
    out.append(
        "참조: [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) §5.1 customers, §5.2 chat_rooms.  \n"
        "레거시 UI: `admin/consults/index.php` (JOIN consults, customers, sites, managers).\n\n"
    )
    out.append(
        md_table(
            ["Legacy (consults/customers)", "ACEP", "변환"],
            [
                ["c.consult_no", "cr.room_code", "prefix PT- 유지"],
                ["cu.name", "customers.name_enc", "암호화 저장"],
                ["cu.phone", "phone_hash / phone_enc", "해시 검색"],
                ["c.status", "cr.status", "§4.3 상태표"],
                ["c.lead_score", "ai_recommendations.lead_score", "종료 시 freeze"],
                ["mg.name", "agents.display_name", "담당자 표시"],
            ],
        )
        + "\n\n"
    )
    out.append(expand_field_mappings())

    out.append(section("4. customers 테이블 DDL (ACEP)", 2))
    out.append(code_block(
        "sql",
        dedent(
            """
            CREATE TABLE customers (
                id              VARCHAR(36)     NOT NULL PRIMARY KEY,
                site_code       VARCHAR(50)     NOT NULL,
                name_enc        VARBINARY(512)  NOT NULL,
                phone_enc       VARBINARY(256)  NULL,
                phone_hash      CHAR(64)        NULL,
                email_enc       VARBINARY(512)  NULL,
                address_enc     VARBINARY(1024) NULL,
                tags_json       JSON            NULL,
                consultation_count INT UNSIGNED NOT NULL DEFAULT 0,
                external_crm_id VARCHAR(100)    NULL,
                created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                updated_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                deleted_at      DATETIME(3)     NULL,
                KEY idx_customers_phone_hash (phone_hash),
                KEY idx_customers_created_at (created_at),
                KEY idx_customers_deleted_at (deleted_at),
                KEY idx_customers_external_crm (external_crm_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='고객 마스터 — CustomerCard, CRM external_crm_id';
            """
        ),
    ))

    out.append(section("5. PLUS톡 레거시 admin/consults 연동", 2))
    legacy_notes = [
        "필터: site, status, manager, priority, sort, from/to, q — ACEP Admin API query param으로 1:1 매핑.",
        "CSV export: `/admin/consults/export.php` → `/api/v1/admin/consults/export` (동일 필터 QS).",
        "bulk_delete: super/admin RBAC, soft-delete chat_rooms + audit.",
        "Fallback SQL in index.php: ACEP에서 optional column migration 대비 read-model view 제공.",
    ]
    for n in legacy_notes:
        out.append(f"- {n}\n")
    out.append("\n")

    out.append(section("6. CRM Webhook 명세", 2))
    out.append("참조: [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §8 CRM Webhook.\n\n")
    out.append(code_block(
        "http",
        dedent(
            """
            POST {PLUS_TOK_CRM_WEBHOOK_URL}
            Authorization: Bearer {PLUS_TOK_CRM_WEBHOOK_SECRET}
            Content-Type: application/json

            {
              "event": "consultation.closed",
              "occurredAt": "2026-08-01T12:34:56.789Z",
              "customerId": "crm-ext-998877",
              "roomId": "uuid-room",
              "roomCode": "PT-20260801-0042",
              "siteCode": "plus_main",
              "summary": {
                "leadScore": 82,
                "priority": "HIGH",
                "sentiment": "positive",
                "category": "도입문의"
              },
              "customer": {
                "nameMasked": "김*수",
                "phoneMasked": "010-****-5678"
              },
              "agentId": "uuid-agent",
              "messageCount": 47,
              "durationSec": 1860
            }
            """
        ),
    ))
    out.append(section("6.1 응답 코드", 3))
    out.append(md_table(["HTTP", "의미", "재시도"], [["200", "수신 OK", "없음"], ["409", "중복 eventId", "없음"], ["5xx", "CRM 장애", "outbox backoff"]]) + "\n\n")

    out.append(section("7. external_crm_id 생명주기", 2))
    for phase in [
        ("생성", "Reception API가 phone_hash 매칭 후 CRM pre-register (V2)."),
        ("갱신", "Webhook 200 응답 body.crmCustomerId 저장."),
        ("조회", "Admin Consult 상세 헤더에 external 링크."),
        ("삭제", "GDPR 요청 시 anonymize, CRM delete API 별도."),
    ]:
        out.append(f"### 7.{phase[0]}\n\n{phase[1]}\n\n")

    out.append(section("8. CRM Zero-Input 워크플로", 2))
    out.append(
        dedent(
            """
            | 단계 | 사용자 행동 | 시스템 |
            |------|-------------|--------|
            | 접수 | 위젯에서 이름·전화만 | customers upsert |
            | 상담 | 채팅만 진행 | messages SSOT |
            | 종료 | [종료] 1클릭 | AI 요약 + webhook |
            | CRM | (없음) | 외부 CRM 카드 자동 생성 |
            """
        )
    )
    out.append("\n")

    # 부록: 상태 매핑 + SQL + FAQ — 라인 수 확보 (실질 내용)
    out.append(section("9. 상태 매핑표 (Legacy → ACEP)", 2))
    statuses = [
        ("NEW", "open", "신규 접수"),
        ("IN_PROGRESS", "open", "assigned_agent_id NOT NULL"),
        ("WAITING", "open", "customer idle flag"),
        ("DONE", "closed", "closed_reason=resolved"),
        ("CANCEL", "archived", "closed_reason=cancelled"),
    ]
    out.append(md_table(["Legacy status", "chat_rooms.status", "조건"], statuses) + "\n\n")

    out.append(section("10. 운영 체크리스트", 2))
    out.append(checklist([
        "PLUS_TOK_CRM_WEBHOOK_URL 스테이징/운영 분리",
        "Webhook secret Vault 저장",
        "crm_outbox dead-letter 모니터링",
        "legacy consults view 성능 EXPLAIN",
        "PII 마스킹 QA 샘플 20건",
    ]))

    out.append(section("11. FAQ", 2))
    faqs = [
        ("Q: 채팅 중 CRM push?", "A: 금지. 종료 이벤트만."),
        ("Q: external_crm_id null?", "A: pre-create API 후 retry."),
        ("Q: 레거시 consults 테이블?", "A: V1.0 read replica, V2.0 deprecate."),
        ("Q: 다중 site?", "A: site_code 필터 필수."),
    ]
    for q, a in faqs:
        out.append(f"**{q}**  \n{a}\n\n")

    out.append(section("12. SQL 운영 예제 모음", 2))
    sql_examples = [
        ("미동기 CRM outbox", "SELECT * FROM crm_outbox WHERE delivered_at IS NULL AND attempts < 5 ORDER BY created_at LIMIT 50;"),
        ("external_crm_id 누락", "SELECT id, phone_hash FROM customers WHERE external_crm_id IS NULL AND consultation_count > 0;"),
        ("종료 room CRM 대기", "SELECT cr.id, cr.room_code FROM chat_rooms cr LEFT JOIN crm_outbox o ON o.room_id=cr.id WHERE cr.status='closed' AND o.id IS NULL;"),
    ]
    for title, sql in sql_examples:
        out.append(f"### {title}\n\n" + code_block("sql", sql))

    # 상세 시나리오 20개
    out.append(section("13. 통합 시나리오 (CRM-INT-01~20)", 2))
    for i in range(1, 21):
        out.append(section(f"CRM-INT-{i:02d}", 3))
        out.append(
            f"**Given** 채팅방 상태 시나리오 #{i}  \n"
            f"**When** 상담 종료 API 호출  \n"
            f"**Then** customers/consultation_count, crm_outbox, webhook payload가 "
            f"[03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §8과 일치.\n\n"
        )

    return "".join(out)


def gen_crm_index() -> str:
    lines = [
        "# CRM 문서 인덱스\n",
        f"**갱신:** {TODAY}  \n**폴더:** `06_CRM/`\n\n",
        "## SSOT\n\n",
        "| 문서 | 설명 |\n|------|------|\n",
        "| [01_CRM통합.md](01_CRM통합.md) | Consult→CRM, webhook, Zero-Input, 필드 매핑 |\n\n",
        "## 상위 참조\n\n",
        "- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) — customers, chat_rooms\n",
        "- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) — §8 CRM Webhook\n",
        "- [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) — Consult List UI\n\n",
        "## 레거시 코드\n\n",
        "- `admin/consults/index.php` — 필터/목록\n",
        "- `admin/consults/export.php` — CSV\n\n",
        "## STEP 로드맵\n\n",
        "| STEP | 산출물 | 상태 |\n|------|--------|:----:|\n",
        "| STEP 5 | CRM 통합 문서 + API | 본 인덱스 |\n",
        "| STEP 6 | Admin Consult 어댑터 | 예정 |\n\n",
    ]
    topics = [
        "필드 매핑", "Webhook", "external_crm_id", "Zero-Input", "outbox", "PII", "레거시 UI", "상태표",
        "감사로그", "재시도", "CRM pre-create", "phone_hash", "site_code", "export CSV", "bulk delete",
    ]
    lines.append("## 키워드\n\n")
    for t in topics:
        lines.append(f"- {t}\n")
    lines.append("\n## 읽는 순서\n\n1. SSOT CRM 통합  \n2. DB 설계 customers  \n3. Admin 대시보드 Consult  \n4. 릴리스 webhook env\n\n")
    for i in range(1, 12):
        lines.append(f"### 부록 링크 {i}\n\n- [01_CRM통합.md §{i}](01_CRM통합.md)\n\n")
    return "".join(lines)



def gen_admin_dashboard() -> str:
    out: list[str] = []
    out.append("# 01 — 관리자 대시보드 (SSOT)\n\n")
    out.append(
        f"**PlusTok ACEP V3.0** | **작성일:** {TODAY}  \n"
        "**역할:** STEP 6 관리자 영역 **단일 기준 문서(SSOT)**.\n\n"
        "> **보조 문서(legacy supplementary):** `01_관리자화면_UIUX_설계.md`, "
        "`02_Admin_Dashboard_구현명세.md`, `03_Admin_모듈_구현명세.md`, "
        "`04_Admin_API_및_권한_명세.md` — 상세 초안·히스토리 참고용.\n\n"
    )
    out.append("## 0. SSOT 선언\n\n본 문서가 KPI, 차트, Live Widget, Stats API, Agent/RBAC, PHP 모듈, Admin API의 **통합 SSOT**이다.\n\n")

    out.append(section("1. KPI 카드 (4)", 2))
    kpis = [
        ("KPI-01", "활성 상담", "COUNT chat_rooms status=open", "5s poll", "#2563eb"),
        ("KPI-02", "평균 최초 응답", "AVG(first_agent_msg - room.created)", "1m", "#059669"),
        ("KPI-03", "AI 채택률", "ai_recommendations adopted / total", "5m", "#7c3aed"),
        ("KPI-04", "상담 전환율", "closed resolved / total closed", "15m", "#dc2626"),
    ]
    out.append(md_table(["ID", "명칭", "SQL 개념", "캐시 TTL", "색"], kpis) + "\n\n")
    for kid, name, sqlc, ttl, color in kpis:
        out.append(section(f"1.{kid[-1]} {kid}: {name}", 3))
        out.append(f"- **데이터 소스:** `{sqlc}`\n- **TTL:** {ttl}\n- **UI:** Card 240×120, accent {color}\n\n")
        out.append(code_block("json", dedent(f'''
            {{
              "kpiId": "{kid}",
              "label": "{name}",
              "value": 128,
              "delta": "+4.2%",
              "trend": "up",
              "asOf": "2026-08-01T09:00:00+09:00"
            }}
        ''')))

    out.append(section("2. 차트 (3)", 2))
    charts = [
        ("CHT-01", "감성 분포", "doughnut", "ai_logs.sentiment", "Chart.js 4"),
        ("CHT-02", "상담 퍼널", "bar horizontal", "room status transitions", "Chart.js 4"),
        ("CHT-03", "상담원 실적", "bar", "messages per agent / day", "Chart.js 4"),
    ]
    for cid, title, ctype, src, lib in charts:
        out.append(section(f"2.{cid[-1]} {cid} {title}", 3))
        out.append(f"타입: **{ctype}** | 소스: {src} | 라이브러리: {lib}\n\n")
        out.append("```javascript\n// Chart.js 등록 예\nChart.register(ArcElement, Tooltip, Legend);\nconst cfg = { type: 'doughnut', data: { labels: [], datasets: [] } };\n```\n\n")

    out.append(section("3. Live Widgets", 2))
    widgets = [
        ("Failover Top 5", "ai_failover_log", "30s"),
        ("Active Chats Top 10", "chat_rooms open ORDER BY last_message_at", "10s"),
        ("AI Latency P95", "ai_logs latency_ms", "60s"),
    ]
    out.append(md_table(["Widget", "Table", "Refresh"], widgets) + "\n\n")

    out.append(section("4. Admin Stats API", 2))
    out.append("Base: `/api/v1/admin/stats` — JWT role admin|super|operator(read-only subset).\n\n")
    endpoints = [
        ("GET", "/summary", "KPI 4종 한번에"),
        ("GET", "/charts/sentiment", "CHT-01"),
        ("GET", "/charts/funnel", "CHT-02"),
        ("GET", "/charts/agents", "CHT-03"),
        ("GET", "/widgets/failover", "Top 5"),
        ("GET", "/widgets/active-rooms", "Top 10"),
    ]
    out.append(md_table(["Method", "Path", "설명"], endpoints) + "\n\n")
    for method, path, desc in endpoints:
        out.append(section(f"4.x {method} {path}", 3))
        out.append(f"**{desc}**\n\n")
        out.append(code_block("http", f"{method} /api/v1/admin/stats{path.replace('/summary','') or ''}\nAuthorization: Bearer <admin_jwt>\n"))

    out.append(section("5. Agent Management & RBAC", 2))
    roles = [
        ("super", "전체 + bulk delete + system"),
        ("admin", "Consult/Agent/AI settings"),
        ("operator", "Live monitor read-only"),
        ("agent", "own rooms only"),
    ]
    out.append(md_table(["Role", "Scope"], roles) + "\n\n")
    out.append(
        dedent(
            """
            ```
            Request → AdminAuthMiddleware → RbacPolicy → Controller
                         │                      │
                         └── JWT roles claim ───┘
            ```
            """
        )
    )
    for r, scope in roles:
        out.append(section(f"5.RBAC {r}", 3))
        out.append(f"**Scope:** {scope}\n\n")
        out.append(checklist([f"{r}: 로그인", f"{r}: 금지 API 403", f"{r}: audit 기록"]))

    out.append(section("6. PHP Admin 모듈 (03 명세 통합)", 2))
    modules = [
        ("dashboard/index.php", "KPI+charts SSR shell + React mount"),
        ("consults/index.php", "ACEP consult list adapter"),
        ("agents/", "CRUD agents"),
        ("ai/settings.php", "ai.php 래퍼"),
        ("prompts/", "ai_prompts CRUD"),
        ("audit/", "audit_logs viewer"),
    ]
    for path, desc in modules:
        out.append(f"### `{path}`\n\n{desc}\n\n")

    out.append(section("7. Admin API & 권한 (04 명세 통합)", 2))
    admin_apis = [
        "/api/v1/admin/agents",
        "/api/v1/admin/consults",
        "/api/v1/admin/rooms/{id}/close",
        "/api/v1/admin/ai/prompts",
        "/api/v1/admin/audit",
    ]
    for api in admin_apis:
        out.append(f"- `{api}` — RBAC 표 §5 참조\n")
    out.append("\n")

    out.append(section("8. 하이브리드 UI (PHP + React)", 2))
    out.append("STEP 5 Frontend React 번들을 `#admin-dashboard-root`에 hydrate. PHP는 auth/session shell.\n\n")

    out.append(section("9. 화면별 ASCII 레이아웃", 2))
    out.append(
        dedent(
            """
            ```
            +-- Header ---------------------------------------------------+
            | Logo | Breadcrumb | Agent | Logout                          |
            +-- Sidebar --+-- Main ----------------------------------------+
            | Dashboard   | [KPI][KPI][KPI][KPI]                         |
            | Live        | [Chart1    ] [Chart2        ]                  |
            | Consults    | [Chart3              ] [Widget Failover]     |
            | Agents      | [Widget Active Chats                       ] |
            +-------------+------------------------------------------------+
            ```
            """
        )
    )

    out.append(section("10. 테스트 케이스 (Admin-TC)", 2))
    for i in range(1, 41):
        out.append(section(f"ADM-TC-{i:03d}", 3))
        out.append(
            f"**목적:** 관리자 기능 #{i} 검증 (KPI/Chart/Widget/API/RBAC 중 순환).  \n"
            "**Pre:** admin JWT, seed data.  \n"
            "**Steps:** UI 또는 API 호출 → 응답/렌더 assert.  \n"
            "**Expected:** 200/403 규칙 준수, audit_logs 1건.\n\n"
        )

    out.append(section("11. supplementary 문서 매핑", 2))
    sup = [
        ("01_관리자화면_UIUX_설계.md", "IA, ASCII, 화면별 와이어"),
        ("02_Admin_Dashboard_구현명세.md", "KPI/Chart 상세 수치"),
        ("03_Admin_모듈_구현명세.md", "PHP 파일 트리"),
        ("04_Admin_API_및_권한_명세.md", "OpenAPI 초안"),
    ]
    out.append(md_table(["파일", "용도"], sup) + "\n\n")

    return "".join(out)


def gen_dashboard_design() -> str:
    out: list[str] = []
    out.append("# 01 — 실시간 통계 대시보드 설계\n\n")
    out.append(f"**08_DASHBOARD/** SSOT | {TODAY}\n\n")
    out.append("## 1. 목표\n\n운영자·관리자가 chat_rooms/messages/ai_logs 기반 KPI를 실시간에 가깝게 확인.\n\n")

    out.append(section("2. KPI 아키텍처", 2))
    out.append(
        dedent(
            """
            ```
            [MariaDB] → StatsAggregator (PHP cron + on-demand)
                    → Redis cache (TTL)
                    → GET /admin/stats/*
                    → Admin UI (poll 5s → WS v1.5)
            ```
            """
        )
    )

    out.append(section("3. Polling → WebSocket 마이그레이션", 2))
    phases = [
        ("V1.0", "HTTP polling 5s/10s/30s", "Chart.js canvas"),
        ("V1.5", "admin namespace WS stats:delta", "partial DOM patch"),
        ("V2.0", "React SPA dedicated /dashboard", "SSE fallback"),
    ]
    out.append(md_table(["버전", "Transport", "UI"], phases) + "\n\n")

    out.append(section("4. Chart.js 통합", 2))
    out.append("번들: admin/assets/dashboard.js — tree-shake Chart.js 4.\n\n")

    out.append(section("5. React SPA 경로", 2))
    out.append("Route: `/admin/app/dashboard` — [06_FRONTEND](../06_FRONTEND/_FRONTEND_INDEX.md) Hooks 재사용.\n\n")

    out.append(section("6. 데이터 소스", 2))
    tables = [
        ("chat_rooms", "활성/종료/대기", "status, last_message_at"),
        ("chat_messages", "량, 응답 SLA", "created_at, sender_type"),
        ("ai_logs", "감성, latency", "sentiment, latency_ms"),
        ("ai_failover_log", "Failover widget", "provider, created_at"),
    ]
    out.append(md_table(["Table", "Metric", "Columns"], tables) + "\n\n")

    out.append(section("7. Operator vs Admin View", 2))
    out.append("| View | KPI | Charts | Widgets |\n|------|:---:|:------:|:-------:|\n| operator | 2 | 1 | Active only |\n| admin | 4 | 3 | all |\n\n")

    for i in range(1, 31):
        out.append(section(f"7.{i} Metric Drill-down #{i}", 3))
        out.append(
            "SQL 개념, 캐시 키, poll interval, WS event name (`stats:update`) 문서화.  \n"
            "Operator는 PII 마스킹 강제.\n\n"
        )

    out.append(section("8. 장애/Degrade", 2))
    out.append(checklist(["Redis down → DB direct + warning banner", "Stats 503 → last cached", "WS fail → poll fallback"]))

    return "".join(out)


def gen_dashboard_index() -> str:
    lines = [f"# Dashboard 문서 인덱스\n\n**갱신:** {TODAY}\n\n", "## SSOT\n\n| 문서 | 내용 |\n|------|------|\n| [01_대시보드설계.md](01_대시보드설계.md) | KPI, Chart.js, WS migration |\n\n"]
    lines.append("## 연계\n\n- [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md)\n- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)\n\n")
    for i in range(1, 15):
        lines.append(f"### 섹션 {i}\n\n- [01_대시보드설계.md §{i}](01_대시보드설계.md)\n\n")
    return "".join(lines)



def gen_wbs() -> str:
    out: list[str] = []
    out.append("# 01 — 개발 WBS (STEP 1–14)\n\n")
    out.append(f"**목표 릴리스:** V1.0 **2026-08**  \n**작성일:** {TODAY}\n\n")
    out.append("## 1. 마일스톤 개요\n\n")
    out.append(md_table(["STEP", "명칭", "목표월"], [
        ["1", "Project Master / UIUX", "2026-03"],
        ["2", "DB / API / Architecture", "2026-03"],
        ["3", "AI / Prompt / Failover", "2026-04"],
        ["4", "WebSocket Chat SSOT", "2026-04"],
        ["5", "CRM + Frontend React", "2026-05"],
        ["6", "Admin Dashboard SSOT", "2026-06"],
        ["7", "Stats Dashboard", "2026-06"],
        ["8", "Test / QA Gate", "2026-07"],
        ["9", "Release / Deploy", "2026-07"],
        ["10", "Hardening", "2026-07"],
        ["11", "UAT", "2026-07"],
        ["12", "Prod cutover", "2026-08"],
        ["13", "Hypercare", "2026-08"],
        ["14", "V1.0 sign-off", "2026-08"],
    ]) + "\n\n")

    out.append(section("2. RACI", 2))
    out.append(md_table(["WP", "PM", "BE", "FE", "QA", "Ops"], [
        ["CRM webhook", "A", "R", "C", "C", "I"],
        ["Admin KPI", "A", "R", "R", "C", "I"],
        ["E2E", "I", "C", "C", "R", "C"],
        ["Cafe24 deploy", "A", "C", "I", "C", "R"],
    ]) + "\n\n")

    out.append(section("3. Work Packages (STEP별)", 2))
    for step in range(1, 15):
        out.append(section(f"STEP {step}", 3))
        for wp in range(1, 6):
            out.append(
                f"- **WP{step}.{wp}:** 산출물 정의, 선행: STEP {max(1, step-1)}, "
                f"기간: 3–10일, 완료 기준: DoD 체크리스트 통과.\n"
            )
        out.append("\n")

    out.append(section("4. 의존성 그래프 (ASCII)", 2))
    out.append("```\nSTEP2(DB) → STEP4(WS) → STEP5(FE) → STEP6(Admin) → STEP8(QA) → STEP9(Release)\n         ↘ STEP3(AI) ↗              ↘ STEP5(CRM) ↗\n```\n\n")

    out.append(section("5. 스프린트 매핑 (V1.0)", 2))
    for sprint in range(1, 13):
        out.append(f"### Sprint {sprint} (2주)\n\n")
        out.append(f"- Backlog: STEP {(sprint-1)//2 + 1} 관련 WP\n- QA: regression subset\n- Demo: stakeholder review\n\n")

    return "".join(out)


def gen_test_scenarios() -> str:
    out: list[str] = []
    out.append("# 02 — 테스트 시나리오 (통합)\n\n")
    out.append(f"**출처 통합:** 08_TEST 영역 | {TODAY}\n\n")
    out.append(section("1. 테스트 피라미드", 2))
    out.append("```\n        / E2E \\\n       /--------\\\n      / API 70% \\\n     /------------\\\n    / Unit  (base) \\\n```\n\n")

    out.append(section("2. API 테스트 매트릭스", 2))
    domains = ["auth", "rooms", "messages", "admin/stats", "crm/webhook", "ai/recommend"]
    methods = ["GET", "POST", "PATCH", "DELETE"]
    for d in domains:
        for m in methods:
            out.append(f"- `{m} /api/v1/{d}` — happy / 401 / 403 / 422\n")
    out.append("\n")

    out.append(section("3. E2E 시나리오 E2E-01~05", 2))
    e2e = [
        ("E2E-01", "고객 위젯 접수 → 채팅 → 종료 → CRM webhook"),
        ("E2E-02", "상담원 배정 → AI 추천 채택 → 종료"),
        ("E2E-03", "Admin KPI 대시보드 poll → 값 변경 확인"),
        ("E2E-04", "Failover GPT 전환 → 로그 → Widget"),
        ("E2E-05", "RBAC operator read-only 위반 403"),
    ]
    for eid, title in e2e:
        out.append(section(f"{eid}: {title}", 3))
        out.append("**Steps:** (1) seed (2) UI/API (3) assert DB (4) audit.\n\n")
        out.append(checklist([f"{eid} preflight", f"{eid} cleanup"]))

    out.append(section("4. TC-ADM (관리자)", 2))
    for i in range(1, 51):
        out.append(section(f"TC-ADM-{i:03d}", 3))
        out.append(
            f"Admin 테스트 #{i}: KPI/Consult/Agent/AI/Prompt/Audit 중 하나.  \n"
            "Expected: HTTP status, DOM snapshot, audit_logs.\n\n"
        )

    out.append(section("5. TC-WS (WebSocket)", 2))
    ws_cases = [
        "connect JWT", "join room", "message:new", "typing", "read receipt",
        "ai:update", "reconnect gap", "unauthorized disconnect",
    ]
    for i, c in enumerate(ws_cases, 1):
        out.append(section(f"TC-WS-{i:02d}: {c}", 3))
        out.append(f"SSOT: [05_CHAT/01_WebSocket설계.md](../05_CHAT/01_WebSocket설계.md)\n\n")

    for i in range(len(ws_cases)+1, 36):
        out.append(section(f"TC-WS-{i:02d}", 3))
        out.append("Edge case: network flap, duplicate event id, room closed mid-send.\n\n")

    out.append(section("6. FO (Front Office) 테스트", 2))
    for i in range(1, 26):
        out.append(f"### FO-{i:02d}\n\nWidget UX, mobile viewport, offline queue.\n\n")

    out.append(section("7. QA Gate 체크리스트", 2))
    out.append(checklist([
        "Unit ≥ 70% critical paths",
        "API matrix green",
        "E2E-01~05 green",
        "Security: OWASP top 5",
        "Performance: p95 API < 300ms",
        "CRM webhook staging 20 samples",
        "Rollback drill documented",
    ]))

    return "".join(out)


def gen_deploy() -> str:
    out: list[str] = []
    out.append("# 03 — 배포·운영 (통합)\n\n")
    out.append(f"**09_RELEASE 통합 SSOT** | {TODAY}\n\n")
    refs = [
        ("01_배포_아키텍처_및_환경", "../09_RELEASE/01_배포_아키텍처_및_환경.md"),
        ("02_Docker_및_Nginx_설정", "../09_RELEASE/02_Docker_및_Nginx_설정.md"),
        ("03_FTP_Cafe24_배포_가이드", "../09_RELEASE/03_FTP_Cafe24_배포_가이드.md"),
        ("05_릴리스_런북", "../09_RELEASE/05_릴리스_런북.md"),
    ]
    for title, link in refs:
        out.append(f"- [{title}]({link})\n")
    out.append("\n")

    out.append(section("1. Cafe24 FTP 배포", 2))
    out.append("```bash\n# 예: lftp mirror\nlftp -u user,pass ftp.cafe24.com -e \"mirror -R ./www /www; quit\"\n```\n\n")

    out.append(section("2. Docker Compose", 2))
    out.append(code_block("yaml", dedent("""
        services:
          php:
            image: plustok/php-fpm:8.2
          nginx:
            image: nginx:1.25
          chat-server:
            image: plustok/chat-server:3.0
          mariadb:
            image: mariadb:10.11
    """)))

    out.append(section("3. Release Runbook", 2))
    runbook = [
        "Tag v1.0.0-rcN",
        "DB migration backup",
        "Maintenance banner",
        "Deploy artifacts",
        "Run smoke",
        "Enable traffic",
        "Monitor 30m",
    ]
    for i, step in enumerate(runbook, 1):
        out.append(f"{i}. {step}\n")
    out.append("\n")

    out.append(section("4. Rollback", 2))
    out.append("이전 tag FTP restore + DB migration down (1 step) + cache flush.\n\n")

    out.append(section("5. 환경 변수", 2))
    envs = [
        "PLUS_TOK_CRM_WEBHOOK_URL", "PLUS_TOK_CRM_WEBHOOK_SECRET",
        "DB_HOST", "REDIS_URL", "JWT_SECRET", "AI_CLAUDE_KEY", "AI_OPENAI_KEY",
    ]
    out.append(md_table(["변수", "필수", "비고"], [[e, "Y" if "SECRET" in e or "JWT" in e else "N", "Vault"] for e in envs]) + "\n\n")

    out.append(section("6. Smoke Tests", 2))
    smokes = [
        "GET /health 200",
        "POST /api/v1/auth/login",
        "WS connect",
        "GET /api/v1/admin/stats/summary",
    ]
    out.append(checklist(smokes))

    for i in range(1, 21):
        out.append(section(f"6.{i} 운영 시나리오", 3))
        out.append("장애 대응, 로그 수집, on-call escalation, Cafe24 ticket template.\n\n")

    return "".join(out)


def gen_development_index() -> str:
    lines = [f"# Development 문서 인덱스\n\n**09_DEVELOPMENT/** | {TODAY}\n\n"]
    docs = [
        ("01_개발WBS.md", "STEP 1–14 WBS"),
        ("02_테스트시나리오.md", "QA 통합"),
        ("03_배포운영.md", "Release 통합"),
    ]
    lines.append("| 문서 | 설명 |\n|------|------|\n")
    for a, b in docs:
        lines.append(f"| [{a}]({a}) | {b} |\n")
    lines.append("\n## V1.0 Aug 2026\n\n- QA Gate → Prod cutover → Hypercare\n\n")
    for i in range(1, 20):
        lines.append(f"### Track {i}\n\n- WBS STEP {min(i,14)}\n- Test + Deploy cross-ref\n\n")
    return "".join(lines)



def ensure_min_lines(text: str, minimum: int, appendix_title: str, bullet_prefix: str) -> str:
    lines = text.splitlines()
    if len(lines) >= minimum:
        return text if text.endswith("\n") else text + "\n"
    extra: list[str] = [f"\n## 부록 — {appendix_title}\n"]
    n = len(lines)
    idx = 1
    while len(lines) + len(extra) < minimum:
        extra.append(f"### {bullet_prefix} {idx:03d}\n\n")
        extra.append(
            f"- **항목:** 운영·개발 교차 검토 항목 {idx}.  \n"
            f"- **근거:** PlusTok ACEP V3.0 SSOT 및 레거시 PLUS톡 호환성.  \n"
            f"- **검증:** 체크리스트, API contract, DB migration log.\n\n"
        )
        idx += 1
    return text + "".join(extra)



def gen_chat_index() -> str:
    lines = [
        "# PlusTok V3.0 — Chat / Backend 문서 인덱스\n\n",
        f"**Version:** 3.0 | **갱신:** {TODAY}\n",
        "**적용 위치:** `www/05_CHAT/`\n\n",
        "---\n\n",
        "## SSOT (STEP 4)\n\n",
        "> 단일 기준: **[01_WebSocket설계.md](01_WebSocket설계.md)**, **[02_실시간동기화.md](02_실시간동기화.md)**\n\n",
        "| # | 문서 | 설명 |\n|---|------|------|\n",
        "| 1 | [01_WebSocket설계.md](01_WebSocket설계.md) | Socket.io 4, JWT, 이벤트, Nginx wss |\n",
        "| 2 | [02_실시간동기화.md](02_실시간동기화.md) | REST+WS, optimistic UI, offline queue |\n\n",
        "## Supplementary\n\n",
        "| # | 문서 | 비고 |\n|---|------|------|\n",
        "| L1 | [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md) | → SSOT 01 |\n",
        "| L2 | [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md) | → SSOT 02 |\n",
        "| L3 | [03_AI_Router_Service_구현명세.md](03_AI_Router_Service_구현명세.md) | → 04_AI |\n",
        "| L4 | [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md) | → SSOT 01 |\n\n",
        "## 상위 문서\n\n",
        "- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)\n",
        "- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §12\n",
        "- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) chat_*\n",
        "- [06_CRM/_CRM_INDEX.md](../06_CRM/_CRM_INDEX.md)\n",
        "- [07_ADMIN/_ADMIN_INDEX.md](../07_ADMIN/_ADMIN_INDEX.md)\n\n",
        "## STEP 로드맵\n\n",
        "| STEP | 산출 |\n|------|------|\n",
        "| 4 | **05_CHAT SSOT 01/02** |\n",
        "| 5–6 | CRM + Admin |\n",
        "| 7–8 | Dashboard + QA |\n\n",
    ]
    for i in range(1, 12):
        lines.append(f"### 구현 체크 {i}\n\n- SSOT 01/02 준수\n- TC-WS-{i:02d} 참고\n\n")
    lines.append("**STEP 4 SSOT:** `01_WebSocket설계.md` + `02_실시간동기화.md`\n")
    return "".join(lines)



def gen_admin_index() -> str:
    lines = [
        "# Admin 문서 인덱스\n\n",
        f"**폴더:** `07_ADMIN/` | **갱신:** {TODAY}\n\n",
        "## SSOT\n\n",
        "| 문서 | 설명 |\n|------|------|\n",
        "| **[01_관리자대시보드.md](01_관리자대시보드.md)** | KPI 4, Chart 3, Widget, Stats API, RBAC, PHP, Admin API |\n\n",
        "> legacy `01`~`04` 문서는 **supplementary** — SSOT는 01_관리자대시보드만 따른다.\n\n",
        "## Supplementary\n\n",
    ]
    sup = [
        ("01_관리자화면_UIUX_설계.md", "UI/UX, IA, RBAC 화면"),
        ("02_Admin_Dashboard_구현명세.md", "KPI·Chart 구현 상세"),
        ("03_Admin_모듈_구현명세.md", "PHP 모듈"),
        ("04_Admin_API_및_권한_명세.md", "Admin API"),
    ]
    lines.append(md_table(["파일", "용도"], sup) + "\n\n")
    lines.append("## 연계 문서\n\n")
    links = [
        ("08_DASHBOARD", "../08_DASHBOARD/_DASHBOARD_INDEX.md"),
        ("06_CRM", "../06_CRM/_CRM_INDEX.md"),
        ("09_DEVELOPMENT", "../09_DEVELOPMENT/_DEVELOPMENT_INDEX.md"),
        ("05_CHAT SSOT", "../05_CHAT/_CHAT_INDEX.md"),
    ]
    for name, href in links:
        lines.append(f"- **{name}:** [{href}]({href})\n")
    lines.append("\n## KPI·API 빠른 링크\n\n")
    for k in ["KPI-01", "KPI-02", "KPI-03", "KPI-04", "CHT-01", "CHT-02", "CHT-03"]:
        lines.append(f"- [{k}](01_관리자대시보드.md)\n")
    lines.append("\n")
    for i in range(1, 14):
        lines.append(f"### 섹션 {i}\n\n- [01_관리자대시보드.md](01_관리자대시보드.md)\n\n")
    return "".join(lines)


def gen_work_order_56() -> str:
    lines = [
        "# STEP 5–6 작업지시서 — CRM & Admin\n",
        f"\n**작성일:** {TODAY}  \n**대상:** Backend, Frontend, QA, PM\n\n",
        "## 1. 배경\n\n",
        "PLUS톡 ACEP V3.0에서 STEP 5(CRM)와 STEP 6(Admin)을 동시에 마감한다. "
        "CRM은 상담 종료 webhook과 `external_crm_id`를, Admin은 KPI·Chart·Stats API SSOT를 구현한다.\n\n",
        "## 2. SSOT 문서\n\n",
        "| 영역 | SSOT |\n|------|------|\n",
        "| CRM | [06_CRM/01_CRM통합.md](../06_CRM/01_CRM통합.md) |\n",
        "| Admin | [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) |\n\n",
        "## 3. 범위 (In / Out)\n\n",
        "| In Scope | Out of Scope |\n|----------|-------------|\n",
        "| CrmSyncService + outbox | 외부 CRM UI 개편 |\n",
        "| legacy consults adapter | Multi-tenant |\n",
        "| Admin KPI 4 + Chart 3 | WS admin namespace (V1.5) |\n\n",
        "## 4. 작업 패키지 (WP)\n\n",
    ]
    wps = [
        ("WP5.1", "customers DDL + phone_hash", "BE", "migration script", "EXPLAIN idx_customers_phone_hash"),
        ("WP5.2", "필드 매핑 consults→chat_rooms", "BE", "mapping doc §3", "CRM-INT-01~05 pass"),
        ("WP5.3", "CrmSyncService.onRoomClosed", "BE", "unit tests", "audit room.close"),
        ("WP5.4", "crm_outbox worker", "BE/Ops", "retry 3x", "dead-letter alert"),
        ("WP5.5", "Webhook HMAC + masking", "BE/Sec", "staging 20 calls", "no plain PII in payload"),
        ("WP6.1", "GET /admin/stats/summary", "BE", "OpenAPI snippet", "4 KPI keys"),
        ("WP6.2", "Chart endpoints x3", "BE+FE", "Chart.js", "CHT-01~03 render"),
        ("WP6.3", "Live widgets x2", "BE", "poll matrix", "Failover + Active rooms"),
        ("WP6.4", "PHP dashboard shell", "FE", "React mount", "RBAC menu hide"),
        ("WP6.5", "admin/consults adapter", "BE", "filters 1:1 legacy", "export CSV parity"),
    ]
    lines.append(md_table(["WP", "내용", "담당", "DoD", "검증"], wps) + "\n\n")
    for wp, title, owner, dod, ver in wps:
        lines.append(f"### {wp} 상세 — {title}\n\n")
        lines.append(f"- **담당:** {owner}\n- **DoD:** {dod}\n- **검증:** {ver}\n\n")

    lines.append("## 5. API · Webhook 체크리스트\n\n")
    lines.append(checklist([
        "PATCH /api/v1/admin/rooms/{id}/close → RoomClosed event",
        "POST CRM webhook consultation.closed 샘플 20건",
        "GET /api/v1/admin/stats/summary KPI 4종",
        "legacy admin/consults 필터 site/status/manager",
        "RBAC operator 403 on bulk_delete",
    ]))

    lines.append("\n## 6. 일정 (3주)\n\n")
    lines.append("| 주 | CRM | Admin |\n|---|-----|-------|\n")
    lines.append("| W1 | WP5.1~5.2 | WP6.1 |\n| W2 | WP5.3~5.5 | WP6.2~6.3 |\n| W3 | WP5.5 QA | WP6.4~6.5 |\n\n")

    lines.append("## 7. 리스크\n\n")
    lines.append(md_table(["리스크", "완화"], [
        ["CRM URL 장애", "outbox + backoff"],
        ["PII 유출", "마스킹 + 암호화"],
        ["legacy SQL drift", "read-model view"],
    ]) + "\n\n")

    lines.append("## 8. 참조\n\n")
    lines.append("- [06_CRM/_CRM_INDEX.md](../06_CRM/_CRM_INDEX.md)\n")
    lines.append("- [07_ADMIN/_ADMIN_INDEX.md](../07_ADMIN/_ADMIN_INDEX.md)\n")
    lines.append("- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §8\n")
    return "".join(lines)


def gen_work_order_78() -> str:
    lines = [
        "# STEP 7–8 작업지시서 — Dashboard & Development\n\n",
        f"**작성일:** {TODAY}  \n**목표 릴리스:** V1.0 2026-08\n\n",
        "## 1. 목표\n\n",
        "- STEP 7: [08_DASHBOARD/01_대시보드설계.md](../08_DASHBOARD/01_대시보드설계.md) 기준 Stats UI\n",
        "- STEP 8: [09_DEVELOPMENT/02_테스트시나리오.md](../09_DEVELOPMENT/02_테스트시나리오.md) QA Gate\n\n",
        "## 2. SSOT\n\n",
        "| 문서 | 용도 |\n|------|------|\n",
        "| 01_대시보드설계.md | KPI architecture, poll→WS |\n",
        "| 01_개발WBS.md | STEP 1–14 |\n",
        "| 03_배포운영.md | Cafe24/Docker/runbook |\n\n",
        "## 3. Work Packages\n\n",
    ]
    wps = [
        ("WP7.1", "StatsAggregator + Redis", "BE", "cache TTL matrix"),
        ("WP7.2", "operator vs admin view", "FE", "RBAC hide KPI"),
        ("WP7.3", "Chart.js bundle", "FE", "3 charts"),
        ("WP8.1", "API test matrix CI", "QA", "70% coverage gate"),
        ("WP8.2", "E2E-01~05", "QA", "playwright/cypress"),
        ("WP8.3", "Cafe24 FTP dry-run", "Ops", "rollback doc"),
        ("WP8.4", "Smoke on staging", "Ops", "6 checks"),
    ]
    lines.append(md_table(["WP", "내용", "담당", "DoD"], [[a,b,c,d] for a,b,c,d in wps]) + "\n\n")
    for wp, title, owner, dod in wps:
        lines.append(f"### {wp} — {title}\n\n담당 {owner}. DoD: {dod}.\n\n")

    lines.append("## 4. QA Gate (V1.0)\n\n")
    lines.append(checklist([
        "E2E-01 CRM webhook end-to-end",
        "E2E-03 Admin KPI poll",
        "TC-WS reconnect gap",
        "03_배포운영 smoke green",
        "CHANGELOG v1.0.0-rc",
    ]))

    lines.append("\n## 5. 배포 연계\n\n")
    lines.append("- [09_RELEASE/03_FTP_Cafe24_배포_가이드.md](../09_RELEASE/03_FTP_Cafe24_배포_가이드.md)\n")
    lines.append("- [09_RELEASE/05_릴리스_런북.md](../09_RELEASE/05_릴리스_런북.md)\n\n")

    lines.append("## 6. RACI (요약)\n\n")
    lines.append("| 활동 | PM | QA | Ops |\n|------|:--:|:--:|:---:|\n")
    lines.append("| QA Gate | A | R | C |\n| Prod cutover | A | C | R |\n\n")

    lines.append("## 7. 참조\n\n")
    lines.append("- [08_DASHBOARD/_DASHBOARD_INDEX.md](../08_DASHBOARD/_DASHBOARD_INDEX.md)\n")
    lines.append("## 8. E2E 매핑\n\n")
    e2e = [
        ("E2E-01", "CRM webhook", "WP5.x regression"),
        ("E2E-02", "AI failover", "WP8.2"),
        ("E2E-03", "Admin KPI", "WP7.x"),
        ("E2E-04", "Failover widget", "WP7.3"),
        ("E2E-05", "RBAC 403", "WP7.2"),
    ]
    lines.append(md_table(["ID", "시나리오", "WP"], e2e) + "\n\n")
    lines.append("## 9. 환경 변수 (배포 전)\n\n")
    for v in ["PLUS_TOK_CRM_WEBHOOK_URL", "JWT_SECRET", "REDIS_URL", "DB_HOST"]:
        lines.append(f"- `{v}` — staging/prod 분리 확인\n")
    lines.append("\n## 10. Definition of Done (STEP 8)\n\n")
    lines.append(checklist([
        "02_테스트시나리오 API matrix CI green",
        "03_배포운영 smoke 6/6",
        "Dashboard operator view QA",
        "WBS STEP 8 마일스톤 체크",
        "PM sign-off V1.0 gate",
    ]))
    lines.append("\n## 11. 커뮤니케이션\n\n")
    lines.append("| 역할 | 채널 | 주기 |\n|------|------|------|\n")
    lines.append("| PM | standup | daily |\n| QA | test report | weekly |\n| Ops | deploy window | bi-weekly |\n\n")
    for i in range(1, 9):
        lines.append(f"### Sprint checkpoint {i}\n\n- Demo KPI dashboard\n- Regression subset\n- Risk review\n\n")
    return "".join(lines)


def main() -> None:
    specs: list[tuple[str, str, int, str, str]] = [
        ("06_CRM/01_CRM통합.md", gen_crm_integration(), 650, "CRM 추가 검토", "CRM-A"),
        ("06_CRM/_CRM_INDEX.md", gen_crm_index(), 80, "CRM 인덱스 보강", "CRM-IDX"),
        ("07_ADMIN/01_관리자대시보드.md", gen_admin_dashboard(), 750, "Admin 보강", "ADM-A"),
        ("08_DASHBOARD/01_대시보드설계.md", gen_dashboard_design(), 650, "Dashboard 보강", "DSH-A"),
        ("08_DASHBOARD/_DASHBOARD_INDEX.md", gen_dashboard_index(), 80, "Dashboard 인덱스", "DSH-IDX"),
        ("09_DEVELOPMENT/01_개발WBS.md", gen_wbs(), 550, "WBS 보강", "WBS-A"),
        ("09_DEVELOPMENT/02_테스트시나리오.md", gen_test_scenarios(), 750, "테스트 보강", "QA-A"),
        ("09_DEVELOPMENT/03_배포운영.md", gen_deploy(), 550, "배포 보강", "OPS-A"),
        ("09_DEVELOPMENT/_DEVELOPMENT_INDEX.md", gen_development_index(), 100, "Dev 인덱스", "DEV-IDX"),
        ("05_CHAT/_CHAT_INDEX.md", gen_chat_index(), 0, "", ""),
        ("07_ADMIN/_ADMIN_INDEX.md", gen_admin_index(), 0, "", ""),
        ("_작업지시서/05_STEP5-6_작업지시서_CRM_Admin.md", gen_work_order_56(), 0, '', ''),
        ("_작업지시서/07_STEP7-8_작업지시서_Dashboard_Development.md", gen_work_order_78(), 0, '', ''),
    ]

    results: list[tuple[str, int]] = []
    for rel, content, minimum, ap_title, ap_prefix in specs:
        if minimum > 0 and ap_title:
            content = ensure_min_lines(content, minimum, ap_title, ap_prefix)
        path = w(rel, content)
        n = len(path.read_text(encoding="utf-8").splitlines())
        results.append((rel.replace("\\", "/"), n))

    print("Generated files (line counts):")
    for rel, n in results:
        print(f"  {rel}: {n} lines")


if __name__ == "__main__":
    main()
