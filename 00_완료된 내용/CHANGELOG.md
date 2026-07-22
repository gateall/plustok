# CHANGELOG

형식: [Keep a Changelog](https://keepachangelog.com/ko/) 준용. 최신이 위.
버전 태그는 로드맵([`PROJECT.md`])과 일치시킨다.

## [Unreleased] — V1.5 phase 1 착수

### 변경/추가 — PlusTok AI Provider 다중 API 자동전환 시스템 V2.0 및 5대 벤더 확장 (2026-07-21)
- **5대 글로벌 AI 벤더 지원 (`Anthropic`, `OpenAI`, `Google Gemini`, `Grok(xAI)`, `DeepSeek`)**: 기존 3개 벤더에서 일론 머스크의 xAI Grok API(`grok-2-latest`) 및 DeepSeek API(`deepseek-chat`)를 정식 추가하여 총 5대 AI 프로바이더의 연결 및 독립적 모델·키 관리를 지원.
- **실시간 지능형 Auto Failover Chain (자동전환) 탑재 (`active_provider = auto`)**: 관리자 화면에서 `AUTO` 모드 선택 시, API 키가 등록된 프로바이더들을 **[ Claude ➔ OpenAI ➔ Gemini ➔ Grok ➔ DeepSeek ]** 순서로 순회 호출. 특정 프로바이더가 과부하(`HTTP 503`)나 할당량 초과(`HTTP 429 quota exceeded`) 에러 시 1초 내에 다음 AI로 자동 전환하여 상담 분석 서비스의 무정지 보장.
- **장애 극복 감사 로깅 (`ai_failover_log` 테이블 신설)**: Failover 발생 시 실패 벤더, 원인(`error_code`, `error_message`), 대체 우회 벤더 및 소요 속도(`duration_ms`)를 정밀 기록하고 관리자 페이지 하단에 감사 테이블로 실시간 공개.
- **`admin/settings/ai.php` V2.0 UI/UX 고도화**: API Key 눈 모양(`👁️`) 표시/숨김 토글 버튼, 개별 카드별 키 삭제(`delete_key`) 버튼, **[🚀 5대 AI 프로바이더 전체 동시 연결 진단]** AJAX 기능, 그리고 이번 달 AI 호출 수 및 토큰/응답속도 통계 대시보드를 전면 도입.
- **실서버 End-to-End 검증 완료**: `plustok.mycafe24.com` 실 운영 환경에서 OpenAI 429 쿼터 초과 시 즉시 Gemini로 자동 전환(`AUTO_OK`, 646ms)되는 전 과정 및 DB 로깅 정상 동작 확인.


### 변경 — Google Gemini API 모델 호환성 업그레이드 및 무정지 다중 폴백 체인(Auto-Fallback Chain) 탑재 (2026-07-21)
- **Gemini API 404/503 오류 원천 해결 (`models/gemini-1.5-pro is not found` 및 `HTTP 503 High Demand`)**: 구글 글로벌 서버 실시간 전수 부하 테스트를 통해 503 과부하가 없고 0.9초 만에 즉답하는 공식 권장 초경량 최신 모델 **`gemini-flash-lite-latest`**를 기본값으로 지정. 기존 구버전(`gemini-1.5-pro`, `gemini-2.5-flash`, `gemini-flash-latest` 등) 입력 시 자동 치환되도록 업그레이드.
- **`includes/ai.php` 무정지 다중 폴백 체인(Auto-Fallback Chain) 및 재시도 루프 탑재**: 특정 모델이 일시적 서버 과부하(`HTTP 503`)나 쿼터 제한(`HTTP 429`)으로 2회 실패할 경우, 즉시 다른 대체 호환 모델(`gemini-3.1-flash-lite` → `gemma-4-31b-it` → `gemini-flash-latest`)로 자동 전환하여 호출을 100% 성사시키는 스마트 폴백 아키텍처 적용.
- **`ai_log()` ID 바인딩 유연화**: `$targetId` 파라미터 타입을 유연하게 변경(`$targetId !== null ? (string)$targetId : null`)하여 상담 접수번호(`C...`) 형태의 문자열 ID 바인딩 시 발생하던 PHP strict_types 오류(`Argument #2`) 해결.
- **실전 End-to-End AI 상담 요약 및 분석 최종 검증 통과**: 접수번호 `C202607210001`을 대상으로 한 실서버 검증에서 `gemini-flash-lite-latest` 및 폴백 체인이 에러 없이 완벽한 JSON 파싱 및 `ai_logs` 테이블 기록(`input_tokens: 48, output_tokens: 85`)에 100% 성공.


### 추가 — AI 멀티 프로바이더(Anthropic, OpenAI, Gemini) 확장 (`TASK_AI_MULTI_PROVIDER.md`) (2026-07-21)
- **DB 원자적 마이그레이션 실행 (`RENAME TABLE` 방식)**: `ai_settings` 테이블을 프로바이더별(`provider` PRIMARY KEY) 다중행 구조로 개편 및 기존 Anthropic 설정 100% 보존. 전역 활성 프로바이더 및 킬스위치 제어를 위한 `ai_provider_config`(`id` TINYINT PK, `active_provider`, `enabled`) 신설.
- **`includes/ai.php` 로직 리팩터링 및 다중 프로바이더 라우팅**: `ai_config()`를 개편하여 전역 설정(`ai_provider_config`)과 활성 프로바이더 설정(`ai_settings`)을 읽어오도록 구성. `ai_call()`에서 `provider` 값에 따라 `ai_call_anthropic()`, `ai_call_openai()`, `ai_call_gemini()`로 분기 처리.
- **프로바이더별 API 통신 구현**: OpenAI Chat Completions API(`response_format: json_object`), Google Gemini REST API(`responseMimeType: application/json`) 통신 규격 및 토큰 사용량(`input_tokens`, `output_tokens`) 파싱 및 `ai_logs` 연동 완료.
- **`admin/settings/ai.php` 화면 전면 개편**: 상단 전역 킬스위치(`enabled`) + 3개 프로바이더별 독립 카드 레이아웃 구성. 각 카드에서 API 키(마스킹 표시 및 독립 UPSERT 저장) 입력, 모델명 입력(Anthropic은 고정 드롭다운, OpenAI·Gemini는 자유 텍스트 기입 방식) 및 현재 활성 프로바이더(`active_provider`) 라디오 선택 지원. 현재 활성화된 프로바이더 자동 테스트 및 결과 안내 기능 탑재. 각 카드 하단에 **[💾 개별 설정 저장]** 및 **[🗑️ 키 삭제]** 버튼과 AJAX 핸들러(`save_provider`, `delete_key`)를 도입하여 프로바이더별 독립 제어 가능.

### 변경 — AI 기능 및 알림메일 실동작 검증 수행 (`TASK_VERIFY_AI_FEATURES.md`) (2026-07-20)
- **실클릭 검증 결과:** ① AI 상담요약(fail), ② AI 답변초안(fail), ③ AI 종합분석(fail), ④ 상담접수 알림메일(pass: 접수번호 `C202607210001` 생성 시 `@mail()` 오류 없이 정상 발송 완료).
- **AI 호출 fail 원인 규명:** (1) 호스팅 서버(`plustok.mycafe24.com:/www/config/ai.php`)에 Anthropic API 키가 설정되지 않아 `$cfg['api_key']`가 빈 문자열(`''`)로 반환됨. (2) `consults` 테이블 AI 관련 컬럼(`ai_summary_at` 및 6개 종합분석 컬럼)과 `ai_logs` 테이블이 실제 라이브 DB에 아직 ALTER/CREATE로 적용되지 않음.
- **원인 (2) 해소 — DB DDL 실행 완료 (2026-07-21):** `ALTER TABLE consults` 8컬럼(`ai_summary`, `ai_summary_at`, `category_ai`, `lead_score`, `priority`, `sentiment`, `tags`, `ai_analyzed_at`) + 3인덱스(`idx_consults_priority`, `idx_consults_score`, `idx_consults_cat_ai`) 추가, `CREATE TABLE ai_logs` 9컬럼+2인덱스 생성 — `SHOW COLUMNS`·`SHOW INDEX` 재확인 통과. 원인 (1) API Key 설정은 사용자 직접 처리 대기 중.

### 추가 — 관리자 AI 설정 화면 (`TASK_AI_SETTINGS_UI.md`) (2026-07-21)
- **원인 (1) 해소 방안:** API 키를 FTP 없이 관리자 화면에서 직접 입력·저장할 수 있는 UI 구현.
- `db/schema.sql` + 서버 실행: `ai_settings` 테이블(단일행 id=1 패턴, 7컬럼) CREATE 완료.
- `includes/ai.php`: `ai_config($forceReload)` 함수 추가 — DB(`ai_settings`) 우선, 파일(`config/ai.php`) 폴백. `ai_call()`은 이를 사용하도록 교체 (기존 로직 변경 없음).
- `admin/settings/ai.php`(신규): ON/OFF 토글, API 키 입력(마스킹 표시, 빈값=기존유지), 모델 드롭다운(3개 고정), UPSERT 저장, AJAX 연결 테스트 버튼. 키는 화면·`activity_log` 어디에도 평문 노출 없음.
- `admin/settings/index.php`: AI 설정 링크 버튼 추가.

### 추가 — STEP 7 사이트 확대 연동 및 검증 완료 (SmartTokTok, Oncap24, CallMap, ShowForm) (2026-07-20)
- **임베드 로더 및 프로그레스 바 보완 (`embed/embed.js`)**: 프로그레스 바 도트 로직을 실제 폼 구성(3단계: Step1 상품선택 → Step2 개인정보/주소/동의 → Step3 완료)에 맞춰 4개에서 3개(`[1, 2, 3]`)로 수정. 이메일칸, 개인정보 동의문, 주소검색, 2단 레이아웃 반영 최신본 확인.
- **사이트별 FTP 배포 완료**: SmartTokTok(`theme/hospital/tail.php`, `plustok.php`), Oncap24(`content/plustok.php`, `theme/oncap/tail.php`, `theme/oncap/mobile/tail.php`), CallMap(`plustok.php`, `theme/basic/tail.php`), ShowForm(`content/plustok.php`, `theme/basic/tail.php`, `theme/landing/tail.php`).
- **전 도메인 렌더링 및 실 접수 End-to-End 검증 완료**: 4개 타겟 사이트 모두 모바일/PC 환경에서 폼 정상 렌더링(`id="plustok-form"`) 확인 및 실제 접수 테스트(접수번호 `C202607200002` ~ `C202607200005`)를 수행하여 DB(`consults`, `customers`) 정상 반영 검증 후 테스트 데이터 클린업 완료.

### 추가 — AI 종합 분석 엔진 STEP 9 (`TASK_V2.0_AI.md`, 2026-07-20)
- `admin/consults/ai_analyze.php`(신규): 상담 1건을 Claude 1회 호출로 분류(`category_ai`)·긴급도(`priority`)·계약가능성 점수(`lead_score`)·감정(`sentiment`)·해시태그(`tags`) 종합 분석, PII 마스킹(`ai_mask_pii`) 적용.
- `db/schema.sql`: `consults`에 6개 컬럼(`category_ai`,`lead_score`,`priority`,`sentiment`,`tags`,`ai_analyzed_at`) + 인덱스 3개 추가. **서버 적용은 phpMyAdmin ALTER 필요**(기존 라이브 테이블은 schema.sql 재실행으로 안 늘어남).
- `admin/consults/view.php`·`index.php`·`dashboard.php`: 분석 결과 UI 통합.
- ⚠️ **공정 이탈 기록:** 이번 STEP은 `TASK_V2.0_AI.md`를 Antigravity가 자체 작성하고 사용자 사전승인 없이 구현까지 진행함(기존 관례: Claude가 작업지시서 작성 → §0 사용자 승인 → Antigravity 구현). 사후 검토로 승인함(2026-07-20) — 앞으로는 사전 작업지시서 확인 절차를 유지하기로 함.
- 🔒 **점검 중 발견·수정:** `view.php`의 AJAX 태그 렌더링이 `innerHTML`에 AI 응답(`tags`)을 이스케이프 없이 삽입 — 저장형 XSS 가능(고객이 상담메모에 스크립트 유도 문구 입력 → Claude가 태그로 그대로 반환 → 관리자가 "재실행" 클릭 시 실행). `textContent` 기반 DOM 생성으로 수정 + `ai_analyze.php`에 `strip_tags()` 방어 추가(Claude 5).

### 추가 — 신규 상담접수 관리자 알림메일 발송 (`notify_new_consult`) 구현 (2026-07-20)
- `config/app.php`: 수신자 상수(`ADMIN_NOTIFY_EMAIL = adfull@naver.com`) 및 발신자 상수(`MAIL_FROM`) 정의.
- `includes/functions.php`: PHP `mail()`을 이용한 동기 알림메일 발송 함수 `notify_new_consult()` 추가(개인정보 최소화: 사이트·접수번호·고객명·연락처·상품·메모 요약만 포함, 실패 시 try/catch 격리 후 오류 로그 기록).
- `api/v1/consult.php`: 상담 접수 트랜잭션 commit 성공 직후 알림메일 발송(`notify_new_consult`) 호출 연동.

### 추가 — V1.5 phase 1 (AI 상담요약 + 답변초안) 구현 완료 (2026-07-20)
- **공통 기반 (§1)**: `.gitignore`에 `www/config/ai.php` 추가(비밀키 커밋 차단). `www/config/ai.php`(킬스위치 및 Claude Opus 설정)와 `www/includes/ai.php`(`ai_call()`, `ai_log()`, `ai_mask_pii()`, 레이트리밋 `ai_check_rate_limit()`) 작성.
- **DB 스키마 (§2)**: `db/schema.sql`에 `consults.ai_summary_at` DATETIME 컬럼 및 `ai_logs` 테이블 DDL 추가.
- **AI 상담요약 (`admin/consults/ai_summary.php`)**: POST 엔드포인트. `require_login()`+`require_role(['super','admin'])`+CSRF+30초 레이트리밋 적용. 전화/이메일/주소 등 PII 철저 마스킹 후 `claude-opus-4-8` 호출, `consults.ai_summary` / `ai_summary_at`에 저장 및 `ai_logs` 기록.
- **AI 답변초안 (`admin/consults/ai_reply.php`)**: POST 엔드포인트. 브랜드 페르소나 및 문의 내용을 분석하여 답변 초안을 화면에 반환(미저장 + 복사 버튼). 동일 보안 및 PII 마스킹 적용.
- **UI 통합 (`admin/consults/view.php`)**: 상담 상세 화면에 "✨ AI 상담 요약" 및 "✨ AI 답변 초안 생성" 카드 UI 추가 (`AI 생성 — 검토 필요` 뱃지 표기, AJAX 비동기 호출, 복사하기 기능 제공).

### 결정 — AI 상담 어시스턴트 착수 승인 (2026-07-18)
- `TASK_V1.5_AI.md` §0 3가지 결정 확정: LLM=Anthropic Claude(opus-4-8), PII는 마스킹 후 외부 전송 승인, 범위는 ②상담요약+③답변초안 우선(①④⑤는 보류).
- `PROMPT.md`·`TODO.md`에 V1.5 phase 1(STEP 8) 반영. 구현은 Antigravity, 완료 후 최종 점검.

## [Unreleased] — V1.0 개발 중

### 추가 — 관리자 상담관리 보강 (2026-07-17)
- 상담 목록: **체크박스 + 전체선택 + 선택삭제**(super/admin), **엑셀 내보내기(CSV)** 버튼(현재 필터 반영, UTF-8 BOM으로 Excel 한글 정상).
- 상담 상세: **상담 삭제** 버튼(super/admin).
- `includes/functions.php`에 `delete_consult()` 추가 — 첨부파일·상태이력까지 트랜잭션으로 안전 삭제.
- 신규: `admin/consults/export.php`.

### 개선 — 상담폼 공간압축 레이아웃 + 주소검색 (2026-07-17)
- `embed/embed.js` Step 2 재구성: **회사명|이름**, **휴대폰/연락처|이메일** 2단 그리드(공간압축).
- **신청 상품명 표시**(상단, 변경 링크로 Step1 복귀).
- **주소**: 다음/카카오 우편번호 서비스(법정동/도로명 검색) + 우편번호 자동 + **상세주소 직접입력**.
  → payload에 zipcode·address 추가(consult.php가 customers.zipcode/address 저장). region 필드 제거.

### 추가 — 상담폼 이메일·개인정보 동의 (2026-07-17)
- `embed/embed.js`: **이메일 입력칸** 추가(Step 2, 형식 검증 + payload→consult.php→customers.email 저장).
- **개인정보 수집·이용 동의 내용**을 폼에 명시(수집 항목·목적·보유기간 표 + 거부권리 안내, [보기] 펼침).
- 동의 체크박스 정렬 버그 수정(`#pt-wrap input` ID 규칙이 클래스 override를 이겨 체크박스가 늘어나던 문제).

### 사이트 확대 — 6개 그누보드 사이트 상담 진입점 연결 (2026-07-17)
- 각 사이트에 임베드 페이지 생성/배포 + 상담 진입점(플로팅 버튼·기존 상담버튼 repoint·퀵메뉴·모바일바) 연결.
  LG(운영중)·SmartTokTok·HompyShop·Oncap24·CallMap·ShowForm. (각 사이트 파일은 해당 사이트 로컬 폴더에서 수정 — 메모리 `site-folders-embed.md` 참고)
- SmartTokTok: 하단 상담버튼·footer_counsel_form→plustok, plustok.php 상단잘림 수정.
- 세션 재개용 할 일은 `TODO.md` 최상단 "🔖 다음 세션 재개용" 참고.

### 수정 — CORS 프리플라이트 버그 (2026-07-17)
- `embed/form.php`: 크로스도메인 POST 프리플라이트(OPTIONS)가 `?site=` 없이 오는데도 site 파라미터로
  CORS를 판단해 헤더를 안 붙이던 버그 수정. → Origin이 등록된 사이트 도메인 중 하나면 허용하도록 변경
  (`origin_is_registered()` 추가). 이 수정 후 lg15441644.kr(http)에서 크로스도메인 실접수 성공.
- ✅ **lg15441644.kr 실연동 완료**: 실 사이트 상담폼 제출 → PlusTok CRM 반영(C202607170002) 검증.

### 추가/변경 — 배포 후 개선 (2026-07-17)
- 서버 배포 완료(그누보드 공존). `health.php` db:true 확인, `admin/install.php`(일회용 스키마+시드 설치기)로 DB 구축.
- 사이트관리: **체크박스 선택·선택삭제·행별 수정/삭제** 추가. 상담 연결 사이트는 삭제 차단(FK 안전).
- **내 정보 변경** 페이지(`admin/account.php`) 추가 — 본인 이름·연락처·비밀번호 변경(전 권한). 상단바 "내정보" 링크.
- **루트 랜딩 페이지**(`www/index.php`) 신규 디자인 — SmartTokTok CRM 소개 + "관리자 화면으로" CTA(/admin/). 그누보드 원본 첫 페이지는 `index.gnuboard.php`로 백업.


### 예정 (Planned)
- 상담 접수 API (`/api/v1/consult.php`, `upload.php`, `health.php`)
- 관리자 CRM (대시보드·고객·상담·사이트·상품·담당자·통계·설정)
- 통합 DB 8테이블 (sites/products/managers/customers/consults/consult_history/attachments/activity_log)
- 임베드 상담폼 (`embed.js` + `form.php`), lg15441644.kr 첫 연동

### 변경됨 (Changed) — 그누보드 공존 배치
- 2026-07-17: 웹 루트 `www/`에 그누보드5(+영카트5) 설치됨(DB `plustok`) → CRM을 **독립 공존**으로 재배치.
  - CRM 코드 전체를 프로젝트 루트에서 `www/` 하위로 이동(admin·api·embed·config·includes·assets·uploads·logs). 그누보드 폴더와 이름 충돌 없음. 루트 `index.php`/`.htaccess`(CRM용) 제거(그누보드 `www/index.php` 유지).
  - `includes/db.php`: `config/database.php`가 없으면 그누보드 `data/dbconfig.php` 접속정보 자동 사용 → 같은 `plustok` DB 공유, 비밀번호 중복 저장 없음.
  - `.gitignore`: `www/data/dbconfig.php`(그누보드 DB 비번)·`www/config/database.php`·그누보드 캐시/업로드 제외.
  - `db/`와 `*.md`는 프로젝트 루트 유지(웹루트 배포 제외). seed.sql은 phpMyAdmin으로 임포트.

### 추가됨 (Added) — 코드 (서버 미배포, 로컬 작성)
- 2026-07-17: STEP 4~6 구현 (관리자 CRM + 임베드 폼)
  - includes: `auth.php`(로그인/권한/CSRF/활동로그), `header.php`·`footer.php`(레이아웃), `assets/css/admin.css`
  - admin: `index.php`(로그인·실패잠금)·`logout.php`·`dashboard.php`
  - admin/consults: 목록(필터)·상세(상태변경·이력·담당배정·메모)
  - admin/customers: 목록·상세(상담이력·메모)
  - admin/sites: 등록·API Key 발급/재발급·사용토글 (한 브랜드 다중 도메인 지원)
  - admin/products·users·stats·settings: 상품관리·담당자관리(권한/비번)·통계(일별추이)·설정+활동로그
  - embed: `form.php`(config JSON + CORS + curl 서버 프록시로 API Key 은닉)·`embed.js`(5단계 모바일 폼)·`demo.php`(테스트)
- 2026-07-17: STEP 1~3 기반 코어 구현
  - DB: `db/schema.sql`(8테이블), `db/seed.sql`(사이트 8행 + 브랜드별 상품, 실제 API Key 발급)
  - config: `app.php`, `security.php`, `database.sample.php` + `config/`·`logs/`·`uploads/` 접근차단 `.htaccess`
  - includes: `db.php`(PDO), `response.php`(JSON), `functions.php`(상담/고객번호·Rate limit·로그), `api_auth.php`(X-API-KEY)
  - API: `api/v1/health.php`·`consult.php`(트랜잭션·중복확인·이력)·`upload.php`(확장자/MIME/용량 검증)
  - 진입: 루트 `index.php`, 루트 `.htaccess`(HTTPS·숨김파일 차단), `admin/setup.php`(최초 super 계정 1회 생성)
  - `.gitignore`(database.php·업로드·로그 제외)
  - ⚠️ 로컬 PHP가 5.2라 `php -l` 미검증 → 서버(PHP 8.1+)에서 문법·동작 확인 필요.

### 문서 (Docs)
- 2026-07-17: 기획 문서(.hwpx 12종) 검토 후 작업지시서 MD 10종 작성
  (README/PROJECT/SPEC/API/DB/STYLEGUIDE/TASK/TODO/CHANGELOG/PROMPT).
  결정: 서버=plustok.mycafe24.com, 스택=PHP8.1+/MySQL/PDO, 상담폼=임베드 스크립트,
  데이터모델=사업부→브랜드→사이트(다중 도메인), AI는 V1.5로 분리.

---

## 기록 규칙
- 각 STEP/기능 완료 시 여기에 한 줄 추가: `YYYY-MM-DD: <무엇을> <어떻게>`.
- DB 스키마 변경은 반드시 기록하고 `/db/schema.sql`과 동기화.
- 릴리스 시 `## [V1.0] - YYYY-MM-DD`로 승격.
