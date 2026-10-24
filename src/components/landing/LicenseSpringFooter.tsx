import React from "react";
import { Link } from "@tanstack/react-router";
import { Shield, ExternalLink, Heart } from "lucide-react";

export function LicenseSpringFooter() {
  return (
    <footer className="border-t border-border/60 bg-muted/20 py-16">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8 mb-12">
          {/* Brand Info */}
          <div className="col-span-2 space-y-3.5">
            <Link to="/" className="flex items-center gap-2.5">
              <div className="flex size-8 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-xs font-bold text-sm">
                <Shield className="size-4.5" />
              </div>
              <span className="text-lg font-bold text-foreground" style={{ fontFamily: "var(--font-display)" }}>
                lara<span className="text-primary font-semibold">licensing</span>
              </span>
            </Link>
            <p className="text-sm text-muted-foreground leading-relaxed max-w-sm">
              Modern multi-tenant software licensing, node-locking, and offline verification engine. Powered by isolated SQLite shards and Ed25519 signatures.
            </p>
            <p className="text-xs text-muted-foreground font-mono">
              Zero cardholder data stored • Decoupled payment webhooks
            </p>
          </div>

          {/* Column 1: Platform */}
          <div className="space-y-3">
            <h4 className="text-sm font-bold text-foreground uppercase tracking-wider">Platform</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Overview Dashboard</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Products & Deals</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Reseller Wholesale Hub</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Fraud Guard & Telemetry</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Webhook Integrations</Link></li>
            </ul>
          </div>

          {/* Column 2: Licensing Models */}
          <div className="space-y-3">
            <h4 className="text-sm font-bold text-foreground uppercase tracking-wider">Models</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Node-Locked Devices</a></li>
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Floating Machine Seats</a></li>
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Daily & Monthly Passes</a></li>
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Lifetime LTD Deals</a></li>
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Offline Air-Gapped Tokens</a></li>
            </ul>
          </div>

          {/* Column 3: Developers */}
          <div className="space-y-3">
            <h4 className="text-sm font-bold text-foreground uppercase tracking-wider">Developers</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link to="/admin/login" className="hover:text-foreground transition-colors">Admin Console</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">AI Integration Blueprint</Link></li>
              <li><a href="#code-showcase" className="hover:text-foreground transition-colors">Interactive Code SDKs</a></li>
              <li><span className="hover:text-foreground transition-colors cursor-pointer">Ed25519 Public Keys</span></li>
              <li><span className="hover:text-foreground transition-colors cursor-pointer">Platform Changelog</span></li>
            </ul>
          </div>
        </div>

        <div className="pt-8 border-t border-border/40 flex flex-col sm:flex-row items-center justify-between text-xs sm:text-sm text-muted-foreground gap-3">
          <p>© {new Date().getFullYear()} LaraLicensing Platform. All rights reserved.</p>
          <div className="flex items-center gap-5">
            <Link to="/portal/home" className="hover:underline">Privacy Policy</Link>
            <Link to="/portal/home" className="hover:underline">Terms of Service</Link>
            <Link to="/portal/home" className="hover:underline">Security Architecture</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
