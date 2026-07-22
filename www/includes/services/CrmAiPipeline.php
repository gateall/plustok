<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../ai.php';

/**
 * CRM close AI pipeline — ai_call() 단일 진입점 (Phase 2 컨벤션).
 */
final class CrmAiPipeline
{
    /**
     * @param list<array<string,mixed>> $messages
     * @return array{text: string, from_ai: bool}
     */
    public function summarize(array $room, array $messages, ?string $override = null): array
    {
        if ($override !== null && trim($override) !== '') {
            return ['text' => trim($override), 'from_ai' => false];
        }

        $transcript = $this->buildTranscript($messages);
        if ($transcript === '') {
            return ['text' => $this->fallbackSummary($room), 'from_ai' => false];
        }

        if (acep_is_test_mode()) {
            return ['text' => $this->fallbackSummary($room, $transcript), 'from_ai' => false];
        }

        $system = '채팅 상담 내용을 250자 이상 한국어로 요약하라. 핵심 문의, 결정사항, 다음 액션만. 마크다운 없이 본문만.';
        $res = ai_call($system, $transcript, [
            'feature'    => 'summarize',
            'target_id'  => (string)$room['id'],
            'max_tokens' => 600,
        ]);

        if (!empty($res['ok']) && mb_strlen((string)$res['text']) >= 250) {
            return ['text' => trim((string)$res['text']), 'from_ai' => true];
        }

        return ['text' => $this->fallbackSummary($room, $transcript), 'from_ai' => false];
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @return array<string,mixed>
     */
    public function analyze(array $room, array $messages, string $summaryText): array
    {
        $defaults = $this->defaultAnalysis($room);

        if (acep_is_test_mode()) {
            return $defaults;
        }

        $transcript = $this->buildTranscript($messages);
        $system = <<<EOT
통신/IT CRM 상담 분석 AI. 순수 JSON 객체 1개만 출력.
{
  "category_ai": "인터넷|대표번호|CCTV|TV|홈페이지|플레이스|쇼핑몰|기타",
  "confidence": 0-100,
  "priority": "LOW|NORMAL|HIGH|URGENT",
  "lead_score": 0-100,
  "sentiment": "POSITIVE|NEUTRAL|NEGATIVE",
  "tags": ["#태그"]
}
EOT;
        $user = "문의유형: {$room['inquiry_type']}\n요약: {$summaryText}\n대화:\n{$transcript}";

        $res = ai_call($system, $user, [
            'feature'     => 'analyze',
            'target_id'   => (string)$room['id'],
            'max_tokens'  => 600,
            'json_schema' => [
                'type' => 'object',
                'properties' => [
                    'category_ai' => ['type' => 'string'],
                    'confidence'  => ['type' => 'integer'],
                    'priority'    => ['type' => 'string'],
                    'lead_score'  => ['type' => 'integer'],
                    'sentiment'   => ['type' => 'string'],
                    'tags'        => ['type' => 'array'],
                ],
                'required' => ['category_ai', 'lead_score', 'sentiment'],
            ],
        ]);

        $parsed = is_array($res['json'] ?? null) ? $res['json'] : null;
        if (empty($res['ok']) || !$parsed) {
            return $defaults;
        }

        return $this->normalizeAnalysis($parsed, $defaults);
    }

    /** @param list<array<string,mixed>> $messages */
    private function buildTranscript(array $messages): string
    {
        $lines = [];
        foreach ($messages as $m) {
            $role = (string)($m['sender_type'] ?? 'customer');
            $content = ai_mask_pii((string)($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $lines[] = "[{$role}] {$content}";
        }
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $room */
    private function fallbackSummary(array $room, string $transcript = ''): string
    {
        $subject = (string)($room['subject'] ?? $room['inquiry_type'] ?? '상담');
        $snippet = mb_substr(str_replace("\n", ' ', $transcript), 0, 180);
        $base = "【{$subject}】 고객 문의 상담이 종료되었습니다. ";
        if ($snippet !== '') {
            $base .= "주요 내용: {$snippet}... ";
        }
        $base .= '후속 연락 및 견적 안내가 필요합니다. 상담원 메모 및 CRM 일정을 확인하세요.';
        while (mb_strlen($base) < 250) {
            $base .= ' 추가 검토 예정.';
        }
        return $base;
    }

    /** @param array<string,mixed> $room */
    private function defaultAnalysis(array $room): array
    {
        $inquiry = (string)($room['inquiry_type'] ?? '기타');
        $score = (int)($room['priority_score'] ?? 50);
        return [
            'category_ai' => $this->mapInquiryCategory($inquiry),
            'confidence'  => 60,
            'priority'    => $score >= 80 ? 'HIGH' : 'NORMAL',
            'lead_score'  => max(0, min(100, $score)),
            'sentiment'   => 'NEUTRAL',
            'tags'        => '#ACEP #' . mb_substr($inquiry, 0, 10),
        ];
    }

    /** @param array<string,mixed> $parsed */
    /** @param array<string,mixed> $defaults */
    private function normalizeAnalysis(array $parsed, array $defaults): array
    {
        $categories = ['인터넷', '대표번호', 'CCTV', 'TV', '홈페이지', '플레이스', '쇼핑몰', '기타'];
        $cat = in_array($parsed['category_ai'] ?? '', $categories, true)
            ? $parsed['category_ai'] : $defaults['category_ai'];

        $priorities = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];
        $prio = in_array(strtoupper((string)($parsed['priority'] ?? '')), $priorities, true)
            ? strtoupper((string)$parsed['priority']) : $defaults['priority'];

        $sentiments = ['POSITIVE', 'NEUTRAL', 'NEGATIVE'];
        $sent = in_array(strtoupper((string)($parsed['sentiment'] ?? '')), $sentiments, true)
            ? strtoupper((string)$parsed['sentiment']) : $defaults['sentiment'];

        $tagsArr = is_array($parsed['tags'] ?? null) ? $parsed['tags'] : [];
        $tags = [];
        foreach ($tagsArr as $t) {
            if (is_string($t) && trim($t) !== '') {
                $tStr = trim(strip_tags($t));
                if ($tStr !== '') {
                    $tags[] = $tStr[0] === '#' ? $tStr : '#' . $tStr;
                }
            }
        }
        $tagsStr = $tags !== [] ? implode(' ', array_slice(array_unique($tags), 0, 6)) : (string)$defaults['tags'];

        return [
            'category_ai' => $cat,
            'confidence'  => max(0, min(100, (int)($parsed['confidence'] ?? 60))),
            'priority'    => $prio,
            'lead_score'  => max(0, min(100, (int)($parsed['lead_score'] ?? $defaults['lead_score']))),
            'sentiment'   => $sent,
            'tags'        => $tagsStr,
        ];
    }

    private function mapInquiryCategory(string $inquiry): string
    {
        foreach (['인터넷', 'CCTV', 'TV', '홈페이지', '쇼핑몰'] as $k) {
            if (str_contains($inquiry, $k)) {
                return $k;
            }
        }
        return '기타';
    }
}
