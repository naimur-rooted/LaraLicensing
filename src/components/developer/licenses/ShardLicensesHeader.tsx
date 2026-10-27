import { KeyRound, RefreshCw, PlusCircle, Search, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { ProductSelectItem } from "./types";

interface ShardLicensesHeaderProps {
  readonly products: readonly ProductSelectItem[];
  readonly selectedSlug: string;
  readonly onSelectProduct: (slug: string) => void;
  readonly searchTerm: string;
  readonly onSearchChange: (val: string) => void;
  readonly onSimulateMint: () => void;
  readonly onRefresh: () => void;
  readonly isMinting: boolean;
  readonly isLoading: boolean;
  readonly totalCount: number;
}

export function ShardLicensesHeader({
  products,
  selectedSlug,
  onSelectProduct,
  searchTerm,
  onSearchChange,
  onSimulateMint,
  onRefresh,
  isMinting,
  isLoading,
  totalCount,
}: ShardLicensesHeaderProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-border/60">
      <div>
        <div className="flex items-center gap-2">
          <KeyRound className="size-5 text-primary" />
          <h2 className="text-base font-semibold text-foreground">Product Shard Licenses</h2>
          <span className="text-xs px-2 py-0.5 rounded-full font-mono bg-primary/10 text-primary border border-primary/20">
            {totalCount} Total
          </span>
        </div>
        <p className="text-xs text-muted-foreground mt-0.5">
          Live SQLite-isolated serials and bound machine seats.
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {products.length > 0 && (
          <select
            value={selectedSlug}
            onChange={(e) => onSelectProduct(e.target.value)}
            className="h-8 text-xs rounded-md border border-input bg-background px-2.5 py-1 text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
          >
            {products.map((p) => (
              <option key={p.productSlug} value={p.productSlug}>
                {p.productName} ({p.productSlug})
              </option>
            ))}
          </select>
        )}

        <div className="relative">
          <Search className="size-3.5 absolute left-2.5 top-2.5 text-muted-foreground" />
          <input
            type="text"
            placeholder="Filter serials..."
            value={searchTerm}
            onChange={(e) => onSearchChange(e.target.value)}
            className="h-8 w-36 sm:w-44 text-xs pl-8 pr-2.5 rounded-md border border-input bg-background text-foreground"
          />
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-8 px-2 text-xs"
          onClick={onRefresh}
          disabled={isLoading}
        >
          <RefreshCw className={`size-3.5 ${isLoading ? "animate-spin" : ""}`} />
        </Button>

        <Button
          type="button"
          size="sm"
          className="h-8 text-xs gap-1.5 font-medium"
          onClick={onSimulateMint}
          disabled={isMinting || selectedSlug.length === 0}
        >
          <PlusCircle className="size-3.5" />
          <span>{isMinting ? "Minting..." : "Mint Test Serial"}</span>
        </Button>
      </div>
    </div>
  );
}
