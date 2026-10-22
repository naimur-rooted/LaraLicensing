import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Sparkles, Loader2 } from "lucide-react";
import { toast } from "sonner";

import { SerialLivePreviewCard } from "./SerialLivePreviewCard";
import { SerialMintFormControls } from "./SerialMintFormControls";
import {
  formatSerialPreview,
  issueResellerLicense,
  type LicenseCategoryName,
} from "../../lib/lara-reseller-mint";
import { formatLaraApiError } from "../../lib/lara-api-error";

export interface ResellerSerialMintingStudioProps {
  resellerId: number;
}

export function ResellerSerialMintingStudio({ resellerId }: ResellerSerialMintingStudioProps) {
  const queryClient = useQueryClient();
  const [prefix, setPrefix] = useState("ALIM");
  const [category, setCategory] = useState<LicenseCategoryName>("Monthly");
  const [tier, setTier] = useState<"Starter" | "Growth" | "Scale" | "Enterprise">("Starter");
  const [environment, setEnvironment] = useState<"Production" | "Staging" | "Sandbox">("Production");
  const [mintedSerial, setMintedSerial] = useState<string | null>(null);

  const previewString = mintedSerial ?? formatSerialPreview(prefix, category, "V1");
  const hasMinted = Boolean(mintedSerial);

  const mintMutation = useMutation({
    mutationFn: () =>
      issueResellerLicense({
        PrefixValue: prefix,
        LicenseCategory: category,
        TierName: tier,
        EnvironmentName: environment,
      }),
    onSuccess: (data) => {
      setMintedSerial(data.LicenseKey);
      toast.success("License minted successfully!");
      void queryClient.invalidateQueries({ queryKey: ["LaraApi"] });
    },
    onError: (err) => {
      toast.error(formatLaraApiError(err));
    },
  });

  return (
    <div className="space-y-6">
      <SerialLivePreviewCard serialPreview={previewString} isMinted={hasMinted} />
      <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 className="font-semibold text-foreground">Mint Configuration</h3>
        <p className="mt-1 text-xs text-muted-foreground">Select license parameters and vanity prefix</p>
        <div className="mt-4 space-y-5">
          <SerialMintFormControls
            resellerId={resellerId}
            prefix={prefix}
            category={category}
            tier={tier}
            environment={environment}
            onPrefixChange={setPrefix}
            onCategoryChange={setCategory}
            onTierChange={setTier}
            onEnvironmentChange={setEnvironment}
          />
          <div className="flex justify-end pt-2">
            <button
              type="button"
              disabled={mintMutation.isPending}
              onClick={() => mintMutation.mutate()}
              className="focus-ring inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
            >
              {mintMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
              <span>{mintMutation.isPending ? "Minting License..." : "Mint License"}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
