import { createFileRoute } from "@tanstack/react-router";

import { PageHeader } from "../../components/shell/PageHeader";
import { RoutePending, RouteErrorState } from "../../components/shell/RouteFallbacks";
import { EmptyState } from "../../components/ui/empty-state";
import { useApi } from "../../hooks/use-api";
import type { FeatureDefinition } from "@/generated/api/schema";

export const Route = createFileRoute("/_authenticated/admin/features")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Features Catalog | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  pendingComponent: () => (
    <RoutePending title="Features Catalog" description="Loading features catalog..." />
  ),
  errorComponent: ({ error, reset }) => (
    <RouteErrorState title="Features Catalog" error={error} reset={reset} />
  ),
  component: FeaturesPage,
});

function FeaturesPage() {
  const query = useApi("admin.features.list", {}, { staleTime: 15_000 });
  const rows = query.data?.Items ?? [];

  if (query.isLoading) {
    return <RoutePending title="Features Catalog" description="Loading features catalog..." />;
  }

  if (query.isError) {
    return <RouteErrorState title="Features Catalog" error={query.error} reset={query.refetch} />;
  }

  return (
    <>
      <PageHeader
        title="Features Catalog"
        breadcrumbs={[{ label: "Admin", to: "/admin" }, { label: "Features" }]}
        description={`Standard feature catalog and entitlements. ${rows.length} features available.`}
      />
      {rows.length === 0 ? (
        <EmptyState
          preset="box"
          headline="No features found"
          body="The feature catalog is currently empty."
        />
      ) : (
        <FeaturesTable rows={rows} />
      )}
    </>
  );
}

function FeaturesTable({ rows }: { rows: FeatureDefinition[] }) {
  return (
    <div className="mt-6 overflow-x-auto rounded-md border border-border">
      <table className="min-w-full text-sm">
        <thead className="bg-muted/40 text-left text-xs uppercase text-muted-foreground">
          <tr>
            <th className="px-3 py-2 font-medium">Code</th>
            <th className="px-3 py-2 font-medium">Name</th>
            <th className="px-3 py-2 font-medium">Category</th>
            <th className="px-3 py-2 font-medium">Value Type</th>
            <th className="px-3 py-2 font-medium">Billable</th>
            <th className="px-3 py-2 font-medium">Description</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {rows.map((row) => (
            <FeatureRow key={row.Code} row={row} />
          ))}
        </tbody>
      </table>
    </div>
  );
}

function FeatureRow({ row }: { row: FeatureDefinition }) {
  return (
    <tr className="align-top">
      <td className="whitespace-nowrap px-3 py-2 font-mono text-xs font-semibold">{row.Code}</td>
      <td className="whitespace-nowrap px-3 py-2 font-medium">{row.DisplayName}</td>
      <td className="whitespace-nowrap px-3 py-2 text-xs">
        <span className="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground font-medium">
          {row.Category}
        </span>
      </td>
      <td className="whitespace-nowrap px-3 py-2 font-mono text-xs text-muted-foreground">
        {row.ValueType ?? "boolean"}
      </td>
      <td className="whitespace-nowrap px-3 py-2 text-xs">
        {row.IsBillable ? (
          <span className="text-primary font-medium">Yes</span>
        ) : (
          <span className="text-muted-foreground">No</span>
        )}
      </td>
      <td className="px-3 py-2 text-xs text-muted-foreground max-w-xs">{row.Description}</td>
    </tr>
  );
}
