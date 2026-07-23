<?php
declare(strict_types=1);
/**
 * 공통 유틸.
 */

require_once __DIR__ . '/../config/app.php';

/**
 * 상담 1건 삭제 (첨부파일·상태이력 포함, 트랜잭션). 관리자 전용 로직에서만 호출한다.
 */
function delete_consult(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT saved_path FROM attachments WHERE consult_id = :id');
        $st->execute([':id' => $id]);
        foreach ($st->fetchAll() as $a) {
            $p = BASE_PATH . '/' . ltrim((string)$a['saved_path'], '/');
            if (is_file($p)) { @unlink($p); }
        }
        $pdo->prepare('DELETE FROM attachments WHERE consult_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM consult_history WHERE consult_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM consults WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/** 클라이언트 IP */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** 전화번호 정규화: 숫자만 남긴다. (DB.md customers.phone) */
function normalize_phone(string $phone): string
{
    return preg_replace('/\D/', '', $phone) ?? '';
}

/** 요청 body를 JSON으로 파싱. 실패 시 빈 배열. */
function read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** 문자열 트림 + 최대 길이 제한. */
function clean_str($v, int $max = 255): string
{
    $s = trim((string)($v ?? ''));
    if (mb_strlen($s) > $max) {
        $s = mb_substr($s, 0, $max);
    }
    return $s;
}

/** HTML 이스케이프 (관리자 출력용). */
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * 상담번호 생성: C + YYYYMMDD + 4자리 일련번호(당일 0001부터).
 * consults 테이블에서 당일 건수를 세어 다음 번호를 반환한다.
 */
function next_consult_no(PDO $pdo): string
{
    $prefix = 'C' . date('Ymd');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM consults WHERE consult_no LIKE :p'
    );
    $stmt->execute([':p' => $prefix . '%']);
    $seq = ((int)$stmt->fetchColumn()) + 1;
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

/** 고객번호 생성: M + YYYYMMDD + 4자리. */
function next_customer_no(PDO $pdo, string $table = 'customers'): string
{
    $prefix = 'M' . date('Ymd');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS c FROM {$table} WHERE customer_no LIKE :p"
    );
    $stmt->execute([':p' => $prefix . '%']);
    $seq = ((int)$stmt->fetchColumn()) + 1;
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

/** 오류 로그 (개인정보 원문은 남기지 않는다). */
function log_error(string $context, string $message): void
{
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0750, true);
    }
    $line = sprintf("[%s] %s: %s\n", date('c'), $context, $message);
    @file_put_contents(LOG_PATH . '/error-' . date('Ymd') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** Cafe24 PHP mail() — SPF 정렬 From 헤더 + 실패 로그 (수신자 원문은 남기지 않음) */
function acep_send_mail(string $to, string $subject, string $body, string $context = 'mail'): bool
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = 'From: ' . APP_BRAND . ' <' . MAIL_FROM . ">\r\n"
             . 'Reply-To: ' . MAIL_FROM . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . 'X-Mailer: PlusTok-ACEP';

    $ok = @mail($to, $encodedSubject, $body, $headers);
    if (!$ok) {
        log_error($context, 'mail() returned false to_hash=' . substr(hash('sha256', strtolower($to)), 0, 12));
    }
    return $ok;
}

/**
 * 간이 Rate Limit (파일 기반). IP+site 분당 RATE_LIMIT_PER_MIN 초과 시 false.
 * 공유호스팅에서 별도 스토리지 없이 동작하도록 임시파일 사용.
 */
function rate_limit_ok(string $key): bool
{
    $bucket = LOG_PATH . '/rl_' . md5($key . date('YmdHi')) . '.tmp';
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0750, true);
    }
    $count = 0;
    if (is_file($bucket)) {
        $count = (int)@file_get_contents($bucket);
    }
    if ($count >= RATE_LIMIT_PER_MIN) {
        return false;
    }
    @file_put_contents($bucket, (string)($count + 1), LOCK_EX);
    return true;
}

/**
 * 신규 상담접수 알림메일. 실패해도 예외를 던지지 않는다(접수 흐름을 막지 않기 위함).
 */
function notify_new_consult(array $site, string $consultNo, string $name, string $phone, string $product, string $memo): void
{
    try {
        $subject = "[PlusTok] 신규 상담접수 - {$site['site_name']} ({$consultNo})";
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

        acep_send_mail(ADMIN_NOTIFY_EMAIL, $subject, $body, 'notify_mail');
    } catch (Throwable $e) {
        log_error('notify_mail', $e->getMessage());
    }
}
