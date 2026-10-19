import { createFileRoute, redirect } from "@tanstack/react-router";

import { AdminShell } from "../../components/admin/admin-shell";
import { meQueryOptions } from "../../lib/lara-me";

export const Route = createFileRoute("/_authenticated/admin")({
  beforeLoad: async ({ context }) => {
    if (typeof window === "undefined") return;
    try {
      const meRows = await context.queryClient.ensureQueryData(meQueryOptions());
      const me = meRows?.[0];
      if (me) {
        if (me.RoleName === "Reseller") {
          const targetResellerId = me.ResellerId ? me.ResellerId : 1;
          throw redirect({
            to: "/reseller/$resellerId/licenses",
            params: { resellerId: targetResellerId },
          });
        }
        if (me.RoleName === "EndUser") {
          throw redirect({
            to: "/portal/home",
          });
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
      { title: "Admin Console | Licensing Portal" },
      { name: "description", content: "Licensing Portal administrative console." },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminShell,
});
