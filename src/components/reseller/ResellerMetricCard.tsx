import type { LucideIcon } from "lucide-react";

export interface ResellerMetricCardProps {
  label: string;
  value: string | number;
  hint: string;
  icon: LucideIcon;
  badge?: string;
}

export function ResellerMetricCard(props: ResellerMetricCardProps) {
  const Icon = props.icon;

  return (
    <div className="rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:border-primary/40 hover:shadow-md">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
          {props.label}
        </span>
        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Icon className="size-4" />
        </div>
      </div>
      <div className="mt-3 flex items-baseline justify-between gap-2">
        <span className="font-mono text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
          {props.value}
        </span>
        {props.badge ? (
          <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
            {props.badge}
          </span>
        ) : null}
      </div>
      <p className="mt-2 text-xs text-muted-foreground">{props.hint}</p>
    </div>
  );
}
