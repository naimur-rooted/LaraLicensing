import { useRouterState } from "@tanstack/react-router";
import { LayoutDashboard, Sparkles, KeyRound, Gauge, Cpu } from "lucide-react";

import { ResellerNavItemRow } from "./ResellerNavItemRow";

export interface ResellerSidebarNavProps {
  resellerId: string;
}

export function ResellerSidebarNav({ resellerId }: ResellerSidebarNavProps) {
  const routerState = useRouterState();
  const pathname = routerState.location.pathname;
  const search = routerState.location.search as Record<string, string>;
  const activeView = search?.view ?? "mint";

  const isOverview = pathname === `/reseller/${resellerId}`;
  const isLicenses = pathname.startsWith(`/reseller/${resellerId}/licenses`);
  const isQuotas = pathname.startsWith(`/reseller/${resellerId}/quota-requests`);
  const isSerialsPath = pathname.startsWith(`/reseller/${resellerId}/serials`);
  const isMintStudio = isSerialsPath && activeView === "mint";
  const isHardwareTroubleshoot = isSerialsPath && activeView === "troubleshoot";

  return (
    <nav aria-label="Reseller navigation" className="flex flex-col gap-1 text-sm">
      <ResellerNavItemRow
        icon={LayoutDashboard}
        label="Overview"
        to={`/reseller/${resellerId}`}
        isActive={isOverview}
      />
      <ResellerNavItemRow
        icon={Sparkles}
        label="Serial Minting Studio"
        to={`/reseller/${resellerId}/serials`}
        search={{ view: "mint" }}
        isActive={isMintStudio}
      />
      <ResellerNavItemRow
        icon={KeyRound}
        label="Licenses"
        to={`/reseller/${resellerId}/licenses`}
        isActive={isLicenses}
      />
      <ResellerNavItemRow
        icon={Gauge}
        label="Quota Economics"
        to={`/reseller/${resellerId}/quota-requests`}
        isActive={isQuotas}
      />
      <ResellerNavItemRow
        icon={Cpu}
        label="Hardware Troubleshooting"
        to={`/reseller/${resellerId}/serials`}
        search={{ view: "troubleshoot" }}
        isActive={isHardwareTroubleshoot}
      />
    </nav>
  );
}
