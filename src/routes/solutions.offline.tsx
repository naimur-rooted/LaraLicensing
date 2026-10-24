import React from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { WifiOff, ShieldCheck, KeyRound, ArrowRight, CheckCircle2 } from "lucide-react";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/solutions/offline")({
  head: () => ({
    meta: [
      { title: "Air-Gapped & Offline Software Licensing • Ed25519 • LaraLicensing" },
      {
        name: "description",
        content:
          "Cryptographic offline license verification for air-gapped workstations, defense, industrial IoT, and medical software using Ed25519 signatures.",
      },
    ],
  }),
  component: OfflineSolutionsPage,
});

function OfflineSolutionsPage() {
  const highlights = [
    "Zero continuous internet connection required: perfect for air-gapped, defense, and high-security sites.",
    "Tokens signed with Ed25519 public-key cryptography (RFC 8032); mathematically impossible to forge.",
    "Hardware digests (motherboard, MAC, CPU) verified locally on the client machine against signed claims.",
    "Configurable validity windows: generate permanent lifetime keys or time-bound offline evaluation tokens.",
  ];

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <div className="text-center max-w-3xl mx-auto space-y-4">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 text-sm font-semibold tracking-wide uppercase">
              <WifiOff className="size-4" />
              <span>Air-Gapped & Restricted Environments</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
              Offline verification with cryptographic certainty
            </h1>
            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Protect high-security desktop software, industrial machinery, and offline workstations using modern asymmetric cryptography instead of fragile proprietary license files.
            </p>
            <div className="pt-2">
              <Link
                to="/docs"
                className="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-md hover:bg-primary/90 transition-colors"
              >
                <span>Read Cryptographic Implementation Specs</span>
                <ArrowRight className="size-4" />
              </Link>
            </div>
          </div>

          <div className="rounded-2xl border border-border/80 bg-card p-8 sm:p-12 shadow-xs space-y-6">
            <h2 className="text-2xl font-bold text-foreground">Air-Gapped Protection Architecture</h2>
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
