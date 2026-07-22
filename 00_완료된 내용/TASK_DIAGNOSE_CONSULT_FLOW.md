# TASK — smarttoktok.com 상담 접수 흐름 점검 (진단 작업지시서)

- **대상 작업자:** Antigravity (점검·원인분석) / 작성: Claude
- **완료일:** 2026-07-21 (STEP 6 완료, 수동 진단 건너뜀)
- **작성일:** 2026-07-20

> **코드 경로 확인 완료:** embed.js → form.php(CORS+프록시) → consult.php → DB → notify_new_consult()  
> **V1.0 실연동 이력:** C202607170002 ~ C202607200005

## 0. 실제 흐름 (코드로 확인됨)

```
smarttoktok.com/plustok.php (껍데기, script 태그만)
        │
        ▼ (embed.js 로드)
브라우저에서 직접 fetch(CORS)
        │
        ▼
plustok.mycafe24.com/embed/form.php  (CORS 검증 + API Key 프록시)
        │
        ▼
plustok.mycafe24.com/api/v1/consult.php  (인증 + 검증 + DB insert + mail)
        │
        ▼
plustok DB (customers / consults / consult_history)
```

## 완료 기준
- [x] 코드 경로 전체 구현 확인
- [~] 브라우저/수신함 수동 체크리스트 — **건너뜀**

(전체 점검 항목 본문은 아카이브)
