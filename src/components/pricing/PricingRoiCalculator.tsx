import { useState } from "react";

export function PricingRoiCalculator() {
  const [activationsCount, setActivationsCount] = useState(1000);

  return (
    <div className="rounded-2xl border border-border/80 bg-muted/20 p-8 sm:p-12 space-y-6">
      <div className="max-w-2xl">
        <h2 className="text-2xl font-bold text-foreground" style={{ fontFamily: "var(--font-display)" }}>
          Calculate your ROI vs Cryptlex & LicenseSpring
        </h2>
        <p className="text-sm text-muted-foreground mt-1">
          Drag the slider to see how much you save with LaraLicensing's split-DB flat pricing model.
        </p>
      </div>

      <div className="space-y-4">
        <div className="flex justify-between items-center text-sm font-semibold">
          <span>Active Workstation Activations</span>
          <span className="text-primary font-mono text-lg font-bold">{activationsCount.toLocaleString()} devices</span>
        </div>
        <input
          type="range"
          min="100"
          max="25000"
          step="100"
          value={activationsCount}
          onChange={(e) => setActivationsCount(Number(e.target.value))}
          className="w-full accent-primary h-2 bg-muted rounded-lg cursor-pointer"
        />
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-border/60">
        <div className="p-4 rounded-xl border bg-card/80">
          <span className="text-xs text-muted-foreground font-semibold uppercase">LaraLicensing</span>
          <p className="text-2xl font-bold text-primary font-mono mt-1">$49 / mo</p>
          <p className="text-[11px] text-muted-foreground mt-0.5">Flat predictable tier</p>
        </div>
        <div className="p-4 rounded-xl border bg-card/40 opacity-80">
          <span className="text-xs text-muted-foreground font-semibold uppercase">Cryptlex Business</span>
          <p className="text-2xl font-bold text-muted-foreground font-mono mt-1">$600 / mo</p>
          <p className="text-[11px] text-rose-500 font-semibold mt-0.5">+12x more expensive</p>
        </div>
        <div className="p-4 rounded-xl border bg-card/40 opacity-80">
          <span className="text-xs text-muted-foreground font-semibold uppercase">LicenseSpring Business</span>
          <p className="text-2xl font-bold text-muted-foreground font-mono mt-1">$750 / mo</p>
          <p className="text-[11px] text-rose-500 font-semibold mt-0.5">+15x more expensive</p>
        </div>
      </div>
    </div>
  );
}
