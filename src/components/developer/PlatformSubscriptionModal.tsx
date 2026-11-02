import React, { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Check, ShieldCheck, Zap, Sparkles, Loader2 } from "lucide-react";
import { getApiBaseUrl } from "@/lib/lara-api-client";
import { getLaraAccessToken } from "@/lib/lara-api-session";

interface PlatformSubscriptionModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  currentTier: string;
  onTierSelect: (tierCode: string) => void;
}

export interface DynamicPricingPlan {
  PricingPlanId: number;
  planCode: string;
  planName: string;
  billingInterval: string;
  retailPriceCents: number;
  discountedPriceCents: number;
  allowedMachineSlots: number;
  isBestValue: boolean | number;
  isActive: boolean | number;
}

export function PlatformSubscriptionModal({
  isOpen,
  onOpenChange,
  currentTier,
  onTierSelect,
}: PlatformSubscriptionModalProps) {
  const [plans, setPlans] = useState<DynamicPricingPlan[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen) return;
    let isMounted = true;
    setIsLoading(true);
    setError(null);

    const loadPlans = async () => {
      try {
        const baseUrl = getApiBaseUrl();
        const token =
          getLaraAccessToken() ||
          sessionStorage.getItem("LicensingPortal.AccessToken") ||
          localStorage.getItem("LicensingPortal.AccessToken") ||
          localStorage.getItem("lara_access_token") ||
          localStorage.getItem("token");

        // First attempt authenticated Admin/PricingPlans (if operator), otherwise public /Pricing/Plans
        let resp = await fetch(`${baseUrl}/Pricing/Plans`, {
          headers: {
            Accept: "application/json",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });

        if (!resp.ok) {
          resp = await fetch(`${baseUrl}/Admin/PricingPlans`, {
            headers: {
              Accept: "application/json",
              ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
          });
        }

        if (!resp.ok) {
          throw new Error(`Failed to load plans: HTTP ${resp.status}`);
        }

        const json = await resp.json();
        const items: DynamicPricingPlan[] = json.Data || json.Results?.Items || json.Results || [];
        if (isMounted) {
          setPlans(items.filter((p) => Boolean(p.isActive)));
        }
      } catch (err: unknown) {
        if (isMounted) {
          setError(err instanceof Error ? err.message : "Failed to load dynamic pricing deals");
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    void loadPlans();

    return () => {
      isMounted = false;
    };
  }, [isOpen]);

  const formatPrice = (cents: number) => {
    return (cents / 100).toLocaleString("en-US", {
      style: "currency",
      currency: "USD",
    });
  };

  const getIntervalLabel = (interval: string) => {
    switch (interval.toLowerCase()) {
      case "monthly":
        return "/ month";
      case "yearly":
      case "annual":
        return "/ year";
      case "biennial":
        return "/ 2 yr";
      default:
        return `/${interval}`;
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl max-h-[92vh] overflow-y-auto">
        <DialogHeader className="text-center sm:text-left">
          <div className="flex items-center gap-2 text-primary font-semibold text-xs uppercase tracking-wider">
            <Zap className="size-4" />
            <span>LaraLicensing Platform Subscriptions</span>
          </div>
          <DialogTitle className="text-2xl">Choose Your Developer Platform Tier</DialogTitle>
          <DialogDescription>
            Live pricing deals and software licensing packages configured by the administration.
          </DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <div className="flex flex-col items-center justify-center py-16 text-muted-foreground gap-3">
            <Loader2 className="size-6 animate-spin text-primary" />
            <span className="text-xs">Fetching live dynamic pricing deals...</span>
          </div>
        ) : error ? (
          <div className="rounded-xl border border-destructive/40 bg-destructive/10 p-5 text-center text-xs text-destructive">
            <p className="font-semibold">{error}</p>
            <p className="text-[11px] text-muted-foreground mt-1">Please check your network connection or try again.</p>
          </div>
        ) : plans.length === 0 ? (
          <div className="rounded-xl border border-border bg-card p-8 text-center text-xs text-muted-foreground">
            No active pricing deals are currently available.
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 py-2">
            {plans.map((plan) => {
              const isSelected =
                currentTier.toUpperCase() === plan.planCode.toUpperCase() ||
                currentTier.toLowerCase() === plan.planName.toLowerCase();
              const isBestValue = Boolean(plan.isBestValue);
              const retailPrice = formatPrice(plan.retailPriceCents);
              const discountedPrice = formatPrice(plan.discountedPriceCents);
              const hasDiscount = plan.retailPriceCents > plan.discountedPriceCents;

              return (
                <div
                  key={plan.PricingPlanId}
                  className={`relative flex flex-col justify-between rounded-xl border p-5 text-xs transition-all ${
                    isBestValue
                      ? "border-emerald-500 bg-emerald-500/5 shadow-md ring-1 ring-emerald-500/30"
                      : "border-border bg-card hover:border-border/80"
                  }`}
                >
                  {isBestValue && (
                    <Badge className="absolute -top-2.5 right-3 bg-emerald-600 text-white text-[9px] font-bold">
                      RECOMMENDED (BEST VALUE)
                    </Badge>
                  )}

                  <div>
                    <div className="text-[10px] font-mono text-muted-foreground tracking-wider uppercase mb-1">
                      {plan.planCode}
                    </div>
                    <div className="font-bold text-base text-foreground mb-1">{plan.planName}</div>

                    <div className="flex items-baseline gap-1.5 my-2">
                      <span className="font-mono text-2xl font-extrabold text-foreground">{discountedPrice}</span>
                      {hasDiscount && (
                        <span className="text-xs line-through text-muted-foreground font-mono">{retailPrice}</span>
                      )}
                      <span className="text-xs text-muted-foreground font-medium">
                        {getIntervalLabel(plan.billingInterval)}
                      </span>
                    </div>

                    <div className="space-y-1 mb-4 pb-3 border-b border-border/80 text-[11px] text-muted-foreground">
                      <div className="font-semibold text-foreground flex items-center gap-1.5">
                        <span>💻</span>
                        <span>{plan.allowedMachineSlots} Machine Activation Slot{plan.allowedMachineSlots > 1 ? "s" : ""}</span>
                      </div>
                      <div className="flex items-center gap-1.5">
                        <span>⚡</span>
                        <span>Full REST API & SDK Ingestion</span>
                      </div>
                    </div>

                    <ul className="space-y-2 text-muted-foreground text-[11px]">
                      <li className="flex items-start gap-1.5">
                        <Check className="size-3.5 text-emerald-500 shrink-0 mt-0.5" />
                        <span>Ed25519 Cryptographic Signatures</span>
                      </li>
                      <li className="flex items-start gap-1.5">
                        <Check className="size-3.5 text-emerald-500 shrink-0 mt-0.5" />
                        <span>Automated Webhook Checkout Provisioning</span>
                      </li>
                      <li className="flex items-start gap-1.5">
                        <Check className="size-3.5 text-emerald-500 shrink-0 mt-0.5" />
                        <span>Offline License Token Sync</span>
                      </li>
                    </ul>
                  </div>

                  <Button
                    className="w-full mt-5 h-9 text-xs font-semibold"
                    variant={isSelected ? "outline" : isBestValue ? "default" : "secondary"}
                    onClick={() => {
                      onTierSelect(plan.planCode);
                      onOpenChange(false);
                    }}
                  >
                    {isSelected ? "Current Plan" : "Select Tier"}
                  </Button>
                </div>
              );
            })}
          </div>
        )}

        {/* Lifetime Deal LTD Banner */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-xs">
          <div className="flex items-center gap-3">
            <div className="flex size-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
              <Sparkles className="size-5" />
            </div>
            <div>
              <div className="font-bold text-foreground">Need Perpetual / Lifetime License Engine?</div>
              <div className="text-muted-foreground">
                Get unlimited product shards, 10,000 burst requests/day, and lifetime upgrades for a one-time payment of $999.
              </div>
            </div>
          </div>
          <Button
            size="sm"
            className="bg-amber-600 hover:bg-amber-700 text-white font-semibold shrink-0"
            onClick={() => {
              onTierSelect("LIFETIME_DEAL");
              onOpenChange(false);
            }}
          >
            Claim Lifetime Deal ($999)
          </Button>
        </div>

        <DialogFooter className="sm:justify-between text-xs text-muted-foreground pt-2">
          <span className="flex items-center gap-1">
            <ShieldCheck className="size-3.5 text-emerald-500" />
            Cancel or change platform tiers anytime with zero lock-in
          </span>
          <Button variant="ghost" size="sm" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
