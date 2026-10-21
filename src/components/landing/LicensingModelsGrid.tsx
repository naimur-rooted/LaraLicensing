import React from "react";
import {
  Smartphone,
  Network,
  Gauge,
  CreditCard,
  Key,
  FileCheck,
  CheckCircle2,
  Sparkles,
} from "lucide-react";

export function LicensingModelsGrid() {
  const models = [
    {
      icon: Smartphone,
      title: "Node-Locked & Hardware ID",
      description: "Lock licenses to specific hardware digests (Machine GUID, MAC hash, Chrome profile UUID, or server host). Prevent unauthorized key sharing.",
      badge: "Most Popular",
      color: "text-sky-500",
      bg: "bg-sky-50 dark:bg-sky-950/40",
    },
    {
      icon: Network,
      title: "Floating & Concurrent Seats",
      description: "Pool machine seats across teams. Dynamically allocate quotas (e.g., 3 seats, 10 Chrome profiles, 5 domains) with over-limit protection.",
      badge: "Team Friendly",
      color: "text-indigo-500",
      bg: "bg-indigo-50 dark:bg-indigo-950/40",
    },
    {
      icon: Gauge,
      title: "Usage-Based & Metered",
      description: "Enforce monthly lead scraping caps, API call limits, or export counts with real-time balance tracking and overage gates.",
      badge: "High Growth",
      color: "text-teal-500",
      bg: "bg-teal-50 dark:bg-teal-950/40",
    },
    {
      icon: CreditCard,
      title: "Subscriptions & Daily Passes",
      description: "Support recurring billing intervals: 24-hour daily passes, weekly sprints, monthly plans, and 2-year high-converting decoy packages.",
      badge: "Recurring ARR",
      color: "text-blue-500",
      bg: "bg-blue-50 dark:bg-blue-950/40",
    },
    {
      icon: Key,
      title: "Perpetual & Lifetime Deals (LTD)",
      description: "Sell one-time lifetime licenses with permanent cryptographic offline tokens. Ideal for AppSumo campaigns and indie hacker launches.",
      badge: "Instant Cash",
      color: "text-amber-500",
      bg: "bg-amber-50 dark:bg-amber-950/40",
    },
    {
      icon: FileCheck,
      title: "Trial & Evaluation",
      description: "Time-limited free evaluation licenses with automated expiration, grace periods, and seamless conversion to paid subscription tiers.",
      badge: "Zero Friction",
      color: "text-emerald-500",
      bg: "bg-emerald-50 dark:bg-emerald-950/40",
    },
  ];

  return (
    <section id="licensing-models" className="py-20 border-b border-border/60 bg-muted/10">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto space-y-3 mb-14">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold tracking-wide uppercase">
            <Sparkles className="size-3.5" />
            <span>Versatile Entitlements</span>
          </div>
          <h2 className="text-3xl font-bold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
            Implement any licensing model with zero hassle
          </h2>
          <p className="text-sm text-muted-foreground leading-relaxed">
            Every software business is unique. LaraLicensing natively supports every monetization strategy without requiring complex database migrations or custom billing adapters.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {models.map((m, idx) => {
            const Icon = m.icon;
            return (
              <div
                key={idx}
                className="group relative rounded-2xl border border-border/70 bg-card p-6 shadow-xs hover:border-primary/50 hover:shadow-md transition-all duration-200 flex flex-col justify-between"
              >
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div className={`p-2.5 rounded-xl ${m.bg} ${m.color} group-hover:scale-105 transition-transform`}>
                      <Icon className="size-5" />
                    </div>
                    <span className="text-[10px] font-semibold font-mono uppercase px-2 py-0.5 rounded-md bg-muted text-muted-foreground">
                      {m.badge}
                    </span>
                  </div>

                  <h3 className="text-base font-semibold text-foreground group-hover:text-primary transition-colors">
                    {m.title}
                  </h3>

                  <p className="text-xs text-muted-foreground leading-relaxed">
                    {m.description}
                  </p>
                </div>

                <div className="pt-4 mt-4 border-t border-border/40 flex items-center gap-1.5 text-[11px] font-medium text-primary">
                  <CheckCircle2 className="size-3.5 text-emerald-500" />
                  <span>Configurable in 1 click</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
