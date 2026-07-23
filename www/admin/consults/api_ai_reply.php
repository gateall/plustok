<?php
declare(strict_types=1);
/**
 * AI 고객 답변 자동 생성 엔드포인트
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';

require_login();
require_role(['super', 'admin', 'agent']);
csrf_check();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ai_json_response(['ok' => false, 'error' => 'POST 요청만 가능합니다.'], 405);
}

$id = (int)($_POST['consult_id'] ?? 0);
if ($id <= 0) {
    ai_json_response(['ok' => false, 'error' => '잘못된 상담 ID입니다.'], 400);
}

if (!ai_check_rate_limit('reply', $id)) {
    ai_json_response(['ok' => false, 'error' => '30초 이내에 답변 초안 생성을 중복 호출할 수 없습니다.'], 429);
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

$system = <<<EOT
당신은 통신/IT 전문 상담원이다.
고객의 문의 사항을 분석하여, 고객에게 보낼 친절하고 명확한 이메일/문자 답변 초안을 작성하라.
작성 규칙:
1. 시작은 "안녕하세요 고객님."으로 할 것.
2. 고객의 요청 사항에 대한 간략한 확인과 함께 구체적인 안내를 포함할 것.
3. 맺음말은 "감사합니다." 등으로 마무리할 것.
4. 오직 아래 JSON 규격에 맞춘 순수 JSON 객체 단 1개만 출력할 것. 마크다운(` ```json ` 등) 코드 블록이나 설명글을 절대 포함하지 마라.
5. content_html 필드 내에 줄바꿈은 <br> 태그나 <p> 태그를 사용하여 Rich Text 형태로 작성할 것.

JSON 출력 규격:
{
  "subject": "답변 이메일의 적절한 제목",
  "content_html": "작성된 답변 내용 (HTML 형식)"
}
EOT;

$user = sprintf(
    "고객회사: %s\n사이트/브랜드: %s (%s)\n신청카테고리: %s\n신청상품명: %s\n상담상태: %s\n문의상세: %s\n고객 요청내용: %s",
    $maskedCompany,
    $c['site_name'],
    $c['brand'],
    $c['category'] ?? '-',
    $c['product_name'] ?? '-',
    $c['status'],
    $maskedDetail,
    $maskedMemo ?: '내용 없음'
);

$jsonSchema = [
    'type' => 'object',
    'properties' => [
        'subject' => ['type' => 'string'],
        'content_html' => ['type' => 'string']
    ],
    'required' => ['subject', 'content_html']
];

$res = ai_call($system, $user, [
    'feature'     => 'reply',
    'target_id'   => $id,
    'max_tokens'  => 1000,
    'json_schema' => $jsonSchema
]);

if (empty($res['ok'])) {
    ai_json_response(['ok' => false, 'error' => $res['error'] ?? 'AI 초안 생성에 실패했습니다.'], 500);
}

$parsed = $res['json'];
if (!$parsed || !is_array($parsed)) {
    $cleanText = preg_replace('/^```(?:json)?|```$/m', '', trim($res['text']));
    $parsed = json_decode(trim($cleanText), true);
}

if (!$parsed || !is_array($parsed) || empty($parsed['content_html'])) {
    ai_log('reply', $id, 'claude-opus-4-8', 'error', $res['usage'] ?? [], 'JSON 파싱 실패: ' . substr($res['text'], 0, 200));
    ai_json_response(['ok' => false, 'error' => 'AI 응답 형식이 올바르지 않습니다.'], 500);
}

ai_json_response([
    'ok' => true,
    'data' => [
        'subject' => htmlspecialchars($parsed['subject'] ?? '문의 답변 드립니다.', ENT_QUOTES, 'UTF-8'),
        'content_html' => $parsed['content_html'] // 에디터에 삽입할 HTML
    ]
]);
