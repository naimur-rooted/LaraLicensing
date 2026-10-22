import { useSuspenseQuery } from "@tanstack/react-query";
import { Gauge } from "lucide-react";

import { resellerQuotasQueryOptions } from "../../lib/lara-quota";

export interface ResellerQuotaGaugeProps {
  resellerId: number;
}

export function ResellerQuotaGauge({ resellerId }: ResellerQuotaGaugeProps) {
  const { data: quotas } = useSuspenseQuery(resellerQuotasQueryOptions(resellerId));

  const granted = quotas.reduce((sum, q) => sum + q.LicensesGranted, 0);
  const consumed = quotas.reduce((sum, q) => sum + q.LicensesConsumed, 0);
  const remaining = quotas.reduce((sum, q) => sum + q.LicensesRemaining, 0);
  const percentage = Math.min(100, Math.round((consumed / (granted || 1)) * 100));

  return (
    <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
      <div className="flex items-center justify-between border-b border-border pb-4">
        <div>
          <h3 className="font-semibold text-foreground">Quota Utilization</h3>
          <p className="text-xs text-muted-foreground">Current billing cycle consumption</p>
        </div>
        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Gauge className="size-4" />
        </div>
      </div>
      <div className="mt-6 flex flex-col items-center justify-center">
        <GaugeCircle percentage={percentage} />
        <div className="mt-4 grid w-full grid-cols-3 gap-2 border-t border-border pt-4 text-center">
          <div>
            <p className="text-xs text-muted-foreground">Granted</p>
            <p className="font-mono text-base font-semibold">{granted.toLocaleString()}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Consumed</p>
            <p className="font-mono text-base font-semibold text-primary">{consumed.toLocaleString()}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Remaining</p>
            <p className="font-mono text-base font-semibold text-emerald-600 dark:text-emerald-400">
              {remaining.toLocaleString()}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

function GaugeCircle({ percentage }: { percentage: number }) {
  const radius = 64;
  const circumference = 2 * Math.PI * radius;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;

  return (
    <div className="relative flex items-center justify-center">
      <svg className="size-36 -rotate-90 transform" viewBox="0 0 160 160">
        <circle cx="80" cy="80" r={radius} className="stroke-muted" strokeWidth="12" fill="none" />
        <circle
          cx="80"
          cy="80"
          r={radius}
          className="stroke-primary transition-all duration-700 ease-out"
          strokeWidth="12"
          strokeDasharray={circumference}
          strokeDashoffset={strokeDashoffset}
          strokeLinecap="round"
          fill="none"
        />
      </svg>
      <div className="absolute flex flex-col items-center">
        <span className="font-mono text-3xl font-bold">{percentage}%</span>
        <span className="text-[10px] uppercase tracking-wider text-muted-foreground">Utilized</span>
      </div>
    </div>
  );
}
