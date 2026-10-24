import React from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Globe, ShieldCheck, Zap, ArrowRight, CheckCircle2 } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/solutions/wordpress")({
  head: () => ({
    meta: [
      { title: "WordPress Plugin & Theme Licensing • 0% Revenue Cut • LaraLicensing" },
      {
        name: "description",
        content:
          "Self-hosted licensing for WordPress plugins and themes. Site domain locking, localhost exemptions, transient caching, and WooCommerce support.",
      },
    ],
  }),
  component: WordPressSolutionsPage,
});

function WordPressSolutionsPage() {
  const highlights = [
    "Zero commission: Keep 100% of your revenue instead of losing 7–10%+ to platforms like Freemius.",
    "Binds licenses to client WordPress site URLs with automatic localhost and staging exemptions.",
    "Fast performance: caches license status in WordPress transients to eliminate external API lag.",
    "Decoupled webhook support: mint keys instantly from WooCommerce or Stripe checkout.",
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <div className="text-center max-w-3xl mx-auto space-y-4">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
              <Globe className="size-4" />
              <span>WordPress & PHP Web Apps</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              The zero-commission alternative for WordPress creators
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Sell WordPress plugins and themes directly on your terms. Enforce site domain locking and automated updates without giving away a percentage of your business.
            </p>
            <div className="pt-2">
              <Link
                to="/docs"
                className="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-md hover:bg-primary/90 transition-colors"
              >
                <span>View WordPress PHP Client Snippet</span>
                <ArrowRight className="size-4" />
              </Link>
            </div>
          </div>

          <div className="rounded-2xl border border-border/80 bg-card p-8 sm:p-12 shadow-xs space-y-6">
            <h2 className="text-2xl font-bold text-foreground">Why WordPress Developers Choose LaraLicensing</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {highlights.map((h, idx) => (
                <div key={idx} className="flex items-start gap-3 p-4 rounded-xl bg-muted/20 border border-border/50">
                  <CheckCircle2 className="size-5 text-emerald-500 shrink-0 mt-0.5" />
                  <span className="text-sm text-foreground/90 font-medium">{h}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </main>

      <LicenseSpringFooter />
    </div>
  );
}
