import { useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { PlatformSubscriptionModal } from "@/components/developer/PlatformSubscriptionModal";
import { DeveloperSupportChatbot } from "@/components/developer/DeveloperSupportChatbot";
import { PortalHeader } from "@/components/portal/PortalHeader";
import { PortalSidebar } from "@/components/portal/PortalSidebar";
import { PortalTabContent } from "@/components/portal/PortalTabContent";
import type { PortalTabId } from "@/components/portal/portal-types";
import { meQueryOptions } from "@/lib/lara-me";
import { clearLaraSession } from "@/lib/lara-api-session";
import { RuntimeModeFlipButton } from "@/components/shell/RuntimeModeFlipButton";

export const Route = createFileRoute("/_authenticated/portal/home")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Portal | Licensing Portal" },
      {
        name: "description",
        content: "Developer Studio, Overview Analytics, Global Billing, Fraud Guard, and Client Verification.",
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
  const [activeTab, setActiveTab] = useState<PortalTabId>("overview");
  const [isSidebarOpen, setIsSidebarOpen] = useState(true);
  const [currentPlanTier, setCurrentPlanTier] = useState("Pro Developer");
  const [isGlobalPlanModalOpen, setIsGlobalPlanModalOpen] = useState(false);

  const handleSignOut = () => {
    clearLaraSession();
    window.location.href = "/login";
  };

  const handleTierSelect = (tierCode: string) => {
    const tierMap: Record<string, string> = {
      MAX: "Max Enterprise",
      PRO: "Pro Developer",
      ESSENTIAL: "Essential",
    };
    setCurrentPlanTier(tierMap[tierCode] ?? "Starter / Free");
  };

  return (
    <div className="min-h-screen bg-background flex flex-col w-full text-foreground">
      <PortalHeader
        isSidebarOpen={isSidebarOpen}
        onToggleSidebar={() => setIsSidebarOpen(!isSidebarOpen)}
        currentPlanTier={currentPlanTier}
        onOpenPlanModal={() => setIsGlobalPlanModalOpen(true)}
        displayName={displayName}
        onSignOut={handleSignOut}
      />

      <div className="flex-1 flex w-full">
        <PortalSidebar
          isSidebarOpen={isSidebarOpen}
          activeTab={activeTab}
          onSelectTab={(tab) => setActiveTab(tab)}
        />
        <main className="flex-1 min-w-0 w-full px-4 sm:px-6 lg:px-8 py-6 overflow-y-auto" data-page-region="portal-home">
          <PortalTabContent
            activeTab={activeTab}
            currentPlanTier={currentPlanTier}
            customerEmail={customerEmail}
            selectedSerial={selectedSerial}
            onOpenPlanModal={() => setIsGlobalPlanModalOpen(true)}
            onSelectTab={(tab) => setActiveTab(tab)}
            onTierChange={(tier) => setCurrentPlanTier(tier)}
            onApplySerial={(serial) => setSelectedSerial(serial)}
          />
        </main>
      </div>

      <DeveloperSupportChatbot userName={displayName} />
      <RuntimeModeFlipButton />
      <PlatformSubscriptionModal
        isOpen={isGlobalPlanModalOpen}
        onOpenChange={setIsGlobalPlanModalOpen}
        currentTier={currentPlanTier}
        onTierSelect={handleTierSelect}
      />
    </div>
  );
}
