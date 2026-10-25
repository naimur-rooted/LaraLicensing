import React from "react";
import { PLAN_OPTIONS, type PlanOption } from "./developer-plans";

interface DeveloperPlanPickerProps {
  selectedPlan: string;
  onSelectPlan: (planCode: string) => void;
}

export function DeveloperPlanPicker({ selectedPlan, onSelectPlan }: DeveloperPlanPickerProps) {
  return (
    <div className="space-y-2">
      <label className="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">
        Select Platform Tier
      </label>
      <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
        {PLAN_OPTIONS.map((plan: PlanOption) => {
          const isSelected = selectedPlan.toUpperCase() === plan.code;
          return (
            <button
              key={plan.code}
              type="button"
              onClick={() => onSelectPlan(plan.code)}
              className={`p-3 rounded-xl border text-left transition-all relative flex flex-col justify-between cursor-pointer ${
                isSelected
                  ? "border-primary bg-primary/10 shadow-xs ring-1 ring-primary/40"
                  : "border-border/70 hover:border-border hover:bg-muted/40"
              }`}
            >
              {plan.badge && (
                <span
                  className={`text-[9px] px-1.5 py-0.5 rounded-full font-semibold absolute top-2 right-2 ${
                    plan.isPopular
                      ? "bg-primary text-primary-foreground"
                      : "bg-muted text-muted-foreground"
                  }`}
                >
                  {plan.badge}
                </span>
              )}
              <div>
                <p className="font-bold text-xs text-foreground">{plan.name}</p>
                <div className="flex items-baseline gap-1 mt-1">
                  <span className="text-base font-extrabold text-foreground font-mono">
                    {plan.price}
                  </span>
                  <span className="text-[10px] text-muted-foreground">{plan.period}</span>
                </div>
              </div>
              <p className="text-[10px] text-muted-foreground mt-2 border-t border-border/40 pt-1.5 truncate">
                {plan.limits}
              </p>
            </button>
          );
        })}
      </div>
    </div>
  );
}
