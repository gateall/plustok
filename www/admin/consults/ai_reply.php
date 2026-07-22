<?php
declare(strict_types=1);
/**
 * AI 답변초안 엔드포인트 (POST 전용)
 * 대상 상담의 내용을 기반으로 답변 초안을 생성 (DB 미저장, 화면 표시 및 복사 버튼용)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';

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
if (!ai_check_rate_limit('reply_draft', $id)) {
    ai_json_response(['ok' => false, 'error' => '30초 이내에 동일 상담에 대한 AI 요청을 중복 실행할 수 없습니다. 잠시 후 다시 시도해주세요.'], 429);
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT c.*, cu.name AS cust_name, cu.company, s.brand, s.site_name, s.persona
     FROM consults c
     JOIN customers cu ON cu.id = c.customer_id
     JOIN sites s ON s.id = c.site_id
     WHERE c.id = :id LIMIT 1"
);
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();
if (!$c) {
    ai_json_response(['ok' => false, 'error' => '상담 정보를 찾을 수 없습니다.'], 404);
}

// PII 마스킹 필수 (§7) — 전화/이메일/주소/우편번호는 외부 전송에서 철저히 제외. 이름은 답변 호칭용으로 허용, 메모 및 상세항목 마스킹
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

$system = "당신은 전문 상담원이다. 고객 문의에 대한 정중하고 명확한 답변 '초안'을 작성하라. 존댓말, 과장·허위 금지, 확정 불가한 가격/조건은 '담당자 확인 후 안내'로. 서명 제외. 답변 본문만 출력.";
if (!empty($c['persona'])) {
    $system .= " 브랜드 페르소나 및 톤앤매너 안내: " . $c['persona'];
}

$user = sprintf(
    "고객명: %s 고객님\n브랜드/사이트: %s (%s)\n문의상품: %s (%s)\n문의내용 및 요청사항: %s\n상세항목: %s",
    $c['cust_name'],
    $c['brand'],
    $c['site_name'],
    $c['product_name'] ?? '-',
    $c['category'] ?? '-',
    $maskedMemo ?: '내용 없음',
    $maskedDetail
);

$res = ai_call($system, $user, [
    'feature' => 'reply_draft',
    'target_id' => $id,
    'max_tokens' => 700
]);

if (!empty($res['ok'])) {
    ai_json_response(['ok' => true, 'text' => $res['text']]);
} else {
    ai_json_response(['ok' => false, 'error' => $res['error'] ?? 'AI 답변 초안 생성 실패'], 500);
}
