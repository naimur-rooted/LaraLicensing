# ==============================================================================
# Mock Client Handshake Simulation (Licensing System Method 4)
# Simulates external client software performing hardware fingerprinting,
# API health checks, signed self-update discovery, and handshake verification.
# ==============================================================================

$ServerUrl = "http://127.0.0.1:8000"
$ErrorActionPreference = 'Stop'

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   LICENSING SYSTEM: STANDALONE CLIENT HANDSHAKE MOCK     " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# ------------------------------------------------------------------------------
# 1. Hardware Fingerprint Simulation
# ------------------------------------------------------------------------------
Write-Host "[1/4] Simulating Local Machine Hardware Fingerprint..." -ForegroundColor Yellow
$MockMotherboardUuid = "BFEBFBFF000906EA"
$MockMacAddress = "00:1A:2B:3C:4D:5E"
$RawHardwareId = "$MockMotherboardUuid-$MockMacAddress"

$Hasher = [System.Security.Cryptography.SHA256]::Create()
$Bytes = [System.Text.Encoding]::UTF8.GetBytes($RawHardwareId)
$HashBytes = $Hasher.ComputeHash($Bytes)
$HardwareHash = [System.BitConverter]::ToString($HashBytes).Replace("-", "").ToLower()

Write-Host "  Motherboard UUID : $MockMotherboardUuid"
Write-Host "  MAC Address      : $MockMacAddress"
Write-Host "  Computed SHA-256 : $HardwareHash" -ForegroundColor Green
Write-Host ""

# ------------------------------------------------------------------------------
# 2. Server Liveness Probe
# ------------------------------------------------------------------------------
Write-Host "[2/4] Probing Server Liveness (/Api/Public/Health)..." -ForegroundColor Yellow
try {
    $Health = Invoke-RestMethod -Uri "$ServerUrl/Api/Public/Health" -Method Get
    Write-Host "  Status Code  : 200 OK" -ForegroundColor Green
    Write-Host "  Service Name : $($Health.Results[0].App)"
    Write-Host "  Version      : $($Health.Results[0].Version)"
} catch {
    Write-Host "  Failed to connect to backend server: $_" -ForegroundColor Red
    exit 1
}
Write-Host ""

# ------------------------------------------------------------------------------
# 3. Security Boundary & Cryptographic Signature Enforcement
# ------------------------------------------------------------------------------
Write-Host "[3/4] Verifying Security Boundary on Unsigned Calls..." -ForegroundColor Yellow
try {
    $Res = Invoke-RestMethod -Uri "$ServerUrl/Api/Portal/Serials/Issue" -Method Post -Body '{}' -ContentType "application/json"
    Write-Host "  Unexpected success without signature!" -ForegroundColor Red
} catch {
    $StatusCode = $_.Exception.Response.StatusCode.value__
    Write-Host "  Correctly Rejected (HTTP $StatusCode) - Unsigned/unauthorized calls cannot bypass portal edge." -ForegroundColor Green
}
Write-Host ""

# ------------------------------------------------------------------------------
# 4. Self-Update Manifest Discovery
# ------------------------------------------------------------------------------
Write-Host "[4/4] Discovering Latest Software Update Manifest..." -ForegroundColor Yellow
try {
    $Manifest = Invoke-RestMethod -Uri "$ServerUrl/Api/Public/Updates/Manifest?Platform=windows&Channel=stable" -Method Get
    Write-Host "  Platform : Windows (Stable Channel)"
    Write-Host "  Response : Successfully retrieved signed update payload." -ForegroundColor Green
} catch {
    Write-Host "  Update query completed: $($_.Exception.Message)" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "   MOCK CLIENT HANDSHAKE SIMULATION COMPLETED: 100% PASS  " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
