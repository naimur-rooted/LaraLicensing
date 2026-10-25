import { Link } from "@tanstack/react-router";
import { Sparkles } from "lucide-react";

import { RuntimeModeSwitch } from "../runtime-mode-switch";

export function HeroSection() {
  return (
    <section className="relative overflow-hidden border-b border-border/40 bg-linear-to-b from-background via-background to-muted/20 py-20 sm:py-28">
      <div className="container relative mx-auto flex max-w-5xl flex-col items-center gap-6 px-4 text-center sm:px-6">
        <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary">
          <Sparkles className="size-3.5" />
          <span>Multi-tenant licensing architecture</span>
        </div>

        <h1
          className="text-balance text-4xl font-semibold tracking-tight text-foreground sm:text-5xl md:text-6xl"
          style={{ fontFamily: "var(--font-display)" }}
        >
          License issuance and verification, tuned for resellers and app builders.
        </h1>

        <p
          className="max-w-2xl text-pretty text-base text-muted-foreground"
          style={{ fontFamily: "var(--font-sans)" }}
        >
          Issue tiered licenses, bind serials to machines, verify offline with Ed25519 signatures,
          and audit every mutation. Split-DB tenancy keeps each reseller isolated by design.
        </p>

        <div className="flex flex-wrap items-center justify-center gap-3">
          <Link
            to="/register"
            search={{ plan: "STARTER" }}
            className="inline-flex h-11 items-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
          >
            Start Free Developer Trial
          </Link>
          <Link
            to="/admin/login"
            className="inline-flex h-11 items-center rounded-md border border-input bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
          >
            Operator Console
          </Link>
          <RuntimeModeSwitch />
        </div>
      </div>
    </section>
  );
}
