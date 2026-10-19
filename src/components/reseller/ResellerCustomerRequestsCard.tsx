import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { Check, X, Clock, Key, Archive, ArchiveRestore, Trash2, CheckCircle2, Package, AlertCircle } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { resellerQuotasQueryOptions } from "@/lib/lara-quota";
import {
  customerRequestsByResellerQueryOptions,
  approveCustomerLicenseRequest,
  denyCustomerLicenseRequest,
  archiveCustomerLicenseRequest,
  archiveAllResolvedCustomerRequests,
  deleteCustomerLicenseRequest,
  type CustomerLicenseRequest,
} from "@/lib/lara-customer-request";

export function ResellerCustomerRequestsCard({ resellerId }: { resellerId: number }) {
  const queryClient = useQueryClient();
  const { data: requests, isLoading } = useQuery(
    customerRequestsByResellerQueryOptions(resellerId),
  );
  const { data: quotas, isLoading: isQuotasLoading } = useQuery(
    resellerQuotasQueryOptions(resellerId),
  );
  const quotaRows = quotas ?? [];
  const totalRemaining = quotaRows.reduce(
    (sum, r) => sum + (r.LicensesRemaining ?? Math.max(0, r.LicensesGranted - r.LicensesConsumed)),
    0,
  );
  const isOutOfQuota = !isQuotasLoading && quotaRows.length > 0 && totalRemaining <= 0;

  const [processingId, setProcessingId] = useState<string | null>(null);
  const [showResolved, setShowResolved] = useState(false);
  const [showArchived, setShowArchived] = useState(false);

  const approveMutation = useMutation({
    mutationFn: (requestId: string) => approveCustomerLicenseRequest(requestId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
      void queryClient.invalidateQueries({ queryKey: ["LaraApi", "Reseller", resellerId, "Licenses"] });
      setProcessingId(null);
    },
    onError: () => setProcessingId(null),
  });

  const denyMutation = useMutation({
    mutationFn: (requestId: string) => denyCustomerLicenseRequest(requestId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
      setProcessingId(null);
    },
    onError: () => setProcessingId(null),
  });

  const archiveMutation = useMutation({
    mutationFn: ({ requestId, isArchived }: { requestId: string; isArchived: boolean }) =>
      archiveCustomerLicenseRequest(requestId, isArchived),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
    },
  });

  const archiveAllMutation = useMutation({
    mutationFn: () => archiveAllResolvedCustomerRequests(resellerId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
      setShowResolved(false);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (requestId: string) => deleteCustomerLicenseRequest(requestId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
    },
  });

  if (isLoading || !requests || requests.length === 0) {
    return null;
  }

  const pendingRequests = requests.filter((r: CustomerLicenseRequest) => r.Status === "Pending");
  const resolvedRequests = requests.filter((r: CustomerLicenseRequest) => r.Status !== "Pending");
  const activeResolved = resolvedRequests.filter((r) => (showArchived ? true : !r.IsArchived));
  const archivedCount = resolvedRequests.filter((r) => r.IsArchived).length;

  const visibleRequests = [
    ...pendingRequests,
    ...(showResolved ? activeResolved : []),
  ];

  return (
    <section className="mb-6 rounded-lg border border-border bg-card p-5 shadow-sm">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between border-b pb-3 mb-4 gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <Clock className="size-4 text-amber-500" />
          <h2 className="text-base font-semibold text-foreground">
            Incoming Customer License Requests
          </h2>
          {pendingRequests.length > 0 ? (
            <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
              {pendingRequests.length} pending
            </span>
          ) : (
            <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
              <CheckCircle2 className="size-3" />
              All caught up
            </span>
          )}
          <div className="flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-0.5 text-xs">
            <Package className="size-3.5 text-primary" />
            <span className="text-muted-foreground">Available Quota:</span>
            <strong
              className={
                totalRemaining > 0
                  ? "font-semibold text-emerald-600 dark:text-emerald-400"
                  : "font-semibold text-destructive"
              }
            >
              {isQuotasLoading ? "..." : `${totalRemaining} keys left`}
            </strong>
            <Link
              to="/reseller/$resellerId/quota-requests"
              params={{ resellerId: String(resellerId) }}
              className="ml-1 text-[11px] font-medium text-primary underline hover:text-primary/80"
              title="View full quota breakdown or request more keys from Admin"
            >
              (Manage Quotas)
            </Link>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {resolvedRequests.length > 0 && (
            <>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                onClick={() => setShowResolved((prev) => !prev)}
              >
                {showResolved ? "Hide resolved" : `Show resolved (${resolvedRequests.length})`}
              </Button>
              {showResolved && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                  disabled={archiveAllMutation.isPending}
                  onClick={() => archiveAllMutation.mutate()}
                  title="Archive all processed requests"
                >
                  <Archive className="mr-1 size-3" />
                  Archive all
                </Button>
              )}
            </>
          )}
        </div>
      </div>

      {visibleRequests.length === 0 ? (
        <div className="py-4 text-center text-xs text-muted-foreground">
          No pending requests requiring action.{" "}
          {resolvedRequests.length > 0 && (
            <button
              type="button"
              onClick={() => setShowResolved(true)}
              className="text-primary underline hover:text-primary/80 ml-1"
            >
              View resolved history ({resolvedRequests.length})
            </button>
          )}
        </div>
      ) : (
        <div className="flex flex-col divide-y divide-border">
          {visibleRequests.map((req: CustomerLicenseRequest) => (
            <div
              key={req.RequestId}
              className={`flex flex-col sm:flex-row sm:items-center justify-between py-3 gap-3 ${
                req.IsArchived ? "opacity-60" : ""
              }`}
            >
              <div className="flex flex-col gap-1">
                <div className="flex items-center gap-2">
                  <span className="font-mono text-xs font-semibold text-primary">{req.RequestId}</span>
                  <span className="text-xs font-medium text-foreground">{req.CustomerName}</span>
                  <span className="text-xs text-muted-foreground">({req.CustomerEmail})</span>
                  <BadgeStatus status={req.Status} />
                  {req.IsArchived && (
                    <span className="text-[10px] font-medium text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                      Archived
                    </span>
                  )}
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                  <span>
                    Plan: <strong className="text-foreground">{req.CategoryName}</strong> ({req.TierName})
                  </span>
                  <span>Requested: {new Date(req.RequestedAt).toLocaleString()}</span>
                  {req.Notes && <span>Note: &ldquo;{req.Notes}&rdquo;</span>}
                </div>
                {req.Status === "Pending" && isOutOfQuota && (
                  <div className="mt-1 flex items-center gap-1.5 text-xs text-destructive font-medium">
                    <AlertCircle className="size-3.5 shrink-0" />
                    <span>Out of quota (0 keys left). Request quota from Admin before issuing.</span>
                  </div>
                )}
                {req.IssuedLicenseKey && req.Status === "Approved" && (
                  <div className="mt-1 flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-mono">
                    <Key className="size-3.5" />
                    <span>Issued Serial: <strong>{req.IssuedLicenseKey}</strong></span>
                  </div>
                )}
                {req.IssuedLicenseKey && req.Status === "Revoked" && (
                  <div className="mt-1 flex items-center gap-1.5 text-xs text-rose-600 dark:text-rose-400 font-mono">
                    <Key className="size-3.5 line-through" />
                    <span className="line-through">Issued Serial: <strong>{req.IssuedLicenseKey}</strong></span>
                    <span className="text-[10px] font-semibold uppercase text-rose-600 dark:text-rose-400 ml-1 bg-rose-500/10 px-1 py-0.5 rounded">
                      Revoked by admin
                    </span>
                  </div>
                )}
              </div>

              <div className="flex items-center gap-2 shrink-0">
                {req.Status === "Pending" ? (
                  <>
                    <Button
                      size="sm"
                      variant="outline"
                      className="h-8 text-destructive hover:bg-destructive/10"
                      disabled={processingId === req.RequestId}
                      onClick={() => {
                        setProcessingId(req.RequestId);
                        denyMutation.mutate(req.RequestId);
                      }}
                    >
                      <X className="mr-1 size-3.5" />
                      Deny
                    </Button>
                    <Button
                      size="sm"
                      className={`h-8 ${
                        isOutOfQuota
                          ? "bg-muted text-muted-foreground opacity-60 cursor-not-allowed"
                          : "bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                      }`}
                      disabled={processingId === req.RequestId || isOutOfQuota}
                      title={
                        isOutOfQuota
                          ? "Cannot approve: Reseller quota is exhausted. Request more quota from Admin first."
                          : "Approve and issue license key"
                      }
                      onClick={() => {
                        setProcessingId(req.RequestId);
                        approveMutation.mutate(req.RequestId);
                      }}
                    >
                      <Check className="mr-1 size-3.5" />
                      {isOutOfQuota ? "No Quota" : "Approve & Issue"}
                    </Button>
                  </>
                ) : (
                  <>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
                      title={req.IsArchived ? "Unarchive" : "Archive"}
                      aria-label={req.IsArchived ? "Unarchive request" : "Archive request"}
                      disabled={archiveMutation.isPending}
                      onClick={() =>
                        archiveMutation.mutate({
                          requestId: req.RequestId,
                          isArchived: !req.IsArchived,
                        })
                      }
                    >
                      {req.IsArchived ? (
                        <ArchiveRestore className="size-3.5" />
                      ) : (
                        <Archive className="size-3.5" />
                      )}
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                      title="Delete / Dismiss"
                      aria-label="Delete request"
                      disabled={deleteMutation.isPending}
                      onClick={() => {
                        if (window.confirm(`Delete request ${req.RequestId}?`)) {
                          deleteMutation.mutate(req.RequestId);
                        }
                      }}
                    >
                      <Trash2 className="size-3.5" />
                    </Button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function BadgeStatus({ status }: { status: CustomerLicenseRequest["Status"] }) {
  if (status === "Approved") {
    return (
      <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
        Approved
      </span>
    );
  }
  if (status === "Revoked") {
    return (
      <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
        Revoked
      </span>
    );
  }
  if (status === "Denied") {
    return (
      <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-[10px] font-semibold text-destructive">
        Denied
      </span>
    );
  }
  return (
    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
      Pending
    </span>
  );
}
