# PlusTok 통합 CRM (SmartTokTok CRM)

여러 홈페이지의 **상담 신청을 한 곳에서 수집·관리**하는 통합 상담관리 플랫폼입니다.
각 사이트는 상담을 **접수**만 하고, 모든 데이터는 하나의 서버(`plustok.mycafe24.com`)에 모여
통합 고객관리(CRM)로 처리됩니다.

- **시스템 명칭(내부):** PlusTok 통합 CRM
- **브랜드 명칭(외부):** SmartTokTok CRM
- **기준 서버:** `plustok.mycafe24.com` (Cafe24 웹호스팅) — 추후 `crm.smarttoktok.com` 서브도메인 연결
- **현재 단계:** V1.0 (상담 접수 API + 관리자 CRM + 통합 DB)
- **배치:** 웹 루트 `www/`에 **그누보드5(+영카트5)가 설치**되어 있고, CRM은 그와 **독립 공존**(폴더 분리·같은 `plustok` DB·자체 로그인). 그누보드 파일은 건드리지 않는다. → [PROJECT.md](PROJECT.md) 3장

> ⚠️ 이 저장소의 `*.md` 문서는 **AI 코딩 에이전트(Codex / Antigravity)를 위한 작업지시서**입니다.
> 구현 전에 반드시 [`PROMPT.md`](PROMPT.md)를 먼저 읽으세요.

---

## 1. 왜 만드나 (문제)

현재 8개 브랜드가 각각 별도 사이트로 운영되어, 상담이 사이트마다 흩어져 있습니다.
전화·문자·홈페이지 문의가 통합되지 않아 **누가 언제 무엇을 문의했는지 한눈에 볼 수 없습니다.**

→ **모든 사이트의 상담을 하나의 CRM으로 모으고, 사이트가 늘어나도 관리 프로그램은 하나만 운영**합니다.

## 2. 무엇을 만드나 (V1.0 범위)

| 구성 | 내용 |
|---|---|
| **상담 접수 API** | 각 사이트에서 JSON으로 상담을 전송받아 저장 (`POST /api/v1/consult`) |
| **상담폼(임베드)** | 사이트에 `<script>` 한 줄로 삽입, `site_code`로 브랜드·상품·질문 자동 로드 |
| **관리자 CRM** | 대시보드·고객·상담·사이트·상품·담당자·통계·설정 (`/admin/`) |
| **통합 DB** | 고객/상담/사이트/상품/담당자/이력/첨부 7개 테이블 |
| **파일 첨부** | 사업자등록증·견적서·도면 등 업로드 |

> AI 요약·답변초안(V1.5), 계약·정산 ERP(V2.0), AI 비서(V3.0), 자율 플랫폼(V4.0)은
> [`PROJECT.md`](PROJECT.md)의 로드맵 참고. **V1.0에는 포함하지 않습니다.**

## 3. 사업부 · 브랜드 · 사이트

**사업부(division) → 브랜드(brand) → 사이트(도메인, 여러 개 가능)** 3단계 구조입니다.
한 브랜드가 여러 도메인을 가질 수 있으며, `sites` 테이블에 도메인 1개 = 1행으로 등록합니다.

| 사업부 | 브랜드 | 주 도메인 | 대표 상품군 |
|---|---|---|---|
| 통신사업 | SmartTokTok | smarttoktok.com | 대표번호·070전화·기업인터넷 |
| 통신가입 | LG15441644 | lg15441644.kr | 인터넷·070·대표번호·IPTV·CCTV·결합 |
| 웹제작 | HompyShop | hompyshop.com | 홈페이지·쇼핑몰·SEO·랜딩·유지관리 |
| AI 플랫폼 | ShowForm | showform.kr | AI 랜딩페이지·설문/폼 |
| 광고플랫폼 | CallMap | callmap.kr | 플레이스·지도상위·지역광고 |
| 판촉사업 | HongPansa | hongpansa.kr | 판촉물·체험단·상위노출·홍보 |
| 중개서비스 | Oncap24 | oncap24.com | 이사·공사·역경매 |
| 플랫폼 사업 | nuguupso | nuguupso.com (준비) | 역경매(인테리어·청소·설비 등) |

## 4. 기술 스택

- **서버:** Cafe24 웹호스팅 (`plustok.mycafe24.com`)
- **언어:** PHP (서버 확정 **8.4**, 코드는 8.1+ 기준 작성) — 프레임워크 없음, 순수 PHP 라이브러리형
- **DB:** **MariaDB 10.x** (UTF-8), 접근은 **PDO Prepared Statement**만 사용
- **프론트:** HTML + Vanilla JS + CSS (모바일 우선 반응형), 상담폼은 임베드 스크립트
- **인증:** 관리자 = 세션 + `password_hash()`, 상담 API = 사이트별 API Key
- **HTTPS 전용**, 무료 SSL 적용

자세한 규칙은 [`STYLEGUIDE.md`](STYLEGUIDE.md), 디렉터리 구조는 [`PROJECT.md`](PROJECT.md) 참고.

## 5. 첫 실행 흐름 (검증 시나리오)

```
lg15441644.kr 상담폼  →  POST plustok.mycafe24.com/api/v1/consult.php
                       →  DB 저장 (customers, consults)
                       →  관리자 상담 목록에 표시
```

이 흐름이 정상 동작하면 SmartTokTok, HompyShop 등으로 순차 확대합니다.
(첫 연동 시험 사이트 = **lg15441644.kr 한 곳**)

## 6. 문서 지도

| 파일 | 용도 |
|---|---|
| [README.md](README.md) | 프로젝트 전체 설명 (이 문서) |
| [PROJECT.md](PROJECT.md) | 구조·개발 방향·로드맵·디렉터리 |
| [SPEC.md](SPEC.md) | 기능 명세 (상담폼·관리자·상태흐름) |
| [API.md](API.md) | REST API 명세 |
| [DB.md](DB.md) | DB 스키마 + DDL |
| [STYLEGUIDE.md](STYLEGUIDE.md) | 코딩·보안·디자인 규칙 |
| [TASK.md](TASK.md) | 현재 스프린트 작업지시서 (순서대로) |
| [TODO.md](TODO.md) | 할 일 체크리스트 |
| [CHANGELOG.md](CHANGELOG.md) | 변경 내역 |
| [PROMPT.md](PROMPT.md) | AI 에이전트 상시 규칙 (**먼저 읽기**) |

## 7. 접속 주소 (초기)

- 관리자: `https://plustok.mycafe24.com/admin/`
- 상담 접수 API: `https://plustok.mycafe24.com/api/v1/consult.php`
- 서버 상태 확인: `https://plustok.mycafe24.com/api/v1/health.php`
