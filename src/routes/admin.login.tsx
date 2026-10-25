import { createFileRoute, Link } from "@tanstack/react-router";
import { KeyRound, ShieldCheck, Sparkles } from "lucide-react";

import { AuthCard, type AuthCardTrustPoint } from "../components/auth/AuthCard";
import { AdminLoginForm } from "../components/auth/AdminLoginForm";

export const Route = createFileRoute("/admin/login")({
  head: () => ({
    meta: [
      { title: "Operator Sign In | Licensing Portal" },
      {
        name: "description",
        content: "Sign in to the Licensing Portal operator console. Strictly reserved for platform operators.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminLoginPage,
});

const TRUST_POINTS: ReadonlyArray<AuthCardTrustPoint> = [
  {
    icon: ShieldCheck,
    title: "Session-scoped tokens",
    body: "Every sign-in opens a Root AuthSession row; logout revokes the bearer at the source.",
  },
  {
    icon: KeyRound,
    title: "Rate-limited + CAPTCHA",
    body: "Repeated failures trip a stateless HMAC challenge before any hint reaches the client.",
  },
  {
    icon: Sparkles,
    title: "Audit-logged actions",
    body: "Destructive changes carry lineage badges and land in the Admin AuditLogs viewer.",
  },
];

function AdminLoginPage() {
  return (
    <AuthCard
      title="Platform Operator Console"
      headerBadge="Operator Console"
      description="Internal Operator Console. Strictly reserved for platform administrators and operators."
      asideHeadline="Licensing, quotas, and self-updates. One console, every reseller."
      asideBody="Session-scoped tokens, rate-limited endpoints, and every destructive action lineage-tagged in the Admin AuditLogs viewer."
      asideTrustPoints={TRUST_POINTS}
      asideFooterNote={`© ${new Date().getFullYear()} Licensing Portal. All systems normal.`}
      footerSlot={
        <div className="flex flex-col gap-2.5 text-xs text-muted-foreground w-full">
          <div className="flex items-center justify-between">
            <span>Customer or Developer?</span>
            <Link to="/login" className="font-semibold text-primary hover:underline">
              Portal Sign In &rarr;
            </Link>
          </div>
          <div className="flex items-center justify-between">
            <span>Want to sell software?</span>
            <Link to="/register" className="font-semibold text-primary hover:underline">
              Create Developer Account &rarr;
            </Link>
          </div>
          <div className="border-t border-border/40 pt-2 text-[11px] text-center">
            Trouble signing in?{" "}
            <a
              href="mailto:admin@licensingportal.local"
              className="font-medium text-foreground underline underline-offset-4 hover:underline"
            >
              Contact workspace administrator
            </a>
          </div>
        </div>
      }
    >
      <AdminLoginForm />
    </AuthCard>
  );
}
