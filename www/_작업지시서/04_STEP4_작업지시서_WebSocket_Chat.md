# 🎯 PlusTok V3.0 WebSocket & 실시간 채팅
## STEP 4 작업지시서 (www 폴더 적용)

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**적용 위치:** E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www/  
**단계:** STEP 4 - WebSocket & Real-time Chat Design  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  
**상태:** 작업 준비 완료  

---

## 📌 STEP 4 목표

**2개 문서 작성 (www/05_CHAT 폴더 내)**
1. ✅ `www/05_CHAT/01_WebSocket설계.md` - Socket.io 프로토콜 & 아키텍처
2. ✅ `www/05_CHAT/02_실시간동기화.md` - 메시지 동기화 & 읽음표시 로직

**산출물:** 약 30~40페이지 규모  
**예상 소요시간:** 3~4시간  
**전제조건:** STEP 1, 2, 3 완료

---

## 📂 폴더 구조 (www 기준)

```
www/
├── 04_AI/                       ← STEP 3 참조
│
├── 05_CHAT/                     ← STEP 4 작성 대상
│   ├── 01_WebSocket설계.md      ← 작성 대상 1
│   ├── 02_실시간동기화.md       ← 작성 대상 2
│   └── _CHAT_INDEX.md
│
├── 06_CRM/                      ← STEP 5 (향후)
├── 07_ADMIN/                    ← STEP 6 (향후)
├── 08_DASHBOARD/                ← STEP 7 (향후)
├── 09_DEVELOPMENT/              ← STEP 8 (향후)
│
└── (기존 www 구조)
```

---

# 🔴 작업 1: www/05_CHAT/01_WebSocket설계.md 작성

## 목표
Socket.io 기반 실시간 양방향 통신 설계  
고가용성, 대규모 동시 접속 (1000명+) 지원

## 파일 위치
```
www/05_CHAT/01_WebSocket설계.md
```

## 문서 헤더
```markdown
# ACEP (PlusTok Enterprise) — WebSocket 설계

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Design Phase (STEP 4)  
**Created:** 2026-07-21  
**Owner:** Chat Architecture Team  

**적용 위치:** `www/` (E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 상위 문서 | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) (PART 4) |
| 참조 문서 | [02_실시간동기화.md](../05_CHAT/02_실시간동기화.md), [../03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) |
| 프로토콜 | WebSocket (Socket.io v4.x) |
| 서버 | Node.js + Express + Socket.io |
| 클라이언트 | React + Socket.io Client |
| 동시 연결 | 1000명+ 지원 |
| 메시지 큐 | Redis (선택사항) |

---
```

## 작성 순서 (11개 섹션)

### 1. 목적(Purpose)
- 고객과 상담원 간 실시간 양방향 통신
- 0.1초 이내 메시지 전송 (카카오톡 수준)
- 채팅 연속성 보장 (오프라인 메시지 큐)
- 자동 재연결 (네트워크 끊김 복구)

### 2. 범위(Scope)
- Socket.io 프로토콜 정의
- 이벤트 목록 (15개 이상)
- 연결 관리 (인증, 재연결)
- 성능 최적화 (압축, 배치)
- 안정성 (에러 처리, 타임아웃)

### 3. 요구사항
- 메시지 지연: 0.1~0.3초
- 동시 연결: 1000명+
- 메모리 효율: 연결당 50KB 이하
- 가용성: 99.9%
- 자동 재연결
- 오프라인 메시지 저장

### 4. WebSocket 아키텍처

#### 4-1. 연결 흐름

```
┌─────────────────────────────────────────────┐
│           Socket.io 연결 흐름                │
├─────────────────────────────────────────────┤
│                                             │
│ 1. 클라이언트 접속                          │
│    ↓                                        │
│    new Socket("https://api.../socket.io")  │
│    - reconnection: true                     │
│    - reconnectionDelay: 1000ms              │
│    - reconnectionDelayMax: 5000ms           │
│    - reconnectionAttempts: Infinity         │
│                                             │
│ 2. 서버 인증 (JWT 토큰)                    │
│    ↓                                        │
│    socket.on('connect', (sid) => {         │
│      // 연결 성공, 클라이언트 ID 획득      │
│    })                                       │
│                                             │
│ 3. 채팅방 입장                             │
│    ↓                                        │
│    socket.emit('join_room', {               │
│      room_id: 123,                          │
│      user_id: 456,                          │
│      user_type: 'agent'                     │
│    })                                       │
│                                             │
│ 4. 서버 확인                               │
│    ↓                                        │
│    socket.on('room_joined', (data) => {    │
│      // 채팅방 입장 완료                   │
│      // 이전 메시지 히스토리 로드           │
│    })                                       │
│                                             │
│ 5. 메시지 송수신                           │
│    ↔ socket.emit('send_message', ...)      │
│    ↔ socket.on('receive_message', ...)     │
│                                             │
│ 6. 연결 종료 (또는 끊김)                   │
│    ↓                                        │
│    socket.disconnect()                      │
│    또는 자동 재연결 시작                    │
│                                             │
└─────────────────────────────────────────────┘
```

#### 4-2. 이벤트 정의 (15개 이상)

```
클라이언트 → 서버 emit:

1. 'connect' (Socket.io 내장)
   - 클라이언트 연결 성공
   - 서버에서 자동 발생

2. 'join_room'
   요청:
   {
     room_id: number,
     user_id: number,
     user_type: 'customer' | 'agent',
     auth_token: string
   }

3. 'send_message'
   요청:
   {
     room_id: number,
     content: string,
     message_type: 'text' | 'image' | 'file',
     file_id?: number,
     metadata?: object
   }
   응답:
   {
     message_id: number,
     timestamp: ISO8601,
     status: 'sent'
   }

4. 'mark_read'
   요청:
   {
     message_id: number,
     room_id: number
   }

5. 'typing_status'
   요청:
   {
     room_id: number,
     is_typing: boolean
   }

6. 'leave_room'
   요청:
   {
     room_id: number
   }

7. 'disconnect' (Socket.io 내장)
   - 클라이언트 연결 끊김

서버 → 클라이언트 emit:

8. 'room_joined'
   {
     room_id: number,
     users: [
       { user_id, user_type, name, avatar_url },
       ...
     ],
     history: [
       { message_id, sender_id, content, created_at },
       ...
     ]
   }

9. 'receive_message'
   {
     message_id: number,
     sender_id: number,
     sender_type: 'customer' | 'agent',
     content: string,
     message_type: string,
     created_at: ISO8601,
     read_at?: ISO8601
   }

10. 'message_read'
    {
      message_id: number,
      read_by_id: number,
      read_at: ISO8601
    }

11. 'typing_indicator'
    {
      room_id: number,
      user_id: number,
      is_typing: boolean
    }

12. 'user_joined'
    {
      room_id: number,
      user_id: number,
      user_type: string,
      timestamp: ISO8601
    }

13. 'user_left'
    {
      room_id: number,
      user_id: number,
      timestamp: ISO8601
    }

14. 'error'
    {
      code: string,
      message: string
    }

15. 'reconnect'
    - 자동 재연결 성공
```

### 5. DB 참조
- `chat_rooms` - 상담방
- `chat_messages` - 메시지
- `chat_read_status` - 읽음 상태
- `websocket_sessions` - 연결 상태 (또는 Redis)

### 6. API 연동
- REST API와 병행 사용
- 히스토리 로드: GET /api/v1/chat/rooms/{id}/messages
- 파일 업로드: POST /api/v1/files/upload

### 7. Business Rule

```
WebSocket 비즈니스 규칙:

1. 연결 관리
   - 로그인 필수 (JWT 토큰)
   - 연결당 고유 socket ID
   - 사용자당 최대 3개 동시 연결

2. 채팅방 입장
   - 권한 확인 (고객: 자신의 방만, 상담원: 할당된 방)
   - 입장 시 최근 50개 메시지 히스토리 로드
   - 온라인 사용자 목록 동기화

3. 메시지 전송
   - 즉시 저장 (DB)
   - 1ms 이내 상대방에게 전송
   - 전송 실패 시 재시도 (최대 3회)

4. 읽음 표시
   - 메시지 클릭 시 자동 표시
   - 더블 체크 마크 표시 (✓✓)
   - 상대방에게 실시간 알림

5. 입력 중 표시
   - 사용자 입력 시작 → 상대방에게 "입력 중..." 표시
   - 3초 입력 없으면 자동 종료
   - 메시지 전송하면 즉시 종료

6. 재연결 정책
   - 연결 끊김 자동 감지
   - 즉시 재연결 시도 (1초)
   - 실패 시 지수 백오프 (1s, 2s, 4s, 8s, 최대 5s)
   - 최대 무제한 재시도

7. 타임아웃
   - 클라이언트 ping/pong: 25초
   - 메시지 응답 대기: 10초
   - 연결 유휴: 60초 (keep-alive)
```

### 8. AI Rule
- AI 메시지도 WebSocket으로 전송
- sender_type = 'ai'로 마킹
- AI 응답 대기 중 "분석 중..." 표시

### 9. Exception 처리

```
에러 상황:

1. 네트워크 끊김
   - 자동 재연결 시도
   - 오프라인 상태 UI 표시
   - 로컬 메시지 큐 저장

2. 인증 실패
   - 연결 거부
   - 로그인 페이지로 이동

3. 권한 없음
   - 채팅방 입장 거부
   - 에러 메시지 표시

4. 타임아웃
   - 서버에서 연결 종료
   - 클라이언트 자동 재연결

5. 서버 과부하
   - 연결 제한 (1000명 초과)
   - 대기 큐 처리 또는 거부

6. 메시지 저장 실패
   - 재시도 (최대 3회)
   - 사용자에게 "재전송" 버튼 제공
```

### 10. Test Case

```
TC-WS-001: 정상 연결
- 클라이언트 연결 → 인증 → 채팅방 입장
- 예상: 연결 성공, 히스토리 로드

TC-WS-002: 메시지 송수신
- 메시지 전송 → 상대방 수신
- 예상: 0.1초 이내 전송

TC-WS-003: 네트워크 끊김 복구
- WiFi 끊김 (5초) → 자동 재연결
- 예상: 3초 내 재연결 완료

TC-WS-004: 동시 다중 연결
- 1000명 동시 입장
- 예상: 메모리 < 50GB, 응답 지연 < 500ms

TC-WS-005: 읽음 표시
- 메시지 클릭 → 상대방 확인
- 예상: 100ms 이내 동기화
```

### 11. Future

```
V4.0:
- 다중 서버 (Socket.io Redis adapter)
- 메시지 암호화 (E2E)
- 오프라인 메시지 푸시 알림

V4.5:
- 음성 채팅 (WebRTC)
- 화면 공유
- 파일 동시 전송 (progress bar)
```

## 검수 기준

- [ ] Socket.io 연결 흐름도
- [ ] 15개 이상 이벤트 정의
- [ ] 각 이벤트 요청/응답 JSON
- [ ] 인증 방식 명시
- [ ] 재연결 정책 상세
- [ ] 성능 목표 (0.1~0.3초)
- [ ] 에러 처리 명확
- [ ] 동시 연결 처리 (1000명+)
- [ ] 총 15~20페이지

---

# 🟢 작업 2: www/05_CHAT/02_실시간동기화.md 작성

## 목표
메시지 동기화 및 읽음표시 로직 상세 설계

## 파일 위치
```
www/05_CHAT/02_실시간동기화.md
```

## 포함 내용

### 1. 목적(Purpose)
- 메시지 일관성 보장
- 읽음표시 실시간 동기화
- 오프라인 메시지 처리
- 메시지 순서 보장

### 2. 범위(Scope)
- 메시지 상태 (전송중/전송됨/실패)
- 읽음 상태 (미읽/읽음)
- 입력중 표시
- 오프라인 큐 처리
- 동기화 충돌 해결

### 3. 요구사항
- 메시지 순서 보장
- 중복 메시지 방지
- 상태 일관성
- 오프라인 복구
- 성능 (50ms 이내)

### 4. 메시지 상태 다이어그램

```
┌─────────────────────────────────────────────┐
│          메시지 생명 주기                   │
├─────────────────────────────────────────────┤
│                                             │
│ 1. 작성 (Composing)                         │
│    사용자가 입력 중                        │
│    저장 안 함                              │
│                                             │
│ 2. 전송 중 (Sending)                        │
│    클라이언트: 로컬 저장 (IndexedDB)       │
│    UI: 회색 메시지 표시                    │
│    상태 아이콘: ⌛ (시계)                   │
│                                             │
│ 3. 전송됨 (Sent)                            │
│    서버: DB 저장                           │
│    타 클라이언트: 수신 완료                │
│    UI: 검은색 메시지 표시                 │
│    상태 아이콘: ✓ (한 체크)                │
│                                             │
│ 4. 수신됨 (Delivered)                      │
│    상대방 클라이언트 수신 완료            │
│    상태 아이콘: ✓✓ (더블 체크, 회색)     │
│                                             │
│ 5. 읽음 (Read)                              │
│    상대방이 메시지 클릭/표시               │
│    상태 아이콘: ✓✓ (더블 체크, 파란색)   │
│    read_at 타임스탬프 기록                 │
│                                             │
└─────────────────────────────────────────────┘

트랜지션:
작성 → 전송중 → 전송됨 → 수신됨 → 읽음

에러 케이스:
전송중 → 실패 (⚠️)
  ↓
 재시도 (수동 또는 자동)
```

### 5. 읽음표시 동기화

```
Flow:
1. 사용자가 메시지 클릭
2. 클라이언트: socket.emit('mark_read', {message_id})
3. 서버: DB 업데이트 (chat_read_status 또는 chat_messages.read_at)
4. 서버: 모든 클라이언트에 broadcast
5. 발신자 클라이언트: ✓✓ (파란색)로 업데이트
6. 수신자 클라이언트: 읽음 표시 확인

응답 시간: 100ms 이내
```

### 6. 입력 중 표시

```
Flow:
1. 사용자 입력 시작
2. 클라이언트: socket.emit('typing_status', {is_typing: true})
3. 서버: 해당 room의 모든 클라이언트에 broadcast
4. 상대방: "입력 중..." 표시

타이밍:
- 입력 시작: 즉시 emit
- 입력 중지: 3초 경과 후 emit({is_typing: false})
- 메시지 전송: 즉시 emit({is_typing: false})

응답 시간: 50ms 이내
```

### 7. 오프라인 메시지 처리

```
시나리오: 고객이 오프라인 중에 메시지 전송

1. 클라이언트: IndexedDB에 로컬 저장
   {
     id: 'temp-1',
     room_id: 123,
     content: '안녕하세요',
     status: 'pending',
     created_at: Date.now(),
     sync_pending: true
   }

2. UI: 메시지 표시 (회색, 재시도 버튼)

3. 네트워크 복구 감지
   - navigator.onLine 또는 Socket.io reconnect

4. 자동 재시도:
   socket.emit('sync_messages', {
     pending_messages: [...],
     last_sync_at: ISO8601
   })

5. 서버:
   - 각 메시지 검증
   - DB 저장
   - 클라이언트에 확인

6. 클라이언트:
   - temp-id를 실제 id로 변경
   - 상태 업데이트 (sent)
   - 로컬 저장소 정리
```

### 8. 동기화 충돌 해결

```
시나리오: 메시지 전송 후 연결 끊김, 재연결 시 상태 불일치

1. 클라이언트가 메시지 전송 (상태: sending)
2. 연결 끊김 (before ack received)
3. 클라이언트는 여전히 'sending' 상태
4. 서버는 이미 DB 저장됨 (상태: sent)

해결 방법:
a) 클라이언트 재연결 시:
   socket.emit('sync_state', {
     room_id: 123,
     last_message_id: 456,
     last_sync_at: ISO8601
   })

b) 서버 응답:
   {
     status: 'synced',
     messages: [
       {
         id: 456,
         status: 'sent',
         read_at: null
       }
     ],
     unread_count: 3
   }

c) 클라이언트: 로컬 상태 업데이트
```

## 검수 기준

- [ ] 메시지 상태 다이어그램
- [ ] 읽음표시 동기화 로직
- [ ] 입력중 표시 구현
- [ ] 오프라인 메시지 처리
- [ ] 충돌 해결 메커니즘
- [ ] 성능 목표 명시
- [ ] 에러 시나리오 포함
- [ ] 총 10~15페이지

---

# 📋 작업 체크리스트

## 작업 1: 01_WebSocket설계.md (www/05_CHAT)
- [ ] 1. 목적(Purpose)
- [ ] 2. 범위(Scope)
- [ ] 3. 요구사항
- [ ] 4. WebSocket 아키텍처:
  - [ ] 연결 흐름도
  - [ ] 15개 이상 이벤트 정의
  - [ ] 각 이벤트 요청/응답 JSON
- [ ] 5. DB 참조
- [ ] 6. API 연동
- [ ] 7. Business Rule
- [ ] 8. AI Rule
- [ ] 9. Exception 처리
- [ ] 10. Test Case (5개 이상)
- [ ] 11. Future 계획
- [ ] **최종 검수** (15~20페이지)

## 작업 2: 02_실시간동기화.md (www/05_CHAT)
- [ ] 1. 목적(Purpose)
- [ ] 2. 범위(Scope)
- [ ] 3. 요구사항
- [ ] 4. 메시지 상태 다이어그램
- [ ] 5. 읽음표시 동기화:
  - [ ] Flow 상세
  - [ ] 응답 시간 SLA
- [ ] 6. 입력중 표시:
  - [ ] Flow 상세
  - [ ] 타이밍 규칙
- [ ] 7. 오프라인 메시지:
  - [ ] IndexedDB 사용
  - [ ] 자동 재시도
- [ ] 8. 동기화 충돌 해결
- [ ] 9. (Exception/Rule/Test 필요시)
- [ ] **최종 검수** (10~15페이지)

---

# 🎁 산출물

**예상 산출물:**
```
총 2개 문서 (모두 www/05_CHAT 폴더 내)
약 30~40페이지
Markdown 형식
개발자가 즉시 구현 가능한 수준
```

**구성:**
- www/05_CHAT/01_WebSocket설계.md (15~20페이지)
  - Socket.io 아키텍처
  - 15개+ 이벤트 정의
  - 동시 접속 1000명+ 지원

- www/05_CHAT/02_실시간동기화.md (10~15페이지)
  - 메시지 상태 관리
  - 읽음표시 동기화
  - 오프라인 메시지 처리

---

# 🚀 Cursor 작업 방법

**Cursor에 다음과 같이 입력:**

```
STEP 4 작업지시서 기준으로 다음 2개 문서를 www/05_CHAT 폴더 내에 작성해줘:

1. www/05_CHAT/01_WebSocket설계.md (15~20페이지)
   - Socket.io 프로토콜 및 연결 흐름
   - 15개 이상 이벤트 정의 (클라이언트→서버, 서버→클라이언트)
   - 동시 1000명+ 지원
   - 자동 재연결 정책
   - 성능 목표 (0.1~0.3초)

2. www/05_CHAT/02_실시간동기화.md (10~15페이지)
   - 메시지 상태 다이어그램 (작성→전송중→전송됨→읽음)
   - 읽음표시 동기화 로직
   - 입력중 표시 구현
   - 오프라인 메시지 처리 (IndexedDB)
   - 동기화 충돌 해결

모든 내용을 11개 섹션 구조로 작성하고,
STEP 1~3 문서 형식과 일관성 있게 작성해줘.
```

---

# 🎯 다음 단계 (STEP 5, 6)

STEP 4 완료 후:

**3차 작업 (STEP 5, 6):**
- [ ] 06_CRM/01_CRM통합.md (상담 → CRM 자동화)
- [ ] 07_ADMIN/01_관리자대시보드.md (통계, 상담원 관리)

---

**STEP 4 준비 완료! Cursor에서 작업 시작하세요!** 🚀
