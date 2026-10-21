import { useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { SerialLookupPanel } from "@/components/portal/serial-lookup-panel";
import { CustomerRequestsHistory } from "@/components/portal/CustomerRequestsHistory";
import { DeveloperProductsDashboard } from "@/components/developer/DeveloperProductsDashboard";
import { DeveloperOverviewDashboard } from "@/components/developer/DeveloperOverviewDashboard";
import { DeveloperSupportChatbot } from "@/components/developer/DeveloperSupportChatbot";
import { ResellerWholesaleStudio } from "@/components/reseller/ResellerWholesaleStudio";
import { FraudAlertCenter } from "@/components/security/FraudAlertCenter";
import { meQueryOptions } from "@/lib/lara-me";
import { clearLaraSession } from "@/lib/lara-api-session";
import {
  LogOut,
  LayoutGrid,
  KeyRound,
  Store,
  ShieldAlert,
  BarChart3,
  Shield,
  Menu,
  ChevronRight,
  User,
  PanelLeftClose,
  PanelLeft,
} from "lucide-react";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/_authenticated/portal/home")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Portal | Licensing Portal" },
      {
        name: "description",
        content: "Developer Studio, Overview Analytics, Reseller Wholesale Hub, Fraud Guard, and Client Verification.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: PortalHome,
});

function PortalHome() {
  const meQuery = useQuery(meQueryOptions());
  const me = meQuery.data?.[0];
  const displayName = me?.DisplayName ?? me?.Email ?? "Portal User";
  const customerEmail = me?.Email ?? "user@licensingportal.local";
  const [selectedSerial, setSelectedSerial] = useState("");
  const [activeTab, setActiveTab] = useState<"overview" | "developer" | "reseller" | "security" | "client">("overview");
  const [isSidebarOpen, setIsSidebarOpen] = useState(true);

  const handleSignOut = () => {
    clearLaraSession();
    window.location.href = "/admin/login";
  };

  const navItems = [
    { id: "overview", label: "Overview", icon: BarChart3, badge: null },
    { id: "developer", label: "Products & Deals", icon: LayoutGrid, badge: "Tier 2" },
    { id: "reseller", label: "Reseller Wholesale Hub", icon: Store, badge: null },
    { id: "security", label: "Fraud Guard", icon: ShieldAlert, badge: null },
    { id: "client", label: "Issued Licenses & Serials", icon: KeyRound, badge: null },
  ] as const;

  return (
    <div className="min-h-screen bg-background flex flex-col w-full text-foreground">
      {/* Top Universal Navbar */}
      <header className="sticky top-0 z-40 w-full border-b bg-card/80 backdrop-blur-md px-4 lg:px-6 h-14 flex items-center justify-between shadow-2xs">
        <div className="flex items-center gap-3">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setIsSidebarOpen(!isSidebarOpen)}
            className="size-8 text-muted-foreground hover:text-foreground"
            title={isSidebarOpen ? "Collapse Sidebar" : "Expand Sidebar"}
          >
            {isSidebarOpen ? <PanelLeftClose className="size-4" /> : <PanelLeft className="size-4" />}
          </Button>

          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 border border-primary/20 text-primary flex items-center justify-center font-bold text-sm shadow-2xs">
              <Shield className="size-4 text-primary" />
            </div>
            <span className="font-bold tracking-tight text-sm text-foreground hidden sm:inline-block">
              LaraLicensing <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-muted text-muted-foreground ml-1">Portal</span>
            </span>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <div className="hidden sm:flex items-center gap-2 text-xs text-muted-foreground">
            <User className="size-3.5" />
            <span>Hello, <strong className="text-foreground">{displayName}</strong></span>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={handleSignOut}
            className="h-8 text-xs text-muted-foreground hover:text-foreground gap-1.5 shadow-2xs"
          >
            <LogOut className="size-3.5" />
            <span className="hidden sm:inline">Sign out</span>
          </Button>
        </div>
      </header>

      {/* Main SaaS Two-Column Layout matching Cryptlex */}
      <div className="flex-1 flex w-full">
        {/* Left Sidebar Navigation */}
        <aside
          className={`shrink-0 border-r bg-card/40 transition-all duration-300 flex flex-col justify-between select-none ${
            isSidebarOpen ? "w-64 p-3" : "w-0 p-0 overflow-hidden border-none"
          }`}
        >
          <div className="space-y-1">
            <div className="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/70">
              Workspace
            </div>

            <nav className="space-y-1">
              {navItems.map((item) => {
                const Icon = item.icon;
                const isActive = activeTab === item.id;
                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => setActiveTab(item.id)}
                    className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                      isActive
                        ? "bg-primary text-primary-foreground shadow-2xs font-semibold"
                        : "text-muted-foreground hover:bg-muted/60 hover:text-foreground"
                    }`}
                  >
                    <div className="flex items-center gap-2.5 truncate">
                      <Icon className="size-4 shrink-0" />
                      <span className="truncate">{item.label}</span>
                    </div>
                    {item.badge && (
                      <span
                        className={`text-[9px] px-1.5 py-0.2 rounded font-mono ${
                          isActive
                            ? "bg-primary-foreground/20 text-primary-foreground"
                            : "bg-muted text-muted-foreground"
                        }`}
                      >
                        {item.badge}
                      </span>
                    )}
                  </button>
                );
              })}
            </nav>
          </div>

          {/* Sidebar Footer info */}
          <div className="p-3 border-t bg-muted/20 rounded-lg text-[11px] text-muted-foreground space-y-1">
            <div className="font-semibold text-foreground flex items-center gap-1.5">
              <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>Multi-Tenant Sharding</span>
            </div>
            <p className="text-[10px] text-muted-foreground/80 leading-relaxed">
              Every product is isolated in its own dedicated SQLite shard.
            </p>
          </div>
        </aside>

        {/* Right Main Fluid Canvas - Wide, Edge-to-Edge */}
        <main
          className="flex-1 min-w-0 w-full px-4 sm:px-6 lg:px-8 py-6 overflow-y-auto"
          data-page-region="portal-home"
        >
          <div className="w-full space-y-6">
            {activeTab === "overview" && (
              <DeveloperOverviewDashboard
                onNavigateToProducts={() => setActiveTab("developer")}
                onNavigateToLicenses={() => setActiveTab("client")}
              />
            )}
            {activeTab === "developer" && <DeveloperProductsDashboard />}
            {activeTab === "reseller" && <ResellerWholesaleStudio />}
            {activeTab === "security" && <FraudAlertCenter />}
            {activeTab === "client" && (
              <div className="flex flex-col gap-6">
                <CustomerRequestsHistory
                  customerEmail={customerEmail}
                  onApplySerial={(serial) => setSelectedSerial(serial)}
                />

                <SerialLookupPanel
                  key={selectedSerial}
                  initialSerial={selectedSerial}
                  testIdPrefix="portal-home-serial"
                />
              </div>
            )}
          </div>
        </main>
      </div>

      {/* Floating Global AI Chatbot matching Cryptlex / Intercom */}
      <DeveloperSupportChatbot userName={displayName} />
    </div>
  );
}
