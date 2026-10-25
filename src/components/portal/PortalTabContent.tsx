import { SerialLookupPanel } from "./serial-lookup-panel";
import { CustomerRequestsHistory } from "./CustomerRequestsHistory";
import { DeveloperProductsDashboard } from "@/components/developer/DeveloperProductsDashboard";
import { DeveloperOverviewDashboard } from "@/components/developer/DeveloperOverviewDashboard";
import { DeveloperBillingTab } from "@/components/developer/DeveloperBillingTab";
import { FraudAlertCenter } from "@/components/security/FraudAlertCenter";
import type { PortalTabId } from "./portal-types";

interface PortalTabContentProps {
  readonly activeTab: PortalTabId;
  readonly currentPlanTier: string;
  readonly customerEmail: string;
  readonly selectedSerial: string;
  readonly onOpenPlanModal: () => void;
  readonly onSelectTab: (tab: PortalTabId) => void;
  readonly onTierChange: (tier: string) => void;
  readonly onApplySerial: (serial: string) => void;
}

export function PortalTabContent({
  activeTab,
  currentPlanTier,
  customerEmail,
  selectedSerial,
  onOpenPlanModal,
  onSelectTab,
  onTierChange,
  onApplySerial,
}: PortalTabContentProps) {
  if (activeTab === "overview") {
    return (
      <DeveloperOverviewDashboard
        onNavigateToProducts={() => onSelectTab("developer")}
        onNavigateToLicenses={() => onSelectTab("client")}
      />
    );
  }
  if (activeTab === "developer") {
    return <DeveloperProductsDashboard onOpenPlanModal={onOpenPlanModal} />;
  }
  if (activeTab === "billing") {
    return <DeveloperBillingTab currentTier={currentPlanTier} onTierChange={onTierChange} />;
  }
  if (activeTab === "security") return <FraudAlertCenter />;
  return (
    <div className="flex flex-col gap-6">
      <CustomerRequestsHistory customerEmail={customerEmail} onApplySerial={onApplySerial} />
      <SerialLookupPanel
        key={selectedSerial}
        initialSerial={selectedSerial}
        testIdPrefix="portal-home-serial"
      />
    </div>
  );
}
