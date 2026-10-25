# Plan 29: Auth Login Credential Verification & Live API Routing Parity

**Status:** COMPLETED  
**Created:** 2026-10-26  
**Completed:** 2026-10-26  
**Normative Specifications:**
- `02-spec/21-app/16-ui-surfaces.md`
- `02-spec/21-app/53-developer-registration-and-billing-architecture-spec.md`
- `02-spec/28-runtime-modes/00-overview.md`

---

## 1. Executive Summary
Remediated the security and functional bug where unknown/non-existent accounts were allowed to log into the application, and live backend login requests were routed to an invalid endpoint:

1. **Purged Fake Pass-Through in `lara-auth.ts`:**
   - Eliminated `createPreviewLoginSession(email)` which previously returned 200 OK with a synthetic token for any arbitrary string without password checking or credential lookup.
   - Implemented `verifyPreviewLoginCredentials(email, password)`: Queries `auth::credentials` in the preview store and strictly rejects any non-matching email or password with `401: Invalid email or password`.
2. **Preview Registration Credential Persistence:**
   - In `createPreviewDeveloperSession`, new developer registrations now persist email and password into `auth::credentials` and check for duplicate registrations (HTTP 409 Conflict).
3. **Live Backend Login Route & Envelope Parity:**
   - In `loginToLaraApi`, updated the live backend route from non-existent `/Auth/Token` to `/Api/Auth/Login`.
   - Updated `tokenResultSchema` to cleanly parse OAuth2 token payloads (`AccessToken`, `RefreshToken`, `TokenType`, `ExpiresIn`).
   - Added backend route aliases `POST /api/v1/auth/login` and `POST /v1/auth/login` in `backend/routes/api.php`.
   - Updated `LoginController.php` to include standard `'TokenType' => 'Bearer'` and `'ExpiresIn' => 3600`.
4. **Verification:**
   - Added `tests/lara-auth-credentials.test.ts` (5 tests passed).
   - Ran `php artisan test tests/Feature/Auth` (31 tests, 260 assertions passed).
   - Ran `npx vitest run` (145 test files, 950 tests passed).
