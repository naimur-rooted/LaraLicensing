import React from "react";
import { Link } from "@tanstack/react-router";
import { Key, Users, Layers, ShieldCheck, ArrowRight, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";

export function LiveDashboardShowcase() {
  return (
    <section className="py-20 border-b border-border/60 bg-background">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto space-y-3 mb-12">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold tracking-wide uppercase">
            <span>Visual Management Console</span>
          </div>
          <h2 className="text-3xl font-bold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
            Easily manage your licenses and activations
          </h2>
          <p className="text-sm text-muted-foreground leading-relaxed">
            Monitor your software business from an enterprise-grade control room. Track real-time machine activations, active seat quotas, trial conversions, and geographic telemetry across isolated SQLite shards.
          </p>
        </div>

        {/* Mockup Frame of the Cryptlex-Style Dashboard */}
        <div className="rounded-2xl border border-border/80 bg-card/60 shadow-2xl p-4 sm:p-6 backdrop-blur-xs relative overflow-hidden">
          {/* Top Bar Mock */}
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-4 border-b gap-3">
            <div className="flex items-center gap-2">
              <span className="text-xs font-mono text-muted-foreground">Home / Overview</span>
              <span className="size-1 rounded-full bg-muted-foreground/40" />
              <span className="text-xs font-semibold text-foreground">Live Telemetry</span>
            </div>
            <div className="flex items-center gap-2 text-xs">
              <span className="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 font-mono font-medium flex items-center gap-1">
                <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" /> Production Ready
              </span>
              <Link to="/portal/home" className="text-primary hover:underline font-medium flex items-center gap-1">
                Open Portal <ArrowRight className="size-3" />
              </Link>
            </div>
          </div>

          {/* 5 KPI Metric Cards Mock */}
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 my-5">
            <div className="p-3.5 rounded-xl border bg-background/80 shadow-2xs space-y-1">
              <div className="text-xl font-bold text-foreground font-mono">
                1 <span className="text-muted-foreground/60 text-sm font-normal">/ 50</span>
              </div>
              <p className="text-[11px] font-medium text-muted-foreground">Activations</p>
            </div>
            <div className="p-3.5 rounded-xl border bg-background/80 shadow-2xs space-y-1">
              <div className="text-xl font-bold text-foreground font-mono">
                0 <span className="text-muted-foreground/60 text-sm font-normal">/ 200</span>
              </div>
              <p className="text-[11px] font-medium text-muted-foreground">Trial Activations</p>
            </div>
            <div className="p-3.5 rounded-xl border bg-background/80 shadow-2xs space-y-1">
              <div className="text-xl font-bold text-foreground font-mono">1</div>
              <p className="text-[11px] font-medium text-muted-foreground">Licenses Issued</p>
            </div>
            <div className="p-3.5 rounded-xl border bg-background/80 shadow-2xs space-y-1">
              <div className="text-xl font-bold text-foreground font-mono">
                1 <span className="text-muted-foreground/60 text-sm font-normal">/ 50</span>
              </div>
              <p className="text-[11px] font-medium text-muted-foreground">Active Workstations</p>
            </div>
            <div className="p-3.5 rounded-xl border bg-background/80 shadow-2xs space-y-1 col-span-2 sm:col-span-1">
              <div className="text-xl font-bold text-foreground font-mono">
                3 <span className="text-muted-foreground/60 text-sm font-normal">/ 50</span>
              </div>
              <p className="text-[11px] font-medium text-muted-foreground">Sharded Products</p>
            </div>
          </div>

          {/* Area Chart Silhouette Mock */}
          <div className="rounded-xl border bg-background/90 p-4 space-y-3">
            <div className="flex items-center justify-between text-xs">
              <span className="font-semibold text-foreground">Activations (Last 30 Days)</span>
              <div className="flex items-center gap-4 text-[11px]">
                <span className="flex items-center gap-1.5 text-blue-500 font-medium">
                  <span className="size-2 rounded-full bg-blue-500" /> Activations
                </span>
                <span className="flex items-center gap-1.5 text-rose-500 font-medium">
                  <span className="size-2 rounded-full bg-rose-500" /> Trials
                </span>
              </div>
            </div>

            {/* Visual SVG curve */}
            <div className="h-28 w-full flex items-end">
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

          <div className="mt-5 text-center">
            <Link
              to="/portal/home"
              className="inline-flex items-center gap-2 text-xs font-semibold text-primary hover:underline"
            >
              Experience the full interactive dashboard live in your browser <ArrowRight className="size-3.5" />
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
