import React, { useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import {
  Terminal,
  Code2,
  Copy,
  Check,
  BookOpen,
  Zap,
  ShieldCheck,
  FileText,
  Webhook,
  Sparkles,
  ArrowRight,
} from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/docs")({
  head: () => ({
    meta: [
      { title: "Developer Documentation & REST API Reference • LaraLicensing" },
      {
        name: "description",
        content:
          "Integrate headless software licensing, node-locking, and offline Ed25519 verification in 3 lines of code. Complete API reference and SDK guides.",
      },
    ],
  }),
  component: DocsPage,
});

function DocsPage() {
  const [activeLang, setActiveLang] = useState<"python" | "chrome" | "wordpress" | "csharp">("python");
  const [copiedSnippet, setCopiedSnippet] = useState(false);

  const apiEndpoints = [
    {
      method: "POST",
      path: "/api/v1/products/{productSlug}/verify",
      desc: "Validates a serial key and hardware digest without binding a permanent seat. Returns feature entitlements.",
    },
    {
      method: "POST",
      path: "/api/v1/products/{productSlug}/activate",
      desc: "Binds the machine hardware fingerprint to the license key. Consumes 1 workstation seat from the quota.",
    },
    {
      method: "POST",
      path: "/api/v1/products/{productSlug}/deactivate",
      desc: "Releases the machine hardware binding and restores 1 workstation seat to the license quota pool.",
    },
    {
      method: "POST",
      path: "/api/v1/products/{productSlug}/heartbeat",
      desc: "Periodic telemetry check-in. Updates LastSeenAt and returns updated signed Ed25519 offline token.",
    },
    {
      method: "POST",
      path: "/api/v1/webhooks/{provider}",
      desc: "Decoupled webhook ingestion for Stripe, LemonSqueezy, and WooCommerce. Mints serial automatically.",
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
              <Code2 className="size-4" />
              <span>Developer Reference & SDKs</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              Integrate licensing in minutes, not months
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              LaraLicensing is built API-first. Verify serials, enforce hardware node-locking, and validate offline tokens using lightweight REST endpoints.
            </p>
          </div>

          {/* Quickstart 3-Step Grid */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="p-6 rounded-2xl border border-border/80 bg-card space-y-3">
              <div className="size-8 rounded-lg bg-primary/10 text-primary font-bold flex items-center justify-center font-mono text-sm">
                01
              </div>
              <h3 className="text-lg font-bold text-foreground">Create Product Shard</h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Log into the Vendor Portal or call the Admin API to spawn a dedicated SQLite shard for your software.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-border/80 bg-card space-y-3">
              <div className="size-8 rounded-lg bg-primary/10 text-primary font-bold flex items-center justify-center font-mono text-sm">
                02
              </div>
              <h3 className="text-lg font-bold text-foreground">Drop in Handshake Code</h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Add our lightweight HTTP verification call to your desktop app, Chrome extension, or WordPress plugin.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-border/80 bg-card space-y-3">
              <div className="size-8 rounded-lg bg-primary/10 text-primary font-bold flex items-center justify-center font-mono text-sm">
                03
              </div>
              <h3 className="text-lg font-bold text-foreground">Connect Payment Webhook</h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Point Stripe or LemonSqueezy to our webhook URL. Serials mint automatically upon successful purchase.
              </p>
            </div>
          </div>

          {/* REST Endpoints Table */}
          <div id="api" className="space-y-6 pt-6 scroll-mt-20">
            <div className="space-y-1">
              <h2 className="text-2xl font-bold text-foreground" style={{ fontFamily: "var(--font-display)" }}>
                Core REST API Endpoints
              </h2>
              <p className="text-sm text-muted-foreground">
                All requests accept JSON payloads and return our universal response envelope with Ed25519 cryptographic tokens.
              </p>
            </div>

            <div className="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-xs divide-y divide-border/60">
              {apiEndpoints.map((ep, idx) => (
                <div key={idx} className="p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2.5">
                      <span className="px-2.5 py-1 rounded bg-primary/10 text-primary font-mono text-xs font-bold">
                        {ep.method}
                      </span>
                      <code className="text-sm font-semibold text-foreground font-mono">{ep.path}</code>
                    </div>
                    <p className="text-xs text-muted-foreground pl-0.5">{ep.desc}</p>
                  </div>
                  <span className="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold bg-emerald-500/10 px-2.5 py-1 rounded-full shrink-0">
                    200 OK Envelope
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Offline Cryptography & EULA Generator Utility */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6">
            <div className="p-8 rounded-2xl border border-border/80 bg-muted/20 space-y-4">
              <div className="flex items-center gap-2 text-primary font-bold text-sm uppercase tracking-wide">
                <ShieldCheck className="size-4" />
                <span>Ed25519 Offline Verification</span>
              </div>
              <h3 className="text-xl font-bold text-foreground">Air-Gapped Cryptography</h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Your client software embeds your product's Ed25519 Public Key. When activated, LaraLicensing signs a lightweight JWT containing expiration and machine digests. Your software verifies this locally with zero network calls.
              </p>
              <div className="p-3 bg-zinc-950 text-zinc-100 rounded-xl font-mono text-xs">
                <code>ed25519.Verify(publicKey, tokenDigest, signature)</code>
              </div>
            </div>

            <div id="eula-generator" className="p-8 rounded-2xl border border-border/80 bg-muted/20 space-y-4 scroll-mt-20">
              <div className="flex items-center gap-2 text-primary font-bold text-sm uppercase tracking-wide">
                <FileText className="size-4" />
                <span>EULA Generator Utility</span>
              </div>
              <h3 className="text-xl font-bold text-foreground">Standard Software License (EULA)</h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Need a ready-to-use End User License Agreement that legally reinforces your machine node-locking and prohibits reverse engineering?
              </p>
              <Link
                to="/portal/home"
                className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
              >
                Access EULA Builder in Vendor Portal <ArrowRight className="size-3.5" />
              </Link>
            </div>
          </div>
        </div>
      </main>

      <LicenseSpringFooter />
    </div>
  );
}
