import { useState, useEffect, useCallback } from "react";
import type { ShardLicense, ProductSelectItem } from "./types";
import {
  fetchProductList,
  fetchProductLicenses,
  issueSimulatedLicense,
  deleteProductLicenseShard,
} from "./shardLicensesApi";

export function useShardLicenses() {
  const [products, setProducts] = useState<readonly ProductSelectItem[]>([]);
  const [selectedSlug, setSelectedSlug] = useState<string>("");
  const [licenses, setLicenses] = useState<readonly ShardLicense[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isMinting, setIsMinting] = useState<boolean>(false);
  const [searchTerm, setSearchTerm] = useState<string>("");

  const loadProducts = useCallback(async () => {
    const list = await fetchProductList();
    setProducts(list);
    if (list.length > 0) {
      setSelectedSlug((prev) => (prev.length > 0 ? prev : list[0].productSlug));
    }
  }, []);

  const loadLicenses = useCallback(async (slug: string) => {
    if (slug.length === 0) return;
    setIsLoading(true);
    try {
      const data = await fetchProductLicenses(slug);
      setLicenses(data);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const simulateMint = useCallback(async (dealCode?: string) => {
    if (selectedSlug.length === 0) return;
    setIsMinting(true);
    try {
      await issueSimulatedLicense(selectedSlug, dealCode);
      await loadLicenses(selectedSlug);
    } finally {
      setIsMinting(false);
    }
  }, [selectedSlug, loadLicenses]);

  const deleteLicense = useCallback(async (licenseId: number): Promise<boolean> => {
    if (selectedSlug.length === 0) return false;
    const ok = await deleteProductLicenseShard(selectedSlug, licenseId);
    if (ok) {
      await loadLicenses(selectedSlug);
    }
    return ok;
  }, [selectedSlug, loadLicenses]);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  useEffect(() => {
    if (selectedSlug.length > 0) {
      loadLicenses(selectedSlug);
    }
  }, [selectedSlug, loadLicenses]);

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
    deleteLicense,
    refresh: () => loadLicenses(selectedSlug),
  };
}
