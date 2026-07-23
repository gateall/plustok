# 작업지시서: `/admin/` 403 Forbidden 해결 (2026-07-22)

**상태:** 원인 확정 — **`admin/index.php` 파일이 서버에 없음**
**증상:** `https://plustok.mycafe24.com/admin/` 접속 시 Apache 기본 403 에러 페이지

---

## 1. 확인된 사실 (직접 테스트 완료)

| 경로 | 결과 | 의미 |
|---|---|---|
| `/admin/` | **403** | index 파일이 없어 디렉토리 리스팅 시도 → 비활성화라 403 |
| `/admin/index.php` | **404** | **파일 자체가 서버에 없음** ← 진짜 원인 |
| `/admin/sso.php` | 405 (Method Not Allowed) | 파일은 존재함, GET 미허용일 뿐 (정상 — POST 전용 엔드포인트) |
| `/admin/consults/` | redirect | 정상 (require_login 리다이렉트) |
| `/admin/dashboard.php` | redirect | 정상 (require_login 리다이렉트) |

**결론:** `.htaccess`/권한 문제가 아니라, **`admin/index.php`가 삭제됐거나 애초에 업로드가 안 된 상태**. 다른 `admin/` 하위 파일들은 정상 존재.

## 2. 조치

**`admin/index.php`를 FTP로 업로드** (또는 재업로드) — 그게 전부입니다.

로컬 파일 확인:
```
E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www\admin\index.php
```
이 파일을 서버의 `/www/admin/index.php`에 올리면 됩니다.

## 3. 왜 없어졌는지 (참고, 조치엔 불필요)

오늘 `admin/consults/view.php`가 대규모로 재작성되는 등 활발한 병행 작업이 있었음 — 그 과정에서 실수로 `admin/index.php`가 삭제되거나 업로드 대상에서 빠졌을 가능성이 높음. 재발 방지를 위해 FTP 업로드 시 `admin/` 폴더 전체를 한 번 훑어서 로컬과 파일 개수가 맞는지 확인하는 걸 권장.

## 4. 완료 기준

- [ ] `admin/index.php` 업로드
- [ ] `https://plustok.mycafe24.com/admin/` → 403 없이 `/frontend/#/login`으로 정상 리다이렉트되는지 확인
