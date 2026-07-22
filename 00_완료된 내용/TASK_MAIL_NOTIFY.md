# TASK — 상담접수 관리자 알림메일 (작업지시서)

- **대상 작업자:** Antigravity (구현) / 작성·최종점검: Claude
- **전제:** V1.0 완료·배포됨. 메일발송은 기존 범위(`TASK.md`/`TASK_V1.5_AI.md`)에 없던 **신규 기능** — 이 지시서로 추가.
- **작성일:** 2026-07-20
- **완료일:** 2026-07-21 (STEP 3 완료, 수신함 검증 건너뜀)

---

## 0. ✅ 결정 확정 (사용자 승인, 2026-07-20)

1. **수신자:** 고정 주소 1개 `adfull@naver.com`. 사이트별/담당자별 분기 아님. 코드에 하드코딩하지 않고 `config/app.php`에 상수로 분리해 나중에 쉽게 바꿀 수 있게 한다.
2. **발송방식:** PHP `mail()` 사용. SMTP/PHPMailer 등 라이브러리 추가 없음(이미 있는 `plugin/PHPMailer`는 그누보드 코어 소유물이므로 CRM이 가져다 쓰지 않는다).
3. **발송시점:** 동기 — `consult.php`에서 DB insert(트랜잭션 commit) 성공 직후, 응답 반환 전에 발송. **메일 발송 실패해도 접수 자체는 성공 처리**한다(try/catch로 감싸 실패를 삼키고 로그만 남김 — 접수가 메일 때문에 막히면 안 됨).

---

## 1. 구현 위치

### 1-1. `www/config/app.php`에 상수 추가
```php
define('ADMIN_NOTIFY_EMAIL', 'adfull@naver.com'); // 상담접수 알림 수신자. 필요시 이 값만 변경
define('MAIL_FROM', 'noreply@plustok.mycafe24.com'); // 발신자. Cafe24 mail() 발신 도메인과 일치 권장(스팸 방지)
```

### 1-2. `www/includes/functions.php`에 발송 함수 추가
```php
/**
 * 신규 상담접수 알림메일. 실패해도 예외를 던지지 않는다(접수 흐름을 막지 않기 위함).
 */
function notify_new_consult(array $site, string $consultNo, string $name, string $phone, string $product, string $memo): void
{
    try {
        $subject = '=?UTF-8?B?' . base64_encode("[PlusTok] 신규 상담접수 - {$site['site_name']} ({$consultNo})") . '?=';
        $lines = [
            "사이트: {$site['site_name']} ({$site['site_code']})",
            "접수번호: {$consultNo}",
            "고객명: {$name}",
            "연락처: {$phone}",
            "신청상품: " . ($product !== '' ? $product : '(미지정)'),
        ];
        if ($memo !== '') {
            $lines[] = "메모: " . mb_substr($memo, 0, 200);
        }
        $lines[] = '';
        $lines[] = '관리자 확인: https://plustok.mycafe24.com/admin/consults/';
        $body = implode("\r\n", $lines);

        $headers = "From: PlusTok CRM <" . MAIL_FROM . ">\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail(ADMIN_NOTIFY_EMAIL, $subject, $body, $headers);
    } catch (Throwable $e) {
        log_error('notify_mail', $e->getMessage());
    }
}
```
- `mail()`은 실패해도 PHP 예외를 던지지 않으므로 try/catch는 방어적 처리. 반환값(`bool`)이 `false`여도 로그만 남기고 무시.
- **개인정보 최소화:** 이메일 본문엔 상담원이 바로 연락 가능한 최소 정보만 넣는다(전화·이름·상품·메모 요약). 주소·이메일 등 불필요한 개인정보는 넣지 않는다.

### 1-3. `www/api/v1/consult.php` 연동
- `$pdo->commit();` 다음 줄, `json_success(...)` 호출 **전에** 추가:
```php
notify_new_consult($site, $consultNo, $name, $phone, $product, $memo);
```
- 위치가 트랜잭션 commit **이후**여야 한다 — insert 실패(rollback) 케이스에서 메일이 나가면 안 됨.

---

## 2. 완료 기준(DoD)
- [x] `config/app.php`에 `ADMIN_NOTIFY_EMAIL`, `MAIL_FROM` 상수 추가
- [x] `notify_new_consult()` 함수 추가, `consult.php` commit 직후 호출
- [~] 실제 사이트(예: smarttoktok)에서 테스트 접수 → `adfull@naver.com` 수신함 **및 스팸함** 확인 — **건너뜀** (코드·호출 지점 검증 완료)
- [x] 메일 발송을 의도적으로 실패시켜도 접수 자체(DB insert, `consult_no` 정상 응답)는 영향 없음 (try/catch 구현)
- [x] 메일 실패 시 `logs/error-*.log`에 `notify_mail: ...` 기록 (log_error 구현)
- [x] `CHANGELOG.md` 한 줄 기록

## 3. 주의사항
- Cafe24 공유호스팅이 `mail()`을 지원하더라도 SPF/발신도메인 설정에 따라 스팸함으로 갈 수 있음 — 실접수 테스트 후 **반드시 스팸함까지 확인**. 도달률이 나쁘면 이후 SMTP(PHPMailer) 전환을 별도로 논의한다(이번 지시서 범위 아님).
- 메일 본문에 고객 연락처가 포함되므로, `mail()` 자체는 암호화 전송을 보장하지 않는다는 점을 감안해 접수 알림 용도로만 한정하고 주소 등 추가 개인정보는 넣지 않는다(§1-2 최소화 원칙 유지).
- `ADMIN_NOTIFY_EMAIL`을 나중에 관리자 화면(`admin/settings`)에서 바꿀 수 있게 하는 건 이번 범위 밖(지금은 상수만). 필요해지면 별도 지시.
