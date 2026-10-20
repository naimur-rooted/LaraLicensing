import React, { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import {
  Store,
  Tag,
  Plus,
  AlertTriangle,
  CheckCircle2,
  Users,
  Coins,
  Package,
} from "lucide-react";

export interface ResellerWholesaleQuotaItem {
  id: number;
  tierCode: string;
  allocatedCount: number;
  consumedCount: number;
  allocatedAt: string;
}

export interface ResellerCustomOfferItem {
  id: number;
  offerName: string;
  retailPriceCents: number;
  durationDays: number;
  machineSlots: number;
  commissionPercent: number;
  isActive: boolean;
}

export function ResellerWholesaleStudio() {
  const [quotas, setQuotas] = useState<ResellerWholesaleQuotaItem[]>([
    {
      id: 1,
      tierCode: "ESSENTIAL",
      allocatedCount: 500,
      consumedCount: 435,
      allocatedAt: new Date().toISOString(),
    },
    {
      id: 2,
      tierCode: "PRO_ANNUAL",
      allocatedCount: 200,
      consumedCount: 65,
      allocatedAt: new Date().toISOString(),
    },
  ]);

  const [offers, setOffers] = useState<ResellerCustomOfferItem[]>([
    {
      id: 1,
      offerName: "Regional Agency Starter (3-Seat)",
      retailPriceCents: 4900,
      durationDays: 365,
      machineSlots: 3,
      commissionPercent: 25,
      isActive: true,
    },
    {
      id: 2,
      offerName: "Freelancer 1-Month Quick Pass",
      retailPriceCents: 999,
      durationDays: 30,
      machineSlots: 1,
      commissionPercent: 20,
      isActive: true,
    },
  ]);

  const [isTopupOpen, setIsTopupOpen] = useState(false);
  const [isOfferOpen, setIsOfferOpen] = useState(false);
  const [topupTier, setTopupTier] = useState("ESSENTIAL");
  const [topupCount, setTopupCount] = useState("200");

  const [newOfferName, setNewOfferName] = useState("");
  const [newOfferPrice, setNewOfferPrice] = useState("39.00");
  const [newOfferSlots, setNewOfferSlots] = useState("3");
  const [newOfferCommission, setNewOfferCommission] = useState("20");

  const totalAllocated = quotas.reduce((acc, q) => acc + q.allocatedCount, 0);
  const totalConsumed = quotas.reduce((acc, q) => acc + q.consumedCount, 0);
  const remaining = Math.max(0, totalAllocated - totalConsumed);
  const usageRatio = totalAllocated > 0 ? (totalConsumed / totalAllocated) : 0;
  const isThresholdAlert = totalAllocated > 0 && (remaining / totalAllocated) < 0.20;

  const handleTopupSubmit = () => {
    const count = parseInt(topupCount, 10) || 100;
    const item: ResellerWholesaleQuotaItem = {
      id: Date.now(),
      tierCode: topupTier,
      allocatedCount: count,
      consumedCount: 0,
      allocatedAt: new Date().toISOString(),
    };
    setQuotas([item, ...quotas]);
    setIsTopupOpen(false);
  };

  const handleCreateOfferSubmit = () => {
    if (!newOfferName.trim()) return;
    const item: ResellerCustomOfferItem = {
      id: Date.now(),
      offerName: newOfferName,
      retailPriceCents: Math.round(parseFloat(newOfferPrice || "0") * 100),
      durationDays: 365,
      machineSlots: parseInt(newOfferSlots, 10) || 1,
      commissionPercent: parseInt(newOfferCommission, 10) || 20,
      isActive: true,
    };
    setOffers([item, ...offers]);
    setIsOfferOpen(false);
    setNewOfferName("");
  };

  return (
    <div className="flex flex-col gap-6" data-component="reseller-wholesale-studio">
      {/* Reseller Wholesale Metrics Card */}
      <Card className="border-border/80 shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2.5">
              <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <Store className="size-5" />
              </div>
              <div>
                <CardTitle className="text-base font-semibold">Reseller Wholesale Hub</CardTitle>
                <CardDescription className="text-xs">
                  Manage bulk license inventory, custom pricing, and sub-client allocations.
                </CardDescription>
              </div>
            </div>
            <Button onClick={() => setIsTopupOpen(true)} size="sm" className="gap-1.5">
              <Plus className="size-3.5" />
              Top-up Bulk Quota
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div className="rounded-lg border bg-muted/30 p-3">
              <div className="text-muted-foreground flex items-center gap-1">
                <Package className="size-3.5" /> Total Allocated
              </div>
              <div className="text-xl font-bold mt-1 text-foreground">{totalAllocated.toLocaleString()} Serials</div>
            </div>
            <div className="rounded-lg border bg-muted/30 p-3">
              <div className="text-muted-foreground flex items-center gap-1">
                <Users className="size-3.5" /> Issued to Clients
              </div>
              <div className="text-xl font-bold mt-1 text-foreground">{totalConsumed.toLocaleString()} Serials</div>
            </div>
            <div className="rounded-lg border bg-muted/30 p-3">
              <div className="text-muted-foreground flex items-center gap-1">
                <Coins className="size-3.5" /> Available Balance
              </div>
              <div className="text-xl font-bold mt-1 text-emerald-600 dark:text-emerald-400">
                {remaining.toLocaleString()} Serials
              </div>
            </div>
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted-foreground">Inventory Consumption Rate</span>
              <span className="font-semibold text-foreground">{(usageRatio * 100).toFixed(1)}%</span>
            </div>
            <Progress value={usageRatio * 100} className="h-2" />
          </div>

          {isThresholdAlert && (
            <div className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-300">
              <AlertTriangle className="size-4 shrink-0" />
              <span>
                <strong>Threshold Warning:</strong> Available quota has dropped below 20%. Please top-up to prevent sub-client license issuance failures.
              </span>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Custom Packages & Offers Section */}
      <Card className="border-border/80 shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-semibold">Reseller Custom Packages & Offers</CardTitle>
              <CardDescription className="text-xs">
                Set custom prices and multi-seat entitlements for your client network.
              </CardDescription>
            </div>
            <Button onClick={() => setIsOfferOpen(true)} variant="outline" size="sm" className="gap-1.5">
              <Tag className="size-3.5" />
              New Offer
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 sm:grid-cols-2">
            {offers.map((offer) => (
              <div
                key={offer.id}
                className="flex flex-col justify-between rounded-lg border bg-card p-3.5 space-y-3"
              >
                <div>
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-sm text-foreground">{offer.offerName}</span>
                    <Badge variant="outline" className="text-[10px] text-emerald-600 border-emerald-500/30">
                      {offer.commissionPercent}% Margin
                    </Badge>
                  </div>
                  <div className="mt-2 text-xs text-muted-foreground space-y-0.5">
                    <div>Machine Seats: {offer.machineSlots} Seat(s)</div>
                    <div>Validity Period: {offer.durationDays} Days</div>
                  </div>
                </div>
                <div className="flex items-center justify-between border-t pt-2 text-xs">
                  <span className="font-mono font-bold text-sm text-foreground">
                    \${(offer.retailPriceCents / 100).toFixed(2)}
                  </span>
                  <Badge variant="secondary" className="text-[10px]">Active in Store</Badge>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Top-up Modal */}
      <Dialog open={isTopupOpen} onOpenChange={setIsTopupOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Top-up Wholesale License Pool</DialogTitle>
            <DialogDescription>
              Purchase or allocate bulk license serial quotas from the platform root registry.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-2 text-sm">
            <div className="grid gap-1.5">
              <Label>Tier Category</Label>
              <select
                value={topupTier}
                onChange={(e) => setTopupTier(e.target.value)}
                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                <option value="STARTER">Starter Tier</option>
                <option value="ESSENTIAL">Essential Tier</option>
                <option value="PRO_ANNUAL">Pro Annual Tier</option>
                <option value="MAX_ENTERPRISE">Max Enterprise</option>
              </select>
            </div>
            <div className="grid gap-1.5">
              <Label>Serial Count</Label>
              <Input
                type="number"
                value={topupCount}
                onChange={(e) => setTopupCount(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsTopupOpen(false)}>Cancel</Button>
            <Button onClick={handleTopupSubmit}>Confirm Allocation</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Create Offer Modal */}
      <Dialog open={isOfferOpen} onOpenChange={setIsOfferOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Create Custom Client Offer</DialogTitle>
            <DialogDescription>
              Set the price and profit margin you want to present to your end clients.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-2 text-sm">
            <div className="grid gap-1.5">
              <Label>Offer Name</Label>
              <Input
                placeholder="e.g. Agency Pro Bundle"
                value={newOfferName}
                onChange={(e) => setNewOfferName(e.target.value)}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label>Retail Price ($USD)</Label>
                <Input
                  type="number"
                  value={newOfferPrice}
                  onChange={(e) => setNewOfferPrice(e.target.value)}
                />
              </div>
              <div className="grid gap-1.5">
                <Label>Machine Seats</Label>
                <Input
                  type="number"
                  value={newOfferSlots}
                  onChange={(e) => setNewOfferSlots(e.target.value)}
                />
              </div>
            </div>
            <div className="grid gap-1.5">
              <Label>Reseller Commission (%)</Label>
              <Input
                type="number"
                value={newOfferCommission}
                onChange={(e) => setNewOfferCommission(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsOfferOpen(false)}>Cancel</Button>
            <Button onClick={handleCreateOfferSubmit}>Publish Offer</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
