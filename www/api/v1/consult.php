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
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';
require_once __DIR__ . '/../../includes/util/ConsultSchema.php';
require_once __DIR__ . '/../../includes/util/ProductSchema.php';
require_once __DIR__ . '/../../includes/util/ConsultMeta.php';

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
if ($email === '') {
    json_error('INVALID_PARAM', '이메일을 입력해주세요.');
}
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

// --- 필드 스키마 기반 필수값 검증 (옵트인: 해당 사이트/상품에 스키마가 명시된 경우에만 동작) ---
require_once __DIR__ . '/../../includes/util/SiteFieldSchema.php';
if (SiteFieldSchema::tableExists($pdo)) {
    $schemaRows = SiteFieldSchema::forSite($pdo, (int)$site['id']);
    $resolvedFields = SiteFieldSchema::resolve($schemaRows, $category, $product);
    foreach ($resolvedFields as $f) {
        if (empty($f['required'])) {
            continue;
        }
        $fKey = (string)($f['key'] ?? '');
        if ($fKey === '') {
            continue;
        }
        $fVal = is_array($detail) ? ($detail[$fKey] ?? '') : '';
        if (trim((string)$fVal) === '') {
            json_error('INVALID_PARAM', (string)($f['label'] ?? $fKey) . '을(를) 입력해주세요.');
        }
    }
}

$custTable = CrmSchema::legacyCustomerTable($pdo);
$chatPayload = null;
try {
    $pdo->beginTransaction();

    // --- 고객 중복 확인(phone) ---
    $stmt = $pdo->prepare("SELECT id FROM {$custTable} WHERE phone = :p LIMIT 1");
    $stmt->execute([':p' => $phone]);
    $customerId = $stmt->fetchColumn();

    if (!$customerId) {
        $customerNo = next_customer_no($pdo, $custTable);
        $custInsert = ConsultSchema::buildInsert($pdo, $custTable, [
            'customer_no' => $customerNo,
            'name'        => $name,
            'phone'       => $phone,
            'company'     => $company ?: null,
            'email'       => $email ?: null,
            'zipcode'     => $zipcode ?: null,
            'address'     => $address ?: null,
            'memo'        => null,
        ], [
            'region' => $region ?: null,
        ]);
        $ins = $pdo->prepare(
            'INSERT INTO ' . $custTable
            . ' (' . implode(', ', $custInsert['columns']) . ')'
            . ' VALUES (' . implode(', ', $custInsert['placeholders']) . ')'
        );
        $ins->execute($custInsert['params']);
        $customerId = (int)$pdo->lastInsertId();
    } else {
        $customerId = (int)$customerId;
        // 기존 고객이 새 상담에서 더 최신 정보(이름/상호/이메일/주소)를 보내오면 반영한다.
        // 그렇지 않으면 최초 접수 당시 값(테스트 데이터 포함)이 영구히 고정되어 보인다.
        $custUpdate = [];
        $updateParams = [':id' => $customerId];
        if ($name !== '') {
            $custUpdate[] = 'name = :u_name';
            $updateParams[':u_name'] = $name;
        }
        if ($company !== '' && acep_column_exists($pdo, $custTable, 'company')) {
            $custUpdate[] = 'company = :u_company';
            $updateParams[':u_company'] = $company;
        }
        if ($email !== '' && acep_column_exists($pdo, $custTable, 'email')) {
            $custUpdate[] = 'email = :u_email';
            $updateParams[':u_email'] = $email;
        }
        if ($address !== '' && acep_column_exists($pdo, $custTable, 'address')) {
            $custUpdate[] = 'address = :u_address';
            $updateParams[':u_address'] = $address;
        }
        if ($custUpdate !== []) {
            $pdo->prepare('UPDATE ' . $custTable . ' SET ' . implode(', ', $custUpdate) . ' WHERE id = :id')
                ->execute($updateParams);
        }
    }

    // --- product_id 매핑(있으면) ---
    $productId = null;
    if ($product !== '') {
        $ps = $pdo->prepare(
            'SELECT id FROM products WHERE brand = :b AND product_name = :n AND '
            . ProductSchema::activeSql($pdo) . ' AND ' . ProductSchema::siteScopeSql($pdo)
            . ' LIMIT 1'
        );
        $ps->execute(array_merge(
            [':b' => $site['brand'], ':n' => $product],
            ProductSchema::siteScopeParams($pdo, (int)$site['id'])
        ));
        $pid = $ps->fetchColumn();
        if ($pid) {
            $productId = (int)$pid;
        }
    }

    // --- 상담 insert ---
    $consultNo = next_consult_no($pdo);
    $initialStatus = ConsultSchema::initialStatus($pdo);
    $consultInsert = ConsultSchema::buildInsert($pdo, 'consults', [
        'consult_no'   => $consultNo,
        'customer_id'  => $customerId,
        'site_id'      => (int)$site['id'],
        'product_id'   => $productId,
        'category'     => $category ?: null,
        'product_name' => $product ?: null,
        'status'       => $initialStatus,
        'detail_json'  => $detailJson,
        'memo'         => $memo ?: null,
    ], [
        'referer' => $referer ?: null,
        'device'  => $device ?: null,
    ]);
    $ic = $pdo->prepare(
        'INSERT INTO consults ('
        . implode(', ', $consultInsert['columns'])
        . ') VALUES ('
        . implode(', ', $consultInsert['placeholders'])
        . ')'
    );
    $ic->execute($consultInsert['params']);
    $consultId = (int)$pdo->lastInsertId();

    ConsultSchema::recordInitialHistory($pdo, $consultId, $initialStatus);
    ConsultMeta::store($pdo, $consultId, is_array($detail) ? $detail : null);

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_error(
        'consult.php',
        sprintf(
            '%s: %s in %s:%d',
            $ex::class,
            $ex->getMessage(),
            basename($ex->getFile()),
            $ex->getLine(),
        ),
    );
    json_error('SERVER_ERROR', '접수 처리 중 오류가 발생했습니다.', 500);
}

// 채팅방 생성은 별도 시도 — 실패해도 상담 접수 자체는 이미 완료된 상태로 유지 (마이그레이션 미완료 시 안전장치)
try {
    if (!acep_table_exists($pdo, 'chat_rooms')) {
        log_error('consult.php:chat_room', sprintf(
            'skipped consult_id=%d: chat_rooms table missing',
            $consultId,
        ));
    } else {
        require_once __DIR__ . '/../../includes/consult_chat.php';
        $consultChat = acep_consult_chat_service($pdo);
        if (!$consultChat->chatTablesAvailable()) {
            log_error('consult.php:chat_room', sprintf(
                'skipped consult_id=%d customer_id=%d: chatTablesAvailable=false cust_table=%s',
                $consultId,
                $customerId,
                CrmSchema::legacyCustomerTable($pdo),
            ));
        } else {
            $inquiry = $category ?: ($product ?: '상담신청');
            $chatPayload = $consultChat->createRoomForConsult(
                $customerId,
                $consultId,
                $name,
                $phone,
                $email !== '' ? $email : null,
                $inquiry,
                $memo !== '' ? $memo : null,
                'web',
            );
            if ($chatPayload === null) {
                log_error('consult.php:chat_room', sprintf(
                    'skipped consult_id=%d customer_id=%d: createRoomForConsult returned null',
                    $consultId,
                    $customerId,
                ));
            }
        }
    }
} catch (Throwable $ex) {
    log_error(
        'consult.php:chat_room',
        sprintf(
            'consult_id=%d customer_id=%d | %s: %s in %s:%d',
            $consultId,
            $customerId,
            $ex::class,
            $ex->getMessage(),
            basename($ex->getFile()),
            $ex->getLine(),
        ),
    );
    $chatPayload = null;
}

notify_new_consult($site, $consultNo, $name, $phone, $product, $memo);

$response = ['consult_no' => $consultNo];
if ($chatPayload !== null) {
    $response = array_merge($response, $chatPayload);
}
json_success($response, '상담 접수가 완료되었습니다.');
