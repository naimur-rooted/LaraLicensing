import { Link } from "@tanstack/react-router";
import { Check, ArrowRight } from "lucide-react";
import type { PricingTierItem } from "./pricing-data";

interface PricingCardProps {
  readonly tier: PricingTierItem;
}

export function PricingCard({ tier }: PricingCardProps) {
  const isEnterprise = tier.code === "ENTERPRISE";
  const buttonStyle = tier.isPopular
    ? "bg-primary text-primary-foreground shadow-md hover:bg-primary/90"
    : "border border-border bg-background hover:bg-muted text-foreground";

  return (
    <div
      className={`rounded-2xl border p-8 flex flex-col justify-between transition-all duration-200 relative bg-card ${
        tier.isPopular
          ? "border-primary shadow-xl ring-2 ring-primary/20 scale-[1.02]"
          : "border-border/80 shadow-sm hover:border-primary/40"
      }`}
    >
      {tier.isPopular && (
        <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-primary text-primary-foreground text-xs font-bold uppercase tracking-wider shadow-sm">
          {tier.badge}
        </div>
      )}

      <div className="space-y-6">
        <div>
          <h3 className="text-xl font-bold text-foreground">{tier.name}</h3>
          <p className="text-xs text-muted-foreground mt-1">{tier.description}</p>
        </div>

        <div className="flex items-baseline gap-1">
          <span className="text-5xl font-extrabold tracking-tight text-foreground font-mono">{tier.price}</span>
          <span className="text-sm font-semibold text-muted-foreground">{tier.period}</span>
        </div>

        <div className="space-y-3 pt-4 border-t border-border/60">
          <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Included Features</p>
          <ul className="space-y-2.5">
            {tier.features.map((feat, fIdx) => (
              <li key={fIdx} className="flex items-start gap-2.5 text-sm text-foreground/90">
                <Check className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                <span>{feat}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="pt-8">
        {isEnterprise ? (
          <Link
            to="/reseller-info"
            className={`w-full inline-flex h-12 items-center justify-center gap-2 rounded-xl text-sm font-semibold transition-colors ${buttonStyle}`}
          >
            <span>{tier.ctaText}</span>
            <ArrowRight className="size-4" />
          </Link>
        ) : (
          <Link
            to="/register"
            search={{ plan: tier.code }}
            className={`w-full inline-flex h-12 items-center justify-center gap-2 rounded-xl text-sm font-semibold transition-colors ${buttonStyle}`}
          >
            <span>{tier.ctaText}</span>
            <ArrowRight className="size-4" />
          </Link>
        )}
      </div>
    </div>
  );
}
