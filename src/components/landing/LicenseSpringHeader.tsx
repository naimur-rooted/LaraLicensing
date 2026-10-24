import React, { useState, useRef, useEffect } from "react";
import { Link } from "@tanstack/react-router";
import {
  Shield,
  ChevronDown,
  Sparkles,
  Layers,
  ShieldCheck,
  CreditCard,
  Gauge,
  Network,
  WifiOff,
  Smartphone,
  Key,
  PackageCheck,
  Store,
  Lock,
  FileText,
  ExternalLink,
  Laptop,
  Chrome,
  Globe,
  Terminal,
  Activity,
  Code2,
  BookOpen,
} from "lucide-react";

type MenuKey = "platform" | "solutions" | "developers" | "resources" | null;

export function LicenseSpringHeader() {
  const [activeMenu, setActiveMenu] = useState<MenuKey>(null);
  const navRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (navRef.current && !navRef.current.contains(event.target as Node)) {
        setActiveMenu(null);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const closeMenu = () => setActiveMenu(null);

  return (
    <header className="sticky top-0 z-50 w-full border-b border-border/60 bg-background/95 backdrop-blur-md">
      <div className="mx-auto flex h-18 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" ref={navRef}>
        {/* Brand Logo */}
        <Link to="/" onClick={closeMenu} className="flex items-center gap-3 transition-opacity hover:opacity-90">
          <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-tr from-sky-500 via-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/25">
            <Shield className="size-5.5" />
          </div>
          <span className="text-xl font-bold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
            lara<span className="text-primary font-semibold">licensing</span>
          </span>
        </Link>

        {/* Center Nav Links with LicenseSpring style dropdowns */}
        <nav className="hidden lg:flex items-center gap-1.5 text-sm font-semibold text-muted-foreground">
          {/* 1. Platform Mega Menu */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setActiveMenu(activeMenu === "platform" ? null : "platform")}
              onMouseEnter={() => setActiveMenu("platform")}
              className={`flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-colors ${
                activeMenu === "platform" ? "text-foreground bg-muted/60 font-semibold" : "hover:text-foreground hover:bg-muted/40"
              }`}
            >
              <span>Platform</span>
              <ChevronDown className={`size-4 transition-transform duration-200 ${activeMenu === "platform" ? "rotate-180" : ""}`} />
            </button>

            {activeMenu === "platform" && (
              <div
                onMouseLeave={closeMenu}
                className="absolute -left-20 top-full mt-2 w-[860px] rounded-3xl border border-border/80 bg-background p-6 shadow-2xl backdrop-blur-xl animate-in fade-in-50 zoom-in-95 duration-200 z-50"
              >
                <div className="grid grid-cols-12 gap-6">
                  {/* Column 1: Core Capabilities */}
                  <div className="col-span-3 space-y-3">
                    <div className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary">
                      <Sparkles className="size-3.5" />
                      <span>Capabilities</span>
                    </div>
                    <div className="space-y-1">
                      <Link to="/" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                        <p className="text-xs font-semibold text-foreground">Platform Overview</p>
                        <p className="text-[11px] text-muted-foreground">Architecture tour</p>
                      </Link>
                      <Link to="/licensing-models" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                        <p className="text-xs font-semibold text-foreground">License Management</p>
                        <p className="text-[11px] text-muted-foreground">Protect & monetize</p>
                      </Link>
                      <Link to="/security" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                        <p className="text-xs font-semibold text-foreground">Security & Shards</p>
                        <p className="text-[11px] text-muted-foreground">Split-DB isolation</p>
                      </Link>
                    </div>
                  </div>

                  {/* Column 2 & 3: Licensing Models */}
                  <div className="col-span-6 space-y-3 border-x border-border/50 px-4">
                    <div className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary">
                      <Layers className="size-3.5" />
                      <span>Licensing Models</span>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                      <Link to="/licensing-models" onClick={closeMenu} className="p-2 rounded-xl hover:bg-muted/60 flex items-center gap-2">
                        <Smartphone className="size-4 text-sky-500 shrink-0" />
                        <div>
                          <p className="text-xs font-semibold text-foreground">Node-Locked</p>
                          <p className="text-[10px] text-muted-foreground">Machine GUID lock</p>
                        </div>
                      </Link>
                      <Link to="/licensing-models" onClick={closeMenu} className="p-2 rounded-xl hover:bg-muted/60 flex items-center gap-2">
                        <Network className="size-4 text-indigo-500 shrink-0" />
                        <div>
                          <p className="text-xs font-semibold text-foreground">Floating Seats</p>
                          <p className="text-[10px] text-muted-foreground">Concurrent pooling</p>
                        </div>
                      </Link>
                      <Link to="/licensing-models" onClick={closeMenu} className="p-2 rounded-xl hover:bg-muted/60 flex items-center gap-2">
                        <Key className="size-4 text-amber-500 shrink-0" />
                        <div>
                          <p className="text-xs font-semibold text-foreground">Perpetual (LTD)</p>
                          <p className="text-[10px] text-muted-foreground">Lifetime access</p>
                        </div>
                      </Link>
                      <Link to="/solutions/offline" onClick={closeMenu} className="p-2 rounded-xl hover:bg-muted/60 flex items-center gap-2">
                        <WifiOff className="size-4 text-rose-500 shrink-0" />
                        <div>
                          <p className="text-xs font-semibold text-foreground">Offline Tokens</p>
                          <p className="text-[10px] text-muted-foreground">Ed25519 JWT</p>
                        </div>
                      </Link>
                    </div>
                  </div>

                  {/* Column 4: Portals */}
                  <div className="col-span-3 space-y-3">
                    <div className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary">
                      <Store className="size-3.5" />
                      <span>Portals</span>
                    </div>
                    <div className="space-y-1">
                      <Link to="/portal/home" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                        <div className="flex items-center gap-1.5">
                          <p className="text-xs font-semibold text-foreground">Vendor Platform</p>
                          <Lock className="size-3 text-muted-foreground" />
                        </div>
                        <p className="text-[11px] text-muted-foreground">Manage products & keys</p>
                      </Link>
                      <Link to="/reseller-info" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                        <p className="text-xs font-semibold text-foreground">Reseller Hub</p>
                        <p className="text-[11px] text-muted-foreground">Wholesale distribution</p>
                      </Link>
                    </div>
                  </div>
                </div>

                <div className="mt-4 pt-3 border-t border-border/40 flex items-center justify-between text-xs text-muted-foreground">
                  <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                    <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
                    Split-DB Sharding • Ed25519 Tokens
                  </span>
                  <Link to="/status" onClick={closeMenu} className="text-primary hover:underline flex items-center gap-1">
                    System Telemetry <Activity className="size-3" />
                  </Link>
                </div>
              </div>
            )}
          </div>

          {/* 2. Solutions Dropdown */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setActiveMenu(activeMenu === "solutions" ? null : "solutions")}
              onMouseEnter={() => setActiveMenu("solutions")}
              className={`flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-colors ${
                activeMenu === "solutions" ? "text-foreground bg-muted/60 font-semibold" : "hover:text-foreground hover:bg-muted/40"
              }`}
            >
              <span>Solutions</span>
              <ChevronDown className={`size-4 transition-transform duration-200 ${activeMenu === "solutions" ? "rotate-180" : ""}`} />
            </button>

            {activeMenu === "solutions" && (
              <div
                onMouseLeave={closeMenu}
                className="absolute -left-10 top-full mt-2 w-[540px] rounded-3xl border border-border/80 bg-background p-6 shadow-2xl backdrop-blur-xl animate-in fade-in-50 zoom-in-95 duration-200 z-50"
              >
                <div className="space-y-3">
                  <div className="text-xs font-bold uppercase tracking-wider text-primary">
                    <span>By Software Architecture</span>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <Link to="/solutions/desktop" onClick={closeMenu} className="p-3 rounded-xl hover:bg-muted/60 flex items-start gap-3">
                      <Laptop className="size-5 text-sky-500 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-semibold text-foreground">Desktop Software</p>
                        <p className="text-[11px] text-muted-foreground">C#, Python, Electron, Tauri</p>
                      </div>
                    </Link>

                    <Link to="/solutions/chrome-extensions" onClick={closeMenu} className="p-3 rounded-xl hover:bg-muted/60 flex items-start gap-3">
                      <Chrome className="size-5 text-amber-500 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-semibold text-foreground">Chrome Extensions</p>
                        <p className="text-[11px] text-muted-foreground">MV3 Profile UUID binding</p>
                      </div>
                    </Link>

                    <Link to="/solutions/wordpress" onClick={closeMenu} className="p-3 rounded-xl hover:bg-muted/60 flex items-start gap-3">
                      <Globe className="size-5 text-blue-500 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-semibold text-foreground">WordPress Plugins</p>
                        <p className="text-[11px] text-muted-foreground">0% revenue cut alternative</p>
                      </div>
                    </Link>

                    <Link to="/solutions/offline" onClick={closeMenu} className="p-3 rounded-xl hover:bg-muted/60 flex items-start gap-3">
                      <WifiOff className="size-5 text-rose-500 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-semibold text-foreground">Air-Gapped & Offline</p>
                        <p className="text-[11px] text-muted-foreground">Ed25519 token signatures</p>
                      </div>
                    </Link>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* 3. Developers Dropdown */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setActiveMenu(activeMenu === "developers" ? null : "developers")}
              onMouseEnter={() => setActiveMenu("developers")}
              className={`flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-colors ${
                activeMenu === "developers" ? "text-foreground bg-muted/60 font-semibold" : "hover:text-foreground hover:bg-muted/40"
              }`}
            >
              <span>Developers</span>
              <ChevronDown className={`size-4 transition-transform duration-200 ${activeMenu === "developers" ? "rotate-180" : ""}`} />
            </button>

            {activeMenu === "developers" && (
              <div
                onMouseLeave={closeMenu}
                className="absolute -left-10 top-full mt-2 w-[520px] rounded-3xl border border-border/80 bg-background p-6 shadow-2xl backdrop-blur-xl animate-in fade-in-50 zoom-in-95 duration-200 z-50"
              >
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <p className="text-xs font-bold uppercase tracking-wider text-primary">Documentation</p>
                    <Link to="/docs" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                      <p className="text-xs font-semibold text-foreground">Quickstart Guide</p>
                      <p className="text-[11px] text-muted-foreground">Get started in 3 minutes</p>
                    </Link>
                    <Link to="/docs" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                      <p className="text-xs font-semibold text-foreground">REST API Reference</p>
                      <p className="text-[11px] text-muted-foreground">Verify & activate endpoints</p>
                    </Link>
                  </div>

                  <div className="space-y-2">
                    <p className="text-xs font-bold uppercase tracking-wider text-primary">Integrations</p>
                    <Link to="/docs" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                      <p className="text-xs font-semibold text-foreground">Webhook Ingestion</p>
                      <p className="text-[11px] text-muted-foreground">Stripe & LemonSqueezy</p>
                    </Link>
                    <Link to="/status" onClick={closeMenu} className="block p-2 rounded-xl hover:bg-muted/60">
                      <p className="text-xs font-semibold text-foreground">API Status & Health</p>
                      <p className="text-[11px] text-muted-foreground">Live latency metrics</p>
                    </Link>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* 4. Top-level links */}
          <Link to="/pricing" onClick={closeMenu} className="px-3.5 py-2 rounded-lg hover:text-foreground hover:bg-muted/40 transition-colors">
            Pricing
          </Link>
          <Link to="/licensing-models" onClick={closeMenu} className="px-3.5 py-2 rounded-lg hover:text-foreground hover:bg-muted/40 transition-colors">
            Models
          </Link>
        </nav>

        {/* Right Action Buttons */}
        <div className="flex items-center gap-3">
          <Link
            to="/admin/login"
            className="text-sm font-semibold text-muted-foreground hover:text-foreground px-3.5 py-2 rounded-lg transition-colors"
          >
            Login
          </Link>

          <Link
            to="/pricing"
            className="inline-flex h-10 items-center gap-2 rounded-full bg-zinc-950 px-5 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 transition-colors dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-zinc-200"
          >
            <span>Get Started</span>
            <ChevronDown className="size-3.5" />
          </Link>
        </div>
      </div>
    </header>
  );
}
