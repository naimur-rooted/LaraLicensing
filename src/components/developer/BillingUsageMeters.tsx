import React from "react";
import { Database, Zap, Monitor, Activity } from "lucide-react";
import { Progress } from "@/components/ui/progress";

interface BillingUsageMetersProps {
  currentTier: string;
}

export function BillingUsageMeters({ currentTier }: BillingUsageMetersProps) {
  const isFree = currentTier.includes("Free") || currentTier.includes("Starter");
  const maxProducts = isFree ? 1 : currentTier.includes("Essential") ? 3 : currentTier.includes("Pro") ? 15 : 50;
  const maxRequests = isFree ? 100 : currentTier.includes("Essential") ? 200 : currentTier.includes("Pro") ? 1000 : 5000;
  const maxSeats = isFree ? 2 : currentTier.includes("Essential") ? 5 : currentTier.includes("Pro") ? 15 : 999;

  return (
    <div className="rounded-xl border border-border/80 bg-card/80 p-5 shadow-xs space-y-4">
      <div className="flex items-center justify-between">
        <h4 className="text-sm font-bold text-foreground flex items-center gap-2">
          <Activity className="size-4 text-primary" />
          <span>Resource Entitlements & Usage</span>
        </h4>
        <span className="text-[11px] text-muted-foreground font-mono">Current Billing Cycle</span>
      </div>

      <div className="space-y-3.5">
        <div className="space-y-1.5">
          <div className="flex justify-between text-xs font-medium">
            <span className="text-muted-foreground flex items-center gap-1.5">
              <Database className="size-3.5 text-primary" />
              Product Shards Allocated
            </span>
            <span className="font-mono text-foreground font-semibold">1 of {maxProducts}</span>
          </div>
          <Progress value={(1 / maxProducts) * 100} className="h-2" />
        </div>

        <div className="space-y-1.5">
          <div className="flex justify-between text-xs font-medium">
            <span className="text-muted-foreground flex items-center gap-1.5">
              <Zap className="size-3.5 text-amber-500" />
              Daily Verification Quota
            </span>
            <span className="font-mono text-foreground font-semibold">12 / {maxRequests} req</span>
          </div>
          <Progress value={(12 / maxRequests) * 100} className="h-2" />
        </div>

        <div className="flex justify-between items-center pt-2 border-t border-border/40 text-xs">
          <span className="text-muted-foreground flex items-center gap-1.5">
            <Monitor className="size-3.5 text-blue-500" />
            Max Bound Hardware Seats
          </span>
          <span className="font-mono font-bold text-foreground">
            {maxSeats >= 999 ? "Unlimited Seats" : `Up to ${maxSeats} seats/key`}
          </span>
        </div>
      </div>
    </div>
  );
}
