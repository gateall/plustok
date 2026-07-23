<?php
declare(strict_types=1);
/**
 * AI 종합 자동분석 엔드포인트 (POST 전용) - V2.0 Phase 2 STEP 9
 * 상담 내용을 1회 호출로 종합 분석(분류, 긴급도, 계약점수, 감정, 태그)하여 consults 테이블에 저장하고 JSON 반환
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
if (!ai_check_rate_limit('analyze', $id)) {
    ai_json_response(['ok' => false, 'error' => '30초 이내에 동일 상담에 대한 AI 종합분석을 중복 실행할 수 없습니다. 잠시 후 다시 시도해주세요.'], 429);
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

// PII 마스킹 필수 (§7) — 전화/이메일/주소 등 개인정보는 마스킹 처리하여 안전하게 전송
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
당신은 통신/IT CRM 상담 데이터를 분석하는 AI 상담 자동분석 엔진이다.
제공된 고객 문의 내용과 데이터를 종합적으로 분석하여, 오직 아래 JSON 규격에 맞춘 순수 JSON 객체 단 1개만 출력하라. 마크다운(` ```json ` 등) 코드 블록이나 설명글을 절대 포함하지 마라.

JSON 출력 규격:
{
  "category_ai": "인터넷" | "대표번호" | "CCTV" | "TV" | "홈페이지" | "플레이스" | "쇼핑몰" | "기타" 중 하나,
  "confidence": 0~100 사이 정수 (분류에 대한 확신도),
  "priority": "LOW" | "NORMAL" | "HIGH" | "URGENT" 중 하나 (긴급, 당일, 즉시, 장애, 불만 등은 URGENT/HIGH로 판단),
  "lead_score": 0~100 사이 정수 (예산 확보, 구체적 구매의사, 도입일정 구체성에 따른 계약 가망 점수),
  "sentiment": "POSITIVE" | "NEUTRAL" | "NEGATIVE" 중 하나 (호의/칭찬=POSITIVE, 일반문의=NEUTRAL, 불만/화남/이탈우려=NEGATIVE),
  "tags": ["#태그1", "#태그2", ...] 형식의 문자열 배열 (핵심 상품, 통신사, 지역, 조건 등 최대 6개 추출)
}
EOT;

$user = sprintf(
    "고객회사: %s\n사이트/브랜드: %s (%s)\n신청카테고리: %s\n신청상품명: %s\n상담상태: %s\n문의상세: %s\n상담메모: %s",
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
        'category_ai' => ['type' => 'string'],
        'confidence'  => ['type' => 'integer'],
        'priority'    => ['type' => 'string'],
        'lead_score'  => ['type' => 'integer'],
        'sentiment'   => ['type' => 'string'],
        'tags'        => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['category_ai', 'confidence', 'priority', 'lead_score', 'sentiment', 'tags']
];

$res = ai_call($system, $user, [
    'feature'     => 'analyze',
    'target_id'   => $id,
    'max_tokens'  => 600,
    'json_schema' => $jsonSchema
]);

if (empty($res['ok'])) {
    ai_json_response(['ok' => false, 'error' => $res['error'] ?? 'AI 자동분석 호출에 실패했습니다.'], 500);
}

// JSON 파싱 검증
$parsed = $res['json'];
if (!$parsed || !is_array($parsed)) {
    // 마크다운 코드블록 제하고 재파싱 시도
    $cleanText = preg_replace('/^```(?:json)?|```$/m', '', trim($res['text']));
    $parsed = json_decode(trim($cleanText), true);
}

if (!$parsed || !is_array($parsed)) {
    ai_log('analyze', $id, 'claude-opus-4-8', 'error', $res['usage'] ?? [], 'JSON 파싱 실패: ' . substr($res['text'], 0, 200));
    ai_json_response(['ok' => false, 'error' => 'AI 응답을 구조화 JSON으로 변환하지 못했습니다.'], 500);
}

// 검증 및 기본값 적용
$validCategories = ['인터넷', '대표번호', 'CCTV', 'TV', '홈페이지', '플레이스', '쇼핑몰', '기타'];
$categoryAi = in_array($parsed['category_ai'] ?? '', $validCategories, true) ? $parsed['category_ai'] : '기타';

$validPriorities = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];
$priority = in_array(strtoupper((string)($parsed['priority'] ?? '')), $validPriorities, true) ? strtoupper((string)$parsed['priority']) : 'NORMAL';

$validSentiments = ['POSITIVE', 'NEUTRAL', 'NEGATIVE'];
$sentiment = in_array(strtoupper((string)($parsed['sentiment'] ?? '')), $validSentiments, true) ? strtoupper((string)$parsed['sentiment']) : 'NEUTRAL';

$leadScore = max(0, min(100, (int)($parsed['lead_score'] ?? 50)));

$tagsArr = is_array($parsed['tags'] ?? null) ? $parsed['tags'] : [];
$cleanedTags = [];
foreach ($tagsArr as $t) {
    if (is_string($t) && trim($t) !== '') {
        // AI 응답을 그대로 신뢰하지 않는다 — HTML 태그 제거(저장형 XSS 방지, 관리자 화면에 그대로 노출됨)
        $tStr = trim(strip_tags($t));
        if ($tStr === '') { continue; }
        if ($tStr[0] !== '#') { $tStr = '#' . $tStr; }
        $cleanedTags[] = $tStr;
    }
}
$tagsStr = implode(' ', array_slice(array_unique($cleanedTags), 0, 6));

try {
    $pdo->prepare(
        "UPDATE consults 
         SET category_ai = :cat, lead_score = :score, priority = :prio, sentiment = :sent, tags = :tags, ai_analyzed_at = NOW() 
         WHERE id = :id"
    )->execute([
        ':cat'   => $categoryAi,
        ':score' => $leadScore,
        ':prio'  => $priority,
        ':sent'  => $sentiment,
        ':tags'  => $tagsStr,
        ':id'    => $id,
    ]);
} catch (Throwable $e) {
    log_error('ai_analyze_save', $e->getMessage());
    ai_json_response(['ok' => false, 'error' => 'AI 분석은 성공했으나 DB 저장에 실패했습니다: ' . $e->getMessage()], 500);
}

ai_json_response([
    'ok' => true,
    'data' => [
        'category_ai' => $categoryAi,
        'lead_score'  => $leadScore,
        'priority'    => $priority,
        'sentiment'   => $sentiment,
        'tags'        => $tagsStr,
        'analyzed_at' => date('Y-m-d H:i:s')
    ]
]);
