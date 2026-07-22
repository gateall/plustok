-- ACEP V3.0 — Phase 1 Seed
-- 개발/스테이징용. 운영은 agents 수동 등록 권장.

SET NAMES utf8mb4;

-- AI 설정 초기값 (중복 무시)
INSERT IGNORE INTO ai_settings (setting_key, setting_value, description) VALUES
('ai_room_rate_limit', '{"limit":10,"windowSec":60}', 'room당 AI 호출 제한'),
('ai_recommend_cache_ttl', '{"ttlSec":3600}', '답변추천 Redis TTL'),
('ai_debounce_sec', '{"sec":60}', 'BR-AI-003 중복 호출 방지');

-- Failover 체인 (키는 env에서만)
INSERT IGNORE INTO ai_provider_config (provider, model_name, priority, api_key_env) VALUES
('claude', 'claude-3-5-sonnet-20241022', 1, 'AI_CLAUDE_API_KEY'),
('openai', 'gpt-4o', 2, 'AI_OPENAI_API_KEY'),
('gemini', 'gemini-1.5-pro', 3, 'AI_GEMINI_API_KEY'),
('grok', 'grok-beta', 4, 'AI_GROK_API_KEY');

-- admin 계정은 migrations/seed.php 로 생성 (bcrypt)
