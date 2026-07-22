# TASK — 관리자 AI 설정 화면 (작업지시서)

- **대상 작업자:** Antigravity (구현) / 작성·최종점검: Claude
- **배경:** 지금은 Claude API 키를 서버 `config/ai.php` 파일을 FTP로 직접 열어 넣어야 함 — 초보 사용자에게 불편. 관리자 화면에서 입력하도록 개선.
- **작성일:** 2026-07-21
- **완료일:** 2026-07-21
- **상태:** ✅ 완료 (E2E API 연결 테스트·①②③ 기능 검증은 사용자 요청으로 **건너뜀** — API 키는 관리자 화면에서 수동 입력 완료)

---

## 0. ✅ 결정 확정 (검토 후 확정)

1. **저장 위치: DB.** `config/ai.php`를 관리자 입력으로 자동 **생성/덮어쓰기 하지 않는다.** 사용자가 입력한 문자열을 PHP 소스 파일에 그대로 써넣는 방식은 이스케이프 실수 시 임의 코드 실행으로 이어질 수 있는 위험한 패턴이라 채택하지 않는다. 기존 `sites.api_key`처럼 DB 테이블(`ai_settings`)에 저장하는 방식으로 간다.
2. **`.env` 파일, `config/ai_key.txt` 방식은 채택하지 않는다.** 둘 다 결국 FTP/파일관리자로 파일을 직접 편집해야 해서 "FTP 없이 설정" 이라는 목표를 달성하지 못한다.
3. **모델 선택지는 문서에 이미 정의된 것만.** `claude-opus-4-8`(기본값) / `claude-sonnet-5` / `claude-haiku-4-5` 드롭다운. 자유 텍스트 입력 금지(오타로 존재하지 않는 모델명 저장 방지).
4. **멀티 프로바이더(OpenAI·Gemini 등) 미지원.** `provider` 컬럼은 `'anthropic'` 고정값으로 남겨두되(향후 확장 여지), 다른 프로바이더 연동 로직·UI는 이번 범위에 만들지 않는다.
5. **DB 설정이 있으면 DB 우선, 없으면 `config/ai.php` 폴백.** 기존 로컬 개발/최초 부트스트랩 경로를 깨지 않기 위함.

---

## 1. DB 스키마

```sql
CREATE TABLE IF NOT EXISTS ai_settings (
  id          TINYINT PRIMARY KEY DEFAULT 1,  -- 항상 단일 행(id=1)만 사용
  provider    VARCHAR(20)  NOT NULL DEFAULT 'anthropic',
  api_key     VARCHAR(200) DEFAULT NULL,
  model       VARCHAR(40)  NOT NULL DEFAULT 'claude-opus-4-8',
  enabled     TINYINT      NOT NULL DEFAULT 0,
  updated_by  BIGINT       DEFAULT NULL,       -- managers.id
  updated_at  DATETIME     DEFAULT NULL,
  CONSTRAINT chk_ai_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- `db/schema.sql`에 반영 + 서버 phpMyAdmin에 `CREATE TABLE IF NOT EXISTS`로 적용.
- `MariaDB CHECK` 미지원 버전 대비: 애플리케이션 레벨에서도 항상 `id=1`로만 UPSERT(INSERT ... ON DUPLICATE KEY UPDATE) 하도록 코드로 강제.

## 2. `includes/ai.php` 수정 — 설정 로드 우선순위

```php
function ai_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $fileCfg = require __DIR__ . '/../config/ai.php'; // 폴백(로컬/부트스트랩용)
    $cfg = $fileCfg;

    try {
        $row = db()->query('SELECT provider, api_key, model, enabled FROM ai_settings WHERE id = 1')->fetch();
        if ($row) {
            $cfg['provider'] = $row['provider'] ?: $cfg['provider'];
            $cfg['api_key']  = $row['api_key']  !== null && $row['api_key'] !== '' ? $row['api_key'] : $cfg['api_key'];
            $cfg['model']    = $row['model']    ?: $cfg['model'];
            $cfg['enabled']  = (bool)$row['enabled'];
        }
    } catch (Throwable $e) { /* ai_settings 테이블 없으면 파일 설정 그대로 사용 */ }

    return $cfg;
}
```
- 기존 `ai_call()`의 `$cfg = require __DIR__ . '/../config/ai.php';` 줄을 `$cfg = ai_config();`로 교체. **그 외 `ai_call()` 로직은 건드리지 않는다.**
- DB 우선, DB에 값 없거나 테이블 자체가 없으면 파일로 자동 폴백(안전).

## 3. 관리자 화면 — `admin/settings/ai.php` (신규)

- 기존 `admin/settings/index.php`에 "AI 설정" 링크 추가(별도 최상위 메뉴 만들지 않음 — 설정 메뉴 하위).
- 권한: `require_role(['super', 'admin'])` (다른 설정 화면과 동일).
- 폼 항목:
  1. AI 사용 ON/OFF (`enabled`)
  2. Claude API Key (입력창) — **마스킹 표시 필수:** 저장된 키가 있으면 `sk-ant-****...` + 마지막 4자리만 보여주고, 필드를 비워둔 채 저장하면 **기존 키 유지**(빈 값으로 덮어쓰지 않음). 새 값을 입력해야만 교체.
  3. 모델 드롭다운(§0-3 3개 옵션, 기본 `claude-opus-4-8`)
  4. 저장 버튼 — CSRF 체크, `activity_log`에 `ai_settings_update` 기록(누가 언제 바꿨는지, **키 값 자체는 로그에 남기지 않음**)
  5. "연결 테스트" 버튼 — 저장된 설정으로 `ai_call('당신은 테스트 봇이다.', '\'ok\'라고만 답하라.', ['max_tokens'=>10])` 짧게 호출해 성공/실패만 화면에 표시(응답 원문은 화면에 안 띄워도 됨, 성공 시 "✅ 연결 성공", 실패 시 `error` 메시지 표시).
- UPSERT: `INSERT INTO ai_settings (id, provider, api_key, model, enabled, updated_by, updated_at) VALUES (1, 'anthropic', :key, :model, :enabled, :uid, NOW()) ON DUPLICATE KEY UPDATE ...` (키가 빈 입력이면 `api_key` 컬럼은 SQL에서 제외하고 기존 값 유지).

## 4. 완료 기준(DoD)
- [x] `db/schema.sql`에 `ai_settings` 추가 + 서버 CREATE TABLE 적용
- [x] `includes/ai.php`에 `ai_config()` 추가, `ai_call()`이 이를 사용하도록 교체 (기존 동작 안 깨지는지 확인 — DB에 값 없으면 기존처럼 `config/ai.php` 값으로 정상 동작해야 함)
- [x] `admin/settings/ai.php` 신규: 입력폼 + 마스킹 표시 + 저장(빈 키=기존유지) + 연결테스트 버튼
- [x] `admin/settings/index.php`에 링크 추가
- [x] 관리자 화면에서 API 키 수동 입력 완료 (2026-07-21, 사용자 확인)
- [~] 연결 테스트 및 ①②③ AI 기능 E2E 검증 — **사용자 요청으로 건너뜀** (STEP 2 착수 시 선택적 재검증)
- [x] `db/schema.sql`·`CHANGELOG.md` 동기화

## 5. 하지 말 것
- `config/ai.php` 파일을 관리자 입력으로 자동 생성/덮어쓰기(§0-1 사유로 금지)
- OpenAI·Gemini 등 다른 프로바이더 연동 로직/드롭다운 추가(§0-4, 범위 밖)
- API 키를 응답 JSON·로그·`activity_log`에 평문으로 남기는 것(마스킹된 값만 화면 표시, 저장은 DB 컬럼에만)
- 자유 텍스트 모델명 입력(드롭다운 고정 옵션만)
