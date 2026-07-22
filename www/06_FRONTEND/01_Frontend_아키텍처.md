# ACEP (PlusTok Enterprise) — Frontend 아키텍처

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 5 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Frontend Platform Team  
**Audience:** Frontend Developers, Architects, QA  

**적용 위치:** `www/frontend/` (React SPA)  
**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 4, PART 10  
**시스템 아키텍처:** [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §10  
**UI/UX:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md)  
**컴포넌트:** [02_UIUX/UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 스택 | React 18 + TypeScript 5 + Vite 5 + TailwindCSS 3.x |
| 빌드 도구 | Vite (ESM, HMR) |
| 실시간 | Socket.io Client 4.x |
| 서버 상태 | TanStack Query (React Query) v5 |
| UI 상태 | Zustand v4 |
| HTTP | fetch wrapper (`api.client.ts`) |
| 1차 MVP 화면 | 상담채팅화면 (`ChatScreen`) |
| 배포 | `dist/` → Nginx static (`acep-static`) |

본 문서는 ACEP Frontend SPA의 **아키텍처·레이어링·인증·클라이언트·환경변수·빌드·개발 프록시·에러 처리·반응형**을 정의한다. STEP 5는 **문서 산출**이며, 실제 `frontend/` 코드는 개발 스프린트에서 본 문서를 SSOT로 구현한다.

---

## 1. 기술 스택

### 1.1 Core Dependencies

| Package | Version | 용도 |
|---------|---------|------|
| `react` | ^18.3.x | UI 렌더링 |
| `react-dom` | ^18.3.x | DOM 바인딩 |
| `typescript` | ^5.4.x | 타입 안전 |
| `vite` | ^5.4.x | dev server, build |
| `@vitejs/plugin-react` | ^4.x | JSX Fast Refresh |
| `tailwindcss` | ^3.4.x | 유틸리티 CSS |
| `@tanstack/react-query` | ^5.x | 서버 상태 캐시 |
| `zustand` | ^4.x | 클라이언트 UI 상태 |
| `socket.io-client` | ^4.7.x | WebSocket |
| `react-router-dom` | ^6.x | 라우팅 |
| `lucide-react` | ^0.x | 아이콘 ([UI_COMPONENTS_GUIDE](../02_UIUX/UI_COMPONENTS_GUIDE.md)) |
| `clsx` / `tailwind-merge` | latest | className 병합 |
| `react-hot-toast` | ^2.x | Toast 알림 |

### 1.2 Dev Dependencies

| Package | 용도 |
|---------|------|
| `@types/react`, `@types/react-dom` | React 타입 |
| `autoprefixer`, `postcss` | Tailwind 파이프라인 |
| `eslint`, `@typescript-eslint/*` | 린트 |
| `vitest`, `@testing-library/react` | 단위/컴포넌트 테스트 |
| `msw` | API mock (통합 테스트) |

### 1.3 스택 선택 근거 (ADR)

| 결정 | 선택 | 근거 |
|------|------|------|
| Bundler | Vite | 빠른 HMR, ESM native |
| CSS | TailwindCSS | UI_COMPONENTS_GUIDE 토큰 매핑 |
| Server State | React Query | rooms/messages/AI 캐시·invalidation |
| Client State | Zustand | activeRoomId, mobileTab — 경량 |
| WS | Socket.io | MASTER, Chat Server SSOT |
| Router | React Router 6 | protected routes, lazy load |

---

## 2. 폴더 구조 (`www/frontend/`)

[03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §10 기준 확장:

```
www/frontend/
├── package.json
├── vite.config.ts
├── tsconfig.json
├── tsconfig.node.json
├── tailwind.config.js
├── postcss.config.js
├── index.html
├── .env.example
├── .env.local                    # gitignore
├── public/
│   └── favicon.ico
├── dist/                         # build output → Nginx acep-static
└── src/
    ├── main.tsx                  # React root, providers
    ├── App.tsx                   # Router shell
    ├── index.css                 # Tailwind directives + CSS vars
    │
    ├── pages/                    # Route-level pages
    │   ├── LoginPage.tsx
    │   ├── ChatScreen.tsx        # 상담채팅 메인 (3패널)
    │   └── NotFoundPage.tsx
    │
    ├── features/                 # Domain feature modules
    │   ├── auth/
    │   │   ├── AuthProvider.tsx
    │   │   └── ProtectedRoute.tsx
    │   ├── chat/
    │   │   ├── ChatListPanel.tsx
    │   │   ├── ChatMessagePanel.tsx
    │   │   ├── AiAssistantPanel.tsx
    │   │   └── ChatFooter.tsx
    │   └── ai/
    │       └── AiPanelSection.tsx
    │
    ├── components/               # Reusable UI (11 + shared)
    │   ├── chat/
    │   │   ├── MessageBubble.tsx
    │   │   ├── InputField.tsx
    │   │   ├── FileUpload.tsx
    │   │   ├── RecommendationCard.tsx
    │   │   ├── CustomerCard.tsx
    │   │   ├── StatusBadge.tsx
    │   │   ├── Tabs.tsx
    │   │   ├── ChatList.tsx
    │   │   ├── AIPanelCard.tsx
    │   │   ├── ActionButton.tsx
    │   │   ├── TypingIndicator.tsx
    │   │   └── index.ts
    │   ├── layout/
    │   │   ├── AppHeader.tsx
    │   │   ├── AppFooter.tsx
    │   │   └── ErrorBoundary.tsx
    │   └── ui/
    │       ├── Skeleton.tsx
    │       ├── ToastProvider.tsx
    │       └── ConnectionBanner.tsx
    │
    ├── hooks/
    │   ├── useSocket.ts
    │   ├── useChatRooms.ts
    │   ├── useMessages.ts
    │   ├── useAiRecommendations.ts
    │   ├── useTyping.ts
    │   ├── useReadReceipt.ts
    │   └── useMediaQuery.ts
    │
    ├── services/
    │   ├── api.client.ts         # REST /api/v1/*
    │   ├── socket.client.ts      # Socket.io singleton
    │   ├── auth.service.ts
    │   └── upload.service.ts
    │
    ├── stores/
    │   └── ui.store.ts           # Zustand: activeRoomId, mobileTab, aiDrawerOpen
    │
    ├── types/
    │   ├── chat.types.ts         # API + WS 공유 타입
    │   ├── api.types.ts          # ApiResponse<T>, ApiError
    │   └── socket-events.ts      # WS event payloads
    │
    ├── utils/
    │   ├── format.ts             # relativeTime, maskPhone
    │   ├── token.ts              # JWT memory storage
    │   └── constants.ts
    │
    └── test/
        ├── setup.ts
        └── mocks/
            └── handlers.ts       # MSW
```

### 2.1 레이어 의존 규칙

```
pages → features → components → hooks → services → types
         ↓              ↓
       stores         utils
```

| 레이어 | 책임 | import 허용 |
|--------|------|-------------|
| **pages** | 라우트 조합, layout breakpoint 분기 | features, components, hooks, stores |
| **features** | 도메인 UI 블록 (패널 단위) | components, hooks, stores |
| **components** | 순수 UI, props/events | utils, types (props only) |
| **hooks** | 데이터 fetch, WS 구독, side effect | services, stores, types |
| **services** | HTTP/WS transport | types, utils |
| **types** | 인터페이스, enum | (의존 없음) |
| **stores** | UI-only global state | types |

**금지:**

- components → hooks/services 직접 호출 (container/presentational 분리)
- services → React import
- types → runtime 코드 import

---

## 3. 애플리케이션 부트스트랩

### 3.1 `main.tsx` Provider Tree

```tsx
// src/main.tsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import App from './App';
import { AuthProvider } from './features/auth/AuthProvider';
import { ErrorBoundary } from './components/layout/ErrorBoundary';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: 1,
      refetchOnWindowFocus: true,
    },
  },
});

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <AuthProvider>
            <App />
            <Toaster position="top-center" toastOptions={{ duration: 4000 }} />
          </AuthProvider>
        </BrowserRouter>
      </QueryClientProvider>
    </ErrorBoundary>
  </React.StrictMode>
);
```

### 3.2 `App.tsx` 라우팅

```tsx
// src/App.tsx
import { Routes, Route, Navigate } from 'react-router-dom';
import { lazy, Suspense } from 'react';
import { ProtectedRoute } from './features/auth/ProtectedRoute';
import LoginPage from './pages/LoginPage';

const ChatScreen = lazy(() => import('./pages/ChatScreen'));

export default function App() {
  return (
    <Suspense fallback={<div className="flex h-screen items-center justify-center">로딩 중...</div>}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route
          path="/chat"
          element={
            <ProtectedRoute>
              <ChatScreen />
            </ProtectedRoute>
          }
        />
        <Route path="/" element={<Navigate to="/chat" replace />} />
        <Route path="*" element={<Navigate to="/chat" replace />} />
      </Routes>
    </Suspense>
  );
}
```

---

## 4. 인증 흐름 (Auth Flow)

### 4.1 JWT 저장 전략

| Token | 저장 위치 | 이유 |
|-------|-----------|------|
| Access Token | Memory (`token.ts` module variable) | XSS surface 최소화 |
| Refresh Token | HttpOnly Cookie `acep_refresh` | JS 접근 불가 |

```typescript
// src/utils/token.ts
let accessToken: string | null = null;

export const tokenStore = {
  get: () => accessToken,
  set: (token: string) => { accessToken = token; },
  clear: () => { accessToken = null; },
};
```

### 4.2 로그인 시퀀스

```
LoginPage
  │ POST /api/v1/auth/login { loginId, password }
  │ ← 200 { accessToken, user }
  │ tokenStore.set(accessToken)
  │ AuthProvider state update
  │ socket.client.connect(accessToken)
  └ navigate('/chat')
```

### 4.3 Token Refresh

```typescript
// services/auth.service.ts
export async function refreshAccessToken(): Promise<string> {
  const res = await fetch(`${import.meta.env.VITE_API_BASE}/auth/refresh`, {
    method: 'POST',
    credentials: 'include', // acep_refresh cookie
  });
  if (!res.ok) throw new AuthError('UNAUTHORIZED');
  const json = await res.json();
  tokenStore.set(json.data.accessToken);
  return json.data.accessToken;
}
```

**트리거:**

1. REST `401` → refresh → 원 요청 1회 재시도
2. WS `connect_error` message `UNAUTHORIZED` → refresh → reconnect
3. Access token exp 5분 전 proactive refresh (optional V1.5)

### 4.4 ProtectedRoute

```tsx
// features/auth/ProtectedRoute.tsx
export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) return <FullPageSpinner />;
  if (!isAuthenticated) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  return <>{children}</>;
}
```

### 4.5 로그아웃

```
ActionButton / Header logout
  │ POST /api/v1/auth/logout (credentials: include)
  │ tokenStore.clear()
  │ socket.disconnect()
  │ queryClient.clear()
  └ navigate('/login')
```

---

## 5. REST Client (`api.client.ts`)

### 5.1 Base Configuration

```typescript
// src/services/api.client.ts
import { tokenStore } from '../utils/token';
import { refreshAccessToken } from './auth.service';
import type { ApiResponse, ApiError } from '../types/api.types';

const BASE = import.meta.env.VITE_API_BASE; // https://host/api/v1

class ApiClient {
  private async request<T>(
    path: string,
    options: RequestInit = {},
    retry = true
  ): Promise<T> {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(options.headers ?? {}),
    };

    const token = tokenStore.get();
    if (token) {
      (headers as Record<string, string>)['Authorization'] = `Bearer ${token}`;
    }

    const res = await fetch(`${BASE}${path}`, {
      ...options,
      headers,
      credentials: 'include',
    });

    if (res.status === 401 && retry) {
      await refreshAccessToken();
      return this.request<T>(path, options, false);
    }

    const json: ApiResponse<T> = await res.json();

    if (!json.success) {
      throw json.error as ApiError;
    }

    return json.data;
  }

  get<T>(path: string) {
    return this.request<T>(path, { method: 'GET' });
  }

  post<T>(path: string, body?: unknown) {
    return this.request<T>(path, {
      method: 'POST',
      body: body ? JSON.stringify(body) : undefined,
    });
  }

  put<T>(path: string, body?: unknown) {
    return this.request<T>(path, {
      method: 'PUT',
      body: body ? JSON.stringify(body) : undefined,
    });
  }
}

export const api = new ApiClient();
```

### 5.2 Chat Domain API Wrappers

| Method | Path | Hook |
|--------|------|------|
| GET | `/chats/rooms` | `useChatRooms` |
| GET | `/chats/{id}/messages` | `useMessages` |
| POST | `/chats/{id}/messages` | `useMessages.send` |
| PUT | `/chats/{id}/read` | `useReadReceipt` |
| GET | `/ai/recommendations/{id}` | `useAiRecommendations` |
| POST | `/files/upload` | `FileUpload` |
| PUT | `/chats/{id}/close` | Footer ActionButton |

상세 스키마: [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)

### 5.3 Error Handling Matrix

| HTTP | code | UI Action |
|------|------|-----------|
| 401 | UNAUTHORIZED | refresh → fail → /login |
| 403 | FORBIDDEN | toast + redirect |
| 404 | ROOM_NOT_FOUND | toast, clear activeRoom |
| 429 | RATE_LIMIT_EXCEEDED | toast 60s |
| 500 | MSG_SEND_FAILED | MessageBubble failed state |
| 503 | AI_ALL_FAILED | AIPanelCard error |

---

## 6. Socket Client (`socket.client.ts`)

### 6.1 Singleton Pattern

```typescript
// src/services/socket.client.ts
import { io, Socket } from 'socket.io-client';
import type { ClientToServerEvents, ServerToClientEvents } from '../types/socket-events';

type AcepSocket = Socket<ServerToClientEvents, ClientToServerEvents>;

let socket: AcepSocket | null = null;

export function getSocket(): AcepSocket | null {
  return socket;
}

export function connectSocket(token: string): AcepSocket {
  if (socket?.connected) return socket;

  const url = import.meta.env.VITE_WS_URL;

  socket = io(url, {
    path: '/socket.io',
    transports: ['websocket', 'polling'],
    auth: { token },
    reconnection: true,
    reconnectionDelay: 1000,
    reconnectionDelayMax: 30000,
  });

  return socket;
}

export function disconnectSocket(): void {
  socket?.disconnect();
  socket = null;
}
```

### 6.2 Event Subscription Lifecycle

```
ChatScreen mount
  → connectSocket(token)
  → useSocket hook registers listeners
  → room:join(activeRoomId)

activeRoomId change
  → room:leave(prev)
  → room:join(next)

ChatScreen unmount
  → room:leave
  → remove listeners (useSocket cleanup)
  → (keep socket connected for app session)
```

프로토콜 SSOT: [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md)

---

## 7. 환경 변수

### 7.1 Vite Environment Files

| File | 용도 |
|------|------|
| `.env` | 공통 default |
| `.env.development` | local dev |
| `.env.production` | prod build |
| `.env.example` | 템플릿 (commit) |

### 7.2 Required Variables

| Variable | Example (dev) | Example (prod) | Description |
|----------|---------------|----------------|-------------|
| `VITE_API_BASE` | `http://localhost:5173/api/v1` | `https://acep.example.com/api/v1` | REST base (proxy or absolute) |
| `VITE_WS_URL` | `http://localhost:5173` | `https://acep.example.com` | Socket.io origin |
| `VITE_APP_TITLE` | `PlusTok Enterprise (Dev)` | `PlusTok Enterprise` | Header title |
| `VITE_ENABLE_MSW` | `true` | `false` | Mock Service Worker |

```bash
# .env.example
VITE_API_BASE=/api/v1
VITE_WS_URL=
VITE_APP_TITLE=PlusTok Enterprise
VITE_ENABLE_MSW=false
```

> **규칙:** Vite는 `VITE_` prefix만 client에 노출. Secret(JWT_SECRET 등)은 **절대** frontend env에 포함하지 않는다.

### 7.3 TypeScript Env Typing

```typescript
// src/vite-env.d.ts
interface ImportMetaEnv {
  readonly VITE_API_BASE: string;
  readonly VITE_WS_URL: string;
  readonly VITE_APP_TITLE: string;
  readonly VITE_ENABLE_MSW: string;
}
```

---

## 8. 빌드 & 배포

### 8.1 Build Commands

```json
{
  "scripts": {
    "dev": "vite",
    "build": "tsc -b && vite build",
    "preview": "vite preview",
    "lint": "eslint src --ext ts,tsx",
    "test": "vitest run"
  }
}
```

### 8.2 Build Output

```
frontend/dist/
├── index.html
├── assets/
│   ├── index-{hash}.js      # code-split chunks
│   ├── index-{hash}.css
│   └── vendor-{hash}.js     # react, socket.io (manualChunks)
└── favicon.ico
```

**Vite `build.rollupOptions.output.manualChunks`:**

```typescript
// vite.config.ts excerpt
manualChunks: {
  vendor: ['react', 'react-dom', 'react-router-dom'],
  query: ['@tanstack/react-query'],
  socket: ['socket.io-client'],
}
```

### 8.3 Nginx Static Serving

Production: [03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §2.3

```nginx
# acep-static container
location / {
    root /usr/share/nginx/html;
    try_files $uri $uri/ /index.html;  # SPA fallback
    expires 1y;
    add_header Cache-Control "public, immutable";
}

location = /index.html {
    expires -1;
    add_header Cache-Control "no-cache";
}
```

**Docker flow:**

```
npm run build → dist/ → COPY dist/ /usr/share/nginx/html → acep-static:8080
```

---

## 9. 개발 프록시 (Dev Proxy)

### 9.1 `vite.config.ts`

```typescript
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [react()],
    server: {
      port: 5173,
      proxy: {
        '/api/v1': {
          target: env.VITE_PROXY_API ?? 'http://localhost:8081',
          changeOrigin: true,
          secure: false,
        },
        '/socket.io': {
          target: env.VITE_PROXY_WS ?? 'http://localhost:3001',
          changeOrigin: true,
          ws: true,
        },
      },
    },
    build: {
      outDir: 'dist',
      sourcemap: mode !== 'production',
    },
  };
});
```

### 9.2 Local Dev Topology

```
Browser :5173
  ├── /api/v1/*     → proxy → PHP Backend :8081
  ├── /socket.io/*  → proxy → Chat Server :3001
  └── /*            → Vite SPA
```

**`.env.development`:**

```bash
VITE_API_BASE=/api/v1
VITE_WS_URL=http://localhost:5173
VITE_PROXY_API=http://localhost:8081
VITE_PROXY_WS=http://localhost:3001
```

---

## 10. Error Boundary & Toast

### 10.1 ErrorBoundary

```tsx
// components/layout/ErrorBoundary.tsx
import { Component, type ReactNode } from 'react';
import { ActionButton } from '../chat/ActionButton';

interface State { hasError: boolean; error?: Error }

export class ErrorBoundary extends Component<{ children: ReactNode }, State> {
  state: State = { hasError: false };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    console.error('[ErrorBoundary]', error, info.componentStack);
    // V2.0: Sentry.captureException(error)
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-gray-50 p-8">
          <h1 className="text-lg font-semibold text-gray-900">오류가 발생했습니다</h1>
          <p className="text-sm text-gray-500">페이지를 새로고침하거나 잠시 후 다시 시도해 주세요.</p>
          <ActionButton label="새로고침" variant="primary" onClick={() => window.location.reload()} />
        </div>
      );
    }
    return this.props.children;
  }
}
```

**적용 범위:**

- Root `ErrorBoundary` — 전역 catch
- `ChatScreen` 내부 패널별 optional boundary (AI 패널 crash isolation)

### 10.2 Toast Notifications

| Event | Toast | Variant |
|-------|-------|---------|
| MSG_SEND_FAILED | "메시지 전송에 실패했습니다" | error |
| AI_ALL_FAILED | "AI 분석을 사용할 수 없습니다" | error |
| RATE_LIMIT_EXCEEDED | "요청이 많습니다. 잠시 후 다시 시도" | warning |
| WS reconnecting | (ConnectionBanner, not toast) | info |
| WS reconnect fail 30s | "연결 끊김. 새로고침해 주세요" | error |
| File upload error | FileUpload inline + toast | error |
| Login fail | "로그인 정보를 확인해 주세요" | error |

```typescript
// utils/toast.ts
import toast from 'react-hot-toast';
import type { ApiError } from '../types/api.types';

export function showApiError(error: ApiError) {
  const messages: Record<string, string> = {
    MSG_SEND_FAILED: '메시지 전송에 실패했습니다.',
    AI_ALL_FAILED: 'AI 분석을 사용할 수 없습니다.',
    RATE_LIMIT_EXCEEDED: '요청이 많습니다. 잠시 후 다시 시도해 주세요.',
    ROOM_NOT_FOUND: '상담방을 찾을 수 없습니다.',
    FORBIDDEN: '접근 권한이 없습니다.',
  };
  toast.error(messages[error.code] ?? error.message);
}
```

### 10.3 ConnectionBanner (WebSocket)

```tsx
// components/ui/ConnectionBanner.tsx
// Header 하단 fixed banner
// socket 'disconnect' → "재연결 중..."
// reconnect fail 30s → "연결 끊김" + refresh button
```

---

## 11. 반응형 브레이크포인트

[01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §2.3, §4

### 11.1 Tailwind Config

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    screens: {
      sm: '375px',   // Mobile baseline
      md: '768px',   // Tablet
      lg: '1280px',  // Desktop 3-panel
      xl: '1440px',  // Design reference width
    },
    extend: {
      colors: {
        primary: { DEFAULT: '#2563EB', hover: '#1D4ED8', light: '#DBEAFE' },
        ai: { header: '#7C3AED', bg: '#F5F3FF' },
      },
      fontFamily: {
        sans: ['Pretendard', 'system-ui', 'sans-serif'],
      },
    },
  },
};
```

### 11.2 Layout Matrix

| Breakpoint | Width | Layout | Navigation |
|------------|-------|--------|------------|
| Mobile | <768px (375 ref) | Single panel + Tabs | 목록/채팅/AI |
| Tablet | 768px~1279px | 2-panel (280+flex) | AI slide-over |
| Desktop | ≥1280px (1440 ref) | 3-panel (320+800+320) | All visible |

### 11.3 `useMediaQuery` Hook

```typescript
// hooks/useMediaQuery.ts
export type LayoutMode = 'mobile' | 'tablet' | 'desktop';

export function useLayoutMode(): LayoutMode {
  const isDesktop = useMediaQuery('(min-width: 1280px)');
  const isTablet = useMediaQuery('(min-width: 768px)');

  if (isDesktop) return 'desktop';
  if (isTablet) return 'tablet';
  return 'mobile';
}
```

**ChatScreen 분기:**

```tsx
const layoutMode = useLayoutMode();

return (
  <div className="flex h-screen flex-col bg-gray-50">
    <AppHeader />
    {layoutMode === 'desktop' && <DesktopThreePanel />}
    {layoutMode === 'tablet' && <TabletTwoPanel />}
    {layoutMode === 'mobile' && <MobileTabView />}
    <AppFooter />
  </div>
);
```

---

## 12. CSS Design Tokens

[UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md) 색상·spacing을 CSS 변수로 mirror:

```css
/* src/index.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

:root {
  --color-primary: #2563EB;
  --color-primary-hover: #1D4ED8;
  --color-primary-light: #DBEAFE;
  --color-success: #16A34A;
  --color-warning: #D97706;
  --color-error: #DC2626;
  --color-bg: #FFFFFF;
  --color-bg-secondary: #F9FAFB;
  --color-border: #E5E7EB;
  --color-text-primary: #111827;
  --color-text-secondary: #6B7280;
  --color-text-muted: #9CA3AF;
  --color-ai-header: #7C3AED;
  --color-ai-bg: #F5F3FF;
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 32px;
  --radius-sm: 4px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
  --shadow-focus: 0 0 0 3px rgba(37,99,235,0.3);
}
```

---

## 13. Zustand UI Store

```typescript
// stores/ui.store.ts
import { create } from 'zustand';

export type MobileTab = 'list' | 'chat' | 'ai';

interface UiState {
  activeRoomId: string | null;
  mobileTab: MobileTab;
  aiDrawerOpen: boolean;
  filterStatus: ('new' | 'active' | 'closed')[];
  searchQuery: string;
  setActiveRoomId: (id: string | null) => void;
  setMobileTab: (tab: MobileTab) => void;
  setAiDrawerOpen: (open: boolean) => void;
  setFilterStatus: (statuses: UiState['filterStatus']) => void;
  setSearchQuery: (q: string) => void;
}

export const useUiStore = create<UiState>((set) => ({
  activeRoomId: null,
  mobileTab: 'list',
  aiDrawerOpen: false,
  filterStatus: ['new', 'active'],
  searchQuery: '',
  setActiveRoomId: (id) => set({ activeRoomId: id }),
  setMobileTab: (tab) => set({ mobileTab: tab }),
  setAiDrawerOpen: (open) => set({ aiDrawerOpen: open }),
  setFilterStatus: (statuses) => set({ filterStatus: statuses }),
  setSearchQuery: (q) => set({ searchQuery: q }),
}));
```

---

## 14. React Query Key Convention

```typescript
// utils/queryKeys.ts
export const queryKeys = {
  rooms: (params?: Record<string, unknown>) => ['chat', 'rooms', params] as const,
  messages: (roomId: string, cursor?: string) => ['chat', 'messages', roomId, cursor] as const,
  aiRecommendations: (roomId: string) => ['ai', 'recommendations', roomId] as const,
  me: () => ['auth', 'me'] as const,
};
```

**Invalidation triggers:**

| Event | Invalidate |
|-------|------------|
| `message:receive` | `messages(roomId)` |
| `room:update` | `rooms()` |
| `ai:update` completed | `aiRecommendations(roomId)` |
| POST message success | `messages`, `rooms` |
| PUT read | `messages(roomId)` |

---

## 15. Performance Guidelines

| 항목 | 전략 | 목표 |
|------|------|------|
| Initial load | lazy ChatScreen, code split | ≤3s |
| ChatList | virtualize >100 items (react-window) | 60fps scroll |
| Messages | infinite scroll, 50/page | p95 <1s |
| WS latency | optimistic send + replace | ≤0.1s perceived |
| Re-renders | React.memo on MessageBubble, ChatListItem | — |
| Images | lazy load attachment thumbnails | — |

---

## 16. Security (Frontend)

| 항목 | 규칙 |
|------|------|
| XSS | React escape default, `dangerouslySetInnerHTML` 금지 |
| Token | Memory only, localStorage access token 금지 |
| CSP | Nginx `Content-Security-Policy` header |
| PII | API masked fields 그대로 표시, decrypt 금지 |
| CORS | dev proxy, prod same-origin |

---

## 17. Testing Strategy

| Level | Tool | Scope |
|-------|------|-------|
| Unit | Vitest | utils, hooks (mock services) |
| Component | Testing Library | 11 components props/a11y |
| Integration | MSW + RTL | useMessages, useSocket |
| E2E | Playwright (V1.5) | TC-001~007 |

---

## 18. 관련 문서

| 문서 | 경로 |
|------|------|
| 컴포넌트 구현 | [02_React_컴포넌트_구현명세.md](02_React_컴포넌트_구현명세.md) |
| Hooks | [03_Hooks_및_상태관리.md](03_Hooks_및_상태관리.md) |
| ChatScreen 통합 | [04_ChatScreen_통합_구현가이드.md](04_ChatScreen_통합_구현가이드.md) |
| WebSocket | [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md) |
| API | [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) |

---

## 부록 A. package.json Template

```json
{
  "name": "acep-frontend",
  "version": "1.0.0",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "tsc -b && vite build",
    "preview": "vite preview",
    "lint": "eslint src --ext ts,tsx",
    "test": "vitest run"
  },
  "dependencies": {
    "@tanstack/react-query": "^5.51.0",
    "clsx": "^2.1.1",
    "lucide-react": "^0.424.0",
    "react": "^18.3.1",
    "react-dom": "^18.3.1",
    "react-hot-toast": "^2.4.1",
    "react-router-dom": "^6.26.0",
    "socket.io-client": "^4.7.5",
    "tailwind-merge": "^2.4.0",
    "zustand": "^4.5.4"
  },
  "devDependencies": {
    "@types/react": "^18.3.3",
    "@types/react-dom": "^18.3.0",
    "@vitejs/plugin-react": "^4.3.1",
    "autoprefixer": "^10.4.19",
    "postcss": "^8.4.40",
    "tailwindcss": "^3.4.7",
    "typescript": "^5.5.4",
    "vite": "^5.4.0",
    "vitest": "^2.0.5"
  }
}
```

## 부록 B. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 5 — Frontend 아키텍처 초안 |

---

**문서 끝 — 구현 시 [02_React_컴포넌트_구현명세.md](02_React_컴포넌트_구현명세.md), [03_Hooks_및_상태관리.md](03_Hooks_및_상태관리.md) 와 함께 사용한다.**
