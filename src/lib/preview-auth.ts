import { ApiErrorCodeType, LaraApiError } from "@/lib/lara-api-error";
import { setLaraAccessToken, setLaraRefreshToken } from "@/lib/lara-api-session";
import { DEMO_IDENTITIES, type DemoIdentity } from "@/lib/demo-identities";
import { read, write, list } from "@/lib/preview-store";
import type { AdminUser, MeUser } from "@/generated/api/schema";

export { DEMO_IDENTITIES, type DemoIdentity };

/**
 * Signs in as a demo user by writing a synthetic session.
 * Plan 18 Phase C (Step 44-46).
 */
export async function signInWithSeedIdentity(identityId: string): Promise<void> {
  const identity = DEMO_IDENTITIES.find((i) => i.id === identityId);
  const isFailed = !identity;
  if (isFailed) {
    throw new LaraApiError(
      `Unknown seed identity: ${identityId}`,
      ApiErrorCodeType.AuthInvalidCredentials,
      401,
    );
  }

  // Synthetic tokens that bypass real JWT validation but satisfy string-type guards
  // in _authenticated.tsx and api-client.ts.
  const syntheticAccessToken = `seed_access_${identity.id}_${Date.now()}`;
  const syntheticRefreshToken = `seed_refresh_${identity.id}_${Date.now()}`;

  setLaraAccessToken(syntheticAccessToken);
  setLaraRefreshToken(syntheticRefreshToken);

  // Sync me::current in preview store so both synthetic and fallback auth.me paths match immediately
  try {
    const rows = await list<AdminUser>("admin-users");
    const emailMap: Record<string, string> = {
      admin: "admin@demo.lara.test",
      reseller: "reseller@demo.lara.test",
      portal: "user@licensingportal.local",
    };
    const targetEmail = emailMap[identity.id];
    const match = rows.map(([, u]) => u).find((u) => u.Email === targetEmail);

    if (match) {
      const roles = identity.id === "portal" ? ["portal", "enduser"] : match.Roles;
      await write<MeUser>("me", "current", {
        Id: match.Id,
        Email: match.Email,
        DisplayName: match.DisplayName,
        Roles: roles,
        ResellerId: match.ResellerId,
        CreatedAt: match.CreatedAt,
        UpdatedAt: new Date().toISOString(),
      });
    }
  } catch (err) {
    console.warn("signInWithSeedIdentity: could not update me::current", err);
  }

  console.info("auth.seed_signin_success", { identityId: identity.id });
}
