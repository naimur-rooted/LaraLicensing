# Transcript Extraction: Developer Licensing Platform Vision & Core Rules

Source: Bangla System Architecture Session  
Date: 2026-10-19  
Scope: System Workflow, Business Rules, and Pricing Strategy  

---

## 1. Key Business Rules from the Meeting

1. **The Three-Tier Concept:**
   - Platform Level (Us): Licensing engine.
   - Developer Level (Our User): Creates products, packages, deals (e.g., Extension with 1-month, 1-year, lifetime deal).
   - Downstream Consumer: Uses the developer's app, verifies license with machine limits.

2. **Platform Pricing & Value Tiers for Developers:**
   - **Free Plan:** 1 Product, 100 requests/day, 1st year free, then $1/mo.
   - **Essential:** 3 Products, 200 requests/day, 25 requests/hr, $2.50/mo or $19.90/yr.
   - **Pro:** 15 Products, 1,000 requests/day, 120 requests/hr, $5.00/mo or $49.90/yr.
   - **Max:** 50 Products, 5,000 requests/day, 500 requests/hr, $9.90/mo or $89.00/yr.
   - **Lifetime Deal (LTD):** ~$1,000 one-time payment for unlimited products and high-volume burst quota.
   - **Decoy Strategy:** Anchor monthly pricing so that 1-year and 2-year packages are irresistible (70%-82% discounts).

3. **Database Architecture & Partitioning:**
   - Root DB tracks platform-level users, plans, and global quotas.
   - Each product has its own database shard (`product_<slug>.sqlite`).
   - Consumer accounts and bindings are kept small (target 100-200 rows per active partition) to prevent SQLite lock contention.

4. **Zero-Trust Tokens & Periodic Checks:**
   - Verification uses signed Ed25519 JWT offline tokens.
   - Heartbeat/verification check does not hammer the server constantly; monthly licenses verify every 7 days.
   - Multiple machine violations or impossible travel (geo-velocity) trigger automatic rejection and developer notification.

5. **Payment Gateway Separation:**
   - Zero storage of payment card numbers (avoiding regulatory and criminal liability).
   - External payment confirmation dispatches webhooks to issue licenses.

6. **AI Integration Generator:**
   - The developer portal generates full Markdown prompts instructing external AIs on how to integrate the client app with the licensing API in minutes.
