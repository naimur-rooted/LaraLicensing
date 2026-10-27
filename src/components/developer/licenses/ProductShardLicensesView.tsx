import { useShardLicenses } from "./useShardLicenses";
import { ShardLicensesHeader } from "./ShardLicensesHeader";
import { ShardLicensesTable } from "./ShardLicensesTable";
import { ShardLicensesEmptyState } from "./ShardLicensesEmptyState";
import { Loader2 } from "lucide-react";

interface ProductShardLicensesViewProps {
  readonly onApplySerial?: (serial: string) => void;
}

export function ProductShardLicensesView({ onApplySerial }: ProductShardLicensesViewProps) {
  const {
    products,
    selectedSlug,
    setSelectedSlug,
    licenses,
    totalCount,
    isLoading,
    isMinting,
    searchTerm,
    setSearchTerm,
    simulateMint,
    refresh,
  } = useShardLicenses();

  const hasLicenses = licenses.length > 0;

  return (
    <div className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-xs">
      <ShardLicensesHeader
        products={products}
        selectedSlug={selectedSlug}
        onSelectProduct={setSelectedSlug}
        searchTerm={searchTerm}
        onSearchChange={setSearchTerm}
        onSimulateMint={() => simulateMint("MONTHLY_ANCHOR")}
        onRefresh={refresh}
        isMinting={isMinting}
        isLoading={isLoading}
        totalCount={totalCount}
      />

      {isLoading ? (
        <div className="py-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
          <Loader2 className="size-4 animate-spin text-primary" />
          <span>Querying SQLite shard product_{selectedSlug}.sqlite...</span>
        </div>
      ) : hasLicenses ? (
        <ShardLicensesTable licenses={licenses} onApplySerial={onApplySerial} />
      ) : (
        <ShardLicensesEmptyState
          selectedSlug={selectedSlug}
          onSimulateMint={() => simulateMint("MONTHLY_ANCHOR")}
          isMinting={isMinting}
          searchTerm={searchTerm}
        />
      )}
    </div>
  );
}
