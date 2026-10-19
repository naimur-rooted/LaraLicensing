import { useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";

import { useState } from "react";
import { PageHeader } from "@/components/shell/PageHeader";
import { SerialLookupPanel } from "@/components/portal/serial-lookup-panel";
import { CustomerRequestForm } from "@/components/portal/CustomerRequestForm";
import { CustomerRequestsHistory } from "@/components/portal/CustomerRequestsHistory";
import { meQueryOptions } from "@/lib/lara-me";

/**
 * End-user portal home per Plan 09 step 49 and step 50.
 *
 * Renders the reusable `<SerialLookupPanel>` (step 50) so the same widget can
 * be embedded in future reseller/admin surfaces without copy-paste. The panel
 * owns the three-state result rendering and the recent-lookups persistence;
 * this route supplies the greeting + description + license request capability.
 */
export const Route = createFileRoute("/_authenticated/portal/home")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Portal | Licensing Portal" },
      {
        name: "description",
        content: "Verify a license serial and view its authorization status.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: PortalHome,
});

import { clearLaraSession } from "@/lib/lara-api-session";
import { LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";

function PortalHome() {
  const meQuery = useQuery(meQueryOptions());
  const me = meQuery.data?.[0];
  const displayName = me?.DisplayName ?? me?.Email ?? "there";
  const customerEmail = me?.Email ?? "user@licensingportal.local";
  const customerName = me?.DisplayName ?? "Valued Customer";
  const [selectedSerial, setSelectedSerial] = useState("");

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
      className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-[clamp(1rem,0.75rem+1vw,1.5rem)] py-[clamp(1.25rem,1rem+1vw,2rem)]"
      data-page-region="portal-home"
    >
      <div className="flex items-center justify-between">
        <PageHeader
          title={`Hello, ${displayName}`}
          description="Verify a license serial or request a new license directly from your authorized reseller."
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
    </main>
  );
}
