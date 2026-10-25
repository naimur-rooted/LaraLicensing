import React, { useState } from "react";
import { BillingPlanOverviewCard } from "./BillingPlanOverviewCard";
import { BillingUsageMeters } from "./BillingUsageMeters";
import { BillingInvoicesCard } from "./BillingInvoicesCard";
import { PlatformSubscriptionModal } from "./PlatformSubscriptionModal";

interface DeveloperBillingTabProps {
  currentTier?: string;
  onTierChange?: (tier: string) => void;
}

export function DeveloperBillingTab({
  currentTier = "Pro Developer",
  onTierChange,
}: DeveloperBillingTabProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [activeTier, setActiveTier] = useState(currentTier);

  const handleTierSelect = (tierCode: string) => {
    const tierName =
      tierCode === "MAX"
        ? "Max Enterprise"
        : tierCode === "PRO"
        ? "Pro Developer"
        : tierCode === "ESSENTIAL"
        ? "Essential"
        : "Starter / Free";
    setActiveTier(tierName);
    onTierChange?.(tierName);
  };

  return (
    <div className="space-y-6 w-full">
      <div>
        <h2 className="text-xl font-bold tracking-tight text-foreground" style={{ fontFamily: "var(--font-display)" }}>
          Billing & Platform Subscription
        </h2>
        <p className="text-xs text-muted-foreground mt-0.5">
          Manage your software licensing infrastructure tier, quota allowances, and payment methods.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <BillingPlanOverviewCard
          currentTier={activeTier}
          onOpenUpgradeModal={() => setIsModalOpen(true)}
        />
        <BillingUsageMeters currentTier={activeTier} />
      </div>

      <BillingInvoicesCard />

      <PlatformSubscriptionModal
        isOpen={isModalOpen}
        onOpenChange={setIsModalOpen}
        currentTier={activeTier}
        onTierSelect={handleTierSelect}
      />
    </div>
  );
}
