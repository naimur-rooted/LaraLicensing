import { useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/shell/PageHeader";
import { SerialLookupPanel } from "@/components/portal/serial-lookup-panel";
import { CustomerRequestsHistory } from "@/components/portal/CustomerRequestsHistory";
import { DeveloperProductsDashboard } from "@/components/developer/DeveloperProductsDashboard";
import { ResellerWholesaleStudio } from "@/components/reseller/ResellerWholesaleStudio";
import { FraudAlertCenter } from "@/components/security/FraudAlertCenter";
import { meQueryOptions } from "@/lib/lara-me";
import { clearLaraSession } from "@/lib/lara-api-session";
import { LogOut, LayoutGrid, KeyRound, Store, ShieldAlert } from "lucide-react";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/_authenticated/portal/home")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Portal | Licensing Portal" },
      {
        name: "description",
        content: "Developer Studio, Reseller Wholesale Hub, Fraud Guard, and Client License Verification.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: PortalHome,
});

function PortalHome() {
  const meQuery = useQuery(meQueryOptions());
  const me = meQuery.data?.[0];
  const displayName = me?.DisplayName ?? me?.Email ?? "there";
  const customerEmail = me?.Email ?? "user@licensingportal.local";
  const [selectedSerial, setSelectedSerial] = useState("");
  const [activeTab, setActiveTab] = useState<"developer" | "reseller" | "security" | "client">("developer");

  const handleSignOut = () => {
    clearLaraSession();
    window.location.href = "/admin/login";
  };

  return (
    <main
      className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-[clamp(1rem,0.75rem+1vw,1.5rem)] py-[clamp(1.25rem,1rem+1vw,2rem)]"
      data-page-region="portal-home"
    >
      <div className="flex items-center justify-between">
        <PageHeader
          title={`Hello, ${displayName}`}
          description="Enterprise multi-tenant licensing ecosystem: developer product studio, reseller wholesale hub, fraud guard, and client verification."
        />
        <Button
          variant="outline"
          size="sm"
          onClick={handleSignOut}
          className="shrink-0 text-muted-foreground hover:text-foreground"
        >
          <LogOut className="mr-1.5 size-3.5" />
          Sign out
        </Button>
      </div>

      {/* Surface Selector Navigation */}
      <div className="flex flex-wrap items-center gap-2 border-b border-border/80 pb-2">
        <Button
          variant={activeTab === "developer" ? "default" : "ghost"}
          size="sm"
          onClick={() => setActiveTab("developer")}
          className="gap-2 text-xs font-medium"
        >
          <LayoutGrid className="size-4" />
          Developer Studio (Tier 2)
        </Button>
        <Button
          variant={activeTab === "reseller" ? "default" : "ghost"}
          size="sm"
          onClick={() => setActiveTab("reseller")}
          className="gap-2 text-xs font-medium"
        >
          <Store className="size-4" />
          Reseller Wholesale Hub
        </Button>
        <Button
          variant={activeTab === "security" ? "default" : "ghost"}
          size="sm"
          onClick={() => setActiveTab("security")}
          className="gap-2 text-xs font-medium"
        >
          <ShieldAlert className="size-4" />
          Fraud Guard
        </Button>
        <Button
          variant={activeTab === "client" ? "default" : "ghost"}
          size="sm"
          onClick={() => setActiveTab("client")}
          className="gap-2 text-xs font-medium"
        >
          <KeyRound className="size-4" />
          Issued Licenses & Serial Lookup
        </Button>
      </div>

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
    </main>
  );
}
