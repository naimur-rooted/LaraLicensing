import { Sparkles } from "lucide-react";

interface PricingHeaderProps {
  readonly isAnnual: boolean;
  readonly onToggleAnnual: () => void;
}

export function PricingHeader({ isAnnual, onToggleAnnual }: PricingHeaderProps) {
  return (
    <div className="text-center max-w-3xl mx-auto space-y-4">
      <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
        <Sparkles className="size-4" />
        <span>Predictable Developer Pricing</span>
      </div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
        Simple, transparent pricing for software creators
      </h1>
      <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
        No hidden per-seat penalty fees or opaque contract negotiations. Start free and scale as your software empire expands.
      </p>

      <div className="flex items-center justify-center gap-3 pt-4">
        <span className={`text-sm font-semibold ${isAnnual ? "text-muted-foreground" : "text-foreground"}`}>
          Monthly
        </span>
        <button
          type="button"
          onClick={onToggleAnnual}
          className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
            isAnnual ? "bg-primary" : "bg-muted"
          }`}
        >
          <span
            className={`inline-block size-4 transform rounded-full bg-white transition-transform ${
              isAnnual ? "translate-x-6" : "translate-x-1"
            }`}
          />
        </button>
        <span className={`text-sm font-semibold ${isAnnual ? "text-foreground" : "text-muted-foreground"}`}>
          Annual <span className="text-xs text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded-full">Save 20%</span>
        </span>
      </div>
    </div>
  );
}
