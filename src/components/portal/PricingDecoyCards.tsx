import React from "react";
import { Check, Sparkles, Shield, Laptop } from "lucide-react";
import { Badge } from "@/components/ui/badge";

export interface PricingPlanOption {
  planCode: string;
  planName: string;
  billingInterval: string;
  monthlyEquivalent: string;
  billedNote: string;
  savingsBadge?: string;
  machineSlots: number;
  isBestValue: boolean;
  features: string[];
}

export const PRICING_PLANS: PricingPlanOption[] = [
  {
    planCode: "PLAN_1M",
    planName: "1 Month Anchor",
    billingInterval: "monthly",
    monthlyEquivalent: "$12.99",
    billedNote: "$12.99 billed every month",
    machineSlots: 1,
    isBestValue: false,
    features: ["1 Workstation Seat", "Standard Activation", "Online Live Sync"],
  },
  {
    planCode: "PLAN_1Y",
    planName: "1 Year Plan",
    billingInterval: "yearly",
    monthlyEquivalent: "$4.99",
    billedNote: "$59.88 billed annually",
    savingsBadge: "SAVE 61%",
    machineSlots: 3,
    isBestValue: false,
    features: ["3 Workstation Seats", "Priority Verification", "Offline Cache Access"],
  },
  {
    planCode: "PLAN_2Y",
    planName: "2 Years + 3 Mo. Free",
    billingInterval: "biennial",
    monthlyEquivalent: "$2.39",
    billedNote: "$64.50 billed for 27 months",
    savingsBadge: "SAVE 82% - BEST VALUE",
    machineSlots: 10,
    isBestValue: true,
    features: ["10 Floating Device Seats", "Air-Gapped Offline Tokens", "Real-Time Portal Unbinding", "VIP Fast-Track Support"],
  },
];

interface PricingDecoyCardsProps {
  selectedPlan: string;
  onSelectPlan: (planCode: string) => void;
}

export function PricingDecoyCards({ selectedPlan, onSelectPlan }: PricingDecoyCardsProps) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-3.5 my-3">
      {PRICING_PLANS.map((plan) => {
        const isSelected = selectedPlan === plan.planCode;
        return (
          <div
            key={plan.planCode}
            onClick={() => onSelectPlan(plan.planCode)}
            className={`relative flex flex-col justify-between rounded-xl border p-4 cursor-pointer transition-all duration-200 ${
              plan.isBestValue
                ? isSelected
                  ? "border-emerald-500 bg-emerald-500/10 shadow-md ring-2 ring-emerald-500/40"
                  : "border-emerald-500/60 bg-emerald-500/5 hover:border-emerald-500 hover:shadow-sm"
                : isSelected
                ? "border-primary bg-primary/5 shadow-sm ring-2 ring-primary/30"
                : "border-border/70 bg-card hover:border-border hover:bg-muted/30"
            }`}
          >
            {plan.isBestValue && (
              <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                <Badge className="bg-emerald-600 hover:bg-emerald-600 text-white font-semibold text-2xs px-2.5 py-0.5 shadow-xs flex items-center gap-1">
                  <Sparkles className="w-3 h-3" />
                  {plan.savingsBadge}
                </Badge>
              </div>
            )}

            <div>
              <div className="flex items-center justify-between gap-1 mb-1.5">
                <h4 className="font-semibold text-sm text-foreground">{plan.planName}</h4>
                {!plan.isBestValue && plan.savingsBadge && (
                  <Badge variant="secondary" className="text-2xs font-bold text-amber-600 dark:text-amber-400">
                    {plan.savingsBadge}
                  </Badge>
                )}
              </div>

              <div className="flex items-baseline gap-1 mt-2">
                <span className="text-2xl font-black tracking-tight text-foreground">{plan.monthlyEquivalent}</span>
                <span className="text-xs text-muted-foreground font-medium">/ month</span>
              </div>
              <p className="text-2xs text-muted-foreground mt-0.5">{plan.billedNote}</p>

              <div className="mt-3 pt-2.5 border-t border-border/50 flex items-center gap-1.5 text-xs font-medium text-foreground/90">
                <Laptop className="w-3.5 h-3.5 text-primary" />
                <span>{plan.machineSlots} Machine Slot{plan.machineSlots > 1 ? "s" : ""}</span>
              </div>

              <ul className="mt-2 space-y-1.5">
                {plan.features.map((feat, idx) => (
                  <li key={idx} className="flex items-center gap-1.5 text-2xs text-muted-foreground">
                    <Check className="w-3 h-3 text-emerald-500 shrink-0" />
                    <span>{feat}</span>
                  </li>
                ))}
              </ul>
            </div>

            <div className="mt-4 pt-2">
              <button
                type="button"
                className={`w-full py-1.5 px-3 rounded-lg text-xs font-semibold transition-all ${
                  isSelected
                    ? plan.isBestValue
                      ? "bg-emerald-600 text-white shadow-xs"
                      : "bg-primary text-primary-foreground shadow-xs"
                    : "bg-secondary text-secondary-foreground hover:bg-secondary/80"
                }`}
              >
                {isSelected ? "Selected Tier" : "Choose Tier"}
              </button>
            </div>
          </div>
        );
      })}
    </div>
  );
}
