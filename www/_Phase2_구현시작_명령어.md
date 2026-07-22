# 🚀 PlusTok V3.0 — Phase 2 구현 시작 명령어

**프로젝트:** PlusTok V1.0 → V3.0 AI Customer Engagement Platform  
**Phase:** 2 (Chat & AI)  
**기간:** 3주 (Day 11~21)  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  
**전제조건:** Phase 1 완료 ✅

---

## 📍 Phase 2 개요

### 목표
- ✅ WebSocket 기반 실시간 채팅 서버 구축
- ✅ AI Router & Failover 체인 구현
- ✅ React 기반 채팅 UI 개발
- ✅ 메시지 동기화 & 읽음표시 구현

### 산출물
```
Backend:
  ├─ WebSocket 서버 (Socket.io)
  ├─ AI Router (Claude → OpenAI → Gemini → Grok)
  ├─ Failover 메커니즘
  ├─ 메시지 캐싱 (Redis)
  └─ AI 로깅 (ai_logs, ai_failover_log)

Frontend:
  ├─ Chat Screen UI (3-panel)
  ├─ Socket.io 클라이언트
  ├─ 메시지 상태 관리
  ├─ 오프라인 메시지 큐
  └─ 읽음표시 동기화
```

### 일정
```
Week 1 (Day 11~15):
  └─ WebSocket 서버 구축 (3일)
  └─ AI Router 구현 (2일)

Week 2 (Day 16~20):
  └─ React Chat UI 개발 (4일)
  └─ 메시지 동기화 (1일)

Week 3 (Day 21):
  └─ 통합 테스트 & 최적화 (1일)
```

---

## 🎯 Cursor에 전달할 명령어

### Step 1: 문서 읽기 (Day 11)

```markdown
Phase 2를 시작하겠습니다!

다음 문서들을 읽어주세요 (2시간):

1. www/04_AI/01_AI전략.md (전체)
   └─ Failover 체인, 프로바이더별 설정 이해

2. www/04_AI/02_Prompt설계.md (전체)
   └─ AI 프롬프트 템플릿 이해

3. www/04_AI/03_AI엔진구현.md (전체)
   └─ ai_call() 함수, 캐싱 전략 이해

4. www/05_CHAT/01_WebSocket설계.md (전체)
   └─ Socket.io 이벤트, 15개 프로토콜 이해

5. www/05_CHAT/02_실시간동기화.md (전체)
   └─ 메시지 상태, 읽음표시, 오프라인 처리 이해

읽고 난 후 "Phase 2 Step 1 완료" 보고해주세요.
```

---

### Step 2: WebSocket 서버 구축 (Day 11~13)

```markdown
🎯 Task: Socket.io 기반 실시간 채팅 서버 구현

📚 참고: www/05_CHAT/01_WebSocket설계.md

기술:
  ├─ Socket.io v4.x
  ├─ Redis (메시지 브로드캐스트)
  └─ Express 미들웨어

구현 단계:

Day 11: Socket.io 서버 초기화
  [ ] Socket.io 설치 & Express 통합
  [ ] JWT 인증 미들웨어 (connect 이벤트)
  [ ] 에러 처리 & 로깅

Day 12: 15개 이벤트 구현
  
  클라이언트 → 서버 (emit):
  [ ] 'join_room' - 채팅방 입장
  [ ] 'send_message' - 메시지 전송
  [ ] 'mark_read' - 읽음표시
  [ ] 'typing_status' - 입력중 표시
  [ ] 'leave_room' - 채팅방 퇴장
  
  서버 → 클라이언트 (broadcast):
  [ ] 'room_joined' - 입장 확인
  [ ] 'receive_message' - 메시지 수신
  [ ] 'message_read' - 읽음표시 수신
  [ ] 'typing_indicator' - 입력중 표시
  [ ] 'user_joined' - 사용자 입장
  [ ] 'user_left' - 사용자 퇴장
  [ ] 'error' - 에러 발생
  [ ] 'reconnect' - 재연결 성공

Day 13: 메시지 저장 & 테스트
  [ ] DB 저장 (chat_messages)
  [ ] 히스토리 로드 (최근 50개)
  [ ] 읽음 상태 저장 (chat_read_status)
  [ ] Socket.io 테스트 (단위 + 통합)

파일 구조:
```
src/
├── websocket/
│   ├── socketServer.js (Socket.io 초기화)
│   ├── events.js (이벤트 핸들러)
│   ├── namespace.js (namespace 관리)
│   └── middleware.js (인증 등)
│
└── services/
    ├── chatService.js (메시지 처리)
    └── websocketService.js (broadcast 등)
```

✅ 검증:
  [ ] 15개 이벤트 모두 작동?
  [ ] 메시지 0.1~0.3초 지연?
  [ ] 동시 100명 연결?
  [ ] 메모리 정상?
```

---

### Step 3: AI Router 구현 (Day 14~15)

```markdown
🎯 Task: AI Router & Failover 메커니즘 구현

📚 참고: www/04_AI/01_AI전략.md

Failover 체인:
  1. Claude (1순위)
  2. OpenAI GPT (2순위)
  3. Google Gemini (3순위)
  4. xAI Grok (4순위)
  5. Error (최후의 수단)

Day 14: AI Router 핵심 로직
  [ ] ai_router.js 생성
  [ ] Claude API 어댑터
  [ ] OpenAI API 어댑터
  [ ] Gemini API 어댑터
  [ ] Grok API 어댑터
  [ ] Failover 로직 (타임아웃/Rate Limit)

Day 15: 캐싱 & 로깅
  [ ] Redis 캐싱 (1시간 TTL)
  [ ] ai_logs 저장 (모든 호출 기록)
  [ ] ai_failover_log 저장 (Failover 이력)
  [ ] 비용 추적
  [ ] 성능 최적화

파일 구조:
```
src/
├── ai/
│   ├── aiRouter.js (메인 라우터)
│   ├── adapters/
│   │   ├── claudeAdapter.js
│   │   ├── openaiAdapter.js
│   │   ├── geminiAdapter.js
│   │   └── grokAdapter.js
│   │
│   ├── cache.js (Redis 캐싱)
│   └── logger.js (로깅)
│
└── constants/
    └── prompts.js (프롬프트 템플릿)
```

핵심 함수:
```javascript
async function ai_call(prompt, options = {}) {
  // 1. 캐시 확인
  // 2. Claude 시도
  //    └─ 성공: 반환
  //    └─ 실패: Failover
  // 3. OpenAI 시도
  //    └─ 성공: 반환
  //    └─ 실패: Failover
  // 4. Gemini 시도
  //    └─ 성공: 반환
  //    └─ 실패: Failover
  // 5. Grok 시도
  //    └─ 성공: 반환
  //    └─ 실패: 에러
  // 6. 캐시 저장
  // 7. 로그 저장
}
```

✅ 검증:
  [ ] Claude 응답시간 < 5초?
  [ ] Failover 자동 작동?
  [ ] 캐시 히트율 >= 30%?
  [ ] 로그 완전?
  [ ] 비용 추적 정확?
```

---

### Step 4: React Chat UI (Day 16~19)

```markdown
🎯 Task: React 기반 채팅 UI 개발

📚 참고: 
  - www/05_CHAT/01_WebSocket설계.md
  - www/06_FRONTEND/04_ChatScreen_통합_구현가이드.md

UI 구조: 3-panel
  ├─ Left Panel (320px): 채팅방 목록
  ├─ Center Panel (800px): 메시지 목록
  └─ Right Panel (320px): AI 추천

Day 16: 채팅방 목록 UI
  [ ] ChatRoomList 컴포넌트
  [ ] 채팅방별 미읽 표시
  [ ] 마지막 메시지 프리뷰
  [ ] 입장 & 새 채팅 기능

Day 17: 메시지 화면 UI
  [ ] MessageList 컴포넌트
  [ ] 메시지 렌더링 (sender별 스타일)
  [ ] 타임스탐프 표시
  [ ] 메시지 상태 표시 (⌛ → ✓ → ✓✓)
  [ ] 읽음표시 표시

Day 18: 메시지 입력 & 전송
  [ ] MessageInput 컴포넌트
  [ ] 입력중 표시 (Typing Indicator)
  [ ] 메시지 전송 & 로컬 렌더링
  [ ] 전송 실패 시 재전송
  [ ] 오프라인 메시지 큐

Day 19: AI 추천 UI
  [ ] AIRecommendation 컴포넌트
  [ ] AI 응답 실시간 스트리밍
  [ ] "분석 중..." 표시
  [ ] 추천 메시지 제안

파일 구조:
```
src/components/
├── Chat/
│   ├── ChatScreen.jsx (메인)
│   ├── ChatRoomList.jsx (좌측 패널)
│   ├── MessageList.jsx (중앙 패널)
│   ├── MessageInput.jsx (입력창)
│   ├── Message.jsx (메시지 항목)
│   └── AIRecommendation.jsx (우측 패널)
│
├── hooks/
│   ├── useSocket.js
│   ├── useMessages.js
│   ├── useAI.js
│   └── useOfflineQueue.js
│
└── utils/
    ├── messageStatus.js
    └── messageFormatter.js
```

✅ 검증:
  [ ] 메시지 렌더링 < 500ms?
  [ ] 입력중 표시 즉시?
  [ ] 읽음표시 100ms?
  [ ] 오프라인 메시지 저장?
  [ ] 반응형 디자인?
```

---

### Step 5: 메시지 동기화 (Day 20)

```markdown
🎯 Task: 메시지 상태 관리 & 동기화

📚 참고: www/05_CHAT/02_실시간동기화.md

구현:
  [ ] 메시지 상태 머신 (작성 → 전송중 → 전송됨 → 읽음)
  [ ] Optimistic Update (로컬 먼저 표시)
  [ ] 충돌 해결 (동시 업데이트)
  [ ] 읽음표시 동기화
  [ ] 오프라인 복구

핵심:
```javascript
// 메시지 상태
const MessageStatus = {
  COMPOSING: 'composing',      // 작성 중
  SENDING: 'sending',          // 전송 중
  SENT: 'sent',                // 전송됨 (한 체크)
  DELIVERED: 'delivered',      // 전달됨 (회색 더블 체크)
  READ: 'read'                 // 읽음 (파란 더블 체크)
}

// 읽음표시 동기화
socket.on('receive_message', (msg) => {
  addMessageToUI(msg)
})

// 사용자가 메시지 보면
socket.emit('mark_read', { message_id: msg.id })

// 상대방도 즉시 업데이트
socket.on('message_read', (data) => {
  updateMessageStatus(data.message_id, 'read')
})
```

✅ 검증:
  [ ] 상태 전이 정확?
  [ ] Optimistic Update 동작?
  [ ] 오프라인 복구 성공?
  [ ] 읽음표시 100ms?
```

---

### Step 6: 통합 테스트 (Day 21)

```markdown
🎯 Task: Phase 2 전체 통합 테스트

테스트 케이스:

1️⃣ Socket.io 연결 테스트
  TC-PHASE2-001: 정상 연결
  TC-PHASE2-002: 인증 실패
  TC-PHASE2-003: 토큰 만료
  TC-PHASE2-004: 네트워크 재연결

2️⃣ 메시지 송수신 테스트
  TC-PHASE2-005: 메시지 전송 (< 300ms)
  TC-PHASE2-006: 메시지 수신
  TC-PHASE2-007: 읽음표시 동기화
  TC-PHASE2-008: 입력중 표시

3️⃣ AI 호출 테스트
  TC-PHASE2-009: Claude 호출 성공
  TC-PHASE2-010: Claude 타임아웃 → OpenAI Failover
  TC-PHASE2-011: 모든 프로바이더 실패 → 에러
  TC-PHASE2-012: 캐시 히트

4️⃣ UI 테스트
  TC-PHASE2-013: 채팅방 목록 렌더링
  TC-PHASE2-014: 메시지 목록 렌더링
  TC-PHASE2-015: 메시지 입력 & 전송
  TC-PHASE2-016: 모바일 반응형

5️⃣ 성능 테스트
  TC-PHASE2-017: 동시 100명 연결
  TC-PHASE2-018: 초당 100개 메시지 처리
  TC-PHASE2-019: 메모리 누수 없음
  TC-PHASE2-020: CPU 사용률 < 50%

✅ 체크리스트:
  [ ] 모든 테스트 PASS
  [ ] 성능 목표 달성
  [ ] 보안 검토 완료
  [ ] 코드 리뷰 승인
```

---

## 📊 Phase 2 체크리스트

### 일일 체크포인트

```
Day 11:
  [ ] 문서 읽기 완료
  
Day 12~13:
  [ ] WebSocket 이벤트 15개 구현
  [ ] 메시지 DB 저장
  [ ] Socket.io 테스트 PASS
  
Day 14~15:
  [ ] AI Router 5개 어댑터
  [ ] Failover 로직
  [ ] 캐싱 & 로깅
  [ ] AI 테스트 PASS
  
Day 16~19:
  [ ] Chat UI 4개 컴포넌트
  [ ] 메시지 입력 & 전송
  [ ] AI 추천 표시
  [ ] React 테스트 PASS
  
Day 20:
  [ ] 메시지 동기화
  [ ] 읽음표시 동기화
  [ ] 오프라인 처리
  
Day 21:
  [ ] 통합 테스트 20개 모두 PASS
  [ ] 성능 목표 달성
  [ ] 코드 리뷰 완료
```

---

## ✅ Phase 2 완료 조건

### Go 조건
```
□ WebSocket 15개 이벤트 모두 작동
□ AI Router 5개 어댑터 구현 완료
□ React Chat UI 완성
□ 메시지 동기화 정상
□ 통합 테스트 20개 모두 PASS
□ 성능 목표 달성 (메시지 < 300ms, AI < 5초)
□ 보안 테스트 완료
□ 코드 리뷰 승인
```

### No-Go 조건
```
❌ 테스트 실패
❌ 성능 목표 미달성
❌ AI Failover 미작동
❌ 메시지 동기화 불완전
❌ 보안 이슈
```

---

## 🎊 Phase 2 완료 후

### 예상 결과
```
✅ 실시간 채팅 서버 완성
✅ AI Router & Failover 완성
✅ React Chat UI 완성
✅ 메시지 동기화 완성

다음: Phase 3 (CRM & Frontend) 준비 완료
예상 시간: 2026-08-25
```

---

*Phase 2 구현 시작 · 2026-07-21*
