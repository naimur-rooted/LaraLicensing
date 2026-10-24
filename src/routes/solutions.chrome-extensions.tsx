import React from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Chrome, ShieldCheck, Zap, ArrowRight, CheckCircle2 } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/solutions/chrome-extensions")({
  head: () => ({
    meta: [
      { title: "Chrome Extension Licensing • Manifest V3 • LaraLicensing" },
      {
        name: "description",
        content:
          "Monetize Chrome extensions and WebExtensions with persistent profile UUID binding and chrome.storage.local offline token caching.",
      },
    ],
  }),
  component: ChromeSolutionsPage,
});

function ChromeSolutionsPage() {
  const highlights = [
    "Full Manifest V3 (MV3) service-worker compatibility with zero blocking background processes.",
    "Binds license keys to persistent Chrome profile UUIDs to prevent cross-profile piracy.",
    "Caches signed offline tokens in `chrome.storage.local` with non-blocking async checks.",
    "Turnkey Stripe and LemonSqueezy webhook integration for automated tier upgrading.",
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <div className="text-center max-w-3xl mx-auto space-y-4">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
              <Chrome className="size-4" />
              <span>Browser Extensions (MV3)</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              The first licensing engine built for Chrome extensions
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Monetize browser extensions with persistent user profile binding, feature gating, and automated payments.
            </p>
            <div className="pt-2">
              <Link
                to="/docs"
                className="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-md hover:bg-primary/90 transition-colors"
              >
                <span>Explore Chrome MV3 Client Code</span>
                <ArrowRight className="size-4" />
              </Link>
            </div>
          </div>

          <div className="rounded-2xl border border-border/80 bg-card p-8 sm:p-12 shadow-xs space-y-6">
            <h2 className="text-2xl font-bold text-foreground">Why Extension Builders Need LaraLicensing</h2>
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
