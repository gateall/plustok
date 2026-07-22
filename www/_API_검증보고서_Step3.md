# ACEP Phase 1 / Step 3 — Backend API 검증 보고서

**SSOT:** `03_SYSTEM/02_API설계.md`  
**Router:** `api/v1/index.php`  
**검증일:** 2026-07-21

---

## 1. MVP 엔드포인트 구현 현황 (19/19)

| # | Method | Path | 상태 |
|---|--------|------|------|
| 1 | POST | `/auth/login` | ✅ |
| 2 | POST | `/auth/logout` | ✅ |
| 3 | POST | `/auth/refresh` | ✅ |
| 4 | GET | `/auth/me` | ✅ |
| 5 | GET | `/chats/rooms` | ✅ Step 3 |
| 6 | GET | `/chats/{id}` | ✅ Step 3 |
| 7 | POST | `/chats/rooms` | ✅ Step 3 |
| 8 | PUT | `/chats/{id}/close` | ✅ Step 3 |
| 9 | PUT | `/chats/{id}/read` | ✅ Step 3 |
| 11 | GET | `/chats/{id}/messages` | ✅ Step 3 |
| 12 | POST | `/chats/{id}/messages` | ✅ Step 3 |
| 14 | GET | `/ai/recommendations/{id}` | ✅ Step 3 |
| 18 | GET | `/customers/{id}` | ✅ |
| 19 | PUT | `/customers/{id}` | ✅ |
| 23 | PUT | `/agents/{id}/status` | ✅ |
| 24 | PUT | `/agents/me/profile` | ✅ |
| 28 | POST | `/files/upload` | ✅ Step 3 |
| 29 | GET | `/files/{id}` | ✅ Step 3 |
| 30 | GET | `/system/health` | ✅ |

**보너스:** `POST /auth/register` (bootstrap)

### V1.5 (Phase 2+)

| Path | 비고 |
|------|------|
| PUT `/chats/{id}/assign` | Phase 2 |
| DELETE messages | Phase 2 |
| AI retry/settings | Phase 3 AI |
| Admin dashboard | Phase 4 |
| GET `/customers`, `/agents` | V1.5 |

---

## 2. Step 3 수정·추가 파일

### Repositories (신규)
- `ChatRoomRepository.php`
- `ChatMessageRepository.php`
- `ReadStatusRepository.php`
- `AiRecommendationRepository.php`
- `AttachmentRepository.php`

### Services (신규)
- `ChatService.php`
- `MessageService.php`
- `AiRecommendationService.php`
- `FileService.php`

### Router
- `api/v1/index.php` — **require 경로 수정** (`../../config`, `../../includes`)
- MVP Chats/Messages/AI/Files 라우트 추가

---

## 3. 아키텍처

```
api/v1/index.php (Router)
    → Middleware (CORS, JWT)
    → Service (비즈니스)
    → Repository (PDO)
    → MariaDB
```

응답 형식: `acep_success()` / `acep_error()` — SSOT §1.1

---

## 4. 알려진 제한 (Step 3 범위)

| 항목 | 상태 |
|------|------|
| AI 상담 요약 (close) | `summary: null` — Phase 3 AI Engine |
| WebSocket broadcast | 미구현 — Phase 4 |
| Rate limit 429 | 파일 기반 RL — Phase 2 |
| Redis health | health API DB only |

---

## 5. 완료 조건

| 항목 | 상태 |
|------|------|
| MVP 19 endpoints | ✅ |
| SSOT 응답 envelope | ✅ |
| JWT protected routes | ✅ |
| Prepared statements | ✅ |
| IDOR room access check | ✅ |
| PII encrypt (customer phone) | ✅ |
| Legacy upload.php 분리 | ✅ (ACEP `attachments` 테이블) |

---

## 6. Step 3 완료 보고

1. **구현 API 수:** MVP 19개 (+ register 1)
2. **신규 추가:** Chats 5, Messages 2, AI 1, Files 2
3. **버그 수정:** `index.php` config/includes 경로
4. **남은 작업:** Step 4 WebSocket, Step 5 AI `ai_call()` 연동, V1.5 endpoints

**Step 3 상태: ✅ MVP Backend API 완료 — 승인 후 Step 4**
