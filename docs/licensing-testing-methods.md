# Licensing System Testing Methodology & Verification Guide

## 1. Executive Summary: Is Connecting Real External Software Necessary?

> **Direct Answer:** **NO, it is NOT necessary to build, compile, or connect real external software** (such as a desktop `.exe`, mobile app, or C++/C# binary) to verify that this licensing system is working properly.

### Why?
1. **API-Driven Architecture:** In modern software licensing architectures, client applications interact with the licensing server exclusively over standard **HTTP REST APIs**.
2. **Deterministic Payload Handshake:** An external app only does two things:
   - Reads local hardware identifiers (Motherboard UUID, CPU ID, MAC address).
   - Sends those strings formatted as JSON to the server's `/api/Portal/Verify/*` endpoints over HTTPS.
3. **Simulatable in Full Fidelity:** Any standard HTTP testing tool (Postman, Bruno, cURL, or a 20-line test script in Python/Node/PowerShell) can send the exact same hardware fingerprint, receive the cryptographic verification tokens, bind the machine, and test all security and quota gates with 100% fidelity.

---

## 2. Overview of Testing Methods

To thoroughly verify every feature of the licensing system, use the following **5 testing methods**:

```
+---------------------------------------------------------------------------------------------------+
|                                  LICENSING SYSTEM TESTING SUITE                                   |
+-----------------------------------+-----------------------------------+---------------------------+
| 1. API Client Simulation (cURL)  | 2. Web Portal & Seed Mode         | 3. Automated Test Suites  |
| - Verify Serial                   | - SuperAdmin Management           | - PHPUnit / Pest Backend  |
| - Hardware Hash & Single-Use Key  | - Reseller Quota Decrement        | - Vitest / Playwright UI  |
| - Final Authorization & Features  | - Role Impersonation (30m cap)    | - Shard & Ledger Unit Test|
| - Self-Update Manifest & Ed25519  | - Machine Binding Release         |                           |
+-----------------------------------+-----------------------------------+---------------------------+
| 4. Lightweight Mock Client Script | 5. Database & Shard Inspection                                |
| - Ready-to-run Python/JS script   | - Multi-Tenant SQLite Shard Isolation                         |
| - Simulates end-to-end user flow  | - Quota Ledger Anti-Tampering Immutability                    |
+-----------------------------------+---------------------------------------------------------------+
```

---

## 3. Method 1: API Client Simulation (Direct Handshake Verification)

The client software interacts with the server through a 3-step handshake defined in `backend/routes/api.php` under the `/api/Portal/` route group.

### Step 1: Verify Serial (`POST /api/Portal/Verify/Serial`)
Tests whether a serial key entered by a user exists, is active, and is not expired or revoked.

* **Endpoint:** `POST http://127.0.0.1:8000/api/Portal/Verify/Serial`
* **Headers:** `Content-Type: application/json`, `Accept: application/json`
* **Test Payload:**
```json
{
  "Serial": "ALIM-PRO-2026-ABCD",
  "ProductSlug": "video-editor-pro"
}
```
* **Expected Response:**
```json
{
  "Status": { "IsSuccess": true, "Code": 200 },
  "Results": [{ "IsValid": true, "LicenseType": "Yearly", "Status": "Active" }]
}
```

---

### Step 2: Hardware Fingerprint & VerifyKey (`POST /api/Portal/Verify/Hash`)
Tests hardware fingerprint hashing and the issuance of a single-use verification key (`VerifyKey`) with a 5-minute TTL.

* **Endpoint:** `POST http://127.0.0.1:8000/api/Portal/Verify/Hash`
* **Headers:** `Content-Type: application/json`, `Accept: application/json`
* **Test Payload:**
```json
{
  "Serial": "ALIM-PRO-2026-ABCD",
  "HardwareHash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "MachineName": "DESKTOP-TEST-01",
  "Platform": "Windows"
}
```
* **Expected Response:**
```json
{
  "Status": { "IsSuccess": true, "Code": 200 },
  "Results": [{
    "VerifyKey": "vk_9f8a3c2e1b4d5e6f7a8b9c0d1e2f3a4b",
    "ExpiresInSeconds": 300
  }]
}
```

---

### Step 3: Final Authorization (`POST /api/Portal/Verify/Final`)
Tests atomic machine binding, deployment environment validation, and returns active license feature flags and expiration timestamps.

* **Endpoint:** `POST http://127.0.0.1:8000/api/Portal/Verify/Final`
* **Headers:** `Content-Type: application/json`, `Accept: application/json`
* **Test Payload:**
```json
{
  "VerifyKey": "vk_9f8a3c2e1b4d5e6f7a8b9c0d1e2f3a4b",
  "Environment": "Production"
}
```
* **Expected Response:**
```json
{
  "Status": { "IsSuccess": true, "Code": 200 },
  "Results": [{
    "IsAuthorized": true,
    "LicenseId": 1042,
    "ExpiresAt": "2027-10-14T00:00:00Z",
    "Features": {
      "Export4K": true,
      "CloudSync": true,
      "WatermarkRemoval": true
    }
  }]
}
```

---

### Step 4: Software Self-Update Check (`GET /api/App/UpdateManifest`)
Tests version manifest discovery and Ed25519 signature download.

* **Endpoint:** `GET http://127.0.0.1:8000/api/App/UpdateManifest?Platform=Windows&CurrentVersion=1.0.0`
* **Expected Response:**
```json
{
  "LatestVersion": "1.2.0",
  "DownloadUrl": "http://127.0.0.1:8000/api/App/UpdateAsset/1.2.0/Windows",
  "SignatureUrl": "http://127.0.0.1:8000/api/App/UpdateAsset/1.2.0/Windows.sig",
  "ChecksumSha256": "3a7b9c...",
  "IsMandatory": false
}
```

---

## 4. Method 2: Web Portal & Seed Mode Testing (Frontend UI)

The frontend provides a dedicated UI on `http://localhost:8080` for end-to-end role validation.

### Instant Exploration via Seed Mode (No DB Setup Required)
1. Open `http://localhost:8080/admin/login` in your web browser.
2. Press `Ctrl + Shift + D` (or open the **Seed Mode Identities** panel).
3. Select an identity to log in instantly:
   - **Super Admin:** Full access to all resellers, prefix registries, system metrics, and audit logs.
   - **Reseller (e.g., Alim TV):** Dedicated reseller view with license issuing forms, remaining quota bar, and machine bindings table.
   - **App Builder:** App binary upload and update manifest management.

### Key Workflows to Test in UI:
| Feature | Navigation | What to Verify |
| :--- | :--- | :--- |
| **Reseller Management** | `/admin/resellers` | Create new reseller, assign quota balance, inspect status. |
| **Prefix Management** | `/admin/prefixes` | Register a custom serial prefix (e.g. `ALIM-`, `DEMO-`). |
| **Quota Allocation** | `/admin/quota-requests` | Approve or deny a quota replenishment request from a reseller. |
| **License Issuance** | `/reseller/licenses` | Issue a Monthly or Lifetime license, verify that the remaining quota decrements by 1. |
| **Machine Release** | `/reseller/licenses/:id` | View bound hardware IDs, click "Release Machine" or "Clear Cooldown". |
| **Impersonation** | `/admin/users` | Click "Impersonate" on a reseller. Verify the 30-minute yellow countdown banner and exit safely. |

---

## 5. Method 3: Automated Test Suites (PHPUnit & Vitest)

Automated tests test all cryptographic checks, database transactions, concurrency, and RBAC rules under high stress.

### A. Backend PHPUnit / Pest Tests
Run from the `backend/` directory:
```powershell
cd backend
php artisan test
```

* **What it covers:**
  - `tests/Feature/AuthTest.php`: Login rate limiting, session TTL, lockout on invalid attempts.
  - `tests/Feature/VerifyHandshakeTest.php`: 3-step verification flow, expired `VerifyKey` rejection.
  - `tests/Feature/QuotaLedgerTest.php`: Ledger immutability, prevention of quota over-issuance.
  - `tests/Feature/ShardIsolationTest.php`: Cross-tenant boundary enforcement (Reseller A cannot access Reseller B's licenses).
  - `tests/Feature/SelfUpdateTest.php`: Ed25519 detached signature verification.

### B. Frontend Vitest Tests
Run from the repository root:
```powershell
pnpm test
```
* **What it covers:**
  - Component rendering, Seed Mode state management, API client interceptors, and error handling modals.

---

## 6. Method 4: Standalone 30-Line Mock Client Script

If you want to observe an external software simulation programmatically, save and run this Python script:

```python
import hashlib
import requests

SERVER_URL = "http://127.0.0.1:8000"
SERIAL_KEY = "ALIM-PRO-2026-ABCD"

# 1. Simulate reading machine motherboard UUID + MAC address
mock_hardware_id = "BFEBFBFF000906EA-00:1A:2B:3C:4D:5E"
hardware_hash = hashlib.sha256(mock_hardware_id.encode()).hexdigest()

print("[1/3] Verifying Serial Key...")
r1 = requests.post(f"{SERVER_URL}/api/Portal/Verify/Serial", json={"Serial": SERIAL_KEY})
print("Serial Check:", r1.json())

print("[2/3] Submitting Hardware Fingerprint...")
r2 = requests.post(f"{SERVER_URL}/api/Portal/Verify/Hash", json={
    "Serial": SERIAL_KEY,
    "HardwareHash": hardware_hash,
    "MachineName": "Test-Laptop",
    "Platform": "Windows"
})
verify_key = r2.json()["Results"][0]["VerifyKey"]
print("Acquired Single-Use VerifyKey:", verify_key)

print("[3/3] Final Authorization & Machine Binding...")
r3 = requests.post(f"{SERVER_URL}/api/Portal/Verify/Final", json={
    "VerifyKey": verify_key,
    "Environment": "Production"
})
auth_data = r3.json()["Results"][0]
print("Authorization Result:", "SUCCESS" if auth_data["IsAuthorized"] else "FAILED")
print("Unlocked Features:", auth_data.get("Features"))
```

---

## 7. Method 5: Database & Shard Isolation Validation

Verify the multi-tenant architecture directly in SQLite/PostgreSQL:

1. **Root Database (`lara_root.sqlite`):**
   - Check `users` table: contains SuperAdmin and Admins.
   - Check `resellers` table: contains tenant registry and quota totals.
   - Check `audit_logs` table: records every admin action, login, and impersonation.
2. **Tenant Shard Databases (`lara_tenant_{id}.sqlite`):**
   - Check `licenses` table: contains issued serial keys for this specific reseller.
   - Check `machine_bindings` table: contains active hardware hashes bound to licenses.
   - Check `reseller_quota_ledgers` table: verify every quota addition and subtraction has an immutable transaction record.

---

## 8. Summary Checklist for Edge-Case Testing

When verifying the system, test these negative and edge scenarios without any external app:
- [ ] **Wrong Serial:** Submit an invalid serial to `/Verify/Serial` -> Expected `404 LicenseNotFound`.
- [ ] **Expired License:** Set license date to yesterday -> Expected `403 LicenseExpired`.
- [ ] **Exceeded Machine Limit:** Send 3 different `HardwareHash` values for a 2-machine license -> 3rd request rejected with `MachineLimitExceeded`.
- [ ] **Replay Attack:** Try to reuse a `VerifyKey` twice in `/Verify/Final` -> Expected `401 VerifyKeyAlreadyConsumed`.
- [ ] **Tampered Hardware Hash:** Submit a different hash in Step 3 than Step 2 -> Expected `400 HardwareHashMismatch`.
- [ ] **Quota Exhaustion:** Decrement a reseller's quota to 0, then try to issue a license -> Expected `400 QuotaExhausted`.
