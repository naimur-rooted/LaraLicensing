import React from "react";
import { Link } from "@tanstack/react-router";
import { Shield } from "lucide-react";

export function LicenseSpringFooter() {
  return (
    <footer className="border-t border-border/60 bg-muted/20 py-12 sm:py-16">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-12">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8">
          {/* Brand Info */}
          <div className="col-span-2 space-y-4">
            <Link to="/" className="inline-flex items-center gap-2.5 font-bold text-lg text-foreground">
              <div className="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-black">
                <Shield className="size-4" />
              </div>
              <span>LaraLicensing</span>
            </Link>
            <p className="text-sm text-muted-foreground max-w-sm leading-relaxed">
              Autonomous software licensing infrastructure, machine-locked node authentication, offline Ed25519 cryptographic tokens, and multi-tenant wholesale distribution.
            </p>
          </div>

          {/* Column 1: Platform */}
          <div className="space-y-3">
            <h4 className="text-sm font-bold text-foreground uppercase tracking-wider">Platform</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Overview Dashboard</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Products & Deals</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Developer Studio</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Fraud Guard & Telemetry</Link></li>
              <li><Link to="/status" className="hover:text-foreground transition-colors">System Telemetry</Link></li>
            </ul>
          </div>

          {/* Column 2: Solutions & Partners */}
          <div className="space-y-3">
            <h4 className="text-sm font-bold text-foreground uppercase tracking-wider">Partners</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link to="/reseller-info" className="hover:text-foreground transition-colors font-medium text-foreground">Authorized Reseller Hub</Link></li>
              <li><Link to="/reseller-info" className="hover:text-foreground transition-colors">Apply as Partner</Link></li>
              <li><Link to="/login" className="hover:text-foreground transition-colors">Reseller Portal Sign In</Link></li>
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Node-Locked Devices</a></li>
              <li><a href="#licensing-models" className="hover:text-foreground transition-colors">Floating Machine Seats</a></li>
            </ul>
          </div>

          {/* Column 3: Developers */}
          <div className="space-y-3">
            <h4 className="text-sm font-bold text-foreground uppercase tracking-wider">Developers</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">Developer Console</Link></li>
              <li><Link to="/portal/home" className="hover:text-foreground transition-colors">AI Blueprint Synthesizer</Link></li>
              <li><a href="#code-showcase" className="hover:text-foreground transition-colors">Interactive Code SDKs</a></li>
              <li><Link to="/solutions/offline" className="hover:text-foreground transition-colors">Ed25519 Offline Tokens</Link></li>
              <li><Link to="/docs" className="hover:text-foreground transition-colors">API Documentation</Link></li>
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
