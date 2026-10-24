import React from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Store, ShieldCheck, Tag, Cpu, ArrowRight, CheckCircle2, History } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/reseller-info")({
  head: () => ({
    meta: [
      { title: "Reseller Wholesale Hub • LaraLicensing" },
      {
        name: "description",
        content:
          "Empower wholesale distributors with branded serial minting, vanity prefixes, append-only quota ledgers, and downstream machine troubleshooting.",
      },
    ],
  }),
  component: ResellerInfoPage,
});

function ResellerInfoPage() {
  const capabilities = [
    {
      icon: Tag,
      title: "Branded Vanity Prefix Minting",
      desc: "Resellers can register exclusive branded prefixes (e.g. `ALIM-V1-XXXX-XXXX`), minting customized serial numbers tailored to their regional marketing campaigns.",
    },
    {
      icon: History,
      title: "Append-Only Quota Ledger",
      desc: "Every quota grant, license mint, and 24-hour revocation restoration is recorded in an immutable transactional ledger with complete audit transparency.",
    },
    {
      icon: Cpu,
      title: "Downstream Machine Troubleshooting",
      desc: "Resellers can inspect active hardware fingerprints, view last-seen telemetry, and remotely release machine seats with automated 15-minute re-bind cooldowns.",
    },
    {
      icon: Store,
      title: "Self-Service Quota Requests",
      desc: "Request additional license allowance directly through the Reseller Portal. Vendors can review, approve, or deny requests with instant balance updates.",
    },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <div className="text-center max-w-3xl mx-auto space-y-4">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
              <Store className="size-4" />
              <span>Wholesale Distribution</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              Scale your software sales with authorized resellers
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Enable distributors, affiliates, and agency partners to mint branded licenses, manage their client quotas, and troubleshoot hardware bindings without giving them administrative access.
            </p>
            <div className="pt-2">
              <Link
                to="/admin/login"
                className="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-md hover:bg-primary/90 transition-colors"
              >
                <span>Access Reseller Hub</span>
                <ArrowRight className="size-4" />
              </Link>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            {capabilities.map((c, idx) => {
              const Icon = c.icon;
              return (
                <div key={idx} className="rounded-2xl border border-border/80 bg-card p-8 shadow-xs space-y-3">
                  <div className="size-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                    <Icon className="size-5" />
                  </div>
                  <h2 className="text-xl font-bold text-foreground">{c.title}</h2>
                  <p className="text-sm text-muted-foreground leading-relaxed">{c.desc}</p>
                </div>
              );
            })}
          </div>
        </div>
      </main>

      <LicenseSpringFooter />
    </div>
  );
}
