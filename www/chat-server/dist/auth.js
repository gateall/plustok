import jwt from 'jsonwebtoken';
export function getJwtSecret() {
    const secret = process.env.JWT_SECRET?.trim();
    if (!secret) {
        throw new Error('JWT_SECRET not configured');
    }
    return secret;
}
export function isJwtConfigured() {
    return Boolean(process.env.JWT_SECRET?.trim());
}
export function verifyJwtToken(token) {
    try {
        return jwt.verify(token, getJwtSecret());
    }
    catch {
        return null;
    }
}
export function decodeJwtPayload(token) {
    const payload = verifyJwtToken(token);
    if (!payload?.sub || !payload.role) {
        return null;
    }
    return payload;
}
