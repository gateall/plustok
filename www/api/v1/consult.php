<?php
declare(strict_types=1);
/**
 * POST /api/v1/consult.php — 상담 접수. (SPEC.md E / API.md 1)
 * 처리: API키 검증 → 필수값 검증 → 고객 중복확인(phone) → 상담번호 → insert → history → 응답
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST만 허용됩니다.', 405);
}

$site = require_site_by_apikey();

// Rate limit (IP + site)
if (!rate_limit_ok(client_ip() . ':' . $site['site_code'])) {
    json_error('RATE_LIMIT', '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.', 429);
}

$in = read_json_body();
if (!$in) {
    // 서버 프록시가 form-data로 넘기는 경우 지원
    $in = $_POST;
}

// --- 필수값 검증 ---
$name  = clean_str($in['customer_name'] ?? '', 60);
$phone = normalize_phone((string)($in['phone'] ?? ''));
$agree = filter_var($in['agree'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($name === '') {
    json_error('INVALID_PARAM', '이름을 입력해주세요.');
}
if (strlen($phone) < 9 || strlen($phone) > 11) {
    json_error('INVALID_PARAM', '휴대폰 번호를 확인해주세요.');
}
if (!$agree) {
    json_error('INVALID_PARAM', '개인정보 수집·이용에 동의해야 합니다.');
}

// --- 화이트리스트 입력 ---
$company  = clean_str($in['company'] ?? '', 120);
$email    = clean_str($in['email'] ?? '', 150);
$zipcode  = clean_str($in['zipcode'] ?? '', 10);
$region   = clean_str($in['region'] ?? '', 50);
$address  = clean_str($in['address'] ?? '', 255);
$memo     = clean_str($in['memo'] ?? '', 2000);
$category = clean_str($in['category'] ?? '', 60);
$product  = clean_str($in['product'] ?? '', 100);
$referer  = clean_str($in['referer'] ?? '', 255);
$device   = clean_str($in['device'] ?? '', 20);

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('INVALID_PARAM', '이메일 형식을 확인해주세요.');
}

// detail(상품별 가변값)은 배열이면 JSON 인코딩, 아니면 null
$detail = $in['detail'] ?? null;
$detailJson = (is_array($detail) && $detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;

$pdo = db();
try {
    $pdo->beginTransaction();

    // --- 고객 중복 확인(phone) ---
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :p LIMIT 1');
    $stmt->execute([':p' => $phone]);
    $customerId = $stmt->fetchColumn();

    if (!$customerId) {
        $customerNo = next_customer_no($pdo);
        $ins = $pdo->prepare(
            'INSERT INTO customers (customer_no, name, phone, company, email, zipcode, address, region, memo)
             VALUES (:no, :name, :phone, :company, :email, :zip, :addr, :region, :memo)'
        );
        $ins->execute([
            ':no' => $customerNo, ':name' => $name, ':phone' => $phone,
            ':company' => $company ?: null, ':email' => $email ?: null,
            ':zip' => $zipcode ?: null, ':addr' => $address ?: null,
            ':region' => $region ?: null, ':memo' => null,
        ]);
        $customerId = (int)$pdo->lastInsertId();
    } else {
        $customerId = (int)$customerId;
    }

    // --- product_id 매핑(있으면) ---
    $productId = null;
    if ($product !== '') {
        $ps = $pdo->prepare(
            'SELECT id FROM products WHERE brand = :b AND product_name = :n AND use_yn = 1 LIMIT 1'
        );
        $ps->execute([':b' => $site['brand'], ':n' => $product]);
        $pid = $ps->fetchColumn();
        if ($pid) {
            $productId = (int)$pid;
        }
    }

    // --- 상담 insert ---
    $consultNo = next_consult_no($pdo);
    $ic = $pdo->prepare(
        'INSERT INTO consults
           (consult_no, customer_id, site_id, product_id, category, product_name,
            status, detail_json, memo, referer, device)
         VALUES
           (:no, :cid, :sid, :pid, :cat, :pname, :status, :detail, :memo, :referer, :device)'
    );
    $ic->execute([
        ':no' => $consultNo, ':cid' => $customerId, ':sid' => (int)$site['id'],
        ':pid' => $productId, ':cat' => $category ?: null, ':pname' => $product ?: null,
        ':status' => 'new', ':detail' => $detailJson, ':memo' => $memo ?: null,
        ':referer' => $referer ?: null, ':device' => $device ?: null,
    ]);
    $consultId = (int)$pdo->lastInsertId();

    // --- 최초 상태 이력 ---
    $ih = $pdo->prepare(
        'INSERT INTO consult_history (consult_id, from_status, to_status, note)
         VALUES (:cid, NULL, :to, :note)'
    );
    $ih->execute([':cid' => $consultId, ':to' => 'new', ':note' => '상담 접수']);

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_error('consult.php', $ex->getMessage());
    json_error('SERVER_ERROR', '접수 처리 중 오류가 발생했습니다.', 500);
}

notify_new_consult($site, $consultNo, $name, $phone, $product, $memo);

json_success(['consult_no' => $consultNo], '상담 접수가 완료되었습니다.');
