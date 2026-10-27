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

export async function issueSimulatedLicense(slug: string, dealCode: string): Promise<void> {
  await fetch(`${getApiBaseUrl()}/api/v1/products/${slug}/licenses/issue`, {
    method: "POST",
    headers: { "Content-Type": "application/json", ...authHeaders() },
    body: JSON.stringify({
      transactionId: `tx_mock_${Date.now()}`,
      customerEmail: "developer-test@domain.local",
      dealCode,
    }),
  });
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
