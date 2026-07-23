# 작업지시서: 고객용 채팅 위젯 Phase 1 — 최종 점검 (2026-07-22)

**상태:** 코드/배포 전부 완료 · **양방향 실측 1회 — 수동(admin 로그인) 필요**

**최종 점검 실행:** 2026-07-22 (Cursor agent)

---

## 1. 완료 확인된 것

| 항목 | 상태 | 확인 방법 |
|---|---|---|
| 상담 접수 → 채팅방 자동 생성 | ✅ | `api/v1/consult.php` 응답에 `roomId`/`accessToken` 포함 확인 |
| customer_bridge 연결 (레거시 BIGINT ↔ ACEP UUID) | ✅ | `chat_rooms.customer_id`가 FK 제약 통과, `customer_bridge` 테이블에 매핑 존재 |
| 고객 → 상담원 메시지 | ✅ | 고객 위젯에서 보낸 메시지가 `admin/consults/view.php`에 실시간 표시 확인됨 |
| 상담원 → 고객 코드 경로 | ✅ (코드 리뷰) | `view.php`(emit) → `chat-server/message.handler.ts`(브로드캐스트) → `embed/chat-frame.php`(수신) 전부 연결 확인 |
| WebSocket JWT 인증 | ✅ | 신규 발급 토큰으로 Render 소켓 연결 테스트 성공 (`connected`) |
| 폰 스타일 채팅 UI | ✅ | `admin/consults/view.php`에 이미 적용, 배포 완료 |

## 2. 남은 것 — 상담원→고객 실측 1회

**절차:**
1. `https://plustok.mycafe24.com/admin/consults/view.php?no=C202607220017` 채팅창에 아무 메시지나 입력 → 전송
2. 같은 room(`8d987583-e6e2-491a-b41b-34ac53048712`)에 연결된 고객 위젯 쪽에 2~3초 내 표시되는지 확인

**판정:**
- 뜸 → Phase 1 완전 종료
- 안 뜸 → 고객 쪽 F12 Network(WS) 탭에서 `message:receive` 프레임 자체가 오는지, `content` 필드가 있는지 확인

**자동 점검 결과 (2026-07-22):**
| 확인 | 결과 |
|---|---|
| `embed/demo-chat.php` | HTTP 200 ✅ |
| `embed/chat-frame.php` | HTTP 200 ✅ |
| Render `plustok.onrender.com` Socket.io polling | HTTP 200 ✅ |
| `api/v1/health` | `version: 1.5` ✅ |
| `admin/consults/view.php?no=C202607220017` | HTTP 302 → `/admin/` (세션 없음, 로그인 필요) ⏸ |
| Browser MCP E2E | 탭 생성 불가 — 수동 브라우저 테스트 필요 ⏸ |
| Agent→Customer 실측 | **미완료** — 아래 §6 절차로 1회 수행 필요 |

## 3. 기록해둘 사소한 이슈 (Phase 2로 이월, 지금 안 고쳐도 됨)

**고객 위젯 자기 메시지 중복 표시 가능성**
- 원인: 전송 시 선반영(optimistic append) + 서버 브로드캐스트 재수신이 중복될 수 있음
- 해결 방향(나중에): `clientMessageId`로 dedupe, 또는 optimistic append 제거, 또는 `senderId===me` + 이미 있는 id면 skip

## 4. 건드리지 않아도 되는 것

- `frontend/dist` 재빌드/업로드 — `/frontend/#/chat` React 앱(상담원 전용 채팅 화면)에서 이 customer_bridge 경로를 쓸 때만 필요. 지금 `admin/consults/view.php` + `embed/chat-frame.php` 경로는 이미 순수 PHP/JS라 무관.
- `useSocket.tsx` — 위와 같은 이유로 지금 당장 손댈 필요 없음.

## 5. 완료 기준

- [ ] 상담원이 `view.php`에서 보낸 메시지가 고객 위젯에 실시간 표시됨 확인 *(§6 수동 E2E — admin 로그인 필요)*
- [x] (선택) 자기 메시지 중복 표시 이슈를 Phase 2 백로그에 기록 *(§3 기록 완료)*

---

## 6. 수동 E2E 절차 (Phase 1 마지막 1회)

**준비:** Chrome 두 창(또는 일반 + 시크릿) · admin 계정

### A. 고객 위젯 연결 (room `8d987583-e6e2-491a-b41b-34ac53048712`)

상담 `C202607220017` 접수 당시 받은 `roomId`/`accessToken`이 있으면 DevTools 콘솔에서:

```javascript
// https://plustok.mycafe24.com/embed/demo-chat.php 등 plustok 오리진 페이지에서
PlusTokChat.open({
  base: 'https://plustok.mycafe24.com',
  roomId: '8d987583-e6e2-491a-b41b-34ac53048712',
  accessToken: '<접수 응답 accessToken>',
  wsUrl: 'https://plustok.onrender.com'
});
```

`accessToken`이 없으면: `demo-chat.php`에서 **새 상담 접수** → "상담원과 바로 채팅하기" → 해당 room에 admin `view.php`로 들어가 테스트.

### B. 상담원 측

1. https://plustok.mycafe24.com/admin/ 로그인
2. https://plustok.mycafe24.com/admin/consults/view.php?no=C202607220017
3. 하단 채팅 카드 상태가 **「연결됨」** 또는 **「채팅방 입장」** 인지 확인
4. 테스트 메시지 입력 후 전송 (예: `Phase1 E2E agent reply 2026-07-22`)

### C. 판정

| 결과 | 조치 |
|---|---|
| 고객 iframe 2~3초 내 **「상담원」** 말풍선 표시 | §5 첫 항목 `[x]` → **Phase 1 종료** |
| 미표시 | 고객 iframe F12 → WS → `message:receive` 수신 여부·`content` 필드 확인 |
| admin 302/로그인 화면 | admin 세션 후 재시도 |
| 고객 「연결 오류」 | Render chat-server·JWT 만료(accessToken 재발급) 확인 |
