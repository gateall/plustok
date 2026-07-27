# Sites Admin REST API Contract (Required)

**Status:** API BLOCK — not registered in `api/v1/router.php`  
**Owner:** DeepSeek (backend)  
**React owner:** Cursor (after API exists)  
**Date:** 2026-07-27  
**PHP reference:** `www/admin/sites/index.php`

---

## Context

React `/frontend/#/admin/sites` cannot show real data until REST routes exist.  
Cursor `AdminSitesBlockPage` documents this block — no mock list.

---

## Required Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/admin/sites` | Paginated list |
| GET | `/admin/sites/{id}` | Detail |
| POST | `/admin/sites` | Create |
| PUT | `/admin/sites/{id}` | Update |
| PATCH | `/admin/sites/{id}/status` | Active / inactive |
| POST | `/admin/sites/{id}/regenerate-key` | API key rotation |
| DELETE | `/admin/sites/{id}` | Delete (block if consults linked) |
| POST | `/admin/sites/bulk-delete` | Bulk delete |

---

## List Query Parameters

| Param | Type | Notes |
|-------|------|-------|
| page | int | ≥ 1 |
| limit | int | 1–100 |
| q | string | Search site name, code, domain |
| status | string | active, inactive, ok, check |
| sort | string | Whitelist |
| order | asc \| desc | |

Empty filters must be omitted from query string.

---

## List Response Envelope

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "siteCode": "SITE001",
        "siteName": "Example",
        "domain": "example.com",
        "brand": null,
        "isActive": true,
        "integrationStatus": "ok",
        "consultCount": 0,
        "managerName": null,
        "createdAt": "2026-01-01T00:00:00+09:00"
      }
    ],
    "total": 0,
    "page": 1,
    "limit": 20
  }
}
```

---

## Schema Notes (from PHP)

- Use `SiteSchema::activeColumn($pdo)` — column may be `status` or `is_active`
- Optional columns: `domain`, `division`, `persona` — probe with `acep_column_exists`
- Delete must call consult-link check (see PHP `site_has_consults`)
- API key: never return full key in list; mask except on create/regenerate response

---

## Auth

- JWT Bearer required
- Roles: `admin`, `operator` read; write/delete per existing PHP `require_role`

---

## React Integration (post-API)

Cursor will implement:

- `AdminSitesPage`, `SiteFormPage`
- `site.service.ts`
- Mobile cards + PC table via `admin-mobile-list` / `admin-desktop-table`
- Route: replace `AdminSitesBlockPage` in `App.tsx`

**Until then:** `PARTIAL — API BLOCK`
