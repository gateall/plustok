# PROJECT — 구조 및 개발 방향

## 1. 아키텍처 원칙

1. **각 홈페이지는 "상담 접수"에만 집중한다.** 자체 DB를 두지 않는다.
2. **모든 데이터는 PlusTok CRM(`plustok.mycafe24.com`)으로 실시간 전송**한다.
3. **DB와 (향후) AI는 중앙 서버에서 통합 관리**한다.
4. **관리자는 하나의 CRM 화면만 사용**하여 모든 브랜드/사이트를 운영한다.
5. **새 사이트 추가 = API 연동(사이트 등록 + API Key 발급)만** 하면 된다. 관리자 프로그램은 하나만 유지한다.
6. **Cafe24에서 먼저 안정화**하고, 트래픽·AI 사용이 커지면 소스 그대로 VPS로 이전한다.
   (그래서 Cafe24 전용 확장기능에 종속되지 않게 순수 PHP + PDO로만 작성한다.)

```
smarttoktok.com ─┐
lg15441644.kr  ──┤
hompyshop.com  ──┤   상담폼(임베드)     ┌─────────────────────────┐
showform.kr    ──┼──  POST /consult ──▶ │  plustok.mycafe24.com   │
callmap.kr     ──┤                      │  ├─ /api/v1  (접수 API)  │
hongpansa.kr   ──┤                      │  ├─ /admin   (CRM)       │
oncap24.com    ──┤                      │  └─ MySQL 통합 DB        │
nuguupso.com   ──┘                      └─────────────────────────┘
                                                     │
                                              관리자 / 담당자
```

## 2. 데이터 모델 계층 (사업부 → 브랜드 → 사이트)

- **사업부(division):** 통신사업 / 통신가입 / 웹제작 / AI 플랫폼 / 광고플랫폼 / 판촉사업 / 중개서비스 / 플랫폼 사업
- **브랜드(brand):** SmartTokTok / LG15441644 / HompyShop / ShowForm / CallMap / HongPansa / Oncap24 / nuguupso
- **사이트(site = 도메인):** 브랜드당 **여러 도메인 가능**. `sites` 테이블에 도메인 1개 = 1행.
  - `site_code`는 도메인마다 고유. 추가 도메인은 `브랜드코드_b`, `_c` 식으로 부여(예: `lg15441644`, `lg15441644_b`).
  - 상담폼은 `site_code`로 브랜드·상품·질문셋·페르소나 설정을 로드한다.

> V1.0에서 division/brand는 `sites` 테이블의 컬럼(문자열)으로 단순 보관한다.
> 별도 정규화 테이블(divisions/brands)은 V2.0에서 필요 시 분리한다.

## 3. 디렉터리 구조 (Cafe24 웹 루트 `www/`, 그누보드5와 독립 공존)

`www/`가 웹 루트이며 **그누보드5(+영카트5)가 이미 설치**되어 있다. CRM은 그누보드와
**폴더명이 겹치지 않는** 하위 폴더로 나란히 둔다(독립 공존). 그누보드 파일은 건드리지 않는다.

```
www/                          ← Cafe24 웹 루트
│
├── index.php  common.php  adm/  bbs/  shop/  data/  skin/  theme/  plugin/ ...   ← 그누보드5 (그대로)
│   └── data/dbconfig.php     그누보드 DB 접속정보 (CRM도 이 DB=plustok 공유)  ⛔ git 제외
│
├── admin/                    ★ CRM 관리자 (그누보드 adm/ 과 별개)
│   ├── index.php  logout.php  setup.php  dashboard.php
│   └── consults/ customers/ sites/ products/ users/ stats/ settings/
│
├── api/v1/                   ★ 상담 접수 API — consult.php  upload.php  health.php
│
├── embed/                    ★ 상담폼 — embed.js  form.php(설정JSON+CORS+프록시)  demo.php
│
├── config/                   ⛔ 접근 차단 — app.php  security.php  database.sample.php
│   └── (database.php)         선택. 없으면 그누보드 dbconfig.php 자동 사용
│
├── includes/                 db·auth·api_auth·functions·response·header·footer
├── assets/css/admin.css      ★ CRM 관리자 스타일 (그누보드 css/ 와 별개)
├── uploads/                  ⛔ PHP 실행 차단 (consult/ contract/ temp/)
└── logs/                     ⛔ 접근 차단

(프로젝트 루트 = 웹루트 아님, 배포 제외)
├── db/schema.sql  db/seed.sql   ← phpMyAdmin으로 임포트만
└── *.md                        ← 작업지시서
```

**DB 공유:** CRM 전용 테이블(customers/consults/sites/products/managers/…)을 그누보드와 **같은
`plustok` DB**에 둔다. 그누보드는 `g5_`·`yc5_` 접두사, CRM 테이블은 접두사 없음 → 충돌 없음.
`includes/db.php`는 `config/database.php`가 없으면 그누보드 `data/dbconfig.php`의 접속정보를 자동 사용한다.

**접근 경로:** CRM 관리자 = `/admin/` (그누보드 관리자 `/adm/`와 별개), API = `/api/v1/`, 폼 = `/embed/`.

**보안 필수:** `config/`, `logs/`, `uploads/`는 `.htaccess`로 접근·실행을 차단한다.
`www/data/dbconfig.php`(그누보드 DB 비밀번호)와 `www/config/database.php`는 **절대 git 커밋 금지**. 상세는 [`STYLEGUIDE.md`](STYLEGUIDE.md).

## 4. 버전 로드맵

| 버전 | 범위 | 상태 |
|---|---|---|
| **V1.0** | 상담 접수 API + 통합 CRM(고객/상담/사이트/상품/담당자) + DB + 파일첨부 | **← 현재** |
| V1.5 | AI 상담 요약 · AI 답변 초안 · AI 고객 분류 (OpenAI API) | 예정 |
| V2.0 | 계약관리 · 견적 · 설치 일정 · 매출/정산 · 세금계산서 (ERP) | 예정 |
| V3.0 | AI 업무비서 · 영업 추천 · 고객 분석 · 리포트 자동 생성 | 예정 |
| V4.0 | 카카오톡/문자 자동 발송 · STT 녹취 분석 · 모바일 관리자 앱(PWA) · 외부 ERP 연동 | 구상 |

각 버전은 **DB 스키마를 확장 가능하게** 설계한다. V1.0의 `consults.ai_summary`, `ai_logs`(예약),
`contracts`(예약) 등 자리는 미리 열어두되 로직은 구현하지 않는다.

## 5. 개발 순서(요약)

1. Cafe24 환경 확인(PHP 8.1+, MySQL, SSL, `.htaccess`, PDO/curl)
2. DB 생성 → 스키마 적용([`DB.md`](DB.md))
3. `config/`, `includes/` 공통 모듈
4. `api/v1/health.php` → `consult.php` → `upload.php`
5. 관리자 로그인 → 상담 목록/상세 → 사이트/상품/담당자
6. 임베드 상담폼(`embed.js` + `form.php`)으로 lg15441644.kr 시험 연동
7. 나머지 사이트 순차 확대

상세 단계별 지시는 [`TASK.md`](TASK.md).

## 6. 명칭 규칙

- 내부 시스템명: **PlusTok 통합 CRM**
- 외부 브랜드명: **SmartTokTok CRM**
- API 베이스: `https://plustok.mycafe24.com/api/v1/` (추후 `https://crm.smarttoktok.com/api/v1/`)
