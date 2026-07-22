# Frontend `/frontend/login` 404 — 진단 및 해결 (2026-07-22)

**상태:** 코드 수정 완료 · **FTP 재배포 필요**  
**대상 URL:** `https://plustok.mycafe24.com/frontend/`

---

## 1. 원인 (확정)

원인 **2가지**가 동시에 존재했습니다.

| # | 원인 | 증거 |
|---|------|------|
| 1 | **FTP 중첩 `dist/` + dev `index.html` 잔존** | `/frontend/index.html` → dev HTML (`/src/main.tsx` 참조, 빈 페이지). `/frontend/dist/index.html` → 정상 production 빌드 |
| 2 | **Cafe24 mod_rewrite 미작동** | `/frontend/index.html` 200, `/frontend/login` Apache 404 (`Content-Type: iso-8859-1`) — `.htaccess` rewrite 미적용 |

### 원격 curl 진단 (2026-07-22)

```
GET /frontend/index.html              → 200 (dev index — 잘못된 파일)
GET /frontend/dist/index.html         → 200 (production build — dist 중첩)
GET /frontend/assets/index-*.js       → 200
GET /frontend/                        → 200 (dev index)
GET /frontend/login                   → 404 (Apache, React 미도달)
```

**결론:** FTP 구조 정리 + HashRouter 전환(rewrite 불필요)으로 해결.

---

## 2. 코드 변경 (적용됨)

### HashRouter 전환

**파일:** `frontend/src/main.tsx`

- `BrowserRouter` → `HashRouter` (Cafe24 공유호스팅에서 mod_rewrite 불가 시 표준 우회)
- `basename`은 기존과 동일 (`/frontend`)

**로그인 URL (배포 후):**

```
https://plustok.mycafe24.com/frontend/#/login
```

> `/frontend/login` (hash 없음)은 Apache가 여전히 404를 반환합니다. HashRouter 사용 시 `#/login` 경로로 접근합니다.

### Vite base path (변경 없음, 정상)

- `.env.production`: `VITE_BASE_PATH=/frontend/`
- 빌드 산출물: `/frontend/assets/index-*.js` ✓

### .htaccess (로컬/dist 정상, 서버 업로드 필요)

`frontend/public/.htaccess` → `dist/.htaccess` 복사됨. HashRouter 사용 시 rewrite는 필수는 아니나, `/frontend/` 디렉터리 index 서빙용으로 유지.

---

## 3. FTP 배포 체크리스트 (운영자)

**로컬 빌드:**

```bash
cd www/frontend
npm run build
```

**업로드 대상:** `www/frontend/dist/` **내부 파일** (폴더 자체 X)

```
/www/frontend/                    ← FTP 최종 구조
├── .htaccess
├── index.html                    ← production (script: /frontend/assets/index-*.js)
└── assets/
    ├── index-BjNr0Xrd.js         ← HashRouter 빌드 (2026-07-22)
    ├── index-BjNr0Xrd.js.map
    └── index-CwFaCoof.css
```

### FTP 단계

1. [ ] `/www/frontend/dist/` 폴더 **내용물**을 `/www/frontend/`로 이동
2. [ ] `/www/frontend/dist/` 폴더 **삭제**
3. [ ] `/www/frontend/index.html`이 **production** 빌드인지 확인  
       - ✗ `<script src="/src/main.tsx">` → dev 파일, 삭제 후 dist에서 재업로드  
       - ✓ `<script src="/frontend/assets/index-*.js">` → 정상
4. [ ] `.htaccess`가 `/www/frontend/.htaccess`에 있는지 확인 (비어 있지 않아야 함)
5. [ ] `frontend/index.html`(dev 원본)은 **업로드하지 말 것** — `dist/index.html`만 업로드

### 배포 후 검증

```
1. https://plustok.mycafe24.com/frontend/           → 앱 로드 (→ #/chat 리다이렉트)
2. https://plustok.mycafe24.com/frontend/#/login    → 로그인 폼
3. agents 계정 로그인 → localStorage jwt_token 저장
4. https://plustok.mycafe24.com/frontend/login      → 여전히 404 (정상 — HashRouter 한계)
```

---

## 4. HashRouter 필요 여부

| 항목 | 결과 |
|------|------|
| mod_rewrite 동작 | **아니오** — `/frontend/login` Apache 404 |
| HashRouter 적용 | **예** — `main.tsx` 변경 및 재빌드 완료 |
| BrowserRouter 복귀 조건 | Cafe24에서 `.htaccess` rewrite 확인 후 (플랜 업그레이드 또는 AllowOverride 허용 시) |

---

## 5. 건드리지 않은 영역

- Admin (`/admin/*`) — 변경 없음
- Chat Server (`wss://plustok.onrender.com`) — 변경 없음
- Backend API (`/api/v1/*`) — 변경 없음

---

## 6. 완료 기준

- [x] 원인 진단 (curl + FTP 구조)
- [x] HashRouter 코드 변경
- [x] `npm run build` + 테스트 19/19 통과
- [ ] **FTP 재배포** (운영자)
- [ ] `/frontend/#/login` 로그인 폼 + agents 로그인 E2E
