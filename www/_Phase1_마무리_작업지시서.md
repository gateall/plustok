# 🎯 Phase 1 완료 작업지시서 (85% → 100%)

**프로젝트:** PlusTok V3.0  
**현재 상태:** Phase 1 85% 완료  
**목표:** Phase 1 100% 완료  
**예상 소요시간:** 3~4일  
**작성일:** 2026-07-21  

---

## 📊 현재 상태 분석

### ✅ 완료된 것 (85%)
```
✅ DB Migration: 14개 테이블 스키마 완성
   ├─ customers, agents, chat_rooms, chat_messages
   ├─ ai_keys, ai_logs, ai_failover_log
   ├─ consults, schedules, notifications
   └─ audit_logs, settings, websocket_sessions 등

✅ MVP API: 19개 엔드포인트 구현
   ├─ Auth: register, login, refresh, logout (4개)
   ├─ Customers: CRUD (5개)
   ├─ Agents: CRUD + status (4개)
   ├─ Chat rooms: basic (3개)
   └─ Files: basic (3개)

✅ Frontend 초기화
   ├─ React 프로젝트 설정
   ├─ Component 폴더 구조
   └─ Router 기본 설정

✅ React Shell
   ├─ 기본 레이아웃
   ├─ Navigation
   └─ 페이지 라우팅
```

### ❌ 남은 것 (15%)
```
❌ Test 작성 (Backend)
   ├─ PHPUnit: API 테스트 (19개 엔드포인트)
   ├─ 데이터 검증 테스트
   ├─ 에러 처리 테스트
   └─ 통합 테스트

❌ Test 작성 (Frontend)
   ├─ Vitest/Jest: 컴포넌트 테스트
   ├─ 상호작용 테스트
   └─ 통합 테스트

❌ V1.5 Endpoints (11개)
   ├─ Messages: send, get (2개)
   ├─ Notifications: get, update (2개)
   ├─ Settings: get, update (2개)
   ├─ Search: customers, chats (2개)
   ├─ Dashboard: stats (1개)
   └─ Health check (1개)

❌ Production DB Migration
   ├─ 프로덕션 환경 설정
   ├─ 마이그레이션 실행
   ├─ 데이터 검증
   └─ Backup 절차
```

---

## 🎯 남은 15% 완료하는 작업 순서

### Task 1: Backend Test 작성 (PHPUnit) - 2~3일

```markdown
목표: 19개 API 엔드포인트 모두 테스트

현재 상황: API 구현 완료 → 테스트 작성 필요

Day 1: 테스트 환경 설정

[ ] PHPUnit 설치 & 설정
    ├─ composer require phpunit/phpunit --dev
    ├─ phpunit.xml 작성
    └─ 테스트 데이터베이스 설정

[ ] 테스트 기본 구조
    ├─ tests/Unit/ (단위 테스트)
    ├─ tests/Feature/ (기능 테스트)
    └─ tests/fixtures/ (테스트 데이터)

[ ] Trait 작성
    ├─ WithFaker
    ├─ WithAuth
    └─ WithDatabase

파일 구조:
```
tests/
├── Feature/
│   ├── AuthTest.php
│   ├── CustomerTest.php
│   ├── AgentTest.php
│   ├── ChatRoomTest.php
│   ├── MessageTest.php
│   ├── NotificationTest.php
│   ├── SettingTest.php
│   ├── SearchTest.php
│   └── DashboardTest.php
│
├── Unit/
│   ├── Models/
│   ├── Services/
│   └── Utils/
│
└── Traits/
    ├── WithFaker.php
    ├── WithAuth.php
    └── WithDatabase.php
```

Day 2~3: 테스트 케이스 작성

Auth 테스트 (4개 API):
```php
// tests/Feature/AuthTest.php
class AuthTest extends TestCase {
    
    /** @test */
    public function user_can_register() {
        $response = $this->post('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'name' => 'Test User'
        ]);
        
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success', 'data' => ['id', 'email', 'name']
        ]);
    }
    
    /** @test */
    public function user_can_login() {
        $user = User::factory()->create(['password' => 'password123']);
        
        $response = $this->post('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
    }
    
    // 더 많은 테스트...
}
```

Customer 테스트 (5개 API):
```php
class CustomerTest extends TestCase {
    
    protected $user;
    
    protected function setUp(): void {
        parent::setUp();
        $this->user = User::factory()->create();
    }
    
    /** @test */
    public function can_list_customers() {
        $response = $this->actingAs($this->user)
            ->get('/api/v1/customers');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'data' => []
        ]);
    }
    
    // CRUD 테스트...
}
```

[ ] Auth 테스트 (4개)
    ├─ register
    ├─ login
    ├─ refresh
    └─ logout

[ ] Customer 테스트 (5개)
    ├─ list
    ├─ show
    ├─ create
    ├─ update
    └─ delete

[ ] Agent 테스트 (4개)
[ ] ChatRoom 테스트 (3개)
[ ] File 테스트 (3개)

✅ 검증:
  [ ] 모든 테스트 PASS
  [ ] 커버리지 >= 80%
  [ ] 실행 속도 < 30초
```

---

### Task 2: Frontend Test 작성 (Vitest/Jest) - 2일

```markdown
목표: React 컴포넌트 테스트

Day 1: 테스트 환경 설정

[ ] Vitest 또는 Jest 설치
    ├─ npm install vitest @testing-library/react --save-dev
    ├─ vitest.config.ts 작성
    └─ setup.ts 작성

[ ] 테스트 기본 구조
    ├─ tests/unit/ (단위 테스트)
    ├─ tests/integration/ (통합 테스트)
    └─ tests/fixtures/ (테스트 데이터)

파일 구조:
```
tests/
├── unit/
│   ├── components/
│   │   ├── Button.test.tsx
│   │   ├── Form.test.tsx
│   │   ├── Navigation.test.tsx
│   │   └── ...
│   │
│   ├── hooks/
│   │   ├── useAuth.test.ts
│   │   ├── useCustomers.test.ts
│   │   └── ...
│   │
│   └── utils/
│       └── ...
│
├── integration/
│   ├── LoginFlow.test.tsx
│   ├── CustomerManagement.test.tsx
│   └── ...
│
└── fixtures/
    ├── mockData.ts
    └── mockHandlers.ts
```

Day 2: 테스트 케이스 작성

컴포넌트 테스트:
```typescript
// tests/unit/components/Button.test.tsx
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Button from '@/components/Button'

describe('Button', () => {
  it('renders button with text', () => {
    render(<Button>Click me</Button>)
    expect(screen.getByText('Click me')).toBeInTheDocument()
  })
  
  it('calls onClick handler', async () => {
    const handleClick = vi.fn()
    render(<Button onClick={handleClick}>Click</Button>)
    
    await userEvent.click(screen.getByText('Click'))
    expect(handleClick).toHaveBeenCalled()
  })
  
  it('disables button when disabled prop is true', () => {
    render(<Button disabled>Disabled</Button>)
    expect(screen.getByText('Disabled')).toBeDisabled()
  })
})
```

Hook 테스트:
```typescript
// tests/unit/hooks/useAuth.test.ts
import { renderHook, act } from '@testing-library/react'
import useAuth from '@/hooks/useAuth'

describe('useAuth', () => {
  it('initializes with no user', () => {
    const { result } = renderHook(() => useAuth())
    expect(result.current.user).toBeNull()
  })
  
  it('logs in user', async () => {
    const { result } = renderHook(() => useAuth())
    
    await act(async () => {
      await result.current.login('test@example.com', 'password')
    })
    
    expect(result.current.user).toBeDefined()
    expect(result.current.isAuthenticated).toBe(true)
  })
})
```

[ ] Button, Form, Input 테스트 (3개)
[ ] Navigation, Layout 테스트 (2개)
[ ] useAuth, useCustomers Hook 테스트 (2개)
[ ] 통합 테스트 (2개)

✅ 검증:
  [ ] 모든 테스트 PASS
  [ ] 커버리지 >= 70%
  [ ] 스냅샷 검증
```

---

### Task 3: V1.5 Endpoints 구현 (11개) - 1.5일

```markdown
목표: 추가 API 엔드포인트 11개 구현

현재 API: 19개 → 추가: 11개 → 총: 30개

새 엔드포인트:

1️⃣ Messages (2개)
  [ ] POST /api/v1/chat/messages - 메시지 전송
      요청:
      {
        "room_id": 123,
        "content": "메시지",
        "type": "text"
      }
      응답:
      {
        "success": true,
        "data": {
          "id": 1,
          "room_id": 123,
          "sender_id": 456,
          "content": "메시지",
          "created_at": "2026-07-21T..."
        }
      }
  
  [ ] GET /api/v1/chat/rooms/{id}/messages?limit=50&offset=0
      응답:
      {
        "success": true,
        "data": [
          { "id": 1, "sender_id": 456, "content": "..." },
          ...
        ],
        "total": 100,
        "limit": 50,
        "offset": 0
      }

2️⃣ Notifications (2개)
  [ ] GET /api/v1/notifications?unread_only=true
  [ ] PUT /api/v1/notifications/{id}/read

3️⃣ Settings (2개)
  [ ] GET /api/v1/settings - 사용자 설정 조회
  [ ] PUT /api/v1/settings - 사용자 설정 수정

4️⃣ Search (2개)
  [ ] GET /api/v1/search/customers?q=term
  [ ] GET /api/v1/search/chats?q=term

5️⃣ Dashboard (1개)
  [ ] GET /api/v1/dashboard/stats
      응답:
      {
        "success": true,
        "data": {
          "active_chats": 5,
          "total_customers": 100,
          "avg_response_time": 45,
          "today_messages": 250
        }
      }

6️⃣ Health Check (1개)
  [ ] GET /api/v1/health
      응답:
      {
        "status": "ok",
        "timestamp": "2026-07-21T...",
        "version": "1.5"
      }

구현 기준:
  [ ] 표준 응답 형식 준수
  [ ] 권한 검증 포함
  [ ] 입력 검증 포함
  [ ] 에러 처리 포함
  [ ] DB 쿼리 최적화
  [ ] 로깅 포함

파일 구조:
```
src/
├── routes/
│   ├── messages.php
│   ├── notifications.php
│   ├── settings.php
│   ├── search.php
│   ├── dashboard.php
│   └── health.php
│
└── controllers/
    ├── MessageController.php
    ├── NotificationController.php
    ├── SettingController.php
    ├── SearchController.php
    ├── DashboardController.php
    └── HealthController.php
```

✅ 검증:
  [ ] 11개 API 모두 구현?
  [ ] 표준 응답 형식?
  [ ] 테스트 작성?
  [ ] API 문서 업데이트?
```

---

### Task 4: Production DB Migration - 1일

```markdown
목표: 프로덕션 환경 준비

사전 체크리스트:
[ ] 프로덕션 서버 준비?
[ ] DB 인스턴스 생성? (AWS RDS 또는 유사)
[ ] 환경 변수 설정?
[ ] 백업 정책 수립?

Step 1: DB 연결 설정
```
.env.production:
DATABASE_URL=postgresql://user:password@prod-db.example.com:5432/plusok_prod
REDIS_URL=redis://prod-redis.example.com:6379
APP_ENV=production
LOG_LEVEL=info
```

Step 2: 마이그레이션 실행
```bash
# 1. 현재 DB 백업
pg_dump -h dev-db -U user -d plusok > backup_2026-07-21.sql

# 2. 프로덕션 DB 마이그레이션
php artisan migrate --database=production

# 3. Seed 데이터 실행 (필요시)
php artisan db:seed --database=production

# 4. 데이터 검증
php artisan db:check
```

Step 3: 데이터 검증
```php
// scripts/validate_production.php
$tables = ['customers', 'agents', 'chat_rooms', ...];
foreach ($tables as $table) {
    $count = DB::table($table)->count();
    echo "$table: $count rows\n";
}

// 무결성 검사
$orphaned = DB::table('chat_messages')
    ->whereNotIn('room_id', DB::table('chat_rooms')->pluck('id'))
    ->count();
echo "Orphaned messages: $orphaned\n";
```

Step 4: 백업 절차 설정
```bash
# 일일 백업 크론 설정
0 2 * * * pg_dump -h prod-db -U user -d plusok_prod > /backups/plusok_$(date +\%Y\%m\%d).sql
```

[ ] DB 연결 설정
[ ] 마이그레이션 실행
[ ] 데이터 검증 완료
[ ] 백업 절차 설정
[ ] Monitoring 설정
[ ] Log 확인

✅ 검증:
  [ ] 모든 테이블 생성?
  [ ] 데이터 무결성?
  [ ] 백업 동작?
  [ ] 성능 목표?
```

---

## 📋 작업 일정 (3~4일)

```
Day 1: Task 1 시작 (PHPUnit 설정 + 일부 테스트)
Day 2: Task 1 완료 + Task 2 시작 (Frontend 테스트)
Day 3: Task 2 완료 + Task 3 (V1.5 Endpoints)
Day 4: Task 3 완료 + Task 4 (Production DB)
```

---

## ✅ Phase 1 완료 조건

```
[ ] Backend 테스트: 19개 API 모두 테스트 PASS
[ ] Frontend 테스트: 주요 컴포넌트 테스트 PASS
[ ] V1.5 Endpoints: 11개 API 구현 & 테스트 완료
[ ] Production DB: 마이그레이션 & 검증 완료
[ ] 전체 테스트: 커버리지 >= 80%
[ ] 코드 리뷰: 승인 완료
[ ] CI/CD: 모든 파이프라인 GREEN

🎯 최종 판정: Phase 1 100% ✅ → Phase 2 시작 가능
```

---

## 🚀 Cursor에 전달할 명령어

```markdown
Phase 1 마무리 작업을 시작하겠습니다!

현재 상태:
- DB Migration: 완료 ✅
- MVP API (19개): 완료 ✅
- React Shell: 완료 ✅
- Test 작성: 시작 필요 ⏳

다음 작업 (3~4일):

Task 1: Backend Test (PHPUnit)
- 19개 API 엔드포인트 테스트
- 커버리지 >= 80%
- 모든 테스트 PASS

Task 2: Frontend Test (Vitest/Jest)
- React 컴포넌트 테스트
- Hook 테스트
- 통합 테스트

Task 3: V1.5 Endpoints (11개)
- Messages API (2개)
- Notifications (2개)
- Settings (2개)
- Search (2개)
- Dashboard (1개)
- Health Check (1개)

Task 4: Production DB
- DB 연결 설정
- 마이그레이션 실행
- 데이터 검증
- 백업 설정

위 순서대로 진행해주세요!
```

---

## 🎊 Phase 1 완료 후

```
✅ 전체 API 30개 완성 (19 + 11)
✅ 테스트 커버리지 80% 이상
✅ Production DB 준비 완료
✅ CI/CD 파이프라인 GREEN

📍 다음: Phase 2 (Chat & AI) 시작
   ├─ WebSocket 서버
   ├─ AI Router & Failover
   ├─ React Chat UI
   └─ 예상 3주

🎯 최종 완료: 2026-08-25
```

---

*Phase 1 완료 작업지시서 · 2026-07-21*
