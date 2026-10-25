import { createFileRoute, Link, useNavigate, useSearch } from "@tanstack/react-router";
import { useState } from "react";
import { ShieldCheck, Database, KeyRound, AlertCircle } from "lucide-react";
import { AuthCard, type AuthCardTrustPoint } from "@/components/auth/AuthCard";
import { DeveloperRegisterForm } from "@/components/auth/DeveloperRegisterForm";
import { RuntimeModeFlipButton } from "@/components/shell/RuntimeModeFlipButton";

export const Route = createFileRoute("/register")({
  validateSearch: (search: Record<string, unknown>) => ({
    plan: typeof search.plan === "string" ? search.plan : "STARTER",
  }),
  head: () => ({
    meta: [
      { title: "Developer Sign Up • LaraLicensing" },
      {
        name: "description",
        content: "Create a software licensing developer account. Dedicated SQLite shards, Ed25519 offline tokens, and webhook checkout minting.",
      },
    ],
  }),
  component: DeveloperRegisterPage,
});

const TRUST_POINTS: ReadonlyArray<AuthCardTrustPoint> = [
  {
    icon: Database,
    title: "Split SQLite Sharding",
    body: "Every product is completely isolated in its own dedicated SQLite shard database.",
  },
  {
    icon: KeyRound,
    title: "Ed25519 Offline Validation",
    body: "Issue cryptographically signed offline activation tokens with zero roundtrips.",
  },
  {
    icon: ShieldCheck,
    title: "Decoupled Webhook Minting",
    body: "Turnkey Stripe, LemonSqueezy, and WooCommerce checkout auto-issuance.",
  },
];

function DeveloperRegisterPage() {
  const navigate = useNavigate();
  const search = useSearch({ from: "/register" });
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  return (
    <>
      <AuthCard
      title="Create Developer Account"
      description="Select your platform subscription tier and bootstrap your product licensing workspace."
      headerBadge="Developer Portal"
      asideHeadline="Ship software licenses like a Fortune 500 company."
      asideBody="Empowering desktop tool creators, Chrome extension builders, and SaaS vendors with enterprise node-locking and decoy pricing."
      asideTrustPoints={TRUST_POINTS}
      asideFooterNote="Rate-limited. AES-256 encrypted credentials. Audit-logged."
      footerSlot={
        <div className="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted-foreground w-full">
          <span>Already have an account?</span>
          <Link
            to="/login"
            className="font-semibold text-primary hover:underline"
          >
            Sign In &rarr;
          </Link>
        </div>
      }
    >
      <div className="space-y-4">
        {errorMessage && (
          <div className="p-3 rounded-lg border border-destructive/30 bg-destructive/10 text-destructive text-xs flex items-start gap-2">
            <AlertCircle className="size-4 shrink-0 mt-0.5" />
            <div className="flex-1">
              <p>{errorMessage}</p>
              {errorMessage.includes("already exists") && (
                <Link to="/login" className="font-semibold underline mt-1 block">
                  Click here to sign in
                </Link>
              )}
            </div>
          </div>
        )}
        <DeveloperRegisterForm
          initialPlan={search.plan}
          onSuccess={() => navigate({ to: "/portal/home", replace: true })}
          onError={(msg) => setErrorMessage(msg)}
        />
      </div>
    </AuthCard>
    <RuntimeModeFlipButton />
  </>
  );
}
