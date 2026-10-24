import React from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { ShieldCheck, Database, KeyRound, Lock, Server, CheckCircle2, ArrowRight } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/security")({
  head: () => ({
    meta: [
      { title: "Security & Trust Architecture • LaraLicensing" },
      {
        name: "description",
        content:
          "Enterprise data isolation via Split-DB SQLite sharding, Ed25519 elliptic-curve public-key signatures, and zero cardholder data storage.",
      },
    ],
  }),
  component: SecurityPage,
});

function SecurityPage() {
  const securityPillars = [
    {
      icon: Database,
      title: "Split-DB Multi-Tenant Sharding",
      badge: "Absolute Data Isolation",
      desc: "Unlike legacy licensing architectures that pool all vendor keys into a single bloated PostgreSQL or MySQL table, LaraLicensing provisions an isolated, dedicated SQLite database file per product shard.",
      benefits: [
        "Zero noisy-neighbor performance degradation or index lock contention.",
        "Impossible cross-tenant data leaks; each shard is physically separated on disk.",
        "Instant backup, cloning, and export of product data without touching other tenants.",
      ],
    },
    {
      icon: KeyRound,
      title: "Ed25519 Asymmetric Cryptography",
      badge: "High-Speed Cryptographic Security",
      desc: "LaraLicensing issues offline activation tokens signed with Edwards-curve Ed25519 public-key cryptography (RFC 8032), providing military-grade authenticity with microsecond verification times.",
      benefits: [
        "Client software embeds your public key; private keys never leave the secure server.",
        "Tamper-proof JSON Web Tokens (JWT) cannot be forged or modified by crackers.",
        "Supports 100% air-gapped systems with zero continuous phone-home requirements.",
      ],
    },
    {
      icon: Lock,
      title: "Zero Cardholder Data Storage",
      badge: "Decoupled Payment Webhooks",
      desc: "LaraLicensing acts purely as a licensing and entitlement enforcement engine. We deliberately do not store credit card numbers, billing addresses, or bank details.",
      benefits: [
        "Reduces PCI-DSS compliance scope to zero for your licensing infrastructure.",
        "Turnkey webhook ingestion directly from Stripe, LemonSqueezy, and WooCommerce.",
        "Instant cryptographic license minting upon verified payment notification.",
      ],
    },
    {
      icon: Server,
      title: "Fraud Guard & Heartbeat Cadence",
      badge: "Telemetry & Abuse Detection",
      desc: "Our real-time telemetry engine continuously monitors workstation registration frequency, detects erratic IP jumps, and flags license key sharing.",
      benefits: [
        "15-minute re-bind cooldown locks prevent license churning across multiple machines.",
        "Instant fraud alert logs with geographic location and device fingerprint changes.",
        "Autonomous revocation triggers for compromised or cracked serial numbers.",
      ],
    },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <div className="text-center max-w-3xl mx-auto space-y-4">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-sm font-semibold tracking-wide uppercase">
              <ShieldCheck className="size-4" />
              <span>Trust & Architectural Security</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              Engineered with zero compromises on security
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Software protection requires mathematical certainty. Explore how our split SQLite sharding, Ed25519 cryptography, and decoupled webhooks protect your intellectual property.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            {securityPillars.map((p, idx) => {
              const Icon = p.icon;
              return (
                <div key={idx} className="rounded-2xl border border-border/80 bg-card p-8 shadow-xs space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="p-3 rounded-xl bg-primary/10 text-primary">
                      <Icon className="size-6" />
                    </div>
                    <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 font-mono bg-emerald-500/10 px-2.5 py-1 rounded-md uppercase">
                      {p.badge}
                    </span>
                  </div>
                  <h2 className="text-xl font-bold text-foreground">{p.title}</h2>
                  <p className="text-sm text-muted-foreground leading-relaxed">{p.desc}</p>
                  <ul className="space-y-2 pt-2 border-t border-border/50">
                    {p.benefits.map((b, bIdx) => (
                      <li key={bIdx} className="flex items-start gap-2.5 text-xs sm:text-sm text-foreground/90 font-medium">
                        <CheckCircle2 className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                        <span>{b}</span>
                      </li>
                    ))}
                  </ul>
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
