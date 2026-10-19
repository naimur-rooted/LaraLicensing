import { useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/shell/PageHeader";
import { SerialLookupPanel } from "@/components/portal/serial-lookup-panel";
import { CustomerRequestForm } from "@/components/portal/CustomerRequestForm";
import { CustomerRequestsHistory } from "@/components/portal/CustomerRequestsHistory";
import { DeveloperProductsDashboard } from "@/components/developer/DeveloperProductsDashboard";
import { meQueryOptions } from "@/lib/lara-me";
import { clearLaraSession } from "@/lib/lara-api-session";
import { LogOut, LayoutGrid, KeyRound } from "lucide-react";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/_authenticated/portal/home")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Portal | Licensing Portal" },
      {
        name: "description",
        content: "Developer Product Studio and Client License Verification.",
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
  const customerName = me?.DisplayName ?? "Valued Customer";
  const [selectedSerial, setSelectedSerial] = useState("");
  const [activeTab, setActiveTab] = useState<"developer" | "client">("developer");

  const handleSignOut = () => {
    clearLaraSession();
    window.location.href = "/admin/login";
  };

  const [isFormOpen, setIsFormOpen] = useState(false);
  const [formNotes, setFormNotes] = useState("");

  const handleRenew = (request: { IssuedLicenseKey?: string | null; ResellerName: string }) => {
    setFormNotes(`Renewal for ${request.IssuedLicenseKey ?? "existing license"}`);
    setIsFormOpen(true);
  };

  return (
    <main
      className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-[clamp(1rem,0.75rem+1vw,1.5rem)] py-[clamp(1.25rem,1rem+1vw,2rem)]"
      data-page-region="portal-home"
    >
      <div className="flex items-center justify-between">
        <PageHeader
          title={`Hello, ${displayName}`}
          description="Manage software products, configure deal tiers & machine limits, or verify license serials."
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
      <div className="flex items-center gap-2 border-b border-border/80 pb-2">
        <Button
          variant={activeTab === "developer" ? "default" : "ghost"}
          size="sm"
          onClick={() => setActiveTab("developer")}
          className="gap-2 text-xs font-medium"
        >
          <LayoutGrid className="size-4" />
          Developer Product Studio (Tier 2)
        </Button>
        <Button
          variant={activeTab === "client" ? "default" : "ghost"}
          size="sm"
          onClick={() => setActiveTab("client")}
          className="gap-2 text-xs font-medium"
        >
          <KeyRound className="size-4" />
          Client License Portal (Tier 3)
        </Button>
      </div>

      {activeTab === "developer" ? (
        <DeveloperProductsDashboard />
      ) : (
        <div className="flex flex-col gap-6">
          <CustomerRequestForm
            customerEmail={customerEmail}
            customerName={customerName}
            isOpen={isFormOpen}
            onOpenChange={setIsFormOpen}
            initialNotes={formNotes}
          />

          <CustomerRequestsHistory
            customerEmail={customerEmail}
            onApplySerial={(serial) => setSelectedSerial(serial)}
            onRenew={handleRenew}
          />

          <SerialLookupPanel
            key={selectedSerial}
            initialSerial={selectedSerial}
            testIdPrefix="portal-home-serial"
            onRequestRenewal={(serial) => {
              setFormNotes(`Renewal for ${serial}`);
              setIsFormOpen(true);
            }}
          />
        </div>
      )}
    </main>
  );
}
