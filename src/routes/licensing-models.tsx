import React from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import {
  Smartphone,
  Network,
  Gauge,
  CreditCard,
  Key,
  PackageCheck,
  CheckCircle2,
  Sparkles,
  ArrowRight,
  Shield,
} from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/licensing-models")({
  head: () => ({
    meta: [
      { title: "Software Licensing Models & Entitlements • LaraLicensing" },
      {
        name: "description",
        content:
          "Comprehensive guide to software licensing models: Node-locked hardware binding, floating device pools, usage-based metering, subscriptions, and lifetime deals.",
      },
    ],
  }),
  component: LicensingModelsPage,
});

function LicensingModelsPage() {
  const models = [
    {
      id: "node-locked",
      icon: Smartphone,
      title: "Node-Locked & Hardware Binding",
      badge: "Anti-Piracy Standard",
      description:
        "Binds a software license key to a specific hardware digest (Motherboard GUID, MAC address hash, CPU serial, or persistent Chrome profile UUID).",
      details: [
        "Prevents simultaneous license reuse across unauthorized workstations.",
        "Generates deterministic 16-character SHA-256 hardware digest locally.",
        "Allows automated machine release and seat re-binding with configurable 15-minute cooldown locks.",
      ],
      samplePayload: `{"serialValue": "ALIM-V1-9F3K-2XQ8", "hardwareDigest": "A4F8B9210C3E5D71"}`,
    },
    {
      id: "floating",
      icon: Network,
      title: "Floating & Concurrent Seats",
      badge: "Enterprise & Teams",
      description:
        "Allows organizations to purchase a shared pool of concurrent seats (e.g. 5 active workstations) that team members can lease and release on demand.",
      details: [
        "Dynamic workstation registration with automatic heartbeat timeouts.",
        "Graceful deallocation when software closes, freeing seats for other team members.",
        "Prevents over-allocation without forcing users to re-purchase licenses.",
      ],
      samplePayload: `{"maxConcurrentSeats": 5, "activeWorkstations": 3, "availableRemaining": 2}`,
    },
    {
      id: "subscriptions",
      icon: CreditCard,
      title: "Subscriptions & Time-Limited Passes",
      badge: "Recurring ARR",
      description:
        "Supports recurring intervals: 24-hour daily passes, weekly sprints, monthly subscriptions, and annual licenses with automated grace periods.",
      details: [
        "Synced directly via Stripe, LemonSqueezy, or WooCommerce webhooks.",
        "Automatic expiration timestamp encoded into tamper-proof Ed25519 JWTs.",
        "Configurable 72-hour network grace period for users with temporary connectivity dropouts.",
      ],
      samplePayload: `{"expiresAt": "2027-01-01T00:00:00Z", "gracePeriodHours": 72, "isExpired": false}`,
    },
    {
      id: "perpetual",
      icon: Key,
      title: "Perpetual & Lifetime Deals (LTD)",
      badge: "Indie Hacker & AppSumo",
      description:
        "Sell permanent lifetime licenses with permanent cryptographic tokens. Perfect for product launches, LTD campaigns, and perpetual desktop software.",
      details: [
        "Token validity set to permanent; requires zero continuous cloud heartbeats.",
        "Optional major-version entitlement gates (e.g. valid for v1.x and v2.x updates).",
        "Includes hardware node-locking to prevent infinite sharing.",
      ],
      samplePayload: `{"isPerpetual": true, "maxAllowedMajorVersion": 2, "offlineTokenTTL": "Permanent"}`,
    },
    {
      id: "usage-based",
      icon: Gauge,
      title: "Usage-Based & Metered Quotas",
      badge: "Consumption Billing",
      description:
        "Enforce monthly scraping limits, AI token allocations, or export caps with transactional quota tracking and balance queries.",
      details: [
        "Atomic balance decrement on server side with zero race conditions.",
        "Client applications query remaining balance via `/verify` endpoint.",
        "Automatic monthly reset or rollover according to billing tier rules.",
      ],
      samplePayload: `{"allocatedQuota": 10000, "consumedQuota": 3420, "balanceRemaining": 6580}`,
    },
    {
      id: "feature-gating",
      icon: PackageCheck,
      title: "Feature-Based Gating & Entitlements",
      badge: "Modular Upsells",
      description:
        "Ship a single unified application binary and dynamically unlock pro perks, add-on modules, or enterprise flags based on the user's license claims.",
      details: [
        "Entitlements array signed inside the Ed25519 JWT token.",
        "Client verifies claims locally without making extra network requests.",
        "Instantly toggle features on or off from the Vendor Portal.",
      ],
      samplePayload: `{"features": ["export_4k", "batch_processing", "priority_ai_engine"]}`,
    },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <div className="text-center max-w-3xl mx-auto space-y-4">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
              <Sparkles className="size-4" />
              <span>Monetization Architecture</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              Every licensing model, out of the box
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Whether you are distributing node-locked desktop applications, metering SaaS usage, or launching an LTD campaign, LaraLicensing provides bulletproof enforcement without custom billing engineering.
            </p>
          </div>

          <div className="space-y-12">
            {models.map((m, idx) => {
              const Icon = m.icon;
              return (
                <div
                  key={idx}
                  id={m.id}
                  className="rounded-2xl border border-border/80 bg-card p-8 sm:p-10 shadow-xs space-y-6 scroll-mt-24"
                >
                  <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <div className="p-3 rounded-xl bg-primary/10 text-primary">
                        <Icon className="size-6" />
                      </div>
                      <div>
                        <h2 className="text-2xl font-bold text-foreground">{m.title}</h2>
                        <span className="text-xs font-semibold text-primary font-mono uppercase tracking-wider">
                          {m.badge}
                        </span>
                      </div>
                    </div>
                    <Link
                      to="/docs"
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                    >
                      View Implementation Guide <ArrowRight className="size-3.5" />
                    </Link>
                  </div>

                  <p className="text-sm text-muted-foreground leading-relaxed">{m.description}</p>

                  <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center pt-2">
                    <div className="lg:col-span-7 space-y-2.5">
                      <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Architectural Highlights</p>
                      <ul className="space-y-2">
                        {m.details.map((d, dIdx) => (
                          <li key={dIdx} className="flex items-start gap-2.5 text-sm text-foreground/90">
                            <CheckCircle2 className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            <span>{d}</span>
                          </li>
                        ))}
                      </ul>
                    </div>

                    <div className="lg:col-span-5 bg-zinc-950 text-zinc-100 p-4 rounded-xl font-mono text-xs overflow-x-auto border border-zinc-800">
                      <p className="text-[10px] text-zinc-400 mb-1 font-mono uppercase">API Payload Schema</p>
                      <code>{m.samplePayload}</code>
                    </div>
                  </div>
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
