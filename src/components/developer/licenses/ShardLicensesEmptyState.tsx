import { KeyRound, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";

interface ShardLicensesEmptyStateProps {
  readonly selectedSlug: string;
  readonly onSimulateMint: () => void;
  readonly isMinting: boolean;
  readonly searchTerm: string;
}

export function ShardLicensesEmptyState({
  selectedSlug,
  onSimulateMint,
  isMinting,
  searchTerm,
}: ShardLicensesEmptyStateProps) {
  const hasFilter = searchTerm.length > 0;

  return (
    <div className="py-12 px-4 text-center rounded-xl border border-dashed border-border/80 bg-muted/10 my-2">
      <div className="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
        <KeyRound className="size-5" />
      </div>

      <h3 className="text-sm font-semibold text-foreground">
        {hasFilter ? "No matching licenses found" : "No Shard Licenses Minted Yet"}
      </h3>

      <p className="text-xs text-muted-foreground max-w-md mx-auto mt-1 mb-4 leading-relaxed">
        {hasFilter
          ? `No license keys match the filter "${searchTerm}". Clear your search query to see all issued keys.`
          : `No licenses exist in product_${selectedSlug}.sqlite. Serials are minted when customers purchase plans via webhook or when you issue test serials.`}
      </p>

      {hasFilter ? null : (
        <Button
          type="button"
          size="sm"
          className="h-8 text-xs gap-1.5 font-medium"
          onClick={onSimulateMint}
          disabled={isMinting || selectedSlug.length === 0}
        >
          <Sparkles className="size-3.5" />
          <span>{isMinting ? "Minting Test Serial..." : "Mint First Test License Key"}</span>
        </Button>
      )}
    </div>
  );
}
