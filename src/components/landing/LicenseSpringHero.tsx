import React from "react";
import { Link } from "@tanstack/react-router";
import { ArrowRight, ShieldCheck, Terminal, Sparkles, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";

export function LicenseSpringHero() {
  return (
    <section className="relative isolate overflow-hidden pt-12 pb-20 sm:pt-16 sm:pb-28 border-b border-border/60 bg-gradient-to-b from-sky-50/40 via-background to-background dark:from-sky-950/20 dark:via-background dark:to-background">
      {/* Background radial glow */}
      <div
        className="pointer-events-none absolute -top-40 left-1/2 -z-10 -translate-x-1/2 transform-gpu blur-3xl sm:-top-60"
        aria-hidden="true"
      >
        <div
          className="aspect-[1155/678] w-[72rem] bg-gradient-to-tr from-sky-400 via-blue-500 to-indigo-500 opacity-20 dark:opacity-15"
          style={{
            clipPath:
              "polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)",
          }}
        />
      </div>

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center space-y-6">
        {/* Top Eyebrow Badge */}
        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-primary/20 bg-primary/5 text-primary text-xs font-medium shadow-2xs backdrop-blur-xs">
          <Sparkles className="size-3.5 text-amber-500" />
          <span>Next-Gen Multi-Tenant Licensing Engine • Split-DB Shards & Ed25519</span>
        </div>

        {/* Main Headline */}
        <h1
          className="mx-auto max-w-4xl text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-foreground leading-[1.12]"
          style={{ fontFamily: "var(--font-display)" }}
        >
          Unlock Growth with Enterprise-Grade{" "}
          <span className="bg-gradient-to-r from-sky-600 via-blue-600 to-indigo-600 bg-clip-text text-transparent">
            Software Licensing
          </span>
        </h1>

        {/* Subtitle */}
        <p className="mx-auto max-w-2xl text-base sm:text-lg text-muted-foreground leading-relaxed">
          Modern licensing platform for desktop software, Chrome extensions, WordPress plugins, and SaaS. Enforce hardware node-locking, machine seat quotas, offline signed JWTs, and automated webhook minting.
        </p>

        {/* Primary CTA Buttons matching LicenseSpring */}
        <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
          <Link
            to="/portal/home"
            className="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-md hover:bg-primary/90 transition-all hover:scale-[1.02] active:scale-[0.98]"
          >
            <span>Get Started Free</span>
            <ArrowRight className="size-4" />
          </Link>

          <a
            href="http://localhost:5050"
            target="_blank"
            rel="noreferrer"
            className="inline-flex h-11 items-center gap-2 rounded-full border border-border bg-background px-6 text-sm font-semibold text-foreground shadow-2xs hover:bg-muted transition-colors"
          >
            <span>Test Storefront Sandbox</span>
            <ExternalLink className="size-3.5 text-muted-foreground" />
          </a>
        </div>

        {/* Supported Languages & Platforms Matrix matching Cryptlex / LicenseSpring */}
        <div className="pt-10 space-y-3">
          <p className="text-xs uppercase font-semibold tracking-wider text-muted-foreground/80">
            Works across all major platforms, languages & frameworks
          </p>

          <div className="flex flex-wrap items-center justify-center gap-2 max-w-3xl mx-auto">
            {[
              { name: "Python", icon: "🐍" },
              { name: "Chrome Extensions", icon: "🧩" },
              { name: "WordPress (PHP)", icon: "🔌" },
              { name: "C# .NET", icon: "⚡" },
              { name: "Go", icon: "🔷" },
              { name: "Rust", icon: "🦀" },
              { name: "Node.js & React", icon: "🌐" },
              { name: "Windows", icon: "🪟" },
              { name: "macOS", icon: "🍎" },
              { name: "Linux", icon: "🐧" },
              { name: "Docker", icon: "🐳" },
            ].map((p, i) => (
              <span
                key={i}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border/70 bg-card/60 backdrop-blur-xs text-xs font-medium text-foreground shadow-2xs hover:border-primary/40 transition-colors"
              >
                <span>{p.icon}</span>
                <span>{p.name}</span>
              </span>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
