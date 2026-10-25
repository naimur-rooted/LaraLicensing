import React from "react";
import { RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { OverviewProductOption } from "./overview-types";

interface OverviewHeaderProps {
  readonly trialExpiresOn: string;
  readonly isPreviewMode: boolean;
  readonly selectedProduct: string;
  readonly productsList: readonly OverviewProductOption[];
  readonly isRefreshing: boolean;
  readonly onProductChange: (slug: string) => void;
  readonly onRefresh: () => void;
}

export function OverviewHeader({
  trialExpiresOn,
  isPreviewMode,
  selectedProduct,
  productsList,
  isRefreshing,
  onProductChange,
  onRefresh,
}: OverviewHeaderProps) {
  return (
    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-2 border-b border-border/60">
      <div>
        <div className="flex items-center gap-2 text-xs text-muted-foreground font-medium">
          <span className="text-primary hover:underline cursor-pointer">Home</span>
          <span>/</span>
          <span className="text-foreground">Overview</span>
        </div>
        <h1 className="text-xl font-bold tracking-tight text-foreground mt-0.5">Overview</h1>
      </div>

      <div className="flex items-center gap-3 self-end sm:self-auto">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>
            Trial expires on:{" "}
            <strong className="text-foreground font-medium font-mono">
              {trialExpiresOn || "Nov 20, 2026"}
            </strong>
          </span>
        </div>

        <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-md border bg-background text-xs font-medium shadow-2xs">
          <span className={`size-2 rounded-full ${isPreviewMode ? "bg-amber-500" : "bg-emerald-500"} animate-pulse`} />
          <span className="text-foreground">{isPreviewMode ? "Preview Mode" : "Production"}</span>
        </div>

        <div className="flex items-center gap-1.5">
          <select
            value={selectedProduct}
            onChange={(e) => onProductChange(e.target.value)}
            className="h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="all">All Sharded Products</option>
            {productsList.map((p) => (
              <option key={p.slug} value={p.slug}>
                {p.name}
              </option>
            ))}
          </select>

          <Button
            variant="outline"
            size="icon"
            onClick={onRefresh}
            disabled={isRefreshing}
            className="size-8 text-primary hover:text-primary hover:bg-primary/10 transition-colors"
            title="Refresh Dashboard"
          >
            <RefreshCw className={`size-3.5 ${isRefreshing ? "animate-spin" : ""}`} />
          </Button>
        </div>
      </div>
    </div>
  );
}
