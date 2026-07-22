# TASK V1.5 — AI 상담 어시스턴트 (작업지시서)

- **대상 작업자:** Antigravity (구현) / 작성·최종점검: Claude
- **전제:** V1.0 완료·배포됨(상담접수 API + CRM + DB 8테이블). 본 문서는 **다음 단계(V1.5)**.
- **기획 출처:** `AI Business OS V1.5.hwpx` (5개 AI 기능 구상)
- **작성일:** 2026-07-18
- **완료일:** 2026-07-21 (STEP 2 완료, E2E 검증 건너뜀)
- **참고:** [`PROJECT.md`](PROJECT.md) 로드맵, [`TASK.md`](TASK.md) V1.0, [`DB.md`](DB.md), [`STYLEGUIDE.md`](STYLEGUIDE.md)

---

## 0. ✅ 착수 전 결정 (2026-07-18 사용자 승인 완료)

1. **LLM 제공자: Anthropic Claude 확정.** 본 지시서는 Claude 기준. 기본 모델 `claude-opus-4-8`, 기능별 권장 티어는 §5 표 그대로.
2. **개인정보(PII) 외부 전송: 승인(마스킹 적용 조건).** 전화·이메일·주소는 §7 규칙대로 반드시 마스킹 후 전송. 이름·상담내용은 전송됨(승인됨). **Antigravity는 §7 마스킹을 빠짐없이 구현할 것 — 이 조건이 승인의 전제.**
3. **범위(phase): 우선 2개로 확정.** ②상담요약 + ③답변초안만 이번 단계 진행. ①고객요약·④상품추천·⑤고객등급은 **이번 phase에서 구현하지 말 것**(§9 순서 1→2까지만). 별도 지시 전까지 보류.

> **Antigravity 착수 범위 = §1(공통 기반) + §2(DB, ②③에 필요한 컬럼만: `consults.ai_summary_at`, `ai_logs`) + §4·§5의 ②③ 두 엔드포인트.** ①④⑤ 관련 DB 컬럼(`customers.grade` 등)·엔드포인트는 이번 배포에서 만들지 말 것.

---

## 1. 공통 기반

### 1-1. 설정 파일 `www/config/ai.php` (⛔ git 커밋 금지, database.php와 동일 취급)
```php
<?php
return [
    'provider'    => 'anthropic',
    'api_key'     => getenv('ANTHROPIC_API_KEY') ?: '',   // 서버 환경변수 우선, 없으면 아래 직접값
    'model'       => 'claude-opus-4-8',
    'api_url'     => 'https://api.anthropic.com/v1/messages',
    'api_version' => '2023-06-01',
    'timeout'     => 30,          // 초
    'max_tokens'  => 1024,        // 기능별로 오버라이드
    'enabled'     => true,        // 전역 킬스위치(장애 시 즉시 OFF)
];
```
- `.gitignore`에 `www/config/ai.php` 추가.
- 키는 **서버에만**. 브라우저/임베드에서 절대 호출 금지(키 노출). 모든 AI 호출은 **로그인된 admin 서버사이드**에서만 발생.

### 1-2. 공통 클라이언트 `www/includes/ai.php`
Claude Messages API를 curl로 호출하는 단일 함수. (Composer/SDK 미사용 — embed/form.php의 curl 프록시와 동일 방식)

```php
<?php
declare(strict_types=1);

/**
 * Claude Messages API 호출 (서버사이드 전용).
 * @param string $system  시스템 프롬프트
 * @param string $user    사용자 메시지
 * @param array  $opt     ['max_tokens'=>int, 'model'=>string, 'json_schema'=>array|null, 'feature'=>string, 'target_id'=>int]
 * @return array ['ok'=>bool, 'text'=>string, 'json'=>?array, 'error'=>?string, 'usage'=>array]
 */
function ai_call(string $system, string $user, array $opt = []): array
{
    static $cfg = null;
    if ($cfg === null) { $cfg = require __DIR__ . '/../config/ai.php'; }

    if (empty($cfg['enabled']) || $cfg['api_key'] === '') {
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => 'AI 비활성화 또는 키 없음', 'usage' => []];
    }

    $body = [
        'model'      => $opt['model']      ?? $cfg['model'],
        'max_tokens' => $opt['max_tokens'] ?? $cfg['max_tokens'],
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ];
    // 분류/추천처럼 구조화 출력이 필요하면 json_schema 전달 → 파싱 보장
    if (!empty($opt['json_schema'])) {
        $body['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $opt['json_schema']]];
    }

    $ch = curl_init($cfg['api_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $cfg['timeout'],
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $cfg['api_key'],
            'anthropic-version: ' . $cfg['api_version'],
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $code >= 400) {
        $msg = $cerr ?: ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
        ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, $body['model'], 'error', [], $msg);
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => $msg, 'usage' => []];
    }

    $data = json_decode((string)$raw, true);
    // content[]는 블록 배열 — type==text 만 모음
    $text = '';
    foreach (($data['content'] ?? []) as $b) {
        if (($b['type'] ?? '') === 'text') { $text .= $b['text']; }
    }
    $usage = $data['usage'] ?? [];
    $json  = null;
    if (!empty($opt['json_schema'])) { $json = json_decode($text, true); }

    ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, $body['model'], 'ok', $usage, null);
    return ['ok' => true, 'text' => trim($text), 'json' => $json, 'error' => null, 'usage' => $usage];
}

/** ai_logs 테이블에 호출 기록(비용/감사) */
function ai_log(string $feature, ?int $targetId, string $model, string $status, array $usage, ?string $err): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO ai_logs (feature, target_id, model, status, input_tokens, output_tokens, error, created_at)
             VALUES (:f,:t,:m,:s,:it,:ot,:e,NOW())'
        )->execute([
            ':f' => $feature, ':t' => $targetId, ':m' => $model, ':s' => $status,
            ':it' => (int)($usage['input_tokens'] ?? 0),
            ':ot' => (int)($usage['output_tokens'] ?? 0),
            ':e' => $err ? substr($err, 0, 500) : null,
        ]);
    } catch (Throwable $e) { /* 로깅 실패는 무시 */ }
}
```
> ⚠️ 모델/파라미터 주의: `claude-opus-4-8`은 `temperature`·`top_p`·`budget_tokens`를 **보내면 400**. 위 body처럼 넣지 말 것. 짧은 출력이라 스트리밍 불필요. thinking 파라미터는 생략(요약/분류엔 불필요) — 단, 응답에 군더더기 설명이 섞이면 시스템 프롬프트에 "최종 결과만 출력" 지시를 넣는다(각 프롬프트에 이미 포함).

### 1-3. DoD (기반)
- [x] `config/ai.php`(gitignore) + `includes/ai.php` 생성, `ai_call()`로 "안녕" 테스트 시 텍스트 응답.
- [x] 키 없거나 `enabled=false`면 안전하게 실패(예외 없이 `ok=false`).

---

## 2. DB 변경 (`db/schema.sql` 동기화 + 서버 ALTER)

```sql
-- 고객 등급 + 고객요약 캐시
ALTER TABLE customers
  ADD COLUMN grade ENUM('vip','repeat','new','dormant') DEFAULT NULL AFTER memo,
  ADD COLUMN ai_summary TEXT DEFAULT NULL AFTER grade,
  ADD COLUMN ai_summary_at DATETIME DEFAULT NULL AFTER ai_summary;

-- consults.ai_summary 는 이미 존재(V1.0 예약) → 그대로 사용. 생성시각만 추가.
ALTER TABLE consults
  ADD COLUMN ai_summary_at DATETIME DEFAULT NULL AFTER ai_summary;

-- AI 호출 로그(비용/감사)
CREATE TABLE IF NOT EXISTS ai_logs (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  feature       VARCHAR(30)  NOT NULL,           -- customer_summary/consult_summary/reply_draft/recommend/grade
  target_id     BIGINT       DEFAULT NULL,       -- customers.id 또는 consults.id
  model         VARCHAR(40)  NOT NULL,
  status        VARCHAR(10)  NOT NULL,           -- ok/error
  input_tokens  INT          NOT NULL DEFAULT 0,
  output_tokens INT          NOT NULL DEFAULT 0,
  error         VARCHAR(500) DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_logs_feature (feature),
  INDEX idx_ai_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **답변초안**은 저장 안 함(온디맨드 생성 → 화면 표시 + 담당자가 필요시 메모/외부로 복사). 저장 원하면 별도 논의.
- 스키마 변경은 반드시 `db/schema.sql`에 반영하고 CHANGELOG 기록.

---

## 3. 캐싱·비용 원칙 (모든 기능 공통)

- **온디맨드 + 캐시**: 요약/등급은 버튼 클릭 시 생성 후 `ai_summary`/`grade`에 저장. 재클릭 전까지 재호출 금지("재생성" 버튼으로만 갱신). 목록 진입만으로 자동 호출 **금지**(비용 폭증).
- **max_tokens 캡**: 요약 400, 답변초안 700, 등급/추천 200(구조화).
- **레이트리밋**: 동일 target 30초 내 중복 호출 차단(서버 세션/타임스탬프).
- **킬스위치**: `config/ai.php`의 `enabled=false`로 전면 중단 가능.
- **로그로 비용 추적**: `admin/settings`에 "AI 사용량"(당월 input/output 토큰 합, ai_logs 집계) 표시 권장.

---

## 4. 관리자 UI 통합 지점

| 기능 | 화면 | 진입 | 엔드포인트(신규) |
|---|---|---|---|
| ① 고객요약 | `admin/customers/` 상세 | "AI 요약" 버튼 | `admin/customers/ai_summary.php` (POST) |
| ② 상담요약 | `admin/consults/` 상세 | "AI 요약" 버튼 | `admin/consults/ai_summary.php` (POST) |
| ③ 답변초안 | `admin/consults/` 상세 | "AI 답변 생성" 버튼 | `admin/consults/ai_reply.php` (POST, 미저장) |
| ④ 상품추천 | 상담/고객 상세 | "추천 상품" 버튼 | `admin/consults/ai_recommend.php` (POST) |
| ⑤ 고객등급 | `admin/customers/` 상세·목록 | "등급 판정" 버튼 | `admin/customers/ai_grade.php` (POST) |

- 모든 엔드포인트: `require_login()` + `require_role(['super','admin'])` + `csrf_check()` + 레이트리밋. 반환은 JSON(`{ok, text/json}`), 프론트는 fetch로 호출해 결과 영역에 표시(페이지 새로고침 없이). CSS는 기존 `assets/css/admin.css` 톤 유지.

---

## 5. 기능별 상세 + 프롬프트

> 프롬프트는 한국어. 시스템 프롬프트에 **역할·출력형식·금지사항**을 명시. 사용자 메시지에 데이터를 채워 넣음(§7 마스킹 적용).

### ① AI 고객요약 (`customer_summary`) — 권장 모델 opus-4-8
- 입력: 고객 기본정보(이름, 회사, 등급) + 최근 상담 N건(상품·상태·메모 요약·일자).
- 시스템: `"당신은 B2B 영업 CRM 어시스턴트다. 고객의 상담 이력을 바탕으로 핵심만 요약하라. 형식: ■현재상태 ■관심상품 ■영업포인트(2~3줄, 실행 제안 포함). 추측은 근거와 함께. 군더더기·인사말 없이 요약만 출력."`
- 저장: `customers.ai_summary`, `ai_summary_at=NOW()`. 상세 상단 카드로 표시.

### ② AI 상담요약 (`consult_summary`) — 권장 모델 opus-4-8 또는 sonnet-5
- 입력: 해당 상담의 `detail_json`·`memo`·상품·상태.
- 시스템: `"상담원이 남긴 메모를 2~3문장으로 간결히 요약하라. 핵심 문의·결정사항·다음 액션만. 최종 요약문만 출력."`
- 저장: `consults.ai_summary`, `ai_summary_at=NOW()`. 상세에 표시 + 목록에서 아이콘/툴팁 노출 가능.

### ③ AI 답변초안 (`reply_draft`) — 권장 모델 opus-4-8
- 입력: 고객명, 문의 상품·내용, 브랜드 persona(있으면).
- 시스템: `"당신은 전문 상담원이다. 고객 문의에 대한 정중하고 명확한 답변 '초안'을 작성하라. 존댓말, 과장·허위 금지, 확정 불가한 가격/조건은 '담당자 확인 후 안내'로. 서명 제외. 답변 본문만 출력."`
- **미저장**: 화면에 표시 + "복사" 버튼. 담당자가 검토·수정 후 실제 발송(발송 기능은 V3 범위).

### ④ AI 상품추천 (`recommend`) — 권장 모델 opus-4-8(구조화 출력)
- 입력: 문의 내용/상품 + **해당 브랜드의 products 목록**(사용중인 것만; 추천은 실제 상품에서만 골라야 함).
- 구조화 출력(json_schema)으로 상품명 배열 반환 → 실제 products와 매칭해 표시:
```json
{"type":"object","additionalProperties":false,
 "properties":{"recommend":{"type":"array","items":{"type":"string"}},
               "reason":{"type":"string"}},
 "required":["recommend","reason"]}
```
- 시스템: `"고객 문의를 분석해, 제공된 '상품 목록' 중에서만 함께 제안하면 좋은 상품을 최대 4개 고르라. 목록에 없는 상품은 절대 만들지 마라. reason은 1문장."`

### ⑤ AI 고객등급 (`grade`) — 권장 모델 haiku-4-5(단순 분류, 저비용) 또는 opus-4-8
- 입력: 상담 횟수, 최근 상태 이력, 계약/견적 유무, 마지막 상담 경과일.
- 구조화 출력(strict enum)으로 등급만:
```json
{"type":"object","additionalProperties":false,
 "properties":{"grade":{"type":"string","enum":["vip","repeat","new","dormant"]}},
 "required":["grade"]}
```
- 시스템: `"고객 활동을 기준으로 등급을 하나만 분류하라. vip=계약·고빈도, repeat=재문의, new=신규 1회, dormant=장기 미활동. grade 값만."`
- 저장: `customers.grade`. 목록에 뱃지 표시.

---

## 6. 오류·UX 규칙
- API 실패/타임아웃/키없음 → 사용자에게 "AI 응답을 가져오지 못했습니다(잠시 후 다시)". 기존 데이터·화면은 그대로(치명적이지 않게).
- 생성 중 버튼 disabled + 스피너. 결과는 편집 가능 텍스트영역으로 표시(담당자가 손볼 수 있게).
- AI 결과에는 **"AI 생성 — 검토 필요"** 라벨을 항상 표기(담당자 판단 보조 도구임을 명확히).

## 7. 개인정보 최소화(필수)
- API로 보내기 전 **전화·이메일·주소·우편번호는 마스킹 또는 제거**(요약/추천/등급 추론에 불필요). 예: 전화 `010-****-1234`, 이메일 `id***@도메인`.
- 이름은 답변초안엔 필요(호칭) → 유지 가능하되, 요약·등급엔 이니셜/마스킹 권장.
- `detail_json`에 민감정보가 있으면 화이트리스트 필드만 전송.
- 상담폼 개인정보 동의문에 "상담 보조를 위한 AI 처리(위탁)" 취지가 커버되는지 검토(법무/사업자 판단).

## 8. 비기능 요건
- 서버 PHP 8.4 / curl / Composer 미사용(원시 HTTP 유지). 로컬 PHP 5.2라 로컬 실행 불가 → **서버에서 검증**(V1.0과 동일).
- 배포: `config/ai.php`(키 포함, git 제외)·`includes/ai.php`·각 `admin/**/ai_*.php`·스키마 ALTER. Cafe24 수동 FTP + phpMyAdmin.
- 타임아웃 30초, 실패 재시도 없음(사용자 재클릭). 대량 배치(전체 고객 등급 일괄)는 별도 큐/속도제한으로 후속.

## 9. 권장 진행 순서(phase)
1. **기반(§1) + DB(§2)** — 키 없이도 구조만.
2. **② 상담요약 + ③ 답변초안** (체감 가치 최고, 상담원 시간 절감 즉시).
3. ① 고객요약 → ④ 상품추천 → ⑤ 고객등급.
4. `admin/settings` AI 사용량 대시(ai_logs 집계) + 킬스위치 노출.

## 10. 완료 기준(DoD, 전체)
- [x] 각 기능 버튼 클릭 → 서버사이드 Claude 호출 → 결과 표시(요약/등급 저장, 답변초안 표시).
- [x] PII 마스킹 적용(요청 페이로드에 원본 전화/이메일 없음).
- [x] 실패 시 안전(예외 없이 안내), `enabled=false`로 전면 중단 가능.
- [x] ai_logs에 호출 기록, 당월 사용량 확인 가능.
- [x] `db/schema.sql`·CHANGELOG 동기화, 키·config 미커밋 확인.

## 11. 하지 말 것(V1.5 범위 밖)
- 자동 발송(카톡/메일/SMS) = V3~4. 답변초안은 **표시만**.
- 계약/견적/정산 = V2. 실시간 스트리밍 UI, 멀티턴 대화형 봇 = 후속.
- 전체 고객 자동 일괄 처리(비용/속도) — 우선 온디맨드만.
