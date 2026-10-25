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

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center space-y-7">
        {/* Top Eyebrow Badge */}
        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-primary/25 bg-primary/5 text-primary text-sm font-semibold shadow-xs backdrop-blur-xs">
          <Sparkles className="size-4 text-amber-500" />
          <span>Next-Gen Multi-Tenant Licensing Engine • Split-DB Shards & Ed25519</span>
        </div>

        {/* Main Headline */}
        <h1
          className="mx-auto max-w-5xl text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-foreground leading-[1.08]"
          style={{ fontFamily: "var(--font-display)" }}
        >
          Unlock Growth with Enterprise-Grade{" "}
          <span className="bg-gradient-to-r from-sky-500 via-blue-600 to-indigo-600 bg-clip-text text-transparent">
            Software Licensing
          </span>
        </h1>

        {/* Subtitle */}
        <p className="mx-auto max-w-3xl text-lg sm:text-xl lg:text-2xl text-muted-foreground/90 leading-relaxed font-normal">
          Modern licensing platform for desktop software, Chrome extensions, WordPress plugins, and SaaS. Enforce hardware node-locking, machine seat quotas, offline signed JWTs, and automated webhook minting.
        </p>

        {/* Primary CTA Buttons matching LicenseSpring */}
        <div className="flex flex-wrap items-center justify-center gap-4 pt-3">
          <Link
            to="/register"
            search={{ plan: "STARTER" }}
            className="inline-flex h-13 items-center gap-2.5 rounded-full bg-primary px-8 text-base font-semibold text-primary-foreground shadow-lg hover:bg-primary/90 transition-all hover:scale-[1.02] active:scale-[0.98]"
          >
            <span>Get Started Free</span>
            <ArrowRight className="size-5" />
          </Link>

          <a
            href="#code-showcase"
            className="inline-flex h-13 items-center gap-2.5 rounded-full border border-border bg-background/80 backdrop-blur-sm px-8 text-base font-semibold text-foreground shadow-sm hover:bg-muted transition-colors"
          >
            <Terminal className="size-4.5 text-primary" />
            <span>Interactive Code Demo</span>
          </a>
        </div>

        {/* Supported Languages & Platforms Matrix matching Cryptlex / LicenseSpring */}
        <div className="pt-12 space-y-4">
          <p className="text-sm uppercase font-semibold tracking-wider text-muted-foreground">
            Works across all major platforms, languages & frameworks
          </p>

          <div className="flex flex-wrap items-center justify-center gap-2.5 max-w-4xl mx-auto">
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
                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-border/70 bg-card/70 backdrop-blur-xs text-sm font-medium text-foreground shadow-xs hover:border-primary/50 transition-colors"
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
