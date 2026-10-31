import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Sparkles, Loader2, KeyRound } from "lucide-react";
import { toast } from "sonner";
import { z } from "zod";
import { requestLaraApi } from "@/lib/lara-api-client";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { issueResellerLicense, type LicenseCategoryName } from "@/lib/lara-reseller-mint";
import { formatLaraApiError } from "@/lib/lara-api-error";

const productItemSchema = z.object({
  ProductId: z.number(),
  productName: z.string(),
  productSlug: z.string(),
});

export interface ResellerIssueLicenseDialogProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  resellerId: number;
}

export function ResellerIssueLicenseDialog({
  isOpen,
  onOpenChange,
  resellerId,
}: ResellerIssueLicenseDialogProps) {
  const queryClient = useQueryClient();
  const [prefix, setPrefix] = useState("ALIM");
  const [category, setCategory] = useState<LicenseCategoryName>("Monthly");
  const [tier, setTier] = useState<"Starter" | "Growth" | "Scale" | "Enterprise">("Starter");
  const [environment, setEnvironment] = useState<"Production" | "Staging" | "Sandbox">("Production");
  const [productSlug, setProductSlug] = useState<string>("hotel-reservation-system");
  const [mintedKey, setMintedKey] = useState<string | null>(null);

  const { data: products = [] } = useQuery({
    queryKey: ["LaraApi", "Reseller", "Products"],
    queryFn: async () => {
      try {
        return await requestLaraApi("/Reseller/Products", productItemSchema);
      } catch {
        return [];
      }
    },
  });

  const mintMutation = useMutation({
    mutationFn: () =>
      issueResellerLicense({
        PrefixValue: prefix,
        LicenseCategory: category,
        TierName: tier,
        EnvironmentName: environment,
        ProductSlug: productSlug,
      }),
    onSuccess: (data) => {
      setMintedKey(data.LicenseKey);
      toast.success("License issued and registered on your reseller shard!");
      void queryClient.invalidateQueries({ queryKey: ["LaraApi"] });
    },
    onError: (err) => {
      toast.error(formatLaraApiError(err));
    },
  });

  const handleClose = () => {
    setMintedKey(null);
    onOpenChange(false);
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <KeyRound className="size-5" />
            </div>
            <div>
              <DialogTitle>Issue Client License</DialogTitle>
              <DialogDescription>
                Mint a new branded license serial directly from your allocated wholesale quota.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        {mintedKey ? (
          <div className="space-y-4 py-3">
            <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 text-center">
              <p className="text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                License Key Minted Successfully
              </p>
              <p className="mt-2 font-mono text-base font-bold tracking-wider text-emerald-700 dark:text-emerald-400 select-all">
                {mintedKey}
              </p>
            </div>
            <p className="text-xs text-muted-foreground text-center">
              This license is immediately active and can be activated by your client.
            </p>
            <DialogFooter>
              <Button onClick={handleClose} className="w-full">
                Done & Return to Inventory
              </Button>
            </DialogFooter>
          </div>
        ) : (
          <div className="space-y-4 py-2">
            <div className="space-y-1.5">
              <Label>Target Software Product</Label>
              <Select value={productSlug} onValueChange={(v: string) => setProductSlug(v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {products.map((p) => (
                    <SelectItem key={p.ProductId} value={p.productSlug}>
                      {p.productName} ({p.productSlug})
                    </SelectItem>
                  ))}
                  {products.length === 0 ? (
                    <SelectItem value="hotel-reservation-system">
                      Hotel Reservation System (hotel-reservation-system)
                    </SelectItem>
                  ) : null}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="reseller-prefix">Vanity Serial Prefix</Label>
              <Input
                id="reseller-prefix"
                value={prefix}
                onChange={(e) => setPrefix(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 8))}
                placeholder="e.g. ALIM"
                className="font-mono uppercase"
              />
              <p className="text-[11px] text-muted-foreground">
                Your vanity brand identifier (3-8 alphanumeric characters).
              </p>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label>Tier Level</Label>
                <Select value={tier} onValueChange={(v: any) => setTier(v)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Starter">Starter</SelectItem>
                    <SelectItem value="Growth">Growth</SelectItem>
                    <SelectItem value="Scale">Scale</SelectItem>
                    <SelectItem value="Enterprise">Enterprise</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5">
                <Label>Billing Cycle</Label>
                <Select value={category} onValueChange={(v: any) => setCategory(v)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Monthly">Monthly</SelectItem>
                    <SelectItem value="Annual">Annual</SelectItem>
                    <SelectItem value="Perpetual">Perpetual</SelectItem>
                    <SelectItem value="Trial">Trial</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label>Target Environment</Label>
              <Select value={environment} onValueChange={(v: any) => setEnvironment(v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Production">Production</SelectItem>
                  <SelectItem value="Staging">Staging</SelectItem>
                  <SelectItem value="Sandbox">Sandbox</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <DialogFooter className="pt-2">
              <Button variant="outline" onClick={handleClose} disabled={mintMutation.isPending}>
                Cancel
              </Button>
              <Button
                onClick={() => mintMutation.mutate()}
                disabled={mintMutation.isPending || !prefix}
                className="gap-2"
              >
                {mintMutation.isPending ? (
                  <>
                    <Loader2 className="size-4 animate-spin" />
                    Minting...
                  </>
                ) : (
                  <>
                    <Sparkles className="size-4" />
                    Mint License
                  </>
                )}
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
