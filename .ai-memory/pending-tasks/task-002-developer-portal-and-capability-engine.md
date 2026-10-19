# Pending Task: Developer Portal & Capability Licensing Engine

ID: task-002-developer-portal-and-capability-engine  
Status: pending  
Owner: Principal Architect & AI Assistant  
Created: 2026-10-19  
Specification: `02-spec/21-app/51-platform-ecosystem-and-capability-spec.md`  

---

## 1. Objectives

1. **Developer Portal & Product Management:**
   - Allow creators to register and manage Products.
   - Configure custom pricing packages, deal tiers, and seat allocations per product.
2. **Capability Engine:**
   - Enforce metered capabilities (`canCreateProduct`, `maxProductCount`, `dailyRequestQuota`, `hourlyRequestQuota`).
   - Rate limit API access and meter serial creation against developer platform tiers.
3. **Product SQLite Sharding:**
   - Automate provisioning of per-product SQLite databases (`product_<slug>.sqlite`).
   - Enforce connection isolation, WAL mode, and busy timeouts.
4. **AI-Ready Integration Generator:**
   - Provide a UI and API endpoint to generate copy-paste Markdown integration prompts for developers.
5. **Decoupled Payment Webhook Ingestion:**
   - Provide idempotency-safeguarded webhook endpoints for external payment providers to trigger license issuance.

---

## 2. Invariants & Verification Checklist
- [ ] No cardholder data stored.
- [ ] All table names PascalCase, columns camelCase.
- [ ] Positive booleans only (`isActive`, `isRevoked`, `isBound`).
- [ ] Function lengths strictly between 8 and 15 lines.
- [ ] Automated tests for capability quotas and product shard provisioning.
