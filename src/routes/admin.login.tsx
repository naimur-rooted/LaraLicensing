import { createFileRoute, Link } from "@tanstack/react-router";
import { KeyRound, ShieldCheck, Sparkles, ShieldAlert } from "lucide-react";

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
      headerBadge="Platform Operator Console (Internal Only)"
      description="Internal Operator Console. Strictly reserved for platform administrators and operators."
      asideHeadline="Licensing, quotas, and self-updates. One console, every reseller."
      asideBody="Session-scoped tokens, rate-limited endpoints, and every destructive action lineage-tagged in the Admin AuditLogs viewer."
      asideTrustPoints={TRUST_POINTS}
      asideFooterNote={`© ${new Date().getFullYear()} Licensing Portal. All systems normal.`}
      footerSlot={
        <div className="flex flex-col gap-2.5 text-xs text-muted-foreground w-full">
          <p className="text-center text-xs text-muted-foreground">
            Looking for Developer Portal?{" "}
            <Link to="/register" className="font-semibold text-primary hover:underline">
              Create an account
            </Link>{" "}
            or{" "}
            <Link to="/login" className="font-semibold text-primary hover:underline">
              Sign In
            </Link>
          </p>
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
      <div className="mb-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-semibold">
        <ShieldAlert className="size-3.5" />
        <span>Platform Operator Console (Internal Only)</span>
      </div>
      <AdminLoginForm />
    </AuthCard>
  );
}
