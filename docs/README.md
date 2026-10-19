# LaraLicensingV1 Documentation Index

Welcome to the documentation suite for **LaraLicensingV1** (`alimtvnetwork/lara-licensing-v4`), an enterprise-grade multi-tenant software licensing, hardware-bound entitlement, and automated distribution platform.

---

## Master Architecture & System Manual

- **[Comprehensive System & Architecture Documentation](file:///c:/Users/naimu/Desktop/Licensing/docs/COMPREHENSIVE_SYSTEM_DOCUMENTATION.md)**  
  The authoritative technical reference covering architecture, split-DB, APIs, security, client app, and runbooks.
- **[Project Analysis & Gemini Master Prompt Engine](file:///c:/Users/naimu/Desktop/Licensing/docs/PROJECT_ANALYSIS_AND_GEMINI_MASTER_PROMPT.md)**  
  Detailed state-of-the-project breakdown across all layers and a battle-tested Gemini Master Prompt for implementing advanced features (floating concurrent licenses, air-gapped offline QR activation, payment webhooks, polyglot native SDKs, and telemetry).

---

## Domain Guides & Runbooks

### 1. Operations & Deployment
- [Backend Rollout Runbook](file:///c:/Users/naimu/Desktop/Licensing/docs/backend/rollout-runbook.md): Deployment and operational runbook for the Laravel backend.
- [Admin Runtime Runbook](file:///c:/Users/naimu/Desktop/Licensing/docs/backend/admin-runtime-runbook.md): Administrative operational procedures and runtime config instructions.
- [Environment Matrix](file:///c:/Users/naimu/Desktop/Licensing/docs/deploy/environment-matrix.md): Configuration variables for development, staging, and production environments.

### 2. Testing & Quality Assurance
- [Licensing Testing Methods](file:///c:/Users/naimu/Desktop/Licensing/docs/licensing-testing-methods.md): Testing scenarios, hardware binding validation, and test harness execution.
- [Testing Readme & Test Manifest](file:///c:/Users/naimu/Desktop/Licensing/docs/testing/readme.md): Master inventory of automated backend and frontend test suites.
- [Static Analysis & Linters](file:///c:/Users/naimu/Desktop/Licensing/docs/static-analysis-frontend.md): Rules and baselines for frontend static analysis and API surface validation.

### 3. Architecture & Specifications
- [Initial Repository Analysis](file:///c:/Users/naimu/Desktop/Licensing/docs/repository-analysis.md): High-level system overview and architectural foundations.
- [Specification Directory](file:///c:/Users/naimu/Desktop/Licensing/02-spec/): 25+ formal engineering specifications covering database design, API contracts, error taxonomy, and security lifecycles.
- [Persistent AI Memory](file:///c:/Users/naimu/Desktop/Licensing/.ai-memory/): Canonical onboarding frame, guidelines, decisions, and constraints.

---

## Quick Reference: Running the System

```powershell
# 1. Start Laravel Backend (Port 8000)
cd backend
php artisan serve --port=8000

# 2. Start Vite React Frontend (Port 8080)
.\node_modules\.bin\vite.cmd dev

# 3. Launch Desktop Client Simulator
python scripts/demo_client_app.py

# 4. Run Automated Client Test Suite (11 Tests)
python scripts/demo_client_app.py --test-all
```
