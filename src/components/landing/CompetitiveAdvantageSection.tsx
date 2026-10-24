import React from "react";
import { Database, ShieldCheck, Sparkles, Webhook, Check, X } from "lucide-react";

export function CompetitiveAdvantageSection() {
  const comparison = [
    {
      feature: "Database Architecture",
      legacy: "Shared monolithic DB (noisy neighbors, risk of data leaks)",
      lara: "Split-DB SQLite Sharding (100% isolated database per product)",
    },
    {
      feature: "Offline Cryptography",
      legacy: "Proprietary license files & slow RSA signatures",
      lara: "Modern Ed25519 Public Key + Lightweight Signed JWT Tokens",
    },
    {
      feature: "AI Pricing Copilot",
      legacy: "None (Manual tier guesswork & spreadsheet pricing)",
      lara: "Built-in Gemini AI Architect (1-click decoy tier & code synthesis)",
    },
    {
      feature: "Billing & Webhooks",
      legacy: "Coupled merchant accounts with vendor lock-in",
      lara: "Decoupled Webhooks (Works with Stripe, LemonSqueezy, WooCommerce)",
    },
    {
      feature: "Wholesale & Resellers",
      legacy: "Custom enterprise contract add-on",
      lara: "Built-in Reseller Wholesale Studio with sub-quota allocation",
    },
  ];

  return (
    <section id="comparison" className="py-24 border-b border-border/60 bg-background">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto space-y-4 mb-16">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
            <Sparkles className="size-4" />
            <span>Why Developers Switch</span>
          </div>
          <h2 className="text-4xl sm:text-5xl font-extrabold tracking-tight text-foreground leading-[1.12]" style={{ fontFamily: "var(--font-display)" }}>
            Engineered for modern software creators
          </h2>
          <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
            Legacy licensing systems like Cryptlex and Nalpeiron were designed for 2012 enterprise IT. LaraLicensing is built for 2026 developer velocity, privacy, and speed.
          </p>
        </div>

        <div className="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-md">
          <div className="grid grid-cols-12 border-b bg-muted/40 px-6 py-4.5 text-xs sm:text-sm font-bold text-muted-foreground uppercase tracking-wider">
            <div className="col-span-4">Capability</div>
            <div className="col-span-4 text-muted-foreground/80">Legacy Licensing Platforms</div>
            <div className="col-span-4 text-primary font-bold">LaraLicensing Advantage</div>
          </div>

          <div className="divide-y divide-border/50">
            {comparison.map((row, idx) => (
              <div key={idx} className="grid grid-cols-12 px-6 py-5 text-sm items-center hover:bg-muted/10 transition-colors">
                <div className="col-span-4 font-semibold text-foreground flex items-center gap-2">
                  <span>{row.feature}</span>
                </div>
                <div className="col-span-4 text-muted-foreground flex items-start gap-2">
                  <X className="size-4 text-rose-500 shrink-0 mt-0.5" />
                  <span>{row.legacy}</span>
                </div>
                <div className="col-span-4 font-medium text-foreground flex items-start gap-2">
                  <Check className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                  <span>{row.lara}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
