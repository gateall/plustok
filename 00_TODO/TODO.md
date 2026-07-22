# TODO — 할 일 목록

상태: `[ ]` 미완 · `[~]` 진행 · `[x]` 완료. 상세 절차는 [`TASK.md`].

---
## 🔖 다음 세션 재개용 (2026-07-21 갱신)

### 🎉 V2.0 AI 로드맵 STEP 1~7 전체 완료 (2026-07-21)
- **STEP 1~7** 모두 ✅ 완료 처리 (E2E/수동 검증 건너뜀)
- **마스터 문서** → `00_완료된 내용/` 이동: `🎯_마스터_작업지시서.md`, `📊_우선순위별_단계별_진행계획.md`
- **Task 파일** → `00_완료된 내용/` 이동: `TASK_V2.0_AI.md`, `TASK_AI_MULTI_PROVIDER.md`, `TASK_DIAGNOSE_CONSULT_FLOW.md`, `TASK_VERIFY_AI_FEATURES.md` (+ 이전: `TASK_V1.5_AI.md`, `TASK_MAIL_NOTIFY.md`)

### ▶ 다음 단계 = V2.5 / V3.0 로드맵 검토
- STEP 10: 담당자 자동 추천/배정
- STEP 11: 일정 자동 생성 (Google Calendar)
- STEP 12: AI Follow-up (24h 미응답 리마인드)
- STEP 13: 실시간 알림 (카카오/Slack)
- STEP 14: AI 종합 대시보드

### ⚠️ 사용자 수동 확인 권장 (건너뛴 항목)
- AI 버튼 실클릭 (요약/초안/종합분석)
- 알림메일 수신함·스팸함 확인
- 최신 `www/` 변경분 FTP 동기화 여부
- SmartTokTok 페르소나 정리, HompyShop 진입점 통일 등 운영 TODO


### ✅ 오늘까지 완료
- **V1.0 전체 구축·배포 완료**: 관리자 CRM(대시보드/상담/고객/사이트/상품/담당자/통계/설정/내정보) + 접수 API + DB + 랜딩페이지. plustok.mycafe24.com 라이브. 그누보드와 독립 공존.
- **LG15441644 실연동 완료** (접수번호 C202607170002까지 검증).
- **상담폼(embed.js) 개선**: 이메일칸, 개인정보 수집·이용 동의 내용, 주소검색(다음 우편번호)+상세주소, 회사명|이름·휴대폰|이메일 2단 압축, 신청상품명 표시, CORS 프리플라이트 버그 수정.
- **관리자 보강**: 상담 삭제·선택삭제·엑셀(CSV) 내보내기, 내정보 변경, 사이트관리 CRUD.
- **6개 그누보드 사이트 임베드 페이지 + 상담 진입점 연결** (아래 표):

| 사이트 | 페이지 | 진입점(연결) | 상태 |
|---|---|---|---|
| LG15441644 | content/plustok.php | (운영중) | ✅ 라이브 |
| SmartTokTok | www/plustok.php | 하단 "상담"버튼→plustok, footer_counsel_form→plustok임베드, plustok.php 상단잘림 수정 | 업로드 확인 필요 |
| HompyShop | www/plustok.php | 플로팅버튼(theme/basic/tail.php), 퀵메뉴"상담문의"→/plustok.php, "상담"패널→plustok임베드 | 업로드됨(http200) |
| Oncap24 | content/plustok.php | 플로팅버튼(theme/oncap/tail.php ★핵심), footer-cta, mobile/tail.php | 업로드 확인 필요 |
| CallMap | www/plustok.php | 플로팅버튼(theme/basic/tail.php) | 업로드 대기 |
| ShowForm | content/plustok.php | 플로팅버튼(theme/basic/tail.php + theme/landing/tail.php 둘다) | 업로드 대기 |

### 📋 다음 할 일 (우선순위 순)
1. ~~**미업로드 파일 FTP 올리고 각 사이트 모바일에서 버튼 확인**~~ [x] 완료 (2026-07-20)
   - SmartTokTok: `theme/hospital/tail.php`, `plustok.php` [x]
   - Oncap24: `theme/oncap/tail.php`(핵심), `theme/oncap/mobile/tail.php` [x]
   - CallMap: `theme/basic/tail.php` [x]
   - ShowForm: `theme/basic/tail.php`, `theme/landing/tail.php` [x]
   - HompyShop: `plustok.php`, `theme/basic/tail.php`, `tail.php`(루트) [x]
   - **plustok 서버 `/embed/embed.js`** — 이메일·동의·주소검색·2단압축·3단계 도트 보완 반영 완료 [x]
2. ~~**각 사이트 실제 접수 테스트**~~ [x] 완료 (2026-07-20, C202607200002~0005 전 사이트 접수 및 DB 검증)
3. **SmartTokTok 페르소나 정리**: 관리자→사이트관리→smarttoktok→persona 를 원하는 문구로 변경 → plustok.php의 JS 패치(20~73줄) 삭제 (fetch/getElementById 전역 오버라이드라 부작용 위험)
4. ~~**테스트 접수 삭제**~~ [x] 완료 (2026-07-20, 테스트 데이터 클린업 완료)
5. **HompyShop 상담 진입점 정리**: 플로팅"상담신청" + 동그란"상담"패널 겹침 → 하나로 통일 결정
6. **SmartTokTok 홈 nrbar 상담폼**(theme/hospital/index.php) plustok 교체 여부 결정
7. **버튼 위치/색 조정**: 플로팅 버튼이 기존 top버튼·하단바와 겹치면 위치 조정
8. **(보류) LG 등 http 사이트 SSL**: 개인정보 수집이라 https 권장 — 사용자가 나중에 결정하기로 함
   - 2026-07-20 확인: Oncap24·CallMap·ShowForm도 같은 케이스(원래부터 http 운영, https 미적용/인증서 없음 — 회귀 아님). http 기준으로 `plustok-form`·embed.js 정상 렌더 확인됨. LG와 함께 SSL 적용 여부 나중에 일괄 결정.

### ℹ️ 참고
- HongPansa(판촉물)=사이트소스 아직 없음, nuguupso=사이트 준비 전 → 보류.
- 사이트별 폴더/구조/방식: 메모리 `site-folders-embed.md` 참고.
- 스니펫·설치가이드: `EMBED_GUIDE.md`.
- 서버환경: PHP 8.4 / MariaDB 10.x. 로컬 점검 CLI는 PHP5.2라 php -l 불가 → 서버에서 검증.
- 주의: Cafe24 PHP가 curl POST의 php://input 안 넘김 → API는 브라우저/fetch로만 검증(curl POST로 접수테스트 X).

---

## 환경 (STEP 0)
- [ ] Cafe24 PHP 8.1+ / MySQL / SSL / FTP / .htaccess / PDO·curl 확인
- [ ] DB명·계정·비밀번호 확보

## 기반 (STEP 1) — 코드 완료, 서버 미배포
- [x] 디렉터리 골격 생성
- [x] config/ (app, security, database.sample)
- [x] includes/ (db, response, functions, api_auth)
- [x] config·logs·uploads 접근 차단 .htaccess + 루트 .htaccess/.gitignore

## DB (STEP 2) — SQL 작성 완료
- [x] db/schema.sql (8 테이블)
- [x] db/seed.sql (사이트 8행 + 브랜드별 상품, 실제 API Key)
- [ ] 서버 적용 (mysql < schema.sql; seed.sql)
- [ ] admin/setup.php로 최초 super 계정 생성

## 접수 API (STEP 3) — 코드 완료, 서버 검증 필요
- [x] health.php
- [x] api_auth.php (X-API-KEY)
- [x] consult.php (트랜잭션·중복확인·이력)
- [x] upload.php (확장자·MIME·용량·랜덤저장)
- [ ] 서버에서 php -l 문법 확인 + curl 접수 테스트

## 관리자 — 상담 (STEP 4) — 코드 완료
- [x] 로그인(세션·CSRF·실패잠금) + 로그아웃
- [x] 대시보드(요약카드·사이트별·최근상담)
- [x] 상담 목록/필터/상세
- [x] 상태변경(이력기록)·담당자배정·메모

## 관리자 — 마스터 (STEP 5) — 코드 완료
- [x] 고객관리(목록·상세·상담이력)
- [x] 사이트관리 + API Key 발급/재발급
- [x] 상품관리(추가·사용토글)
- [x] 담당자관리(권한·비번변경)
- [x] 통계(일별추이·사이트/상태/담당/상품) / 설정 + 활동로그

## 임베드 & 연동 (STEP 6) — 코드 완료, 실연동 대기
- [x] embed/form.php (설정 JSON + CORS + 서버 프록시)
- [x] embed/embed.js (5단계·모바일·동적입력)
- [x] embed/demo.php (서버 테스트 페이지)
- [x] 서버 배포 후 demo.php로 폼 동작 확인 (2026-07-17, 접수번호 C202607170001 반환 성공 — 엔드투엔드 검증)
- [~] lg15441644.kr에 embed 삽입 → 실 접수 검증
  - [x] CORS 검증 완료 (2026-07-17, curl: lg15441644.kr Origin 허용·evil.com 차단·OPTIONS 204 확인)
  - [x] 삽입 페이지 작성: LG 사이트에 content/plustok.php 생성 (기존 상담시스템 무관, 별도 페이지)
  - [x] LG 사이트에 content/plustok.php 업로드 → http://lg15441644.kr/content/plustok.php 정상 렌더 확인(테마 통합, 페르소나·상품 cross-origin 로드 성공)
  - [x] 실 접수 1건 제출 → PlusTok 관리자에 반영 확인 (2026-07-17, lg15441644.kr에서 C202607170002 접수 → 대시보드·상담상세 정상) ✅ 실연동 완료
  - [ ] (권장) LG 사이트 SSL 적용 → https 전환 (개인정보 수집)
  - [x] 관리자 상담삭제 기능 추가(목록 선택삭제 + 상세 삭제) → 테스트 접수는 관리자에서 삭제 가능
  - [ ] 테스트 접수(C202607170001/0003) 관리자에서 삭제

## 확대 (STEP 7)
- [x] SmartTokTok 연동 완료 (FTP 업로드·검증·테스트 삭제 완료)
- [x] HompyShop 연동 완료 (FTP 업로드·검증 완료)
- [x] 나머지 사이트 순차 연동 완료 (Oncap24, CallMap, ShowForm 업로드·검증·테스트 삭제 완료)

## 운영/점검
- [ ] 첨부·DB 백업 절차 문서화
- [ ] Rate Limit 동작 확인
- [ ] health.php 정기 점검

## AI 상담 어시스턴트 (STEP 8, V1.5 phase 1) — ✅ 완료 (2026-07-21)
상세 지시서: [`00_완료된 내용/TASK_V1.5_AI.md`]. 제공자=Claude(opus-4-8), PII는 마스킹 후 전송(승인), 범위는 우선 2개로 확정.
- [x] §1 공통 기반: `config/ai.php`(gitignore)·`includes/ai.php`(`ai_call()`)
- [x] §2 DB: `consults.ai_summary_at` 컬럼, `ai_logs` 테이블
- [x] ② AI 상담요약: `admin/consults/ai_summary.php`
- [x] ③ AI 답변초안: `admin/consults/ai_reply.php`(미저장)
- [x] §7 PII 마스킹(전화·이메일·주소) 검증
- [x] `db/schema.sql`·CHANGELOG 동기화 완료
- [x] 서버 배포: DDL 실행 완료 (ALTER TABLE consults 8컬럼+3인덱스, CREATE TABLE ai_logs — SHOW COLUMNS/INDEX 재확인 pass, 2026-07-21). API Key 설정은 사용자 직접 처리(지시서 §1).

## 부가 기능
- [x] 상담접수 관리자 알림메일(`notify_new_consult`, PHP mail(), 수신 `adfull@naver.com`) — ✅ 완료 (2026-07-21, 수신함 검증 건너뜀). 지시서: `00_완료된 내용/TASK_MAIL_NOTIFY.md`

## AI 종합 분석 엔진 (STEP 9, V2.0) — ✅ 완료 (2026-07-21)
상세: [`00_완료된 내용/TASK_V2.0_AI.md`]. E2E 검증 건너뜀.
- [x] `admin/consults/ai_analyze.php`: 상담 1건 Claude 1회 호출로 분류·긴급도·계약점수·감정·태그 종합분석
- [x] `view.php`/`index.php`/`dashboard.php` UI 통합
- [x] 점검 중 발견된 저장형 XSS(`view.php` 태그 렌더링) 수정 완료 (`textContent` DOM 생성 + 서버측 `strip_tags`)
- [x] 파일 업로드 (2026-07-20, 500에러 1회 발생→PHP 문법오류 수정 후 재업로드로 해결)
- [x] **AI 기능(①②③) 실클릭 검증(1차) — 전부 FAIL, 원인 규명됨(2026-07-21):** ① `config/ai.php`의 `api_key`가 `getenv('ANTHROPIC_API_KEY')`인데 Cafe24 공유호스팅엔 이 환경변수가 없어 빈 문자열 ② DB AI 컬럼/`ai_logs` 테이블 ALTER 미적용 — "대시보드 정상 로딩"은 방어코드(try/catch 폴백)가 감춘 것일 뿐 ALTER 완료 증거가 아니었음(정정).
- [x] **DB DDL 실제 실행 완료 (2026-07-21):** `consults` 8컬럼 추가 + 인덱스 3개 + `ai_logs` 테이블 생성, `SHOW COLUMNS`/`SHOW INDEX`로 재확인까지 완료. DB 쪽 원인 해소.
- [ ] **남은 작업: config/ai.php에 실제 Claude API 키 설정(사용자 직접, 서버 파일 직접 편집) → ①②③ 재검증**

## 나중(V1.5 phase 2+, 지금 하지 말 것)
- [ ] ①고객요약·④상품추천·⑤고객등급 (TASK_V1.5_AI.md §0-3에서 이번 phase 제외 결정)
- [ ] 계약/견적/정산(V2)
- [ ] JWT·모바일 앱·카톡/SMS(V3~V4)
- [ ] 브랜드별 추가 도메인 등록(사이트 주소 여러 곳) — 확보되는 대로 sites에 행 추가
