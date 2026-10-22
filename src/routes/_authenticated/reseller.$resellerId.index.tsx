import { useSuspenseQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";

import { PageHeader } from "../../components/shell/PageHeader";
import { ResellerOverviewMetrics } from "../../components/reseller/ResellerOverviewMetrics";
import { ResellerQuotaGauge } from "../../components/reseller/ResellerQuotaGauge";
import { ResellerQuickActions } from "../../components/reseller/ResellerQuickActions";
import { meQueryOptions } from "../../lib/lara-me";

export const Route = createFileRoute("/_authenticated/reseller/$resellerId/")({
  ssr: false,
  parseParams: ({ resellerId }) => ({ resellerId: parseResellerId(resellerId) }),
  head: () => ({
    meta: [
      { title: "Reseller overview | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: ResellerHomePage,
});

function parseResellerId(raw: string): number {
  const parsed = Number(raw);
  const isValid = Number.isInteger(parsed) && parsed > 0;
  if (!isValid) {
    throw new Error(`Invalid resellerId: ${raw}`);
  }

  return parsed;
}

function ResellerHomePage() {
  const { resellerId } = Route.useParams();
  const { data } = useSuspenseQuery(meQueryOptions());
  const [me] = data;
  if (!me) {
    throw new Error("Users.Me returned an empty envelope; AC-API-USR-001");
  }

  const isResellerRole = me.RoleName === "Reseller";
  const isIdMismatch = isResellerRole && me.ResellerId !== resellerId;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reseller Overview"
        breadcrumbs={[
          { label: "Reseller", to: "/" },
          { label: `Reseller ${resellerId}`, identifier: true },
        ]}
        description="High-density telemetric dashboard for wholesale distribution, quotas, and devices."
      />
      {isIdMismatch ? (
        <ForbiddenGate callerResellerId={me.ResellerId ?? null} urlResellerId={resellerId} />
      ) : (
        <div className="space-y-6">
          <ResellerOverviewMetrics resellerId={resellerId} />
          <div className="grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-1">
              <ResellerQuotaGauge resellerId={resellerId} />
            </div>
            <div className="lg:col-span-2">
              <ResellerQuickActions resellerId={resellerId} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function ForbiddenGate(props: { callerResellerId: number | null; urlResellerId: number }) {
  return (
    <section
      role="alert"
      className="rounded-md border border-destructive/50 bg-destructive/5 p-5 text-sm"
    >
      <h2 className="text-base font-semibold text-destructive">Access denied</h2>
      <p className="mt-2 text-muted-foreground">
        Your account is scoped to reseller {props.callerResellerId ?? "(none)"}, but this page
        targets reseller {props.urlResellerId}.
      </p>
    </section>
  );
}
