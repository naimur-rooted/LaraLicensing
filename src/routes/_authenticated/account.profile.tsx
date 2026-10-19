import { useSuspenseQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { Building2, CheckCircle2, Key, Mail, Shield, User } from "lucide-react";

import { AdminShell } from "../../components/admin/admin-shell";
import { ResellerShell } from "../../components/reseller/reseller-shell";
import { PageHeader } from "../../components/shell/PageHeader";
import { meQueryOptions, type MeResource } from "../../lib/lara-me";

export const Route = createFileRoute("/_authenticated/account/profile")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "My Profile | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AccountProfilePage,
});

function AccountProfilePage() {
  const { data: meRows } = useSuspenseQuery(meQueryOptions());
  const me = meRows[0];
  if (!me) {
    throw new Error("Unable to load profile data.");
  }

  const isReseller = me.RoleName === "Reseller";
  const content = <ProfileDetails me={me} />;

  if (isReseller) {
    return (
      <ResellerShell resellerId={String(me.ResellerId ?? 1)}>
        {content}
      </ResellerShell>
    );
  }

  return <AdminShell>{content}</AdminShell>;
}

function ProfileDetails({ me }: { me: MeResource }) {
  const isReseller = me.RoleName === "Reseller";
  const displayName = me.DisplayName || me.Email.split("@")[0];

  return (
    <div className="space-y-6">
      <PageHeader
        title="My Profile"
        breadcrumbs={[
          {
            label: isReseller ? "Reseller" : "Admin",
            to: isReseller ? `/reseller/${me.ResellerId ?? 1}/licenses` : "/admin",
          },
          { label: "Profile" },
        ]}
        description="View your active credentials, assigned permissions, and workspace tenant identity."
      />

      <div className="grid gap-6 md:grid-cols-2">
        {/* Account Identity Card */}
        <div className="rounded-xl border border-border bg-card p-6 shadow-sm space-y-4">
          <div className="flex items-center gap-3">
            <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
              <User className="size-6" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-foreground">
                {displayName}
              </h2>
              <p className="text-xs text-muted-foreground">User ID #{me.UserId}</p>
            </div>
          </div>

          <div className="divide-y divide-border pt-2 text-sm">
            <div className="flex items-center justify-between py-2.5">
              <span className="flex items-center gap-2 text-muted-foreground">
                <Mail className="size-4" /> Email Address
              </span>
              <span className="font-medium text-foreground">{me.Email}</span>
            </div>

            <div className="flex items-center justify-between py-2.5">
              <span className="flex items-center gap-2 text-muted-foreground">
                <Shield className="size-4" /> Role
              </span>
              <span className="inline-flex items-center rounded-md border border-primary/20 bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                {me.RoleName}
              </span>
            </div>

            <div className="flex items-center justify-between py-2.5">
              <span className="flex items-center gap-2 text-muted-foreground">
                <CheckCircle2 className="size-4" /> Account Status
              </span>
              <span className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                <span className="size-1.5 rounded-full bg-emerald-500" /> Active
              </span>
            </div>
          </div>
        </div>

        {/* Tenant / Organization Card */}
        <div className="rounded-xl border border-border bg-card p-6 shadow-sm space-y-4">
          <div className="flex items-center gap-3">
            <div className="flex size-12 items-center justify-center rounded-full bg-accent/20 text-accent-foreground">
              <Building2 className="size-6" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-foreground">
                {isReseller ? "Reseller Shard Scope" : "Central System Organization"}
              </h2>
              <p className="text-xs text-muted-foreground">
                {isReseller ? `Dedicated tenant shard #${me.ResellerId ?? 1}` : "Global administrator scope"}
              </p>
            </div>
          </div>

          <div className="divide-y divide-border pt-2 text-sm">
            {isReseller ? (
              <>
                <div className="flex items-center justify-between py-2.5">
                  <span className="text-muted-foreground">Assigned Shard ID</span>
                  <span className="font-mono text-sm font-semibold">{me.ResellerId ?? 1}</span>
                </div>
                <div className="flex items-center justify-between py-2.5">
                  <span className="text-muted-foreground">Row-Level Security</span>
                  <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    Enforced (Isolated)
                  </span>
                </div>
              </>
            ) : (
              <>
                <div className="flex items-center justify-between py-2.5">
                  <span className="text-muted-foreground">Administrative Authority</span>
                  <span className="text-xs font-semibold uppercase tracking-wider text-primary">
                    Full Platform Access
                  </span>
                </div>
                <div className="flex items-center justify-between py-2.5">
                  <span className="text-muted-foreground">Audit Logging</span>
                  <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    Enabled (All actions logged)
                  </span>
                </div>
              </>
            )}

            <div className="flex items-center justify-between py-2.5">
              <span className="flex items-center gap-2 text-muted-foreground">
                <Key className="size-4" /> Session Type
              </span>
              <span className="text-xs font-mono text-muted-foreground">Bearer Token</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
