# Greenfield Schema Reference

**권장 실행 경로 (버전 추적):**

```bash
php migrations/migrate.php --seed
```

**파일 매핑 (SSOT 14 tables):**

| SSOT § | Table | Migration file |
|--------|-------|----------------|
| 5.1 | customers | V1.0.0__mvp_core.sql |
| 5.2 | chat_rooms | V1.0.0__mvp_core.sql |
| 5.3 | chat_messages | V1.0.0__mvp_core.sql |
| 5.4 | ai_recommendations | V1.0.0__mvp_core.sql |
| 5.5 | chat_read_status | V1.0.0__mvp_core.sql |
| 5.6 | agents | V1.5.0__agents_ai_ops.sql |
| 5.7 | chat_room_assignments | V1.5.0__agents_ai_ops.sql |
| 5.8 | attachments | V1.5.0__agents_ai_ops.sql |
| 5.9~5.14 | ai_*, audit_logs | V1.5.0__agents_ai_ops.sql |
| — | fk_chat_rooms_agent, fk_chat_messages_attachment | migrate.php (idempotent) |

`001_init_tables.sql`은 mysql SOURCE 체인 대신 **migrate.php** 사용을 SSOT로 합니다.
