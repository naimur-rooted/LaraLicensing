import React from "react";
import { Link } from "@tanstack/react-router";
import { Shield, ExternalLink, Heart } from "lucide-react";

export function LicenseSpringFooter() {
  return (
    <footer className="border-t border-border/60 bg-muted/20 py-16">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8 mb-12">
          {/* Brand Info */}
          <div className="col-span-2 space-y-3">
            <Link to="/" className="flex items-center gap-2">
              <div className="flex size-7 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-2xs font-bold text-xs">
                <Shield className="size-4" />
              </div>
              <span className="text-base font-bold text-foreground" style={{ fontFamily: "var(--font-display)" }}>
                lara<span className="text-primary">licensing</span>
              </span>
            </Link>
            <p className="text-xs text-muted-foreground leading-relaxed max-w-xs">
              Modern multi-tenant software licensing, node-locking, and offline verification engine. Powered by isolated SQLite shards and Ed25519 signatures.
            </p>
            <p className="text-[11px] text-muted-foreground font-mono">
              Zero cardholder data stored • Decoupled payment webhooks
            </p>
          </div>

          {/* Column 1: Platform */}
          <div className="space-y-2.5">
            <h4 className="text-xs font-semibold text-foreground uppercase tracking-wider">Platform</h4>
            <ul className="space-y-1.5 text-xs text-muted-foreground">
              <li><Link to="/portal/home" className="hover:text-foreground">Overview Dashboard</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground">Products & Deals</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground">Reseller Hub</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground">Fraud Guard</Link></li>
              <li><a href="http://localhost:5050" target="_blank" rel="noreferrer" className="hover:text-foreground flex items-center gap-1">Storefront Sandbox <ExternalLink className="size-2.5" /></a></li>
            </ul>
          </div>

          {/* Column 2: Licensing Models */}
          <div className="space-y-2.5">
            <h4 className="text-xs font-semibold text-foreground uppercase tracking-wider">Models</h4>
            <ul className="space-y-1.5 text-xs text-muted-foreground">
              <li><span className="hover:text-foreground cursor-pointer">Node-Locked Devices</span></li>
              <li><span className="hover:text-foreground cursor-pointer">Floating Machine Seats</span></li>
              <li><span className="hover:text-foreground cursor-pointer">Daily & Monthly Passes</span></li>
              <li><span className="hover:text-foreground cursor-pointer">Lifetime LTD Deals</span></li>
              <li><span className="hover:text-foreground cursor-pointer">Offline Air-Gapped Tokens</span></li>
            </ul>
          </div>

          {/* Column 3: Developers */}
          <div className="space-y-2.5">
            <h4 className="text-xs font-semibold text-foreground uppercase tracking-wider">Developers</h4>
            <ul className="space-y-1.5 text-xs text-muted-foreground">
              <li><Link to="/admin/login" className="hover:text-foreground">Admin Console</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground">AI Integration Blueprint</Link></li>
              <li><span className="hover:text-foreground cursor-pointer">REST API Spec</span></li>
              <li><span className="hover:text-foreground cursor-pointer">Ed25519 Public Keys</span></li>
              <li><span className="hover:text-foreground cursor-pointer">Platform Changelog</span></li>
            </ul>
          </div>
        </div>

        <div className="pt-8 border-t border-border/40 flex flex-col sm:flex-row items-center justify-between text-xs text-muted-foreground gap-3">
          <p>© {new Date().getFullYear()} LaraLicensing Platform. All rights reserved.</p>
          <div className="flex items-center gap-4">
            <Link to="/portal/home" className="hover:underline">Privacy Policy</Link>
            <Link to="/portal/home" className="hover:underline">Terms of Service</Link>
            <Link to="/portal/home" className="hover:underline">Security Architecture</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
