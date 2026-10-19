import { createFileRoute, redirect, useParams } from "@tanstack/react-router";

import { ResellerShell } from "../../components/reseller/reseller-shell";
import { meQueryOptions } from "../../lib/lara-me";

export const Route = createFileRoute("/_authenticated/reseller/$resellerId")({
  beforeLoad: async ({ params, context }) => {
    if (typeof window === "undefined") return;
    try {
      const meRows = await context.queryClient.ensureQueryData(meQueryOptions());
      const me = meRows?.[0];
      if (me) {
        if (me.RoleName === "EndUser") {
          throw redirect({ to: "/portal/home" });
        }
        if (me.RoleName === "Reseller" && me.ResellerId) {
          const targetId = Number(params.resellerId);
          if (me.ResellerId !== targetId) {
            throw redirect({
              to: "/reseller/$resellerId/licenses",
              params: { resellerId: me.ResellerId },
            });
          }
        }
      }
    } catch (error) {
      if (error && typeof error === "object" && "to" in error) {
        throw error;
      }
    }
  },
  head: () => ({
    meta: [
      { title: "Reseller Portal | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: ResellerLayoutRoute,
});

function ResellerLayoutRoute() {
  const { resellerId } = useParams({ from: "/_authenticated/reseller/$resellerId" });

  return <ResellerShell resellerId={String(resellerId)} />;
}
