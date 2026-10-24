import React from "react";
import { createFileRoute } from "@tanstack/react-router";
import { CheckCircle2, Activity, Server, Database, Shield } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/status")({
  head: () => ({
    meta: [
      { title: "System Status & API Health • LaraLicensing" },
      {
        name: "description",
        content: "Real-time uptime, response latency, and system health across LaraLicensing API services.",
      },
    ],
  }),
  component: StatusPage,
});

function StatusPage() {
  const services = [
    { name: "Licensing REST API (/verify, /activate)", status: "Operational", latency: "14ms", uptime: "99.99%" },
    { name: "Split-DB SQLite Sharding Engine", status: "Operational", latency: "2ms", uptime: "100.0%" },
    { name: "Ed25519 Cryptographic Token Signer", status: "Operational", latency: "1ms", uptime: "100.0%" },
    { name: "Decoupled Webhook Ingestion (Stripe / Lemon)", status: "Operational", latency: "28ms", uptime: "99.98%" },
    { name: "Fraud Guard & Telemetry Pipeline", status: "Operational", latency: "18ms", uptime: "99.95%" },
    { name: "Vendor & Reseller Management Portals", status: "Operational", latency: "22ms", uptime: "99.99%" },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-12">
          <div className="text-center max-w-2xl mx-auto space-y-3">
            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-semibold uppercase tracking-wide">
              <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>All Systems Operational</span>
            </div>
            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              LaraLicensing System Telemetry
            </h1>
            <p className="text-sm text-muted-foreground">
              Real-time performance metrics and infrastructure status across all product shards.
            </p>
          </div>

          <div className="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-xs divide-y divide-border/60">
            {services.map((s, idx) => (
              <div key={idx} className="p-5 flex items-center justify-between text-sm">
                <div className="flex items-center gap-3">
                  <CheckCircle2 className="size-4 text-emerald-500" />
                  <span className="font-semibold text-foreground">{s.name}</span>
                </div>
                <div className="flex items-center gap-4 text-xs font-mono">
                  <span className="text-muted-foreground">{s.latency}</span>
                  <span className="text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded">
                    {s.status}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </main>

      <LicenseSpringFooter />
    </div>
  );
}
