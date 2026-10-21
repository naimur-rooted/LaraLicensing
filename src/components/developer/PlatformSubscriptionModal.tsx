import React, { useState } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Check, ShieldCheck, Zap, Sparkles } from "lucide-react";

interface PlatformSubscriptionModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  currentTier: string;
  onTierSelect: (tierCode: string) => void;
}

interface PlatformPlan {
  code: string;
  name: string;
  monthlyPrice: string;
  annualPrice: string;
  biennialPrice: string;
  productsLimit: string;
  requestsLimit: string;
  seatsPerLicense: string;
  isPopular?: boolean;
  isDecoyTarget?: boolean;
  features: string[];
}

const PLATFORM_PLANS: PlatformPlan[] = [
  {
    code: "STARTER",
    name: "Starter / Free",
    monthlyPrice: "$0.00",
    annualPrice: "Free 1st Year",
    biennialPrice: "N/A",
    productsLimit: "1 Product",
    requestsLimit: "100 req / day (max 15/hr)",
    seatsPerLicense: "Up to 2 seats per license",
    features: [
      "1 Software Product Shard",
      "Standard SQLite WAL Mode",
      "Community Documentation",
      "Basic Serial Generation",
    ],
  },
  {
    code: "ESSENTIAL",
    name: "Essential",
    monthlyPrice: "$2.50 / mo",
    annualPrice: "$19.90 / yr",
    biennialPrice: "$35.00 / 2 yr",
    productsLimit: "3 Products",
    requestsLimit: "200 req / day (max 25/hr)",
    seatsPerLicense: "Up to 5 seats per license",
    features: [
      "3 Isolated Product Shards",
      "Periodic 7/14-Day Cadence Checking",
      "Decoupled Stripe & Webhook Integrations",
      "Email Support",
    ],
  },
  {
    code: "PRO",
    name: "Pro Developer",
    monthlyPrice: "$5.00 / mo",
    annualPrice: "$49.90 / yr",
    biennialPrice: "$89.00 / 2 yr",
    productsLimit: "15 Products",
    requestsLimit: "1,000 req / day (max 120/hr)",
    seatsPerLicense: "Up to 15 seats per license",
    isPopular: true,
    isDecoyTarget: true,
    features: [
      "15 Product Shard Databases",
      "Real-Time Fraud & Anomaly Guard",
      "Ed25519 Asymmetric Offline JWTs",
      "1-Click AI Blueprint Generator",
      "Priority Fast-Track Support",
    ],
  },
  {
    code: "MAX",
    name: "Max Enterprise",
    monthlyPrice: "$9.90 / mo",
    annualPrice: "$89.00 / yr",
    biennialPrice: "$149.00 / 2 yr",
    productsLimit: "50 Products",
    requestsLimit: "5,000 req / day (max 500/hr)",
    seatsPerLicense: "Unlimited Floating Seats",
    features: [
      "50 Product Shards",
      "Reseller Wholesale Subsystem Access",
      "Impossible Travel Geo-Velocity Shield",
      "Dedicated Slack / Discord Channel",
    ],
  },
];

export function PlatformSubscriptionModal({
  isOpen,
  onOpenChange,
  currentTier,
  onTierSelect,
}: PlatformSubscriptionModalProps) {
  const [billingCycle, setBillingCycle] = useState<"monthly" | "annual" | "biennial">("biennial");

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
            Select a licensing package to unlock product shard quotas, daily validation bandwidth, and anti-piracy capabilities.
          </DialogDescription>
        </DialogHeader>

        {/* Billing Cycle Switcher */}
        <div className="flex justify-center my-2">
          <div className="flex items-center rounded-lg border bg-muted/40 p-1 text-xs">
            <button
              onClick={() => setBillingCycle("monthly")}
              className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                billingCycle === "monthly" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground"
              }`}
            >
              Billed Monthly
            </button>
            <button
              onClick={() => setBillingCycle("annual")}
              className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                billingCycle === "annual" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground"
              }`}
            >
              Annual (Save 35%)
            </button>
            <button
              onClick={() => setBillingCycle("biennial")}
              className={`flex items-center gap-1 rounded-md px-3 py-1.5 font-medium transition-colors ${
                billingCycle === "biennial" ? "bg-emerald-600 text-white shadow-sm" : "text-muted-foreground"
              }`}
            >
              <span>2-Year Decoy Target</span>
              <span className="text-[10px] font-bold bg-white/20 px-1 rounded">SAVE 82%</span>
            </button>
          </div>
        </div>

        {/* Pricing Cards Grid */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 py-2">
          {PLATFORM_PLANS.map((plan) => {
            const isSelected = currentTier.toUpperCase() === plan.code;
            const price =
              billingCycle === "biennial"
                ? plan.biennialPrice
                : billingCycle === "annual"
                ? plan.annualPrice
                : plan.monthlyPrice;

            return (
              <div
                key={plan.code}
                className={`relative flex flex-col justify-between rounded-xl border p-4 text-xs transition-all ${
                  plan.isDecoyTarget
                    ? "border-emerald-500 bg-emerald-500/5 shadow-md ring-1 ring-emerald-500/30"
                    : "border-border bg-card"
                }`}
              >
                {plan.isDecoyTarget && (
                  <Badge className="absolute -top-2.5 right-3 bg-emerald-600 text-white text-[9px] font-bold">
                    RECOMMENDED (BEST VALUE)
                  </Badge>
                )}

                <div>
                  <div className="font-bold text-sm text-foreground mb-1">{plan.name}</div>
                  <div className="font-mono text-lg font-extrabold text-foreground mb-2">{price}</div>

                  <div className="space-y-1 mb-3 pb-3 border-b border-border/80 text-[11px] text-muted-foreground">
                    <div className="font-semibold text-foreground">📦 {plan.productsLimit}</div>
                    <div>⚡ {plan.requestsLimit}</div>
                    <div>💻 {plan.seatsPerLicense}</div>
                  </div>

                  <ul className="space-y-2 text-muted-foreground text-[11px]">
                    {plan.features.map((f, i) => (
                      <li key={i} className="flex items-start gap-1.5">
                        <Check className="size-3.5 text-emerald-500 shrink-0 mt-0.5" />
                        <span>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <Button
                  className="w-full mt-4 h-8 text-xs font-semibold"
                  variant={isSelected ? "outline" : plan.isDecoyTarget ? "default" : "secondary"}
                  onClick={() => {
                    onTierSelect(plan.code);
                    onOpenChange(false);
                  }}
                >
                  {isSelected ? "Current Plan" : "Select Tier"}
                </Button>
              </div>
            );
          })}
        </div>

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
