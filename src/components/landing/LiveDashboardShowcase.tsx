import React from "react";
import { Link } from "@tanstack/react-router";
import { Key, Users, Layers, ShieldCheck, ArrowRight, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";

export function LiveDashboardShowcase() {
  return (
    <section className="py-24 border-b border-border/60 bg-background">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto space-y-4 mb-14">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
            <span>Visual Management Console</span>
          </div>
          <h2 className="text-4xl sm:text-5xl font-extrabold tracking-tight text-foreground leading-[1.12]" style={{ fontFamily: "var(--font-display)" }}>
            Easily manage your licenses and activations
          </h2>
          <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
            Monitor your software business from an enterprise-grade control room. Track real-time machine activations, active seat quotas, trial conversions, and geographic telemetry across isolated SQLite shards.
          </p>
        </div>

        {/* Mockup Frame of the Cryptlex-Style Dashboard */}
        <div className="rounded-2xl border border-border/80 bg-card/60 shadow-2xl p-5 sm:p-7 backdrop-blur-xs relative overflow-hidden">
          {/* Top Bar Mock */}
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-4 border-b gap-3">
            <div className="flex items-center gap-2.5">
              <span className="text-sm font-mono text-muted-foreground">Home / Overview</span>
              <span className="size-1.5 rounded-full bg-muted-foreground/40" />
              <span className="text-sm font-semibold text-foreground">Live Telemetry</span>
            </div>
            <div className="flex items-center gap-3 text-sm">
              <span className="px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-600 font-mono font-medium flex items-center gap-1.5">
                <span className="size-2 rounded-full bg-emerald-500 animate-pulse" /> Production Ready
              </span>
              <Link to="/portal/home" className="text-primary hover:underline font-semibold flex items-center gap-1">
                Open Portal <ArrowRight className="size-3.5" />
              </Link>
            </div>
          </div>

          {/* 5 KPI Metric Cards Mock */}
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3.5 my-6">
            <div className="p-4 rounded-xl border bg-background/80 shadow-xs space-y-1.5">
              <div className="text-2xl font-bold text-foreground font-mono">
                1 <span className="text-muted-foreground/60 text-sm font-normal">/ 50</span>
              </div>
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Activations</p>
            </div>
            <div className="p-4 rounded-xl border bg-background/80 shadow-xs space-y-1.5">
              <div className="text-2xl font-bold text-foreground font-mono">
                0 <span className="text-muted-foreground/60 text-sm font-normal">/ 200</span>
              </div>
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Trial Activations</p>
            </div>
            <div className="p-4 rounded-xl border bg-background/80 shadow-xs space-y-1.5">
              <div className="text-2xl font-bold text-foreground font-mono">1</div>
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Licenses Issued</p>
            </div>
            <div className="p-4 rounded-xl border bg-background/80 shadow-xs space-y-1.5">
              <div className="text-2xl font-bold text-foreground font-mono">
                1 <span className="text-muted-foreground/60 text-sm font-normal">/ 50</span>
              </div>
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Active Workstations</p>
            </div>
            <div className="p-4 rounded-xl border bg-background/80 shadow-xs space-y-1.5 col-span-2 sm:col-span-1">
              <div className="text-2xl font-bold text-foreground font-mono">
                3 <span className="text-muted-foreground/60 text-sm font-normal">/ 50</span>
              </div>
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sharded Products</p>
            </div>
          </div>

          {/* Area Chart Silhouette Mock */}
          <div className="rounded-xl border bg-background/90 p-5 space-y-3">
            <div className="flex items-center justify-between text-sm">
              <span className="font-bold text-foreground">Activations (Last 30 Days)</span>
              <div className="flex items-center gap-4 text-xs">
                <span className="flex items-center gap-1.5 text-blue-500 font-semibold">
                  <span className="size-2.5 rounded-full bg-blue-500" /> Activations
                </span>
                <span className="flex items-center gap-1.5 text-rose-500 font-semibold">
                  <span className="size-2.5 rounded-full bg-rose-500" /> Trials
                </span>
              </div>
            </div>

            {/* Visual SVG curve */}
            <div className="h-32 w-full flex items-end">
              <svg className="w-full h-full overflow-visible" viewBox="0 0 500 100" preserveAspectRatio="none">
                <path
                  d="M0,90 Q120,88 200,85 T320,80 T400,70 Q450,50 500,20 L500,100 L0,100 Z"
                  fill="url(#mockGrad)"
                  opacity="0.3"
                />
                <path
                  d="M0,90 Q120,88 200,85 T320,80 T400,70 Q450,50 500,20"
                  fill="none"
                  stroke="#3B82F6"
                  strokeWidth="2.5"
                />
                <defs>
                  <linearGradient id="mockGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#3B82F6" stopOpacity="0.8" />
                    <stop offset="100%" stopColor="#3B82F6" stopOpacity="0.0" />
                  </linearGradient>
                </defs>
              </svg>
            </div>
          </div>

          <div className="mt-6 text-center">
            <Link
              to="/portal/home"
              className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
            >
              Experience the full interactive dashboard live in your browser <ArrowRight className="size-4" />
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
