import { createFileRoute, Link, useSearch } from "@tanstack/react-router";
import { KeyRound, ShieldCheck, Sparkles } from "lucide-react";

import { AuthCard, type AuthCardTrustPoint } from "@/components/auth/AuthCard";
import { CustomerLoginForm } from "@/components/auth/CustomerLoginForm";
import { RuntimeModeFlipButton } from "@/components/shell/RuntimeModeFlipButton";

export const Route = createFileRoute("/login")({
  validateSearch: (search: Record<string, unknown>): { redirect?: string } => ({
    redirect: typeof search.redirect === "string" ? search.redirect : undefined,
  }),
  head: () => ({
    meta: [
      { title: "Sign In • Licensing Portal" },
      {
        name: "description",
        content: "Sign in to your customer and developer licensing account.",
      },
    ],
  }),
  component: CustomerLoginPage,
});

const CUSTOMER_TRUST_POINTS: ReadonlyArray<AuthCardTrustPoint> = [
  {
    icon: ShieldCheck,
    title: "Ed25519 Cryptographic Signatures",
    body: "Offline validation tokens signed with asymmetric public keys.",
  },
  {
    icon: KeyRound,
    title: "Hardware Node-Locking",
    body: "Secure machine fingerprinting and floating concurrency guards.",
  },
  {
    icon: Sparkles,
    title: "Instant Turnkey Provisioning",
    body: "Automated license issuance synced with your checkout webhooks.",
  },
];

function CustomerLoginPage() {
  const search = useSearch({ from: "/login" });

  return (
    <>
      <AuthCard
        title="Sign in to your account"
        description="Access your licenses, developer workspace, and software downloads."
        headerBadge="Customer & Developer Sign In"
        asideHeadline="Headless, offline-first licensing infrastructure."
        asideBody="Manage products, cryptographic machine seats, and automated webhook checkout minting in one place."
        asideTrustPoints={CUSTOMER_TRUST_POINTS}
        asideFooterNote="Session-scoped tokens, rate-limited and audit-logged."
        footerSlot={
          <div className="flex items-center justify-between text-xs text-muted-foreground w-full">
            <span>Don't have an account?</span>
            <Link
              to="/register"
              search={{ plan: "STARTER" }}
              className="font-semibold text-primary hover:underline"
            >
              Create Developer Account &rarr;
            </Link>
          </div>
        }
      >
        <CustomerLoginForm redirectTo={search.redirect} />
      </AuthCard>
      <RuntimeModeFlipButton />
    </>
  );
}
