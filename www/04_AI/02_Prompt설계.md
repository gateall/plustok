# ACEP (PlusTok Enterprise) — Prompt 설계

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Design Phase (STEP 3)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** AI Platform Team  
**Audience:** Backend Developers, Prompt Engineers, QA  

**적용 위치:** `www/`  
**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) (PART 5, PART 7)  
**AI 전략:** [01_AI전략.md](01_AI전략.md) — Router, Failover, 비용 추적  
**AI 엔진:** [03_AI엔진구현.md](03_AI엔진구현.md) — `ai_call()`, 캐싱, 로깅  
**DB 설계:** [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) §5.11 `ai_prompts`  
**UI/UX:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §8 AI Rule  
**구현 참조:** [includes/ai.php](../includes/ai.php) — `ai_call()`, `ai_mask_pii()`

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| AI 역할 수 | **12개** (MASTER PART 5.1 + 분류·다음질문) |
| Prompt ID 규칙 | `PROMPT_{ROLE}_v{MAJOR}.{MINOR}` |
| 저장소 | `ai_prompts` 테이블 (Rule-002) |
| 호출 진입점 | `ai_call($system, $user, $opt)` |
| PII 처리 | `ai_mask_pii()` — 외부 AI 전송 **전** 필수 |
| 출력 정규화 | JSON Schema → `ai_recommendations.content` |

본 문서는 ACEP 상담채팅 플랫폼의 **12가지 AI 역할**에 대한 개발자 즉시 구현 가능한 프롬프트 명세를 정의한다. 작업지시서 STEP 3 필수 8종(상담요약·답변초안·계약확률·감정분석·분류·상품추천·FAQ·다음질문)을 포함하며, 고객분석·문서검색·일정·CRM 등 확장 역할을 추가한다. 모든 프롬프트는 `includes/ai.php`의 `ai_call()` 함수와 호환되며, Failover 체인(Anthropic → OpenAI → Gemini → Grok → DeepSeek)을 통해 실행된다.

---

## 1. 프롬프트 버전 관리 (Rule-002)

### 1.1 원칙

| 규칙 | 상세 |
|------|------|
| Rule-002 | 모든 Prompt는 버전 관리 (`v1.0`, `v1.1`, …) |
| 저장 | `ai_prompts` 테이블 — `prompt_id` UNIQUE |
| 활성화 | 역할(`role`)당 `is_active = 1` **1건만** |
| Rollback | `is_active` 플래그 전환 → 즉시 반영 |
| 변경 이력 | `changelog` 컬럼 + 본 문서 §12 Changelog |

### 1.2 ai_prompts 테이블 매핑

| 컬럼 | 용도 | 예시 |
|------|------|------|
| `id` | UUID PK | `prompt-uuid-001` |
| `role` | AI 역할 키 | `recommend`, `summary`, `sentiment` |
| `version` | 시맨틱 버전 | `v1.0` |
| `prompt_id` | 개발자 참조 ID | `PROMPT_RECOMMEND_v1.0` |
| `content` | System Prompt 전문 | (본 문서 각 역할 §) |
| `is_active` | 활성 플래그 | `1` |
| `changelog` | 변경 요약 | `2026-07-21 초기 작성` |

### 1.3 Prompt 로드 패턴 (PHP)

```php
function ai_load_prompt(string $role): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT prompt_id, content FROM ai_prompts
         WHERE role = :role AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([':role' => $role]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException("Active prompt not found for role: {$role}");
    }
    return $row;
}
```

### 1.4 ai_call 옵션 공통 규격

| `$opt` 키 | 타입 | 설명 |
|-----------|------|------|
| `feature` | string | `ai_logs.feature` — 기능 식별자 |
| `target_id` | int\|string | room_id 또는 entity ID |
| `max_tokens` | int | 역할별 오버라이드 (기본: `config/ai.php` 1024) |
| `model` | string | 프로바이더 모델 강제 지정 (선택) |
| `json_schema` | array | JSON Schema — Structured Output |

---

## 2. PII 마스킹 규칙 (§7)

> **필수:** 외부 AI Provider로 텍스트를 전송하기 **전에** 반드시 `ai_mask_pii()`를 적용한다.

### 2.1 적용 시점

```
chat_messages 조회
    ↓
대화 컨텍스트 문자열 조합
    ↓
ai_mask_pii($context)          ← 이 단계 필수
    ↓
ai_call($system, $maskedUser, $opt)
```

### 2.2 ai_mask_pii() 마스킹 규칙

| 유형 | 패턴 | 마스킹 결과 |
|------|------|-------------|
| 이메일 | `user@domain.com` | `use***@domain.com` |
| 휴대전화 | `010-1234-5678` | `010-****-5678` |
| 유선전화 | `02-123-4567` | `02-***-4567` |
| 상세주소 | `서울시 강남구 테헤란로 123 4층` | `서울시 강남구 테헤란로 123 [상세주소 마스킹]` |

### 2.3 프롬프트 내 PII 지침 (System Prompt 공통 접미)

모든 System Prompt 하단에 다음 문단을 **자동 접합**한다:

```
[개인정보 보호 지침]
- 입력에 [상세주소 마스킹], ***@ 형태의 마스킹된 개인정보가 포함될 수 있습니다.
- 마스킹된 값은 원본을 추측하거나 복원하지 마십시오.
- 출력에 고객의 실제 전화번호, 이메일, 상세주소를 포함하지 마십시오.
- CRM 기록 시에도 마스킹된 형태를 유지하거나 필드명만 기술하십시오.
```

### 2.4 DB PII vs AI PII

| 계층 | 처리 | 함수/모듈 |
|------|------|-----------|
| DB 저장 | AES-256-GCM 암호화 | `PiiEncryptor` (MASTER 9.3) |
| AI 전송 | 정규식 마스킹 | `ai_mask_pii()` |
| UI 표시 | 마스킹 표시 | `CustomerCard.phoneMasked` |

---

## 3. AI 역할 12가지 — 개요

| # | 역할 (EN) | Prompt ID | role 키 | feature 키 | UI 컴포넌트 |
|---|-----------|-----------|---------|------------|-------------|
| 1 | Summarization (상담요약) | `PROMPT_SUMMARY_v1.0` | `summary` | `summarize` | Footer 요약 패널 |
| 2 | Answer Recommendation (답변초안) | `PROMPT_RECOMMEND_v1.0` | `recommend` | `chat_recommend` | `RecommendationCard` |
| 3 | Contract Probability (계약확률) | `PROMPT_CONTRACT_PROB_v1.0` | `contract_prob` | `contract_prob` | `AIPanelCard` 별점 |
| 4 | Sentiment (감정분석) | `PROMPT_SENTIMENT_v1.0` | `sentiment` | `sentiment` | `AIPanelCard` 감정 뱃지 |
| 5 | Classification (분류) | `PROMPT_CLASSIFY_v1.0` | `classify` | `classify` | `AIPanelCard` 카테고리 |
| 6 | Product Recommendation (상품추천) | `PROMPT_PRODUCT_REC_v1.0` | `product_rec` | `product_rec` | `AIPanelCard` |
| 7 | FAQ Search (FAQ 매칭) | `PROMPT_FAQ_SEARCH_v1.0` | `faq_search` | `faq_search` | `AIPanelCard` FAQ |
| 8 | Next Question (다음질문) | `PROMPT_NEXT_QUESTION_v1.0` | `next_question` | `next_question` | `AIPanelCard` 질문 제안 |
| 9 | Customer Analysis | `PROMPT_CUSTOMER_ANALYSIS_v1.0` | `customer_analysis` | `analyze` | `CustomerCard` |
| 10 | Document Search | `PROMPT_DOC_SEARCH_v1.0` | `doc_search` | `doc_search` | `AIPanelCard` / `RecommendationCard` |
| 11 | Appointment | `PROMPT_APPOINTMENT_v1.0` | `appointment` | `appointment` | Footer 일정 |
| 12 | CRM Recording | `PROMPT_CRM_RECORD_v1.0` | `crm_record` | `crm_record` | CRM 연동 (비표시) |

---

## 4. 역할 1 — 상담요약 (Summarization)

### 4.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_SUMMARY_v1.0` |
| **role** | `summary` |
| **feature** | `summarize` |
| **max_tokens** | `2048` |
| **json_schema** | 적용 (아래 §4.4) |
| **UI 바인딩** | Footer "상담요약" 패널, 상담 종료 모달 |
| **트리거** | 상담 종료 (`chat_rooms.status = closed`), N=20 메시지마다 중간 요약 (V1.5) |
| **캐시 TTL** | 상담 종료까지 (`roomId`) — MASTER 5.5 |

### 4.2 System Prompt (전문)

```
당신은 PlusTok Enterprise 상담 플랫폼의 AI 상담 요약 전문가입니다.

## 역할
고객과 상담원 간의 대화 기록을 분석하여, 상담원과 관리자가 빠르게 상황을 파악할 수 있는 구조화된 요약을 작성합니다.

## 출력 언어
- 모든 출력은 **한국어**로 작성합니다.
- 존댓말을 사용하되, 요약문은 간결한 개조식 또는 짧은 문장으로 작성합니다.

## 요약 원칙
1. **사실 기반**: 대화에 명시된 내용만 요약합니다. 추측하지 않습니다.
2. **핵심 우선**: 고객 문의 목적, 상담원 안내 내용, 미해결 사항, 후속 조치를 반드시 포함합니다.
3. **시간순 정리**: 주요 논의 흐름을 시간순으로 3~7개 bullet로 정리합니다.
4. **액션 아이템**: 상담원이 후속 처리해야 할 항목을 별도로 나열합니다.
5. **민감정보**: 전화번호, 이메일, 상세주소는 출력에 포함하지 않습니다.

## 금지 사항
- 대화에 없는 가격, 약속, 일정을 임의로 생성하지 않습니다.
- 고객을 비하하거나 판단하는 표현을 사용하지 않습니다.
- JSON 이외의 텍스트를 출력하지 않습니다.
```

### 4.3 User Prompt Template

```
다음은 상담방 {{room_id}}의 대화 기록입니다.
상담 시작: {{started_at}}
상담 종료: {{ended_at}}
고객 유형: {{customer_type}}
문의 카테고리: {{inquiry_type}}

--- 대화 기록 (시간순) ---
{{messages}}

--- 고객 정보 (마스킹됨) ---
이름: {{customer_name}}
태그: {{customer_tags}}

위 대화를 분석하여 JSON 형식으로 요약해 주세요.
```

### 4.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["summary", "keyPoints", "actionItems", "unresolvedIssues", "followUpRequired"],
  "properties": {
    "summary": {
      "type": "string",
      "description": "3~5문장 전체 요약"
    },
    "keyPoints": {
      "type": "array",
      "items": { "type": "string" },
      "minItems": 3,
      "maxItems": 7,
      "description": "핵심 논의 포인트"
    },
    "actionItems": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["task", "priority", "dueHint"],
        "properties": {
          "task": { "type": "string" },
          "priority": { "type": "string", "enum": ["high", "medium", "low"] },
          "dueHint": { "type": "string", "description": "예: 24시간 이내, 다음 영업일" }
        }
      }
    },
    "unresolvedIssues": {
      "type": "array",
      "items": { "type": "string" }
    },
    "followUpRequired": {
      "type": "boolean"
    },
    "estimatedDurationMin": {
      "type": "integer",
      "description": "상담 소요 시간 추정(분)"
    }
  }
}
```

### 4.5 PHP 호출 예시

```php
$system = ai_load_prompt('summary')['content'];
$user = str_replace(
    ['{{room_id}}', '{{messages}}', '...'],
    [$roomId, ai_mask_pii($messageBlock), '...'],
    $userTemplate
);
$result = ai_call($system, $user, [
    'feature'      => 'summarize',
    'target_id'    => $roomId,
    'max_tokens'   => 2048,
    'json_schema'  => $summarySchema,
]);
```

---

## 5. 역할 2 — 고객분석 (Customer Analysis)

### 5.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_CUSTOMER_ANALYSIS_v1.0` |
| **role** | `customer_analysis` |
| **feature** | `analyze` |
| **max_tokens** | `1024` |
| **UI 바인딩** | `CustomerCard` — tags, profileSummary |
| **트리거** | 고객 메시지 3건 이상, 또는 room 최초 배정 시 |
| **DB 저장** | `customers.tags` JSON 갱신 |

### 5.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 고객 분석 전문가입니다.

## 역할
상담 대화와 고객 프로필 정보를 바탕으로 고객의 배경, 관심사, 구매 의사, 위험 신호를 분석하여 상담원이 맞춤 대응할 수 있도록 프로필 인사이트를 제공합니다.

## 분석 프레임워크
1. **고객 유형**: 신규 / 기존 / 재문의 / VIP / 위험(complaint 잠재)
2. **관심 영역**: 가격, 품질, 속도, A/S, 프로모션, 경쟁사 비교
3. **구매 의사**: 탐색 / 비교 / 결정 임박 / 이탈 위험
4. **의사결정 요인**: 가격 민감도, 브랜드 충성, 긴급성
5. **위험 신호**: 반복 불만, 해지/환불 언급, 법적 표현, 감정 격화

## 태그 규칙
- 태그는 2~6개, 각 2~8자 한국어
- 예: "신규", "고가", "긍정", "가격민감", "설치급함", "VIP", "이탈위험"
- 중복·모순 태그 금지 (예: "긍정" + "불만" 동시 불가)

## 출력
반드시 지정된 JSON Schema만 출력합니다.
```

### 5.3 User Prompt Template

```
상담방 ID: {{room_id}}
문의 카테고리: {{inquiry_type}}
기존 고객 태그: {{existing_tags}}
상담 횟수: {{consultation_count}}

--- 최근 대화 (최대 30건) ---
{{messages}}

--- 고객 기본 정보 ---
이름: {{customer_name}}
최초 접수 채널: {{channel}}

고객 프로필 분석 결과를 JSON으로 출력하세요.
```

### 5.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["customerType", "interestAreas", "purchaseIntent", "tags", "profileSummary", "riskLevel"],
  "properties": {
    "customerType": {
      "type": "string",
      "enum": ["new", "returning", "vip", "at_risk"]
    },
    "interestAreas": {
      "type": "array",
      "items": { "type": "string" },
      "maxItems": 5
    },
    "purchaseIntent": {
      "type": "string",
      "enum": ["exploring", "comparing", "ready_to_buy", "churning"]
    },
    "decisionFactors": {
      "type": "array",
      "items": { "type": "string" }
    },
    "tags": {
      "type": "array",
      "items": { "type": "string" },
      "minItems": 2,
      "maxItems": 6
    },
    "profileSummary": {
      "type": "string",
      "description": "2~3문장 고객 프로필 요약"
    },
    "riskLevel": {
      "type": "string",
      "enum": ["low", "medium", "high"]
    },
    "riskSignals": {
      "type": "array",
      "items": { "type": "string" }
    }
  }
}
```

---

## 6. 역할 3 — 감정분석 (Sentiment Analysis)

### 6.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_SENTIMENT_v1.0` |
| **role** | `sentiment` |
| **feature** | `sentiment` |
| **max_tokens** | `512` |
| **UI 바인딩** | `AIPanelCard` — sentiment badge (긍정/중립/부정) |
| **트리거** | 고객 메시지마다 (BR-AI-001), 답변추천 파이프라인 내 병렬 |
| **Admin 알림** | `negative` + `intensity >= 0.7` → Admin 알림 (V1.5) |

### 6.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 감정 분석 전문가입니다.

## 역할
고객 메시지와 최근 대화 맥락을 분석하여 감정 상태를 분류하고, 상담원이 즉시 대응 우선순위를 판단할 수 있도록 합니다.

## 감정 분류
- **positive**: 만족, 감사, 긍정적 기대, 구매 의향 표현
- **neutral**: 정보 요청, 중립적 질문, 사실 확인
- **negative**: 불만, 짜증, 실망, 화남, 항의, 해지/환불 요구

## 강도 (intensity)
- 0.0 ~ 1.0 (0=매우 약함, 1=매우 강함)
- negative + intensity ≥ 0.7 → 긴급 대응 권고

## 맥락 고려
- 단일 메시지만이 아니라 직전 5건 대화 흐름을 반영
- "네", "알겠습니다" 같은 짧은 응답은 맥락에 따라 neutral 또는 positive

## 출력
JSON Schema만 출력. reasoning은 1문장 이내.
```

### 6.3 User Prompt Template

```
상담방: {{room_id}}

--- 직전 대화 맥락 (최대 5건) ---
{{context_messages}}

--- 분석 대상 메시지 (고객) ---
{{target_message}}

--- 분석 대상 메시지 발송 시각 ---
{{message_timestamp}}

감정 분석 결과를 JSON으로 출력하세요.
```

### 6.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["sentiment", "intensity", "urgency", "reasoning"],
  "properties": {
    "sentiment": {
      "type": "string",
      "enum": ["positive", "neutral", "negative"]
    },
    "intensity": {
      "type": "number",
      "minimum": 0,
      "maximum": 1
    },
    "urgency": {
      "type": "string",
      "enum": ["normal", "high", "critical"]
    },
    "reasoning": {
      "type": "string",
      "maxLength": 200
    },
    "suggestedAgentTone": {
      "type": "string",
      "enum": ["empathetic", "professional", "enthusiastic", "apologetic"]
    }
  }
}
```

---

## 7. 역할 4 — 계약확률 (Contract Probability)

### 7.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_CONTRACT_PROB_v1.0` |
| **role** | `contract_prob` |
| **feature** | `contract_prob` |
| **max_tokens** | `768` |
| **UI 바인딩** | `AIPanelCard` — 별점(20점당 ★1), 점수 0~100, 라벨 |
| **트리거** | 고객 메시지마다 실시간 (캐시 없음 — MASTER 5.5) |
| **DB 저장** | `ai_recommendations.contract_probability`, `chat_rooms.priority_score` |

### 7.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 계약 확률 분석 전문가입니다.

## 역할
고객과 상담원의 대화를 분석하여 **구매/계약 성사 가능성**을 0~100점 정수로 산출합니다.

## 점수 산출 가중치 (UI §8.3)
1. 대화 내용 — 구매 의향 키워드 (40%): "가입", "계약", "견적", "설치 일정", "결제"
2. 고객 태그 — 신규/기존/VIP (20%)
3. 감정 — positive +, negative - (20%)
4. 문의 카테고리 base score (20%): purchase > inquiry > complaint

## 점수 구간 및 라벨
- 70~100: "높음 - 우선 대응"
- 40~69: "보통"
- 0~39: "낮음"

## 키워드 시그널
**상승**: 가격 협의 완료, 일정 확정, 서류 요청, 추천인 언급
**하락**: "다른 업체", "생각해볼게요", "비싸다", "해지", "환불"

## 출력
score는 반드시 0~100 정수. JSON Schema만 출력.
```

### 7.3 User Prompt Template

```
상담방: {{room_id}}
문의 카테고리: {{inquiry_type}}
고객 태그: {{customer_tags}}
현재 감정: {{current_sentiment}}

--- 전체 대화 ---
{{messages}}

--- 이전 계약확률 (참고) ---
{{previous_score}}

계약 확률을 JSON으로 출력하세요.
```

### 7.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["score", "label", "confidence", "factors"],
  "properties": {
    "score": {
      "type": "integer",
      "minimum": 0,
      "maximum": 100
    },
    "label": {
      "type": "string",
      "enum": ["높음 - 우선 대응", "보통", "낮음"]
    },
    "confidence": {
      "type": "number",
      "minimum": 0,
      "maximum": 1
    },
    "factors": {
      "type": "object",
      "properties": {
        "conversationSignals": { "type": "array", "items": { "type": "string" } },
        "customerProfile": { "type": "string" },
        "sentimentImpact": { "type": "string" },
        "categoryBase": { "type": "integer" }
      }
    },
    "trend": {
      "type": "string",
      "enum": ["rising", "stable", "falling"]
    },
    "recommendedAction": {
      "type": "string",
      "description": "상담원 권고 1문장"
    }
  }
}
```

---

## 7A. 역할 5 — 분류 (Classification)

### 7A.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_CLASSIFY_v1.0` |
| **role** | `classify` |
| **feature** | `classify` |
| **max_tokens** | `512` |
| **json_schema** | 적용 |
| **UI 바인딩** | `AIPanelCard` — 상담유형 뱃지 |
| **트리거** | 고객 최초 메시지 또는 카테고리 변경 감지 |

### 7A.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 상담 분류 전문가입니다.

## 역할
고객 문의를 LG U+ PlusTok 상담 유형으로 분류합니다.

## 분류 카테고리
- internet: 인터넷/광랜/와이파이
- phone: 대표번호/전화/070
- cctv: CCTV/보안
- tv: IPTV/셋톱/TV
- mobile: 모바일/유심
- bundle: 결합/패키지
- billing: 요금/청구/납부
- complaint: 불만/AS/해지
- other: 기타

## 원칙
1. 대화 맥락과 키워드 기반 분류
2. confidence 0~1 — 0.7 미만이면 secondaryCategory 제시
3. JSON Schema만 출력
```

### 7A.3 User Prompt Template

```
상담방: {{room_id}}

--- 고객 메시지 ---
{{customer_message}}

--- 대화 맥락 (최근 5건) ---
{{context_messages}}

상담 유형을 JSON으로 분류하세요.
```

### 7A.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["primaryCategory", "confidence", "reasoning"],
  "properties": {
    "primaryCategory": {
      "type": "string",
      "enum": ["internet", "phone", "cctv", "tv", "mobile", "bundle", "billing", "complaint", "other"]
    },
    "secondaryCategory": { "type": "string" },
    "confidence": { "type": "number", "minimum": 0, "maximum": 1 },
    "reasoning": { "type": "string", "maxLength": 200 },
    "keywords": { "type": "array", "items": { "type": "string" } }
  }
}
```

---

## 8. 역할 6 — 답변추천 (Answer Recommendation)

### 8.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_RECOMMEND_v1.0` |
| **role** | `recommend` |
| **feature** | `chat_recommend` |
| **max_tokens** | `1536` |
| **UI 바인딩** | `RecommendationCard` × 최대 3개 (BR-AI-002) |
| **트리거** | 고객 메시지 POST/WS 수신 (BR-AI-001) |
| **캐시 TTL** | 1시간 (`roomId + lastMessageHash`) — MASTER 5.5 |

### 8.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 답변 추천 전문가입니다.

## 역할
고객의 최신 질문과 대화 맥락을 분석하여, 상담원이 **즉시 전송 가능한** 답변 후보 3개를 제시합니다.

## 답변 작성 원칙
1. **정확성**: 대화 맥락과 FAQ/상품 정보에 기반. 불확실하면 "확인 후 안내" 형태 사용
2. **간결성**: 각 답변 1~3문장, 200자 이내
3. **톤**: 친절한 존댓말, 카카오톡 상담 수준의 자연스러운 한국어
4. **차별화**: 3개 답변은 접근 방식이 달라야 함 (직접 답변 / 추가 질문 / 프로모션 안내 등)
5. **금지**: 허위 가격, 미확인 약속, 개인정보 요구

## confidence 점수
- 0.0 ~ 1.0: 답변의 적합성 자신도
- 0.9+: FAQ/정책과 직접 일치
- 0.7~0.89: 맥락 기반 추론
- 0.7 미만: 제시하되 confidence 낮게 표시

## 출력
recommendations 배열 최대 3개. JSON Schema만 출력.
```

### 8.3 User Prompt Template

```
상담방: {{room_id}}
문의 카테고리: {{inquiry_type}}
고객 이름: {{customer_name}}

--- 대화 기록 (최근 20건) ---
{{messages}}

--- 고객 최신 메시지 ---
{{latest_customer_message}}

--- 참고 FAQ (있는 경우) ---
{{faq_context}}

--- 참고 상품 정보 (있는 경우) ---
{{product_context}}

상담원이 전송할 답변 3개를 JSON으로 제시하세요.
```

### 8.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["recommendations", "intent", "contextUsed"],
  "properties": {
    "recommendations": {
      "type": "array",
      "minItems": 1,
      "maxItems": 3,
      "items": {
        "type": "object",
        "required": ["id", "text", "confidence", "approach"],
        "properties": {
          "id": { "type": "string" },
          "text": { "type": "string", "maxLength": 500 },
          "confidence": { "type": "number", "minimum": 0, "maximum": 1 },
          "approach": {
            "type": "string",
            "enum": ["direct_answer", "clarifying_question", "promotion", "empathy_first", "escalation"]
          }
        }
      }
    },
    "intent": {
      "type": "string",
      "enum": ["purchase", "inquiry", "complaint", "support", "other"]
    },
    "contextUsed": {
      "type": "array",
      "items": { "type": "string", "enum": ["conversation", "faq", "product", "policy"] }
    }
  }
}
```

### 8.5 UI 바인딩 (RecommendationCard)

| JSON 필드 | UI Props |
|-----------|----------|
| `recommendations[].text` | `RecommendationCard.text` |
| `recommendations[].confidence` | `RecommendationCard.confidence` (0.9+ 강조) |
| `recommendations[].id` | 클릭 시 `chat_messages.ai_recommendation_id` |
| 클릭 동작 | `InputField` 삽입 → `ActionButton` 전송 (`source=ai_recommendation`) |

---

## 9. 역할 6 — FAQ 검색 (FAQ Search)

### 9.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_FAQ_SEARCH_v1.0` |
| **role** | `faq_search` |
| **feature** | `faq_search` |
| **max_tokens** | `1024` |
| **UI 바인딩** | `AIPanelCard` type="faq" |
| **트리거** | 고객 질문형 메시지, 답변추천 파이프라인 병렬 |
| **캐시 TTL** | 24시간 (`questionEmbedding`) — MASTER 5.5 |
| **RAG 연동** | 임베딩 검색 결과를 `{{faq_candidates}}`에 주입 (V1.5) |

### 9.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI FAQ 매칭 전문가입니다.

## 역할
고객 질문과 가장 관련성 높은 FAQ 항목을 후보 목록에서 선별하고, 상담원이 즉시 참조할 수 있도록 정리합니다.

## 선별 원칙
1. 의미적 유사도 우선 (키워드 일치만으로 선택 금지)
2. 상위 3개 FAQ 반환 (관련성 순)
3. 각 FAQ에 relevanceScore (0~1) 부여
4. 후보 목록에 적합한 FAQ가 없으면 `matched: false` 반환

## 답변 재작성
- FAQ 원문을 그대로 복사하지 말고, 현재 대화 맥락에 맞게 1~2문장으로 다듬어 `adaptedAnswer` 제공
- FAQ에 없는 정보는 추가하지 않음

## 출력
JSON Schema만 출력.
```

### 9.3 User Prompt Template

```
상담방: {{room_id}}

--- 고객 질문 ---
{{customer_question}}

--- FAQ 후보 (RAG 검색 Top 10) ---
{{faq_candidates}}

--- 대화 맥락 (최근 5건) ---
{{context_messages}}

관련 FAQ 상위 3개를 JSON으로 출력하세요.
```

### 9.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["matched", "faq"],
  "properties": {
    "matched": { "type": "boolean" },
    "faq": {
      "type": "array",
      "maxItems": 3,
      "items": {
        "type": "object",
        "required": ["faqId", "question", "adaptedAnswer", "relevanceScore"],
        "properties": {
          "faqId": { "type": "string" },
          "question": { "type": "string" },
          "originalAnswer": { "type": "string" },
          "adaptedAnswer": { "type": "string" },
          "relevanceScore": { "type": "number", "minimum": 0, "maximum": 1 }
        }
      }
    },
    "searchQuery": {
      "type": "string",
      "description": "정규화된 검색 쿼리"
    }
  }
}
```

---

## 9A. 역할 8 — 다음질문 (Next Question)

### 9A.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_NEXT_QUESTION_v1.0` |
| **role** | `next_question` |
| **feature** | `next_question` |
| **max_tokens** | `768` |
| **json_schema** | 적용 |
| **UI 바인딩** | `AIPanelCard` — "다음 질문 제안" |
| **트리거** | 상담원 턴 종료 후, 또는 계약확률 상승 시 |

### 9A.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 상담 코치입니다.

## 역할
상담원이 고객에게 물어볼 **다음 질문** 2~3개를 제안하여 계약 전환 가능성을 높입니다.

## 질문 원칙
1. 개방형 질문 우선 — "예/아니오"만 유도하지 않음
2. 고객 니즈·예산·일정·의사결정자 확인
3. 각 질문 50자 이내, 존댓말
4. 계약 임박 고객: 마감/혜택/설치 일정 질문
5. 이탈 위험: 불만 원인·대안 제시 질문

## 출력
questions 배열 2~3개. JSON Schema만 출력.
```

### 9A.3 User Prompt Template

```
상담방: {{room_id}}
문의 카테고리: {{inquiry_type}}
계약확률: {{contract_probability}}
감정: {{sentiment}}

--- 최근 대화 ---
{{messages}}

--- 고객 태그 ---
{{customer_tags}}

상담원이 물어볼 다음 질문을 JSON으로 제안하세요.
```

### 9A.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["questions", "strategy"],
  "properties": {
    "questions": {
      "type": "array",
      "minItems": 2,
      "maxItems": 3,
      "items": {
        "type": "object",
        "required": ["text", "purpose", "priority"],
        "properties": {
          "text": { "type": "string", "maxLength": 100 },
          "purpose": { "type": "string", "enum": ["needs_discovery", "budget", "timeline", "decision_maker", "objection_handling", "closing"] },
          "priority": { "type": "string", "enum": ["high", "medium", "low"] }
        }
      }
    },
    "strategy": { "type": "string", "description": "질문 전략 1문장" }
  }
}
```

---

## 10. 역할 7 — 문서검색 (Document Search)

### 10.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_DOC_SEARCH_v1.0` |
| **role** | `doc_search` |
| **feature** | `doc_search` |
| **max_tokens** | `1536` |
| **UI 바인딩** | `AIPanelCard` 문서 링크 / `RecommendationCard` 인용 |
| **트리거** | 가격/약관/계약서/상품자료 관련 키워드 감지 |
| **RAG 소스** | 가격표 PDF, 상품 카탈로그, 계약서 템플릿 |

### 10.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 문서 검색·요약 전문가입니다.

## 역할
고객 문의와 관련된 내부 문서(가격표, 상품 자료, 계약서, 약관)를 RAG 검색 결과에서 찾아, 상담원이 참조할 수 있는 요약과 인용을 제공합니다.

## 원칙
1. **출처 명시**: 모든 정보에 documentId, documentTitle, pageOrSection 포함
2. **인용 정확성**: 검색 결과에 있는 내용만 인용. 추측 금지
3. **요약**: 상담원이 30초 내 파악 가능한 2~4문장 요약
4. **링크**: `documentUrl`이 제공된 경우 그대로 전달
5. **규정 준수**: 보험/금융 등 규제 업종은 원문 표현 유지

## 출력
JSON Schema만 출력. snippets는 최대 3개.
```

### 10.3 User Prompt Template

```
상담방: {{room_id}}
문의 카테고리: {{inquiry_type}}

--- 고객 질문/요청 ---
{{customer_query}}

--- RAG 문서 검색 결과 (Top 5 chunk) ---
{{document_chunks}}

--- 대화 맥락 ---
{{context_messages}}

관련 문서 요약 및 인용을 JSON으로 출력하세요.
```

### 10.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["found", "documents"],
  "properties": {
    "found": { "type": "boolean" },
    "documents": {
      "type": "array",
      "maxItems": 3,
      "items": {
        "type": "object",
        "required": ["documentId", "documentTitle", "summary", "snippets"],
        "properties": {
          "documentId": { "type": "string" },
          "documentTitle": { "type": "string" },
          "documentType": {
            "type": "string",
            "enum": ["price_list", "product_catalog", "contract", "terms", "manual", "other"]
          },
          "documentUrl": { "type": "string" },
          "summary": { "type": "string" },
          "snippets": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "text": { "type": "string" },
                "pageOrSection": { "type": "string" },
                "relevanceScore": { "type": "number" }
              }
            }
          }
        }
      }
    },
    "agentBriefing": {
      "type": "string",
      "description": "상담원용 1문장 브리핑"
    }
  }
}
```

---

## 11. 역할 8 — 상품추천 (Product Recommendation)

### 11.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_PRODUCT_REC_v1.0` |
| **role** | `product_rec` |
| **feature** | `product_rec` |
| **max_tokens** | `1024` |
| **UI 바인딩** | `AIPanelCard` 상품 카드 |
| **트리거** | 구매/견적/비교 문의, Cross-selling 기회 |
| **DB 연동** | 상품 카탈로그 API 또는 `products` 테이블 (V2.0) |

### 11.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 상품 추천 전문가입니다.

## 역할
고객의 문의, 예산, 사용 패턴, 대화 맥락을 분석하여 가장 적합한 상품/요금제/패키지를 추천합니다.

## 추천 원칙
1. **고객 니즈 우선**: 명시된 요구사항(속도, 가격, 기기 수 등) 반영
2. **Cross-selling**: 상위 1개 primary + 최대 2개 upsell/cross-sell
3. **근거 제시**: 각 추천에 recommendationReason 1~2문장
4. **가격**: 카탈로그에 있는 가격만 사용. 없으면 "상담원 확인 필요"
5. **비교**: 고객이 비교 중이면 comparisonPoints 제공

## 출력
products 배열 1~3개. JSON Schema만 출력.
```

### 11.3 User Prompt Template

```
상담방: {{room_id}}
문의 카테고리: {{inquiry_type}}
고객 태그: {{customer_tags}}
예산 힌트: {{budget_hint}}

--- 대화 기록 ---
{{messages}}

--- 상품 카탈로그 (후보) ---
{{product_catalog}}

추천 상품을 JSON으로 출력하세요.
```

### 11.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["products", "primaryRecommendation"],
  "properties": {
    "products": {
      "type": "array",
      "minItems": 1,
      "maxItems": 3,
      "items": {
        "type": "object",
        "required": ["productId", "productName", "recommendationReason", "matchScore"],
        "properties": {
          "productId": { "type": "string" },
          "productName": { "type": "string" },
          "price": { "type": "string" },
          "recommendationReason": { "type": "string" },
          "matchScore": { "type": "number", "minimum": 0, "maximum": 1 },
          "type": { "type": "string", "enum": ["primary", "upsell", "cross_sell"] },
          "comparisonPoints": {
            "type": "array",
            "items": { "type": "string" }
          }
        }
      }
    },
    "primaryRecommendation": { "type": "string" },
    "customerNeedsSummary": { "type": "string" }
  }
}
```

---

## 12. 역할 9 — 일정생성 (Appointment Generation)

### 12.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_APPOINTMENT_v1.0` |
| **role** | `appointment` |
| **feature** | `appointment` |
| **max_tokens** | `768` |
| **UI 바인딩** | Footer "일정" 패널, `ActionButton` 일정 확정 |
| **트리거** | 상담 종료, 또는 "방문/설치/상담 예약" 키워드 |
| **외부 연동** | 캘린더 API (V2.0), CRM 일정 필드 |

### 12.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI 일정 생성 전문가입니다.

## 역할
상담 내용을 분석하여 후속 일정(방문, 설치, 재연락, 계약 체결 등)을 제안하고, 구조화된 일정 데이터를 생성합니다.

## 일정 유형
- **visit**: 현장 방문, 모델하우스, 시승
- **installation**: 설치/공사
- **callback**: 전화 재연락
- **contract**: 계약서 작성/체결
- **follow_up**: 일반 후속 상담

## 원칙
1. 대화에서 명시된 날짜/시간 선호 반영
2. 불명확하면 suggestedSlots 2~3개 제안 (영업일 기준)
3. durationMin: 일반 30~60분, 설치 120~240분
4. notes: 상담원/기사 전달 메모

## 출력
JSON Schema만 출력.
```

### 12.3 User Prompt Template

```
상담방: {{room_id}}
상담 종료 시각: {{closed_at}}
고객 이름: {{customer_name}}
문의 카테고리: {{inquiry_type}}

--- 대화 기록 ---
{{messages}}

--- 기존 예약 (있는 경우) ---
{{existing_appointments}}

--- 영업 시간 설정 ---
{{business_hours}}

후속 일정을 JSON으로 제안하세요.
```

### 12.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["appointmentRequired", "appointments"],
  "properties": {
    "appointmentRequired": { "type": "boolean" },
    "appointments": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["type", "title", "durationMin", "priority"],
        "properties": {
          "type": {
            "type": "string",
            "enum": ["visit", "installation", "callback", "contract", "follow_up"]
          },
          "title": { "type": "string" },
          "description": { "type": "string" },
          "durationMin": { "type": "integer" },
          "priority": { "type": "string", "enum": ["high", "medium", "low"] },
          "preferredDateTime": { "type": "string", "description": "ISO8601 또는 null" },
          "suggestedSlots": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "start": { "type": "string" },
                "end": { "type": "string" },
                "label": { "type": "string" }
              }
            }
          },
          "notes": { "type": "string" },
          "assigneeHint": { "type": "string", "enum": ["agent", "technician", "sales"] }
        }
      }
    }
  }
}
```

---

## 13. 역할 10 — CRM 기록 (CRM Recording)

### 13.1 메타데이터

| 항목 | 값 |
|------|-----|
| **Prompt ID** | `PROMPT_CRM_RECORD_v1.0` |
| **role** | `crm_record` |
| **feature** | `crm_record` |
| **max_tokens** | `1536` |
| **UI 바인딩** | 비표시 (백그라운드) — CRM webhook |
| **트리거** | 상담 종료, CRM Recording 수동 트리거 |
| **외부 연동** | `PLUS_TOK_CRM_WEBHOOK_URL`, `customers.external_crm_id` |

### 13.2 System Prompt (전문)

```
당신은 PlusTok Enterprise의 AI CRM 기록 전문가입니다.

## 역할
상담 대화를 CRM 시스템에 저장 가능한 구조화된 레코드로 변환합니다. 상담원의 수작업 CRM 입력을 제거하는 것이 목표입니다.

## CRM 필드 매핑 규칙
1. **contactSummary**: 2~3문장 상담 요약
2. **inquiryType**: MASTER inquiry_type ENUM 값 사용
3. **outcome**: resolved / pending / escalated / lost
4. **nextAction**: 후속 조치 1문장
5. **customFields**: 업종별 커스텀 (인터넷: speed, carrier / 건설: unitType / 자동차: model)
6. **tags**: customers.tags 갱신용 배열
7. **contractValue**: 대화에서 언급된 금액 (없으면 null)

## PII 규칙
- 전화/이메일/주소는 CRM API가 별도 처리. AI 출력에 원문 PII 포함 금지
- external_crm_id는 입력에서 그대로 전달

## 출력
JSON Schema만 출력.
```

### 13.3 User Prompt Template

```
상담방: {{room_id}}
CRM 고객 ID: {{external_crm_id}}
상담 상태: {{room_status}}
문의 카테고리: {{inquiry_type}}

--- 대화 기록 ---
{{messages}}

--- AI 분석 결과 (병합) ---
요약: {{ai_summary}}
계약확률: {{contract_probability}}
감정: {{sentiment}}
고객 태그: {{customer_tags}}

--- CRM 필드 스키마 (업종) ---
{{crm_field_schema}}

CRM 저장용 JSON 레코드를 출력하세요.
```

### 13.4 JSON Output Schema

```json
{
  "type": "object",
  "required": ["contactSummary", "inquiryType", "outcome", "nextAction", "tags"],
  "properties": {
    "externalCrmId": { "type": "string" },
    "contactSummary": { "type": "string" },
    "inquiryType": { "type": "string" },
    "outcome": {
      "type": "string",
      "enum": ["resolved", "pending", "escalated", "lost"]
    },
    "nextAction": { "type": "string" },
    "nextActionDue": { "type": "string", "description": "ISO8601 date" },
    "tags": {
      "type": "array",
      "items": { "type": "string" }
    },
    "contractValue": {
      "type": "number",
      "description": "원 단위, null 가능"
    },
    "customFields": {
      "type": "object",
      "additionalProperties": { "type": "string" }
    },
    "productsDiscussed": {
      "type": "array",
      "items": { "type": "string" }
    },
    "competitorMentioned": {
      "type": "array",
      "items": { "type": "string" }
    },
    "satisfactionEstimate": {
      "type": "string",
      "enum": ["satisfied", "neutral", "dissatisfied", "unknown"]
    }
  }
}
```

---

## 14. 통합 파이프라인 — 역할 조합

### 14.1 고객 메시지 수신 시 (실시간)

```
고객 메시지 INSERT
    ↓
[병렬 1] PROMPT_SENTIMENT_v1.0      → sentiment
[병렬 2] PROMPT_CONTRACT_PROB_v1.0  → contract_probability
[병렬 3] PROMPT_RECOMMEND_v1.0      → recommendations
[병렬 4] PROMPT_FAQ_SEARCH_v1.0     → faq (캐시 확인)
[병렬 5] PROMPT_CUSTOMER_ANALYSIS   → tags (3-msg+ 조건)
    ↓
ai_recommendations INSERT (content JSON 병합)
    ↓
WebSocket ai:update
```

### 14.2 상담 종료 시

```
chat_rooms.status = closed
    ↓
PROMPT_SUMMARY_v1.0
    ↓
PROMPT_APPOINTMENT_v1.0
    ↓
PROMPT_CRM_RECORD_v1.0
    ↓
CRM Webhook POST
```

### 14.3 feature 키 ↔ ai_logs 매핑

| feature | Prompt ID | 설명 |
|---------|-----------|------|
| `summarize` | PROMPT_SUMMARY_v1.0 | 상담 요약 |
| `analyze` | PROMPT_CUSTOMER_ANALYSIS_v1.0 | 고객 분석 |
| `sentiment` | PROMPT_SENTIMENT_v1.0 | 감정 분석 |
| `contract_prob` | PROMPT_CONTRACT_PROB_v1.0 | 계약 확률 |
| `chat_recommend` | PROMPT_RECOMMEND_v1.0 | 답변 추천 |
| `reply` | PROMPT_RECOMMEND_v1.0 | 레거시 V2.0 alias |
| `faq_search` | PROMPT_FAQ_SEARCH_v1.0 | FAQ 검색 |
| `doc_search` | PROMPT_DOC_SEARCH_v1.0 | 문서 검색 |
| `product_rec` | PROMPT_PRODUCT_REC_v1.0 | 상품 추천 |
| `appointment` | PROMPT_APPOINTMENT_v1.0 | 일정 생성 |
| `crm_record` | PROMPT_CRM_RECORD_v1.0 | CRM 기록 |
| `classify` | PROMPT_CLASSIFY_v1.0 | 상담 유형 분류 |
| `next_question` | PROMPT_NEXT_QUESTION_v1.0 | 다음 질문 제안 |
| `auto_failover` | (시스템) | Failover 자동 전환 |
| `active_connection_test` | (Admin) | 연결 테스트 |

---

## 15. ai_prompts 초기 시드 SQL

```sql
INSERT INTO ai_prompts (id, role, version, prompt_id, content, is_active, changelog) VALUES
(UUID(), 'recommend', 'v1.0', 'PROMPT_RECOMMEND_v1.0',
 '(System Prompt 본문 — DB 저장 시 04_AI/01 문서 §8.2 참조)', 1,
 '2026-07-21 STEP3 초기 시드'),
(UUID(), 'summary', 'v1.0', 'PROMPT_SUMMARY_v1.0',
 '(System Prompt 본문 — §4.2 참조)', 1,
 '2026-07-21 STEP3 초기 시드');
-- ... 나머지 8개 role 동일 패턴
```

> **운영 참고:** `content` 컬럼에는 본 문서의 System Prompt 전문 + §2.3 PII 지침 접미를 저장한다.

---

## 16. A/B 테스트 (V2.0)

| 항목 | 규칙 |
|------|------|
| 대상 | `recommend`, `contract_prob` 우선 |
| 방법 | 동일 role에 `v1.0` / `v1.1` 공존, `is_active` 또는 traffic % |
| 측정 | `ai_logs` feature + adoption rate (RecommendationCard 클릭률) |
| Rollback | Admin UI에서 `is_active` 1-click 전환 |

---

## 17. Prompt Changelog

| 버전 | Prompt ID | 날짜 | 변경자 | 변경 내용 |
|------|-----------|------|--------|-----------|
| v1.0 | PROMPT_SUMMARY_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — actionItems, followUpRequired 스키마 |
| v1.0 | PROMPT_CUSTOMER_ANALYSIS_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — tags 2~6개 규칙 |
| v1.0 | PROMPT_SENTIMENT_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — urgency critical 임계값 |
| v1.0 | PROMPT_CONTRACT_PROB_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — UI §8.3 가중치 반영 |
| v1.0 | PROMPT_RECOMMEND_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — PLUS톡 V2.0 마이그레이션 |
| v1.0 | PROMPT_FAQ_SEARCH_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — RAG faq_candidates 주입 |
| v1.0 | PROMPT_DOC_SEARCH_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — documentType ENUM |
| v1.0 | PROMPT_PRODUCT_REC_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — cross_sell 타입 |
| v1.0 | PROMPT_APPOINTMENT_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — suggestedSlots |
| v1.0 | PROMPT_CLASSIFY_v1.0 | 2026-07-21 | AI Platform | STEP 3 — 상담유형 9종 ENUM |
| v1.0 | PROMPT_NEXT_QUESTION_v1.0 | 2026-07-21 | AI Platform | STEP 3 — 계약 전환 질문 2~3개 |
| v1.0 | PROMPT_CRM_RECORD_v1.0 | 2026-07-21 | AI Platform | STEP 3 초기 작성 — customFields 업종별 |

---

## 부록 A. PLUS톡 V2.0 Prompt 마이그레이션

| V2.0 (레거시) | ACEP Prompt ID | 비고 |
|---------------|----------------|------|
| `plus_ai_reply_prompt` | PROMPT_RECOMMEND_v1.0 | 답변 3개 JSON |
| `plus_ai_summary_prompt` | PROMPT_SUMMARY_v1.0 | 상담 종료 요약 |
| `plus_ai_analyze_prompt` | PROMPT_CUSTOMER_ANALYSIS_v1.0 | 고객 분석 |

## 부록 B. 관련 문서

- [01_AI전략.md](01_AI전략.md) — Router, Failover, 비용
- [03_AI엔진구현.md](03_AI엔진구현.md) — `ai_call()`, 캐싱, 통합
- [_AI_INDEX.md](_AI_INDEX.md)
- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 5, PART 7
- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) §5.11

---

**문서 끝 — 본 Prompt 설계는 `ai_call()` 및 `ai_prompts` 테이블과 함께 ACEP AI Router의 입력 계층을 정의한다.**
