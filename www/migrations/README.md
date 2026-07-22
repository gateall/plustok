# ACEP DB Migrations

**SSOT:** `03_SYSTEM/01_DB설계.md`  
**검증 보고:** `_DDL_검증보고서_Step2.md`

## 파일

| 파일 | 내용 |
|------|------|
| `lib.php` | SQL 실행, legacy 감지, FK idempotent |
| `V0.0__legacy_prepare.sql` | (선택) 레거시 `customers`/`attachments` → `crm_*` rename |
| `V1.0.0__mvp_core.sql` | MVP 5 tables |
| `V1.5.0__agents_ai_ops.sql` | +9 tables + `customer_bridge` |
| `V1.5.0__fk_constraints.sql` | FK 메타 (실행은 migrate.php) |
| `V1.5.1__phase1_seed.sql` | AI settings / provider seed |
| `schema/001_init_tables.sql` | Greenfield 참조 (실행은 migrate.php 권장) |

## 실행 순서

```
1. (선택) V0.0__legacy_prepare.sql     ← install.php CRM 공존 시 1회
2. php migrations/migrate.php --check   ← 환경 확인
3. php migrations/migrate.php --seed    ← DDL + seed + admin
```

## Legacy CRM 공존

| Legacy (install.php) | ACEP (SSOT) | Bridge |
|---------------------|-------------|--------|
| `crm_customers` (BIGINT) | `customers` (UUID) | `customer_bridge` |
| `crm_attachments` | `attachments` | — |

**V0.0 실행 전 DB 백업 필수.**

## Admin seed

- login: `admin`
- password: `Admin123!` (즉시 변경)

## Meta tables (SSOT 14개 외)

| Table | 용도 |
|-------|------|
| `acep_migrations` | migration 버전 추적 |
| `customer_bridge` | CRM UUID ↔ legacy ID (`06_CRM/01` §5.3) |
