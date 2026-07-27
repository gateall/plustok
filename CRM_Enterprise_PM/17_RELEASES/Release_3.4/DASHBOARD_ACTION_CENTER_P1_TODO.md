# Dashboard Action Center — P1 NEXT SPRINT

**Status:** Deferred (Phase 1 approved without TodayTasksSection)  
**Date:** 2026-07-27  
**Release:** `release/admin-react-integration`

## Context

Claude INTEGRATION PASS approved the current Dashboard structure:

- RealtimeSection
- RecentActivitySection

**TodayTasksSection** is intentionally **not** restored in this release commit.

## Follow-up (P1)

- Reintroduce action-center / today-tasks UX with real API wiring (no mock KPI)
- Align with admin dashboard stats period filters when backend supports query params
- Add tests for task list empty/loading/error states
- Responsive review at 360 / 768 / 769 / 1024 / 1440

## Owner

AntiGravity (Dashboard) + PM sign-off before FTP
