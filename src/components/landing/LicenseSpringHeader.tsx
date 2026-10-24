import React, { useState, useRef, useEffect } from "react";
import { Link } from "@tanstack/react-router";
import {
  Shield,
  ChevronDown,
  Layers,
  ShieldCheck,
  PieChart,
  User,
  Settings,
  CreditCard,
  Gauge,
  Network,
  WifiOff,
  Smartphone,
  Users2,
  Key,
  FileCheck,
  PackageCheck,
  Cpu,
  Store,
  BadgeCheck,
  Lock,
  FileText,
  ExternalLink,
  Sparkles,
  Bot,
  Zap,
} from "lucide-react";
import { Button } from "@/components/ui/button";

export function LicenseSpringHeader() {
  const [isPlatformMenuOpen, setIsPlatformMenuOpen] = useState(false);
  const [isGetStartedOpen, setIsGetStartedOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  // Close dropdown on click outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsPlatformMenuOpen(false);
        setIsGetStartedOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  return (
    <header className="sticky top-0 z-50 w-full border-b border-border/60 bg-background/95 backdrop-blur-md">
      {/* Top Navigation Bar */}
      <div className="mx-auto flex h-18 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        {/* Brand Logo */}
        <Link to="/" className="flex items-center gap-3 transition-opacity hover:opacity-90">
          <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-tr from-sky-500 via-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/25">
            <Shield className="size-5.5" />
          </div>
          <span className="text-xl font-bold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
            lara<span className="text-primary font-semibold">licensing</span>
          </span>
        </Link>

        {/* Center Nav Links with LicenseSpring style dropdowns */}
        <nav className="hidden lg:flex items-center gap-2 text-sm font-semibold text-muted-foreground">
          {/* Platform Mega Menu Trigger */}
          <div className="relative" ref={menuRef}>
            <button
              type="button"
              onClick={() => {
                setIsPlatformMenuOpen(!isPlatformMenuOpen);
                setIsGetStartedOpen(false);
              }}
              onMouseEnter={() => setIsPlatformMenuOpen(true)}
              className={`flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-colors ${
                isPlatformMenuOpen ? "text-foreground bg-muted/60 font-semibold" : "hover:text-foreground hover:bg-muted/40"
              }`}
            >
              <span>Platform</span>
              <ChevronDown className={`size-4 transition-transform duration-200 ${isPlatformMenuOpen ? "rotate-180" : ""}`} />
            </button>

            {/* Mega Menu Dropdown Card matching LicenseSpring screenshot */}
            {isPlatformMenuOpen && (
              <div
                onMouseLeave={() => setIsPlatformMenuOpen(false)}
                className="absolute -left-20 top-full mt-2 w-[920px] rounded-3xl border border-border/80 bg-background p-6 shadow-2xl backdrop-blur-xl animate-in fade-in-50 zoom-in-95 duration-200 z-50"
              >
                <div className="grid grid-cols-12 gap-6">
                  {/* Column 1: Core Capabilities (col-span-3) */}
                  <div className="col-span-3 space-y-4">
                    <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                      <Sparkles className="size-3.5" />
                      <span>Core Capabilities</span>
                    </div>

                    <div className="space-y-1">
                      <Link
                        to="/portal/home"
                        className="group flex items-start gap-3 p-2 rounded-xl hover:bg-muted/60 transition-colors"
                      >
                        <div className="p-2 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 group-hover:scale-105 transition-transform">
                          <Layers className="size-4" />
                        </div>
                        <div>
                          <p className="text-xs font-semibold text-foreground group-hover:text-primary transition-colors">
                            Platform Overview
                          </p>
                          <p className="text-[10px] text-muted-foreground">LaraLicensing Architecture Tour</p>
                        </div>
                      </Link>

                      <Link
                        to="/portal/home"
                        className="group flex items-start gap-3 p-2 rounded-xl hover:bg-muted/60 transition-colors"
                      >
                        <div className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 group-hover:scale-105 transition-transform">
                          <ShieldCheck className="size-4" />
                        </div>
                        <div>
                          <p className="text-xs font-semibold text-foreground group-hover:text-primary transition-colors">
                            License Management
                          </p>
                          <p className="text-[10px] text-muted-foreground">Protect, Control & Monetize</p>
                        </div>
                      </Link>

                      <Link
                        to="/portal/home"
                        className="group flex items-start gap-3 p-2 rounded-xl hover:bg-muted/60 transition-colors"
                      >
                        <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 group-hover:scale-105 transition-transform">
                          <PieChart className="size-4" />
                        </div>
                        <div>
                          <p className="text-xs font-semibold text-foreground group-hover:text-primary transition-colors">
                            Usage Analytics
                          </p>
                          <p className="text-[10px] text-muted-foreground">Real-Time Seat Insights</p>
                        </div>
                      </Link>

                      <Link
                        to="/portal/home"
                        className="group flex items-start gap-3 p-2 rounded-xl hover:bg-muted/60 transition-colors"
                      >
                        <div className="p-2 rounded-lg bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400 group-hover:scale-105 transition-transform">
                          <User className="size-4" />
                        </div>
                        <div>
                          <p className="text-xs font-semibold text-foreground group-hover:text-primary transition-colors">
                            End-User Portals
                          </p>
                          <p className="text-[10px] text-muted-foreground">Self-Service Client Portal</p>
                        </div>
                      </Link>

                      <a
                        href="http://localhost:5050"
                        target="_blank"
                        rel="noreferrer"
                        className="group flex items-start gap-3 p-2 rounded-xl hover:bg-muted/60 transition-colors"
                      >
                        <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform">
                          <Settings className="size-4" />
                        </div>
                        <div>
                          <p className="text-xs font-semibold text-foreground group-hover:text-primary transition-colors flex items-center gap-1">
                            Storefront Sandbox <ExternalLink className="size-2.5 opacity-60" />
                          </p>
                          <p className="text-[10px] text-muted-foreground">Checkout & Webhook Minting</p>
                        </div>
                      </a>
                    </div>
                  </div>

                  {/* Column 2 & 3: Licensing Models (col-span-6, 2 sub-columns) */}
                  <div className="col-span-6 border-x border-border/50 px-4 space-y-4">
                    <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                      <Zap className="size-3.5" />
                      <span>Licensing Models</span>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                      <div className="space-y-1">
                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <CreditCard className="size-3.5 text-blue-500" />
                            <span className="text-xs font-semibold text-foreground">Subscriptions</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Monthly, Daily & Annual Tiers</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <Network className="size-3.5 text-indigo-500" />
                            <span className="text-xs font-semibold text-foreground">Floating & Seats</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Concurrent Device Pooling</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <Smartphone className="size-3.5 text-sky-500" />
                            <span className="text-xs font-semibold text-foreground">Node-Locked</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Machine GUID & MAC Hash</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <Key className="size-3.5 text-amber-500" />
                            <span className="text-xs font-semibold text-foreground">Perpetual (LTD)</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">One-time Lifetime Access</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <PackageCheck className="size-3.5 text-emerald-500" />
                            <span className="text-xs font-semibold text-foreground">Feature Gating</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Lock Modules & Perks</p>
                        </div>
                      </div>

                      <div className="space-y-1">
                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <Gauge className="size-3.5 text-teal-500" />
                            <span className="text-xs font-semibold text-foreground">Usage-Based</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Metered Volume Quotas</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <WifiOff className="size-3.5 text-rose-500" />
                            <span className="text-xs font-semibold text-foreground">Offline & Air-Gapped</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Ed25519 Signed JWTs</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <Users2 className="size-3.5 text-purple-500" />
                            <span className="text-xs font-semibold text-foreground">User-Based</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Assigned Developer Accounts</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <FileCheck className="size-3.5 text-blue-500" />
                            <span className="text-xs font-semibold text-foreground">Trial & Evaluation</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Time-Limited Free Tiers</p>
                        </div>

                        <div className="p-2 rounded-lg hover:bg-muted/60 transition-colors">
                          <div className="flex items-center gap-2">
                            <Bot className="size-3.5 text-amber-500" />
                            <span className="text-xs font-semibold text-foreground">AI Architect</span>
                          </div>
                          <p className="text-[10px] text-muted-foreground pl-5.5">Gemini Decoy Tier Design</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Column 4: Portals & Security (col-span-3) */}
                  <div className="col-span-3 space-y-4">
                    <div className="space-y-3">
                      <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                        <Store className="size-3.5" />
                        <span>Portals</span>
                      </div>

                      <div className="space-y-1">
                        <Link to="/portal/home" className="block p-1.5 rounded-lg hover:bg-muted/60">
                          <p className="text-xs font-semibold text-foreground">Vendor Platform</p>
                          <p className="text-[10px] text-muted-foreground">Manage products and pricing</p>
                        </Link>
                        <Link to="/portal/home" className="block p-1.5 rounded-lg hover:bg-muted/60">
                          <p className="text-xs font-semibold text-foreground">Reseller Hub</p>
                          <p className="text-[10px] text-muted-foreground">Empower wholesale distributors</p>
                        </Link>
                      </div>
                    </div>

                    <div className="space-y-3 border-t border-border/50 pt-3">
                      <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                        <Shield className="size-3.5" />
                        <span>Security & Shards</span>
                      </div>

                      <div className="space-y-1">
                        <div className="p-1.5">
                          <p className="text-xs font-semibold text-foreground">Split-DB Tenancy</p>
                          <p className="text-[10px] text-muted-foreground">Dedicated SQLite shard per product</p>
                        </div>
                        <div className="p-1.5">
                          <p className="text-xs font-semibold text-foreground">Ed25519 Cryptography</p>
                          <p className="text-[10px] text-muted-foreground">Tamper-proof offline tokens</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Footer link in mega menu */}
                <div className="mt-4 pt-3 border-t border-border/40 flex items-center justify-between text-[11px] text-muted-foreground">
                  <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                    <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
                    Production Ready • Zero Cardholder Data
                  </span>
                  <Link to="/portal/home" className="text-primary hover:underline flex items-center gap-1">
                    Platform Overview & Docs <ExternalLink className="size-2.5" />
                  </Link>
                </div>
              </div>
            )}
          </div>

          <Link to="/portal/home" className="px-3.5 py-2 rounded-lg hover:text-foreground hover:bg-muted/40 transition-colors">
            Developers & Docs
          </Link>
          <a href="#licensing-models" className="px-3.5 py-2 rounded-lg hover:text-foreground hover:bg-muted/40 transition-colors">
            Licensing Models
          </a>
          <a href="#comparison" className="px-3.5 py-2 rounded-lg hover:text-foreground hover:bg-muted/40 transition-colors">
            Why LaraLicensing
          </a>
        </nav>

        {/* Right Action Buttons */}
        <div className="flex items-center gap-3">
          <Link
            to="/admin/login"
            className="text-sm font-semibold text-muted-foreground hover:text-foreground px-3.5 py-2 rounded-lg transition-colors"
          >
            Login
          </Link>

          {/* Get Started Button matching LicenseSpring's dark pill */}
          <div className="relative">
            <Link
              to="/portal/home"
              className="inline-flex h-10 items-center gap-2 rounded-full bg-zinc-950 px-5 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 transition-colors dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-zinc-200"
            >
              <span>Get Started</span>
              <ChevronDown className="size-3.5" />
            </Link>
          </div>
        </div>
      </div>
    </header>
  );
}
