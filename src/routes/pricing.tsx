import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";
import { PricingHeader } from "@/components/pricing/PricingHeader";
import { PricingCard } from "@/components/pricing/PricingCard";
import { PricingRoiCalculator } from "@/components/pricing/PricingRoiCalculator";
import { getPricingTiers } from "@/components/pricing/pricing-data";

export const Route = createFileRoute("/pricing")({
  head: () => ({
    meta: [
      { title: "Pricing & Plans • LaraLicensing" },
      {
        name: "description",
        content:
          "Predictable pricing for software licensing. Split-DB SQLite sharding, Ed25519 offline verification, and turnkey webhook minting.",
      },
    ],
  }),
  component: PricingPage,
});

function PricingPage() {
  const [isAnnual, setIsAnnual] = useState(true);
  const tiers = getPricingTiers(isAnnual);

  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      <LicenseSpringHeader />

      <main className="flex-1 py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
          <PricingHeader
            isAnnual={isAnnual}
            onToggleAnnual={() => setIsAnnual(!isAnnual)}
          />

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
            {tiers.map((tier) => (
              <PricingCard key={tier.code} tier={tier} />
            ))}
          </div>

          <PricingRoiCalculator />
        </div>
      </main>

      <LicenseSpringFooter />
    </div>
  );
}
