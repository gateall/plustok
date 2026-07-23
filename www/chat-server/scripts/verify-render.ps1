# Render chat-server 배포 검증 (PowerShell)
# Usage: .\scripts\verify-render.ps1 [-BaseUrl https://plustok.onrender.com]

param(
    [string]$BaseUrl = 'https://plustok.onrender.com'
)

Write-Host "=== Render chat-server verify ===" -ForegroundColor Cyan
Write-Host "URL: $BaseUrl`n"

Write-Host "[1] GET /health"
$healthRaw = curl.exe -sS "$BaseUrl/health"
Write-Host $healthRaw

try {
    $health = $healthRaw | ConvertFrom-Json
} catch {
    Write-Host "FAIL: Invalid JSON" -ForegroundColor Red
    exit 1
}

if (-not ($health.PSObject.Properties.Name -contains 'jwt')) {
    Write-Host "`nFAIL: Legacy health (no jwt/backend) — redeploy latest chat-server" -ForegroundColor Red
} else {
    Write-Host "`nOK: Enhanced /health" -ForegroundColor Green
    if ($health.jwt.configured) {
        Write-Host "OK: jwt.configured = true" -ForegroundColor Green
    } else {
        Write-Host "FAIL: jwt.configured = false — set JWT_SECRET on Render" -ForegroundColor Red
    }
    if ($health.backend.reachable) {
        Write-Host "OK: backend.reachable = true ($($health.backend.latencyMs) ms)" -ForegroundColor Green
    } else {
        Write-Host "WARN: backend.reachable = false — $($health.backend.error)" -ForegroundColor Yellow
    }
}

Write-Host "`n[2] Socket.io polling handshake"
$poll = curl.exe -sS "$BaseUrl/socket.io/?EIO=4&transport=polling"
if ($poll -match '^0\{') {
    Write-Host "OK: handshake" -ForegroundColor Green
} else {
    Write-Host "FAIL: $poll" -ForegroundColor Red
}

Write-Host "`nJWT_SECRET must match config/acep.local.php ACEP_JWT_SECRET exactly."
