# Repository Analysis: `alimtvnetwork/lara-licensing-v4` (`LaraLicensingV1`)

**Target Repo:** [alimtvnetwork/lara-licensing-v4](https://github.com/alimtvnetwork/lara-licensing-v4)  
**Product Name:** `LaraLicensingV1`  
**Analysis Date:** 2026-10-13  

---

## 1. Executive Summary

`LaraLicensingV1` is an enterprise-grade, multi-tenant **Software Licensing, Authentication, and Distribution Platform**.

It provides a comprehensive system for software developers and companies to:
- Generate, manage, distribute, and verify software licenses for external applications (e.g., Windows desktop software, web applications, CLI tools).
- Prevent piracy through multi-parameter hardware binding (CPU/motherboard, MAC, IP, hard drive).
- Manage multi-tier reseller ecosystems with quota enforcement and isolated data storage.
- Safely distribute signed binary updates and releases to client applications.

---

## 2. Core Purpose & Problems Solved

1. **Software Monetization & Copy Protection:** Prevents users from sharing a single license across multiple computers by fingerprinting client hardware and verifying authenticity with the server.
2. **Reseller & Partner Management:** Enables software vendors to onboard resellers, allocate license quotas, and track license issuance per reseller with custom prefixes.
3. **Automated Verification Handshake:** Provides secure, HMAC-signed REST APIs for client applications to validate licenses in real-time.
4. **App Self-Update Service:** Hosts software release manifests and cryptographically signed (`Ed25519`) installer binaries for client self-updates.
5. **AI-First Specification & High Discipline:** Contains formal specifications, continuous compliance linters, and strict coding guidelines ensuring stability across human and AI development.

---

## 3. High-Level Architecture & Tech Stack

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (React 19)                      │
│   - Admin Dashboard        - Reseller Panel                 │
│   - Client Portal          - Documentation Viewer           │
│   (TanStack Start, Vite, Tailwind CSS v4, Radix UI)         │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTP / JSON (PascalCase)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   Backend (Laravel 11 / PHP 8.3)            │
│   - Authentication (Sanctum JWT + OAuth2 + HMAC)            │
│   - Casbin RBAC (Permissions & Roles)                       │
│   - Licensing & Hardware-Binding Engine                     │
│   - Self-Update & Binary Asset Server                       │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                  Split-Database Architecture                │
│  ├── Root Database: Platform users, resellers, audit logs   │
│  └── Shard Databases: Isolated database per reseller        │
└─────────────────────────────────────────────────────────────┘
```

- **Backend (`/backend`)**: Built with **Laravel 11** (PHP 8.3), **Laravel Sanctum** (token authentication), and **Lauthz/Casbin** (fine-grained RBAC).
- **Frontend (`/src`)**: Built with **React 19**, **TanStack Start** (SSR/router), **Vite**, **Tailwind CSS v4**, and **Radix UI**.
- **Specifications (`/02-spec`)**: 28+ structured specification phases covering database schemas, API contracts, error taxonomy, and security lifecycles.
- **AI Memory (`/.ai-memory`)**: Persistent institutional knowledge, coding rules, roadmap, and task tracking.
- **Linters & Tooling (`/linter-scripts`)**: Automated Python and shell scripts enforcing naming conventions, function length limits, and API contract parity.

---

## 4. Key Functional Modules

### A. Licensing System
- **Duration Categories:** `Daily`, `Weekly`, `Monthly`, `Yearly`, `Lifetime`, `Dev`, and `Key`.
- **Constraint Variations:**
  - `User` count: Maximum concurrent users allowed.
  - `Machine` count: Maximum physical machines bound to a license.
- **Serial Format:** Reseller-prefixed serial strings (e.g., `ALIM-XXXX-YYYY`) containing embedded category/version metadata.

### B. 3-Step Verification Handshake (Client ↔ Server)
When an external application verifies a license:
1. **Step 1 — Verify Serial (`POST /api/Portal/Verify/Serial`):** Confirms the serial exists and is active.
2. **Step 2 — Verify Hash (`POST /api/Portal/Verify/Hash`):** Client submits a hardware fingerprint hash (motherboard serial, MAC, disk ID) and receives a short-lived `VerifyKey` (5-minute TTL).
3. **Step 3 — Final Authorization (`POST /api/Portal/Verify/Final`):** Server validates the `VerifyKey`, checks deployment environment (`Development`, `Staging`, `Production`), resolves tier features, and returns an authorization payload.

### C. Reseller Ecosystem & Quotas
- Admins configure license allowances for Resellers.
- When a reseller issues a license, their quota atomically decrements within a transactional boundary.
- All quota changes are recorded in an append-only journal (`ResellerQuotaLedger`).
- Resellers can submit `QuotaRequests`, which Admins review, approve, or deny.

### D. Multi-Tenant Split-Database Architecture
- **Root DB:** Manages global state (users, roles, reseller directory, global audit logs, runtime configurations).
- **Shard DB:** Dedicated database partition per reseller for tenant licenses, machine bindings, and local ledger records.

### E. App Self-Update Service
- Serves update manifests via `GET /api/App/UpdateManifest`.
- Delivers binary assets via `GET /api/App/UpdateAsset/{Version}/{Platform}`.
- Provides detached `Ed25519` cryptographic signatures (`.sig`) to prevent tampered or malicious binary execution.

### F. Security & Administration
- **RBAC:** Roles include `SuperAdmin`, `Admin`, `Reseller`, `AppBuilder`, `EndUser`.
- **Admin Impersonation:** Secure, time-bounded (30 minutes) operator impersonation for user support, fully recorded in audit logs.
- **PII Protection:** Hashes IP addresses and User Agents using rotating cryptographic salts.
- **Disaster Recovery:** Automated snapshot export and import APIs (`/api/Admin/Backup/Exports` and `/Imports`).

---

## 5. Directory Reference

| Path | Purpose |
| :--- | :--- |
| `backend/` | Laravel 11 API application, database migrations, controllers, models, and tests. |
| `src/` | React 19 / TanStack Start frontend (Admin, Reseller, and Client portals). |
| `02-spec/` | System specifications (DB schemas, API contracts, UI tokens, security flows). |
| `.ai-memory/` | AI session context, coding guidelines, task lists, and architectural decisions. |
| `linter-scripts/` | Python/Shell linters for code shape, function lengths, and schema parity. |
| `tests/` | Playwright E2E test suites and backend integration tests. |
| `run.ps1` / `run.sh` | Project orchestration scripts for local development setup. |
