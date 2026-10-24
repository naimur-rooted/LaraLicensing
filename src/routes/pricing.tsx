import React, { useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Check, Sparkles, ArrowRight, ShieldCheck, Zap, HelpCircle } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/pricing")({
  head: () => ({
    meta: [
      { title: "Pricing & Plans • LaraLicensing" },
      {
        name: "description",
        content:
          "Predictable pricing for software licensing. Split-DB SQLite sharding, Ed25519 offline verification, and turnkey webhook minting.",
      },
    ],
  }),
  component: PricingPage,
});

function PricingPage() {
  const [isAnnual, setIsAnnual] = useState(true);
  const [activationsCount, setActivationsCount] = useState(1000);

  const tiers = [
    {
      name: "Starter Developer",
      badge: "Free Forever",
      price: "$0",
      period: "forever",
      description: "Ideal for testing, indie hackers, and open-source projects evaluating LaraLicensing.",
      features: [
        "1 Sharded SQLite Product DB",
        "50 Active Workstation Activations",
        "Node-Locked & Perpetual Models",
        "Ed25519 Offline Verification",
        "Community Discord & GitHub Support",
        "REST API Access",
      ],
      ctaText: "Start Free",
      ctaLink: "/portal/home",
      isPopular: false,
    },
    {
      name: "Pro Creator",
      badge: "Most Popular",
      price: isAnnual ? "$39" : "$49",
      period: "/ month",
      description: "For growing SaaS, desktop tools, Chrome extensions, and WordPress plugin businesses.",
      features: [
        "5 Sharded SQLite Product DBs",
        "2,500 Active Workstation Activations",
        "All 6 Licensing Models (Floating, Metered, Passes)",
        "Stripe, LemonSqueezy & WooCommerce Webhooks",
        "Gemini AI Blueprint Code Synthesizer",
        "Fraud Guard & Device Telemetry Alerts",
        "Standard Email Support (24h SLA)",
      ],
      ctaText: "Get Pro Access",
      ctaLink: "/portal/home",
      isPopular: true,
    },
    {
      name: "Enterprise & Reseller",
      badge: "Wholesale Ready",
      price: isAnnual ? "$159" : "$199",
      period: "/ month",
      description: "For high-volume software distributors, reseller channels, and mission-critical applications.",
      features: [
        "Unlimited Sharded SQLite DBs",
        "50,000+ Active Workstations",
        "Full Reseller Wholesale Studio Hub",
        "Branded Vanity Prefix Minting",
        "Append-Only Quota Ledger & Self-Service Approvals",
        "Hardware Seat Troubleshooter with Remote Release",
        "Dedicated Private Slack & 99.99% Uptime SLA",
      ],
      ctaText: "Deploy Enterprise",
      ctaLink: "/reseller-info",
      isPopular: false,
    },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          {/* Header */}
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

            {/* Annual Billing Toggle */}
            <div className="flex items-center justify-center gap-3 pt-4">
              <span className={`text-sm font-semibold ${!isAnnual ? "text-foreground" : "text-muted-foreground"}`}>
                Monthly
              </span>
              <button
                type="button"
                onClick={() => setIsAnnual(!isAnnual)}
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

          {/* Pricing Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
            {tiers.map((tier, idx) => (
              <div
                key={idx}
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
                  <Link
                    to={tier.ctaLink}
                    className={`w-full inline-flex h-12 items-center justify-center gap-2 rounded-xl text-sm font-semibold transition-colors ${
                      tier.isPopular
                        ? "bg-primary text-primary-foreground shadow-md hover:bg-primary/90"
                        : "border border-border bg-background hover:bg-muted text-foreground"
                    }`}
                  >
                    <span>{tier.ctaText}</span>
                    <ArrowRight className="size-4" />
                  </Link>
                </div>
              </div>
            ))}
          </div>

          {/* Interactive Calculator Section */}
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
        </div>
      </main>

      <LicenseSpringFooter />
    </div>
  );
}
