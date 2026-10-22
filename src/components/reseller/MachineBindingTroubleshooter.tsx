import { useState, type FormEvent } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Search, Loader2 } from "lucide-react";
import { toast } from "sonner";

import { MachineDeviceCard } from "./MachineDeviceCard";
import { ReleaseDeviceConfirmDialog } from "./ReleaseDeviceConfirmDialog";
import {
  resellerLicenseBindingsQueryOptions,
  releaseMachineBinding,
  type MachineBinding,
} from "../../lib/lara-machine-binding";
import { formatLaraApiError } from "../../lib/lara-api-error";

export function MachineBindingTroubleshooter() {
  const queryClient = useQueryClient();
  const [searchInput, setSearchInput] = useState("");
  const [searchedKey, setSearchedKey] = useState("LARA-ALIM-V1-9F3K-2XQ8-77TA");
  const [targetBinding, setTargetBinding] = useState<MachineBinding | null>(null);

  const { data: bindings = [], isLoading } = useQuery(
    resellerLicenseBindingsQueryOptions(searchedKey),
  );

  const releaseMutation = useMutation({
    mutationFn: (id: number) => releaseMachineBinding(searchedKey, id),
    onSuccess: () => {
      toast.success("Machine binding released. 15-minute cooldown initiated.");
      setTargetBinding(null);
      void queryClient.invalidateQueries({ queryKey: ["LaraApi", "Reseller", "Licenses"] });
    },
    onError: (err) => toast.error(formatLaraApiError(err)),
  });

  function handleSearch(e: FormEvent) {
    e.preventDefault();
    if (searchInput.trim()) setSearchedKey(searchInput.trim());
  }

  return (
    <div className="space-y-6">
      <form onSubmit={handleSearch} className="flex gap-2">
        <input
          type="text"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder="Enter License Key or Serial (e.g. LARA-ALIM-V1-...)"
          className="h-10 flex-1 rounded-lg border border-input bg-background px-4 text-sm focus-ring"
        />
        <button
          type="submit"
          className="focus-ring inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        >
          <Search className="size-4" />
          <span>Lookup</span>
        </button>
      </form>

      {isLoading ? (
        <div className="flex h-32 items-center justify-center"><Loader2 className="size-6 animate-spin text-muted-foreground" /></div>
      ) : bindings.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
          No active machine bindings found for {searchedKey}.
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {bindings.map((b) => (
            <MachineDeviceCard key={b.MachineBindingId} binding={b} onReleaseClick={setTargetBinding} />
          ))}
        </div>
      )}

      <ReleaseDeviceConfirmDialog
        isOpen={Boolean(targetBinding)}
        isPending={releaseMutation.isPending}
        fingerprintSnippet={targetBinding ? targetBinding.FingerprintHash.slice(0, 16) : ""}
        onClose={() => setTargetBinding(null)}
        onConfirm={() => targetBinding && releaseMutation.mutate(targetBinding.MachineBindingId)}
      />
    </div>
  );
}
