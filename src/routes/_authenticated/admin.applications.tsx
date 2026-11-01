import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { UserCheck } from "lucide-react";

import { PageHeader } from "../../components/shell/PageHeader";
import { ResellerApplicationsInbox } from "../../components/admin/reseller-applications-inbox";
import { RoutePending, RouteErrorState } from "../../components/shell/RouteFallbacks";
import { resellerApplicationsQueryOptions } from "../../lib/lara-reseller-application";

export const Route = createFileRoute("/_authenticated/admin/applications")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Partner Applications | Admin" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  pendingComponent: () => (
    <RoutePending
      title="Partner Applications"
      description="Review and onboard pending reseller partner applications."
    />
  ),
  errorComponent: ({ error, reset }) => (
    <RouteErrorState title="Partner Applications" error={error} reset={reset} />
  ),
  component: ApplicationsPage,
});

function ApplicationsPage() {
  const { data = [], isLoading, error, refetch } = useQuery(resellerApplicationsQueryOptions);

  return (
    <>
      <PageHeader
        title="Partner Applications"
        breadcrumbs={[{ label: "Admin", to: "/admin" }, { label: "Applications" }]}
        description={`Inbound reseller partner inquiries from the landing page. ${data.length} total applications on record.`}
      />
      <div className="mt-4">
        <ResellerApplicationsInbox />
        {data.length === 0 && !isLoading && (
          <div className="rounded-lg border border-dashed p-12 text-center text-muted-foreground">
            <UserCheck className="mx-auto size-8 opacity-40 mb-2" />
            <p className="font-medium text-sm">No partner applications yet</p>
            <p className="text-xs">Inquiries submitted from the landing page will appear here immediately.</p>
          </div>
        )}
      </div>
    </>
  );
}
