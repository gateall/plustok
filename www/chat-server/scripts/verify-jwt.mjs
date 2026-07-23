#!/usr/bin/env node
/**
 * JWT 검증 테스트 — PHP 발급 토큰이 chat-server 시크릿과 맞는지 확인
 * Usage: node scripts/verify-jwt.mjs <accessToken> [JWT_SECRET]
 * Secret 생략 시 process.env.JWT_SECRET 사용
 */
import jwt from 'jsonwebtoken';

const token = process.argv[2];
const secret = process.argv[3] ?? process.env.JWT_SECRET;

if (!token) {
  console.error('Usage: node scripts/verify-jwt.mjs <accessToken> [JWT_SECRET]');
  process.exit(1);
}
if (!secret) {
  console.error('JWT_SECRET required (arg or env)');
  process.exit(1);
}

try {
  const payload = jwt.verify(token, secret);
  console.log('OK: signature valid');
  console.log(JSON.stringify(payload, null, 2));
} catch (e) {
  console.error('FAIL:', e instanceof Error ? e.message : e);
  process.exit(1);
}
