import { createFileRoute } from "@tanstack/react-router";
import { LicenseSpringHeader } from "@/components/landing/LicenseSpringHeader";
import { LicenseSpringHero } from "@/components/landing/LicenseSpringHero";
import { InteractiveCodeShowcase } from "@/components/landing/InteractiveCodeShowcase";
import { LiveDashboardShowcase } from "@/components/landing/LiveDashboardShowcase";
import { LicensingModelsGrid } from "@/components/landing/LicensingModelsGrid";
import { CompetitiveAdvantageSection } from "@/components/landing/CompetitiveAdvantageSection";
import { LicenseSpringFooter } from "@/components/landing/LicenseSpringFooter";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "LaraLicensing • Modern Multi-Tenant Software Licensing Engine" },
      {
        name: "description",
        content:
          "Developer-first software licensing engine: node-locking, machine seat quotas, offline Ed25519 signatures, split-DB tenancy, and AI blueprint synthesis.",
      },
      { property: "og:title", content: "LaraLicensing Software Licensing" },
      {
        property: "og:description",
        content:
          "Modern software licensing, node-locking, and offline verification engine for desktop, Chrome extensions, WordPress, and SaaS.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Index,
});

function Index() {
  return (
    <div className="min-h-screen bg-background text-foreground flex flex-col">
      {/* LicenseSpring-Style Navigation Header & Mega Menu */}
      <LicenseSpringHeader />

      <main className="flex-1">
        {/* Hero Section with Ambient Pastel Glow & Language Matrix */}
        <LicenseSpringHero />

        {/* Market-Ready in 3 Lines of Code (Interactive Tabs) */}
        <InteractiveCodeShowcase />

        {/* Live Overview Dashboard Showcase */}
        <LiveDashboardShowcase />

        {/* 6 Core Licensing Models Grid */}
        <LicensingModelsGrid />

        {/* Competitive Advantage Comparison Table */}
        <CompetitiveAdvantageSection />
      </main>

      {/* LicenseSpring-Style Footer */}
      <LicenseSpringFooter />
    </div>
  );
}
