import React from "react";
import { Sparkles, Calendar, CheckCircle2, ArrowUpRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface BillingPlanOverviewCardProps {
  currentTier: string;
  onOpenUpgradeModal: () => void;
}

export function BillingPlanOverviewCard({ currentTier, onOpenUpgradeModal }: BillingPlanOverviewCardProps) {
  const isFree = currentTier.includes("Free") || currentTier.includes("Starter");
  const planPrice = isFree ? "$0.00" : currentTier.includes("Essential") ? "$2.50 / mo" : currentTier.includes("Pro") ? "$5.00 / mo" : "$9.90 / mo";

  return (
    <div className="rounded-xl border border-border/80 bg-card/80 p-5 shadow-xs flex flex-col justify-between">
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
              <Sparkles className="size-4" />
            </div>
            <div>
              <p className="text-xs text-muted-foreground font-medium">Current Platform Plan</p>
              <h3 className="text-lg font-bold text-foreground">{currentTier}</h3>
            </div>
          </div>
          <Badge variant="outline" className="border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 gap-1 text-[11px]">
            <CheckCircle2 className="size-3" />
            Active
          </Badge>
        </div>

        <div className="grid grid-cols-2 gap-3 pt-2 border-t border-border/60">
          <div>
            <span className="text-[11px] text-muted-foreground">Price Rate</span>
            <p className="font-mono text-base font-bold text-foreground">{planPrice}</p>
          </div>
          <div>
            <span className="text-[11px] text-muted-foreground">Renewal Cadence</span>
            <p className="text-xs font-semibold text-foreground flex items-center gap-1 mt-0.5">
              <Calendar className="size-3 text-muted-foreground" />
              Annual (Auto-renew)
            </p>
          </div>
        </div>
      </div>

      <div className="pt-5 border-t border-border/60 mt-4 flex items-center gap-3">
        <Button onClick={onOpenUpgradeModal} size="sm" className="font-semibold gap-1.5 shadow-2xs">
          <span>Change / Upgrade Plan</span>
          <ArrowUpRight className="size-3.5" />
        </Button>
      </div>
    </div>
  );
}
