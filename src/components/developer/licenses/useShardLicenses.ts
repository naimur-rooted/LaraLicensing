import { useState, useEffect, useCallback } from "react";
import type { ShardLicense, ProductSelectItem } from "./types";
import { getApiBaseUrl } from "@/lib/api-client-boundary";

export function useShardLicenses() {
  const [products, setProducts] = useState<readonly ProductSelectItem[]>([]);
  const [selectedSlug, setSelectedSlug] = useState<string>("");
  const [licenses, setLicenses] = useState<readonly ShardLicense[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isMinting, setIsMinting] = useState<boolean>(false);
  const [searchTerm, setSearchTerm] = useState<string>("");

  const getHeaders = useCallback((): HeadersInit => {
    const token = sessionStorage.getItem("LicensingPortal.AccessToken") ?? "";
    const headers: Record<string, string> = { Accept: "application/json" };
    if (token.length > 0) {
      headers.Authorization = `Bearer ${token}`;
    }
    return headers;
  }, []);

  const fetchProducts = useCallback(async () => {
    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products`, { headers: getHeaders() });
      const json = await res.json();
      const list: ProductSelectItem[] = json?.Data ?? [];
      setProducts(list);
      if (list.length > 0) {
        setSelectedSlug((prev) => (prev.length > 0 ? prev : list[0].productSlug));
      }
    } catch {
      setProducts([]);
    }
  }, [getHeaders]);

  const fetchLicenses = useCallback(async (slug: string) => {
    if (slug.length === 0) return;
    setIsLoading(true);
    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${slug}/licenses`, { headers: getHeaders() });
      const json = await res.json();
      setLicenses(json?.Data ?? []);
    } catch {
      setLicenses([]);
    } finally {
      setIsLoading(false);
    }
  }, [getHeaders]);

  const simulateMint = useCallback(async (dealCode: string = "MONTHLY_ANCHOR") => {
    if (selectedSlug.length === 0) return;
    setIsMinting(true);
    try {
      await fetch(`${getApiBaseUrl()}/api/v1/products/${selectedSlug}/licenses/issue`, {
        method: "POST",
        headers: { "Content-Type": "application/json", ...getHeaders() },
        body: JSON.stringify({
          transactionId: `tx_mock_${Date.now()}`,
          customerEmail: "developer-test@domain.local",
          dealCode,
        }),
      });
      await fetchLicenses(selectedSlug);
    } finally {
      setIsMinting(false);
    }
  }, [selectedSlug, getHeaders, fetchLicenses]);

  useEffect(() => {
    fetchProducts();
  }, [fetchProducts]);

  useEffect(() => {
    if (selectedSlug.length > 0) {
      fetchLicenses(selectedSlug);
    }
  }, [selectedSlug, fetchLicenses]);

  const filteredLicenses = licenses.filter((item) => {
    if (searchTerm.length === 0) return true;
    const lower = searchTerm.toLowerCase();
    return item.serialValue.toLowerCase().includes(lower) || item.dealCode.toLowerCase().includes(lower);
  });

  return {
    products,
    selectedSlug,
    setSelectedSlug,
    licenses: filteredLicenses,
    totalCount: licenses.length,
    isLoading,
    isMinting,
    searchTerm,
    setSearchTerm,
    simulateMint,
    refresh: () => fetchLicenses(selectedSlug),
  };
}
