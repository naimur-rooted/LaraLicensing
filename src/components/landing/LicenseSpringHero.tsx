import React from "react";
import { Link } from "@tanstack/react-router";
import { ArrowRight, Terminal, Store } from "lucide-react";

export function LicenseSpringHero() {
  return (
    <section className="relative overflow-hidden pt-12 pb-20 sm:pt-20 sm:pb-28">
      {/* Background radial gradient decoration matching LicenseSpring */}
      <div className="pointer-events-none absolute inset-0 -z-10 flex items-center justify-center">
        <div className="h-[500px] w-[500px] sm:h-[700px] sm:w-[700px] rounded-full bg-gradient-to-tr from-sky-500/15 via-blue-500/10 to-indigo-500/15 blur-3xl" />
      </div>

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center space-y-8">
        {/* Top Eyebrow Pill */}
        <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-4 py-1.5 text-xs sm:text-sm font-medium text-primary backdrop-blur-xs">
          <span className="flex size-2 rounded-full bg-primary animate-pulse" />
          <span>Next-Gen Cryptographic Licensing & Multi-Tenant Sharding</span>
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
          Modern licensing platform for desktop software, Chrome extensions, WordPress plugins, and SaaS. Enforce hardware node-locking, machine seat quotas, offline signed JWTs, and multi-tenant wholesale distribution.
        </p>

        {/* Primary Action Buttons */}
        <div className="flex flex-wrap items-center justify-center gap-3.5 pt-3">
          <Link
            to="/register"
            search={{ plan: "STARTER" }}
            className="inline-flex h-13 items-center gap-2.5 rounded-full bg-primary px-8 text-base font-semibold text-primary-foreground shadow-lg hover:bg-primary/90 transition-all hover:scale-[1.02] active:scale-[0.98]"
          >
            <span>Get Started Free</span>
            <ArrowRight className="size-5" />
          </Link>

          <Link
            to="/reseller-info"
            className="inline-flex h-13 items-center gap-2.5 rounded-full border-2 border-primary/30 bg-primary/5 px-7 text-base font-semibold text-primary hover:bg-primary/10 transition-all shadow-sm"
          >
            <Store className="size-5 text-primary" />
            <span>Become a Reseller / Partner</span>
          </Link>

          <a
            href="#code-showcase"
            className="inline-flex h-13 items-center gap-2.5 rounded-full border border-border bg-background/80 backdrop-blur-sm px-6 text-sm font-semibold text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
          >
            <Terminal className="size-4 text-muted-foreground" />
            <span>Code SDK Demo</span>
          </a>
        </div>

        {/* Supported Languages & Platforms Matrix */}
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
              <div
                key={i}
                className="flex items-center gap-2 rounded-full border border-border/80 bg-card/60 backdrop-blur-xs px-3.5 py-1.5 text-xs font-medium text-foreground shadow-2xs"
              >
                <span>{p.icon}</span>
                <span>{p.name}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
