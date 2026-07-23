# 작업지시서: `chat_rooms` 마이그레이션 전 위험 요소 확인 (2026-07-22)

**상태:** ⚠️ **V0.0 실행 보류 권장** — 진행 전 아래 확인 필요
**배경:** 고객용 채팅 위젯(별도 작업지시서 `_작업지시서_고객용채팅위젯.md`)을 위해 `chat_rooms` 테이블이 필요해서 `V0.0 → V1.0.0 → V1.5.0` 순서로 마이그레이션이 제안됨. 그런데 이 계획을 그대로 실행하면 **오늘 하루 종일 고쳐서 정상 작동 확인한 상담관리(consults) 기능 전체가 깨질 위험**이 있음.

---

## 1. 문제 (실행 전 반드시 확인)

`migrations/V0.0__legacy_prepare.sql`:
```sql
RENAME TABLE customers TO crm_customers;
RENAME TABLE attachments TO crm_attachments;
```

이 SQL은 현재 라이브 DB의 `customers` 테이블(BIGINT id, `name`/`phone`/`email`/`company`/`address` 컬럼)을 `crm_customers`로 이름을 바꾼다. 그런데 **`admin/consults/` 하위 파일들은 여전히 `customers`를 직접 참조하고 있음** (오늘 코드 조사로 확인, `crm_customers`로 바뀌어 있지 않음):

```
admin/consults/index.php        (JOIN customers cu ...)
admin/consults/view.php         (JOIN customers cu ...)
admin/consults/ai_reply.php
admin/consults/ai_summary.php
admin/consults/ai_analyze.php
admin/consults/api_ai_reply.php
admin/consults/api_reply_send.php
admin/consults/export.php
admin/consults/send_reply.php
```

**V0.0을 지금 실행하면:**
1. `customers` → `crm_customers` 이름 변경
2. 위 파일들이 전부 `customers` 테이블을 못 찾아 즉시 에러 (상담 목록/상세/상태변경/이메일발송 전멸)
3. V1.0.0이 새로 만드는 `customers`는 **UUID 기반**의 완전히 다른 구조라, 위 파일들을 그냥 `crm_customers`로 이름만 바꿔도 되지만 — **이 작업(파일 9개 수정)이 선행되지 않은 상태**임

---

## 2. 확인 필요 (Cursor에게)

- [ ] V0.0 실행 **전에** 위 9개 파일을 전부 `crm_customers` 참조로 수정 완료했는지?
  - 안 했다면: V0.0 실행을 미루고, 먼저 이 파일들부터 수정
  - 이미 했다면: 이 작업지시서는 기우(false alarm) — 진행해도 됨

## 3. 대안 제안 (더 안전한 경로)

굳이 `customers` 테이블 이름을 바꾸고 UUID 기반 새 `customers`를 만들 필요 없이:

- **`chat_rooms.customer_id`를 BIGINT로 설계**해서 **지금 있는 `customers`(BIGINT) 테이블을 그대로 참조**하게 만들면, V0.0(이름 변경)과 V1.0.0의 UUID `customers` 재생성 자체가 필요 없어짐
- 이러면 `admin/consults/` 9개 파일을 하나도 안 건드리고, `chat_rooms` 테이블만 새로 추가하는 것으로 끝남 — 리스크가 훨씬 낮음
- `agents`, `customer_bridge`(V1.5.0)는 오늘 이미 라이브 DB에 생성 완료 상태(`agents` 테이블 이미 존재 확인됨) — 이 부분은 추가 작업 불필요할 수 있음, `SHOW TABLES LIKE 'chat_rooms'`, `SHOW TABLES LIKE 'customer_bridge'`로 현재 상태부터 확인 권장

## 4. 실행 순서 제안 (수정안)

1. **DB 백업** (phpMyAdmin 내보내기) — 이건 그대로 진행
2. `SHOW TABLES LIKE 'chat_rooms';`, `SHOW TABLES LIKE 'agents';`, `SHOW TABLES LIKE 'customer_bridge';` 로 현재 실제 상태 확인 (문서상 계획이 아니라 라이브 DB 기준)
3. `chat_rooms`가 없다면, **`customer_id BIGINT`로 `customers`(현재 테이블) 참조하는 버전**으로 CREATE TABLE (V1.0.0 원본의 UUID 버전이 아니라 수정판)
4. **V0.0, 원본 V1.0.0의 `customers` 재생성 부분은 실행하지 않음**
5. `chat_rooms` 생성 후, 고객용 채팅 위젯 작업지시서(`_작업지시서_고객용채팅위젯.md`)의 나머지 항목(고객용 JWT 발급, 접근권한 체크 등) 진행

---

## 5. 건드리지 않은 영역

- 오늘 정상화된 상담관리(consults) 전체 흐름 — 이 작업지시서의 목적은 이걸 안 건드리고 chat_rooms만 추가하는 것
