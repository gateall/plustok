<?php
declare(strict_types=1);
/**
 * AI 상담요약 엔드포인트 (POST 전용)
 * 대상 상담의 내용을 요약하여 consults.ai_summary, ai_summary_at에 저장
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';

require_login();
require_role(['super', 'admin']);
csrf_check();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ai_json_response(['ok' => false, 'error' => 'POST 요청만 가능합니다.'], 405);
}

$id = (int)($_POST['consult_id'] ?? 0);
if ($id <= 0) {
    ai_json_response(['ok' => false, 'error' => '잘못된 상담 ID입니다.'], 400);
}

// 레이트리밋: 동일 target 30초 내 중복 호출 차단 (§3)
if (!ai_check_rate_limit('consult_summary', $id)) {
    ai_json_response(['ok' => false, 'error' => '30초 이내에 동일 상담에 대한 AI 요청을 중복 실행할 수 없습니다. 잠시 후 다시 시도해주세요.'], 429);
}

$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);
$stmt = $pdo->prepare(
    "SELECT c.*, cu.name AS cust_name, cu.company, s.brand, s.site_name
     FROM consults c
     LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
     LEFT JOIN sites s ON s.id = c.site_id
     WHERE c.id = :id LIMIT 1"
);
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();
if (!$c) {
    ai_json_response(['ok' => false, 'error' => '상담 정보를 찾을 수 없습니다.'], 404);
}

// PII 마스킹 필수 (§7) — 전화/이메일/주소/우편번호는 제외하고 텍스트 항목 마스킹 처리
$maskedName = mb_substr($c['cust_name'], 0, 1) . '**';
$maskedCompany = ai_mask_pii((string)($c['company'] ?? '없음'));
$maskedMemo = ai_mask_pii((string)($c['memo'] ?? ''));

$detailArr = $c['detail_json'] ? json_decode($c['detail_json'], true) : [];
if (is_array($detailArr)) {
    foreach ($detailArr as $k => $v) {
        if (is_string($v)) {
            $detailArr[$k] = ai_mask_pii($v);
        }
    }
}
$maskedDetail = $detailArr ? json_encode($detailArr, JSON_UNESCAPED_UNICODE) : '없음';

$system = "상담원이 남긴 메모와 문의 상세 내용을 2~3문장으로 간결히 요약하라. 핵심 문의·결정사항·다음 액션만. 군더더기·인사말 없이 최종 요약문만 출력.";
$user = sprintf(
    "고객: %s (회사: %s)\n문의상품: %s (%s)\n상담상태: %s\n문의상세: %s\n상담메모: %s",
    $maskedName,
    $maskedCompany,
    $c['product_name'] ?? '-',
    $c['category'] ?? '-',
    $c['status'],
    $maskedDetail,
    $maskedMemo ?: '내용 없음'
);

$res = ai_call($system, $user, [
    'feature' => 'consult_summary',
    'target_id' => $id,
    'max_tokens' => 400
]);

if (!empty($res['ok'])) {
    try {
        $pdo->prepare("UPDATE consults SET ai_summary = :sum, ai_summary_at = NOW() WHERE id = :id")
            ->execute([':sum' => $res['text'], ':id' => $id]);
    } catch (Throwable $e) {
        log_error('ai_summary_save', $e->getMessage());
        ai_json_response(['ok' => false, 'error' => 'AI 요약 생성은 성공했으나 DB 저장에 실패했습니다: ' . $e->getMessage()], 500);
    }
    ai_json_response(['ok' => true, 'text' => $res['text'], 'summary_at' => date('Y-m-d H:i:s')]);
} else {
    ai_json_response(['ok' => false, 'error' => $res['error'] ?? 'AI 요약 생성 실패'], 500);
}
