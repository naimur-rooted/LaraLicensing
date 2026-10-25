import { useState, useEffect, useCallback } from "react";
import { getApiBaseUrl } from "@/lib/lara-api-client";
import { getLaraAccessToken } from "@/lib/lara-api-session";
import { getRuntimeMode } from "@/lib/runtime-mode";
import {
  type OverviewData,
  type OverviewProductOption,
  PREVIEW_OVERVIEW_DATA,
  mapOverviewData,
  createOverviewAuthHeaders,
} from "./overview-types";

export function useDeveloperOverview() {
  const [data, setData] = useState<OverviewData | null>(null);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<string>("all");
  const [productsList, setProductsList] = useState<readonly OverviewProductOption[]>([]);
  const isPreviewMode = getRuntimeMode().Mode === "preview";

  const fetchOverview = useCallback(async (slug = selectedProduct) => {
    if (isPreviewMode) {
      setData(PREVIEW_OVERVIEW_DATA);
      return;
    }
    setIsRefreshing(true);
    try {
      const query = slug && slug !== "all" ? `?productSlug=${encodeURIComponent(slug)}` : "";
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/overview${query}`, {
        headers: createOverviewAuthHeaders(getLaraAccessToken()),
      });
      const json = await res.json();
      if (json.IsSuccess && json.Data) setData(mapOverviewData(json.Data));
    } catch (e) {
      console.error("Failed to load developer overview metrics", e);
    } finally {
      setIsRefreshing(false);
    }
  }, [isPreviewMode, selectedProduct]);

  useEffect(() => {
    fetchOverview();
    if (isPreviewMode) {
      setProductsList([{ slug: "preview-app", name: "Sample Desktop Suite" }]);
      return;
    }
    fetch(`${getApiBaseUrl()}/api/v1/developer/products`, {
      headers: createOverviewAuthHeaders(getLaraAccessToken()),
    })
      .then((r) => r.json())
      .then((json) => {
        if (json.IsSuccess && Array.isArray(json.Data)) {
          setProductsList(json.Data.map((p: any) => ({ slug: p.productSlug, name: p.productName })));
        }
      })
      .catch(() => {});
  }, [fetchOverview, isPreviewMode]);

  return {
    data,
    isRefreshing,
    selectedProduct,
    setSelectedProduct,
    productsList,
    isPreviewMode,
    fetchOverview,
  };
}
