import { getApiBaseUrl } from "@/lib/lara-api-client";
import type { ShardLicense, ProductSelectItem } from "./types";

function authHeaders(): Record<string, string> {
  const token = sessionStorage.getItem("LicensingPortal.AccessToken") ?? "";
  const headers: Record<string, string> = { Accept: "application/json" };
  if (token.length > 0) headers.Authorization = `Bearer ${token}`;
  return headers;
}

export async function fetchProductList(): Promise<ProductSelectItem[]> {
  try {
    const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products`, { headers: authHeaders() });
    const json = await res.json();
    return json?.Data ?? [];
  } catch {
    return [];
  }
}

export async function fetchProductLicenses(slug: string): Promise<ShardLicense[]> {
  if (slug.length === 0) return [];
  try {
    const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${slug}/licenses`, { headers: authHeaders() });
    const json = await res.json();
    return json?.Data ?? [];
  } catch {
    return [];
  }
}

export async function issueSimulatedLicense(slug: string, dealCode?: string): Promise<void> {
  // Phase 4 item 2: mint via the Sanctum developer endpoint, NOT the
  // HMAC-gated gateway webhookIssue endpoint (which returns 401
  // without a gateway signature). No secret is sent from the browser:
  // auth is the Sanctum Bearer token already used above. dealCode is
  // optional; omitted, the backend mints against the product's first
  // active deal (the old hardcoded MONTHLY_ANCHOR matched no real deal).
  const body: Record<string, string> = {
    transactionId: `tx_mock_${Date.now()}`,
    customerEmail: "developer-test@domain.local",
  };
  if (dealCode !== undefined && dealCode.length > 0) body.dealCode = dealCode;
  try {
    const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${slug}/licenses/mint`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeaders() },
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const json = await res.json().catch(() => ({}));
      alert(json?.Message ?? json?.error ?? `Failed to mint test license (${res.status})`);
    }
  } catch (err: unknown) {
    alert(err instanceof Error ? err.message : "Failed to mint test license.");
  }
}

export async function deleteProductLicenseShard(slug: string, licenseId: number): Promise<boolean> {
  try {
    const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${slug}/licenses/${licenseId}`, {
      method: "DELETE",
      headers: authHeaders(),
    });
    if (!res.ok) {
      const json = await res.json().catch(() => ({}));
      alert(json?.Message ?? json?.error ?? `Failed to delete license (${res.status})`);
      return false;
    }
    return true;
  } catch (err: unknown) {
    alert(err instanceof Error ? err.message : "Failed to delete license.");
    return false;
  }
}
