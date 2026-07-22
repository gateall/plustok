# PlusTok ACEP — FTP Cafe24 배포 가이드

> **프로젝트**: PlusTok Enterprise (ACEP)
> **Version**: 1.0.0
> **작성일**: 2026-07-21
> **Audience**: Operator, DevOps, Release Manager, Architect
> **상위 문서**: [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)
> **STEP**: 8 — Release & Deployment

## 문서 개요

| 항목 | 내용 |
|------|------|
| 목적 | `plustok.mycafe24.com` Cafe24 FTP/SFTP 배포, 업로드/제외 목록, DB migration, 스모크 테스트 |
| 대상 | Operator, Release Manager |
| SSOT Config | [config/app.php](../config/app.php) — BASE_URL, MAIL_FROM |

---

## 1. Cafe24 호스팅 개요

| 항목 | 값 |
|------|-----|
| URL | https://plustok.mycafe24.com |
| 호스팅 | Cafe24 웹호스팅 (공유) |
| PHP | 8.4 |
| DB | MariaDB (Cafe24 제공) |
| 배포 | FTP/SFTP |
| Document Root | `/www` 또는 Cafe24 할당 경로 |

### 1.1 접속 정보 (Operator 보관)

| Item | Location | Notes |
|------|----------|-------|
| FTP Host | Cafe24 panel → FTP 정보 | SFTP 권장 (port 22 or provider spec) |
| FTP User | Cafe24 account | Least privilege |
| DB Host | Cafe24 → DB 관리 | Not localhost from external |
| phpMyAdmin | Cafe24 panel | Migration 실행 |
| SSL | Cafe24 무료 SSL | Auto-renew check monthly |

---

## 2. FTP/SFTP 배포 절차

### 2.1 Pre-Deploy

| # | Task | ☐ |
|---|------|:-:|
| PRE-01 | QA gate PASS — [08_TEST/04](../08_TEST/04_QA_릴리스_게이트.md) | ☐ |
| PRE-02 | Git tag checked out locally | ☐ |
| PRE-03 | Deploy manifest (changed files) generated | ☐ |
| PRE-04 | DB backup exported via phpMyAdmin | ☐ |
| PRE-05 | File snapshot downloaded from server | ☐ |

### 2.2 Upload Sequence

```
1. Connect SFTP to plustok.mycafe24.com
2. Upload includes/ (if changed)
3. Upload config/ — EXCLUDE database.php secrets if unchanged
4. Upload api/v1/
5. Upload admin/
6. Upload embed/
7. Upload assets/ (CSS/JS)
8. Upload root index.php if changed
9. NEVER upload: logs/, .git/, node_modules/, .env
```

### 2.3 SFTP Client 설정 (FileZilla 예시)

| Setting | Value |
|---------|-------|
| Protocol | SFTP |
| Transfer mode | Binary |
| Concurrent transfers | 2 (avoid server throttle) |
| Remote path | /www or assigned root |

---

## 3. 업로드 포함 / 제외 목록

### 3.1 INCLUDE (배포 대상)

| Path | Description |
|------|-------------|
| admin/ | Admin console PHP |
| api/v1/ | REST API endpoints |
| assets/ | CSS, JS, images |
| config/app.php | App constants (review diff) |
| config/ai.php | AI settings (no keys in git) |
| embed/ | Customer widget |
| includes/ | Shared PHP logic |
| index.php | Entry point |

### 3.2 EXCLUDE (절대 업로드 금지)

| Path | Reason |
|------|--------|
| .git/ | Version control |
| .env | Secrets |
| config/database.php | DB credentials (server-only) |
| logs/ | Server-generated |
| uploads/ | User data — preserve on server |
| node_modules/ | Dev dependency |
| 09_RELEASE/ | Documentation only |
| _작업지시서/ | Internal docs |
| docker/ | Docker configs (not for Cafe24) |
| frontend/node_modules/ | Dev |

### 3.3 CONDITIONAL (상황별)

| Path | When to upload |
|------|----------------|
| config/database.php | Only initial setup or credential rotation |
| uploads/ | Never overwrite — backup only |
| test_*.php | Never to production |

---

## 4. config/ 배포 주의

### 4.1 app.php 검증

배포 전 반드시 확인:

```php
define('BASE_URL', 'https://plustok.mycafe24.com');
define('MAIL_FROM', 'noreply@plustok.mycafe24.com');
define('ADMIN_NOTIFY_EMAIL', 'adfull@naver.com');
```

### 4.2 ai.php

- API keys는 서버 `config/ai.php` 또는 환경별 파일에만 존재
- Git 저장소에 live key 커밋 금지 (Rule-003)

---

## 5. DB Migration (phpMyAdmin)

### 5.1 Migration Workflow

| Step | Action | ☐ |
|------|--------|:-:|
| MIG-01 | Export current DB (Structure + Data) | ☐ |
| MIG-02 | Test migration SQL on local/staging copy | ☐ |
| MIG-03 | Open phpMyAdmin → Import or SQL tab | ☐ |
| MIG-04 | Run migration script (transaction if supported) | ☐ |
| MIG-05 | Verify table count and sample queries | ☐ |
| MIG-06 | Rollback SQL ready if migration fails | ☐ |

### 5.2 ACEP Migration Files

| File | Version | Tables |
|------|---------|--------|
| V1.0.0__mvp_core.sql | V1.0 | 5 core tables |
| V1.5.0__agents_ai_ops.sql | V1.5 | agents, ai_failover_log |
| V2.0.0__optimize_partition.sql | V2.0 | indexes, partitions |

### 5.3 Post-Migration Verification SQL

```sql
SHOW TABLES;
SELECT COUNT(*) FROM consults;
SELECT status, COUNT(*) FROM consults GROUP BY status;
```

---

## 6. Opcode Cache & Post-Upload

| Action | Method | ☐ |
|--------|--------|:-:|
| Clear PHP opcode | Cafe24 panel → PHP 설정 → 캐시 초기화 | ☐ |
| Or touch index.php | SFTP re-upload index.php timestamp | ☐ |
| Verify file permissions | dirs 755, files 644 | ☐ |
| Check uploads/ writable | test file upload in admin | ☐ |

---

## 7. Delta Deploy Manifest

릴리스마다 변경 파일 목록을 MD5와 함께 기록한다.

```text
# deploy-manifest-v1.0.0-mvp.txt
admin/consults/ai_analyze.php  md5=abc123...
includes/ai.php                 md5=def456...
api/v1/consult.php             md5=ghi789...
```

생성 예시 (Git):

```bash
git diff --name-only v1.0.0-alpha v1.0.0-mvp > deploy-manifest.txt
```

---

## 8. Post-Deploy Smoke Tests

Ref: [08_TEST/04 §3.4](../08_TEST/04_QA_릴리스_게이트.md)

| ID | Test | URL / Action | Expected | ☐ |
|----|------|--------------|----------|:-:|
| SMK-01 | Admin login | /admin/ | Dashboard loads | ☐ |
| SMK-02 | Consult API | POST /api/v1/consult.php | 201/200 success | ☐ |
| SMK-03 | Embed form | /embed/form.php | Form renders | ☐ |
| SMK-04 | AI analyze | admin consult AI | JSON success | ☐ |
| SMK-05 | Mail notify | new consult submit | ADMIN_NOTIFY_EMAIL received | ☐ |
| SMK-06 | HTTPS | all pages | No mixed content | ☐ |
| SMK-07 | Health | API health endpoint | 200 OK | ☐ |

### 8.1 Smoke Failure Response

| SMK Fail | Severity | Action |
|----------|----------|--------|
| SMK-01~04 | S1 | Rollback immediately |
| SMK-05 | S2 | Check MAIL_FROM, retry |
| SMK-06 | S3 | Fix asset URLs, hotfix |
| SMK-07 | S1 | Check PHP errors, rollback |

---

## 9. Troubleshooting

| Issue | Cause | Resolution |
|-------|-------|------------|
| 500 after upload | PHP syntax error | Check error log, rollback file |
| Blank admin page | includes/ missing | Re-upload includes/ |
| AI not working | ai.php keys | Verify config/ai.php on server |
| Mail not sent | MAIL_FROM mismatch | Match Cafe24 mail domain |
| Upload fail | uploads/ permission | chmod 755 uploads/ |
| DB connection error | database.php | Verify Cafe24 DB credentials |

---

## 11. Admin/API Path Reference

| Path | Purpose | Method | Auth |
|------|---------|--------|------|
| `admin/dashboard.php` | Main dashboard | GET | Admin auth |
| `admin/consults/index.php` | Consult list | GET | Admin auth |
| `admin/consults/view.php` | Consult detail | GET | Admin auth |
| `admin/consults/ai_analyze.php` | AI analysis | POST | Admin + AI keys |
| `admin/consults/ai_reply.php` | AI reply suggest | POST | Admin + AI keys |
| `admin/consults/ai_summary.php` | AI summary | POST | Admin + AI keys |
| `admin/settings/ai.php` | AI provider settings | GET/POST | Super admin |
| `admin/settings/index.php` | General settings | GET | Admin auth |
| `admin/products/index.php` | Product catalog | GET | Admin auth |
| `api/v1/consult.php` | Consult API | POST | Public + rate limit |
| `embed/form.php` | Customer form | GET | Public |
| `embed/embed.js` | Widget loader | GET | Public CDN cache |

## 12. File Permission Matrix (Cafe24)

| Path | Dir Perm | File Perm | Notes |
|------|----------|-----------|-------|
| admin/ | 755 | 644 | PHP execution |
| api/ | 755 | 644 | REST endpoints |
| config/ | 750 | 640 | Restrict database.php |
| includes/ | 755 | 644 | Shared logic |
| uploads/ | 755 | 644 | Writable by PHP |
| logs/ | 750 | 640 | Not web accessible |

## 13. maintenance.html Template

```html
<!DOCTYPE html>
<html lang="ko">
<head><meta charset="UTF-8"><title>PlusTok 점검 중</title></head>
<body style="font-family:sans-serif;text-align:center;padding:4rem">
<h1>시스템 점검 중입니다</h1>
<p>약 15분 내 완료 예정. 문의: adfull@naver.com</p>
</body></html>
```

배포 시작 시 `index.php` 대신 `maintenance.html`을 index로 rename하거나 Cafe24 점검 페이지 사용.

## 14. FTP Rollback Step-by-Step

| # | Action | Time | ☐ |
|---|--------|------|:-:|
| 1 | Announce rollback in team channel | T+0 | ☐ |
| 2 | SFTP connect to production | T+2m | ☐ |
| 3 | Upload files from snapshot tarball | T+10m | ☐ |
| 4 | Skip config/database.php if unchanged | T+10m | ☐ |
| 5 | phpMyAdmin restore if DB migrated | T+20m | ☐ |
| 6 | Clear opcode cache | T+22m | ☐ |
| 7 | Run SMK-01~04 | T+25m | ☐ |
| 8 | Post incident summary | T+30m | ☐ |

## 15. Consult Status Workflow (Deploy Verification)

After deploy, verify consult statuses from [config/app.php](../config/app.php):

| Code | Label |
|------|-------|
| new | 신규(접수) |
| progress | 진행중 |
| consulting | 상담중 |
| quoted | 견적발송 |
| contracted | 계약완료 |
| installed | 설치완료 |
| hold | 보류 |
| canceled | 취소 |

## 16. Upload Allowed Extensions

From app.php — verify upload handler after deploy:

| Ext | Type Label |
|-----|------------|
| jpg, jpeg, png, gif | 사진 |
| pdf | PDF |
| hwp, hwpx | 문서 |

Max size: 10MB (`UPLOAD_MAX_BYTES`)

## 17. SFTP Delta Upload Script (Windows PowerShell)

Operator 로컬에서 변경 파일만 업로드하는 예시:

```powershell
# Requires WinSCP .NET assembly or similar SFTP client
$manifest = Get-Content deploy-manifest-v1.0.0-mvp.txt
$remoteHost = "plustok.mycafe24.com"
$remotePath = "/www"
foreach ($line in $manifest) {
    $file = ($line -split '\s+')[0]
    if ($file -match '^(logs/|uploads/|\.git/)') { continue }
    Write-Host "Uploading $file"
    # winscp put $file ${remoteHost}:${remotePath}/$file
}
```

## 18. Cafe24 Panel Checklist (Post-Deploy)

| Panel Menu | Check | ☐ |
|------------|-------|:-:|
| PHP 버전 | 8.4 selected | ☐ |
| PHP 확장 | pdo_mysql, mbstring, curl, json enabled | ☐ |
| SSL | Certificate active, auto-renew on | ☐ |
| 디스크 용량 | < 80% used | ☐ |
| 트래픽 | No abnormal spike | ☐ |
| 에러 로그 | No fatal errors post-deploy | ☐ |
| cron | Scheduled jobs intact (if any) | ☐ |

## 19. Embed Widget Deploy Verification

Customer sites load `embed/embed.js` — verify after deploy:

| Check | Method | Expected | ☐ |
|-------|--------|----------|:-:|
| JS loads | Browser DevTools Network | 200, cached | ☐ |
| BASE_URL in JS | View source / config | plustok.mycafe24.com | ☐ |
| Form POST | Submit test consult | 200/201 API success | ☐ |
| CORS | From whitelisted parent domain | No CORS error | ☐ |

## 20. Rate Limit Verification

`RATE_LIMIT_PER_MIN = 30` ([config/app.php](../config/app.php))

```bash
# Quick rate limit test (stop after 429 observed)
for i in $(seq 1 35); do
  curl -s -o /dev/null -w "%{http_code}\n" -X POST https://plustok.mycafe24.com/api/v1/consult.php
done
```

Expected: HTTP 429 after threshold with `Retry-After` header.

## 21. ROLES Permission Deploy Check

Verify RBAC from [config/app.php](../config/app.php) after admin deploy:

| Role | Expected Access | Test Account ☐ |
|------|-----------------|:--------------:|
| super | All settings including AI | ☐ |
| admin | Consults, products, dashboard | ☐ |
| manager | Team consults | ☐ |
| sales | Assigned consults | ☐ |
| viewer | Read-only dashboard | ☐ |

## 22. Common Cafe24 FTP Errors

| Error | Meaning | Fix |
|-------|---------|-----|
| 550 Permission denied | Wrong path or chmod | Verify remote path /www |
| 421 Service not available | Server maintenance | Retry, check Cafe24 status |
| Connection timeout | Network/firewall | VPN off, retry SFTP port |
| Transfer incomplete | Large file timeout | Upload in smaller batches |

---

## 10. 관련 문서

- [01_배포_아키텍처_및_환경.md](01_배포_아키텍처_및_환경.md)
- [05_릴리스_런북.md](05_릴리스_런북.md)
- [config/app.php](../config/app.php)
- [08_TEST/04_QA_릴리스_게이트.md](../08_TEST/04_QA_릴리스_게이트.md)
- [_RELEASE_INDEX.md](_RELEASE_INDEX.md)

**문서 끝**
