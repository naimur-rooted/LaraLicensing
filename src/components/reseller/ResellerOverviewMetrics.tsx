import { useSuspenseQuery } from "@tanstack/react-query";
import { KeyRound, Gauge, Cpu, Clock } from "lucide-react";

import { ResellerMetricCard } from "./ResellerMetricCard";
import { resellerLicenseListQueryOptions } from "../../lib/lara-reseller-license";
import {
  resellerQuotasQueryOptions,
  quotaRequestListQueryOptions,
  QuotaRequestStatusType,
} from "../../lib/lara-quota";

export interface ResellerOverviewMetricsProps {
  resellerId: number;
}

export function ResellerOverviewMetrics({ resellerId }: ResellerOverviewMetricsProps) {
  const { data: licenses } = useSuspenseQuery(resellerLicenseListQueryOptions(resellerId));
  const { data: quotas } = useSuspenseQuery(resellerQuotasQueryOptions(resellerId));
  const { data: pendingRequests } = useSuspenseQuery(
    quotaRequestListQueryOptions(resellerId, QuotaRequestStatusType.Pending),
  );

  const activeLicenses = licenses.filter((l) => l.Status === "Active");
  const totalActive = activeLicenses.length;
  const remainingQuota = quotas.reduce((sum, q) => sum + q.LicensesRemaining, 0);
  const grantedQuota = quotas.reduce((sum, q) => sum + q.LicensesGranted, 0);
  const boundSeats = totalActive * 1;
  const pendingCount = pendingRequests.length;

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <ResellerMetricCard
        label="Total Active Licenses"
        value={totalActive.toLocaleString()}
        hint={`${licenses.length} total issued licenses`}
        icon={KeyRound}
        badge="Active"
      />
      <ResellerMetricCard
        label="Available Quota Remaining"
        value={remainingQuota.toLocaleString()}
        hint={`Out of ${grantedQuota.toLocaleString()} total granted`}
        icon={Gauge}
        badge={`${Math.round((remainingQuota / (grantedQuota || 1)) * 100)}% left`}
      />
      <ResellerMetricCard
        label="Bound Hardware Seats"
        value={boundSeats.toLocaleString()}
        hint="Live client machine fingerprints"
        icon={Cpu}
        badge="Online"
      />
      <ResellerMetricCard
        label="Pending Quota Requests"
        value={pendingCount.toLocaleString()}
        hint={pendingCount === 0 ? "All requests processed" : "Awaiting admin review"}
        icon={Clock}
        badge={pendingCount > 0 ? "Pending" : undefined}
      />
    </div>
  );
}
