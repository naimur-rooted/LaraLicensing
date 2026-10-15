import { createFileRoute } from "@tanstack/react-router";
import { z } from "zod";

import { LicenseCreateWizard } from "../../components/admin/license-create-wizard";
import { PageHeader } from "../../components/shell/PageHeader";

const newLicenseSearchSchema = z.object({
  resellerId: z.coerce.number().optional(),
});

export const Route = createFileRoute("/_authenticated/admin/licenses/new")({
  ssr: false,
  validateSearch: (search) => newLicenseSearchSchema.parse(search),
  head: () => ({
    meta: [
      { title: "Issue license | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: IssueLicensePage,
});

function IssueLicensePage() {
  const { resellerId } = Route.useSearch();

  return (
    <>
      <PageHeader
        title="Issue license"
        breadcrumbs={[{ label: "Admin", to: "/admin" }, { label: "Issue license" }]}
        description="5-step wizard: select reseller, tier, features, environment, then confirm."
      />
      <LicenseCreateWizard defaultResellerId={resellerId ? String(resellerId) : undefined} />
    </>
  );
}
