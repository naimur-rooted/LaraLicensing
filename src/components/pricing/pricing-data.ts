export interface PricingTierItem {
  readonly name: string;
  readonly code: "STARTER" | "PRO" | "ENTERPRISE";
  readonly badge: string;
  readonly price: string;
  readonly period: string;
  readonly description: string;
  readonly features: readonly string[];
  readonly ctaText: string;
  readonly isPopular: boolean;
}

export function getPricingTiers(isAnnual: boolean): readonly PricingTierItem[] {
  return [
    {
      name: "Starter Developer",
      code: "STARTER",
      badge: "Free Forever",
      price: "$0",
      period: "forever",
      description: "Ideal for testing, indie hackers, and open-source projects evaluating LaraLicensing.",
      features: [
        "1 Sharded SQLite Product DB",
        "50 Active Workstation Activations",
        "Node-Locked & Perpetual Models",
        "Ed25519 Offline Verification",
        "Community Discord & GitHub Support",
        "REST API Access",
      ],
      ctaText: "Start Free",
      isPopular: false,
    },
    {
      name: "Pro Creator",
      code: "PRO",
      badge: "Most Popular",
      price: isAnnual ? "$39" : "$49",
      period: "/ month",
      description: "For growing SaaS, desktop tools, Chrome extensions, and WordPress plugin businesses.",
      features: [
        "5 Sharded SQLite Product DBs",
        "2,500 Active Workstation Activations",
        "All 6 Licensing Models (Floating, Metered, Passes)",
        "Stripe, LemonSqueezy & WooCommerce Webhooks",
        "Gemini AI Blueprint Code Synthesizer",
        "Fraud Guard & Device Telemetry Alerts",
        "Standard Email Support (24h SLA)",
      ],
      ctaText: "Get Pro Access",
      isPopular: true,
    },
    {
      name: "Enterprise & Reseller",
      code: "ENTERPRISE",
      badge: "Wholesale Ready",
      price: isAnnual ? "$159" : "$199",
      period: "/ month",
      description: "For high-volume software distributors, reseller channels, and mission-critical applications.",
      features: [
        "Unlimited Sharded SQLite DBs",
        "50,000+ Active Workstations",
        "Full Reseller Wholesale Studio Hub",
        "Branded Vanity Prefix Minting",
        "Append-Only Quota Ledger & Self-Service Approvals",
        "Hardware Seat Troubleshooter with Remote Release",
        "Dedicated Private Slack & 99.99% Uptime SLA",
      ],
      ctaText: "Deploy Enterprise",
      isPopular: false,
    },
  ];
}
