import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CheckCircle2,
  Clock,
  XCircle,
  Copy,
  Key,
  Archive,
  ArchiveRestore,
  Ban,
  Laptop,
  Monitor,
  Unlink,
  Info,
  CalendarCheck,
  ShieldAlert,
  Trash2,
} from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import {
  customerRequestsByCustomerQueryOptions,
  archiveCustomerLicenseRequest,
  deactivateCustomerLicense,
  cancelCustomerLicenseRequest,
  deleteCustomerLicenseRequest,
  clearArchivedCustomerRequests,
  getSerialDevices,
  unbindCustomerLicenseDevice,
  cancelCustomerSubscriptionRenewal,
  type CustomerLicenseRequest,
  type BoundDevice,
} from "@/lib/lara-customer-request";

export function CustomerRequestsHistory({
  customerEmail,
  onApplySerial,
  onRenew,
}: {
  customerEmail: string;
  onApplySerial?: (serial: string) => void;
  onRenew?: (request: CustomerLicenseRequest) => void;
}) {
  const queryClient = useQueryClient();
  const { data: requests, isLoading } = useQuery(
    customerRequestsByCustomerQueryOptions(customerEmail),
  );
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [showArchived, setShowArchived] = useState(false);
  const [managingRequest, setManagingRequest] = useState<CustomerLicenseRequest | null>(null);

  const archiveMutation = useMutation({
    mutationFn: ({ requestId, isArchived }: { requestId: string; isArchived: boolean }) =>
      archiveCustomerLicenseRequest(requestId, isArchived),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
    },
  });

  const cancelRenewalMutation = useMutation({
    mutationFn: (requestId: string) => cancelCustomerSubscriptionRenewal(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
      queryClient.invalidateQueries({ queryKey: ["LaraApi"] });
      window.dispatchEvent(new Event("portal-history-updated"));
    },
  });

  const revokeMutation = useMutation({
    mutationFn: (requestId: string) => deactivateCustomerLicense(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
      queryClient.invalidateQueries({ queryKey: ["LaraApi"] });
      window.dispatchEvent(new Event("portal-history-updated"));
    },
  });

  const cancelMutation = useMutation({
    mutationFn: (requestId: string) => cancelCustomerLicenseRequest(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (requestId: string) => deleteCustomerLicenseRequest(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
    },
  });

  const clearArchivedMutation = useMutation({
    mutationFn: () => clearArchivedCustomerRequests(customerEmail),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
    },
  });

  if (isLoading || !requests || requests.length === 0) {
    return null;
  }

  const archivedCount = requests.filter((r) => r.IsArchived).length;
  const visibleRequests = requests.filter((r) => (showArchived ? true : !r.IsArchived));

  const handleCopy = (serial: string) => {
    navigator.clipboard.writeText(serial);
    setCopiedKey(serial);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const handleToggleArchive = (requestId: string, currentArchived: boolean) => {
    archiveMutation.mutate({ requestId, isArchived: !currentArchived });
  };

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-foreground">Your License Requests</h3>
        {archivedCount > 0 && (
          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-6 px-2 text-xs text-muted-foreground hover:text-foreground"
              onClick={() => setShowArchived((prev) => !prev)}
            >
              {showArchived ? "Hide archived" : `Show archived (${archivedCount})`}
            </Button>
            {showArchived && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-6 px-2 text-xs text-destructive/80 hover:text-destructive hover:bg-destructive/10"
                disabled={clearArchivedMutation.isPending}
                onClick={() => {
                  if (
                    window.confirm(
                      `Permanently delete all ${archivedCount} archived requests? This cannot be undone.`,
                    )
                  ) {
                    clearArchivedMutation.mutate();
                  }
                }}
              >
                <Trash2 className="size-3 mr-1" />
                Clear all archived
              </Button>
            )}
          </div>
        )}
      </div>

      {visibleRequests.length === 0 ? (
        <div className="py-4 text-center text-xs text-muted-foreground">
          All requests are archived.{" "}
          <button
            type="button"
            onClick={() => setShowArchived(true)}
            className="underline hover:text-foreground"
          >
            View archived ({archivedCount})
          </button>
        </div>
      ) : (
        <div className="flex flex-col gap-2.5">
          {visibleRequests.map((req: CustomerLicenseRequest) => (
            <div
              key={req.RequestId}
              className={`rounded-lg border border-border/70 bg-muted/20 p-3.5 flex flex-col gap-2.5 transition-colors hover:border-border ${
                req.IsArchived ? "opacity-60 bg-muted/10" : ""
              }`}
            >
              {/* Header Row: Request ID, Reseller, Status, Date, and Archive/Delete Actions */}
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-mono text-xs font-semibold text-foreground whitespace-nowrap bg-background border border-border/60 px-2 py-0.5 rounded shadow-2xs">
                    {req.RequestId}
                  </span>
                  <span className="text-xs text-muted-foreground whitespace-nowrap">
                    to <span className="font-medium text-foreground">{req.ResellerName}</span>
                  </span>
                  <StatusBadge status={req.Status} />
                  {req.IsArchived && (
                    <span className="text-[10px] font-medium text-muted-foreground bg-muted px-1.5 py-0.5 rounded border border-border/50">
                      Archived
                    </span>
                  )}
                </div>

                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <span className="whitespace-nowrap">{new Date(req.RequestedAt).toLocaleDateString()}</span>
                  <div className="flex items-center gap-0.5 border-l border-border pl-1.5">
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                      onClick={() => handleToggleArchive(req.RequestId, !!req.IsArchived)}
                      title={req.IsArchived ? "Unarchive" : "Archive"}
                    >
                      {req.IsArchived ? (
                        <ArchiveRestore className="size-3.5" />
                      ) : (
                        <Archive className="size-3.5" />
                      )}
                    </Button>

                    {req.IsArchived && (
                      <Button
                        size="sm"
                        variant="ghost"
                        className="h-6 w-6 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                        disabled={deleteMutation.isPending}
                        onClick={() => {
                          if (window.confirm(`Permanently delete request ${req.RequestId}?`)) {
                            deleteMutation.mutate(req.RequestId);
                          }
                        }}
                        title="Permanently delete this request"
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    )}
                  </div>
                </div>
              </div>

              {/* Plan Information & Notes */}
              <div className="text-xs text-muted-foreground flex flex-wrap items-center gap-1.5">
                <span>
                  Plan: <span className="font-semibold text-foreground">{req.CategoryName}</span> ({req.TierName})
                </span>
                {req.Notes && (
                  <span className="text-muted-foreground/80 italic font-sans">
                    — {req.Notes}
                  </span>
                )}
              </div>

              {/* Action Toolbar for Approved Requests */}
              {req.Status === "Approved" && req.IssuedLicenseKey && (
                <div className="flex flex-wrap items-center justify-between gap-2.5 pt-2 border-t border-border/40">
                  {/* License Key Pill */}
                  <div className="flex items-center gap-2 bg-emerald-500/10 px-2.5 py-1.5 rounded-md border border-emerald-500/25">
                    <Key className="size-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span className="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300 select-all">
                      {req.IssuedLicenseKey}
                    </span>
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-6 px-1.5 text-xs text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/20"
                      onClick={() => handleCopy(req.IssuedLicenseKey!)}
                    >
                      <Copy className="size-3 mr-1" />
                      {copiedKey === req.IssuedLicenseKey ? "Copied" : "Copy"}
                    </Button>
                    {onApplySerial && (
                      <Button
                        size="sm"
                        variant="outline"
                        className="h-6 px-2 text-xs border-emerald-500/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/15"
                        onClick={() => onApplySerial(req.IssuedLicenseKey!)}
                        title="Fill serial into verification box below"
                      >
                        Verify
                      </Button>
                    )}
                  </div>

                  {/* Device and Renewal Actions */}
                  <div className="flex flex-wrap items-center gap-1.5">
                    <Button
                      size="sm"
                      variant="outline"
                      className="h-7 px-2.5 text-xs gap-1.5 border-primary/30 text-primary hover:bg-primary/10 shadow-2xs"
                      onClick={() => setManagingRequest(req)}
                      title="Manage connected devices & unbinding"
                    >
                      <Laptop className="size-3.5" />
                      <span>Devices</span>
                    </Button>

                    {onRenew && (
                      <Button
                        size="sm"
                        variant="secondary"
                        className="h-7 px-2.5 text-xs bg-amber-500/15 text-amber-800 dark:text-amber-200 hover:bg-amber-500/25 border border-amber-500/30 font-medium"
                        onClick={() => onRenew(req)}
                      >
                        Renew
                      </Button>
                    )}

                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-7 px-2 text-xs text-muted-foreground hover:text-amber-600 hover:bg-amber-500/10"
                      disabled={cancelRenewalMutation.isPending}
                      onClick={() => {
                        if (
                          window.confirm(
                            `Cancel subscription renewal for key ${req.IssuedLicenseKey}?\n\n• Your paid access will continue until the end of this billing period.\n• Subscription will NOT renew after expiration.`,
                          )
                        ) {
                          cancelRenewalMutation.mutate(req.RequestId);
                        }
                      }}
                      title="Cancel subscription renewal (remains active until period ends)"
                    >
                      Cancel Renewal
                    </Button>
                  </div>
                </div>
              )}

              {/* Action Toolbar for Revoked Requests */}
              {req.Status === "Revoked" && req.IssuedLicenseKey && (
                <div className="flex flex-wrap items-center justify-between gap-2.5 pt-2 border-t border-border/40">
                  <div className="flex items-center gap-2 bg-rose-500/10 px-2.5 py-1.5 rounded-md border border-rose-500/25">
                    <Ban className="size-3.5 text-rose-600 dark:text-rose-400 shrink-0" />
                    <span className="font-mono text-xs font-semibold text-rose-700/80 dark:text-rose-300/80 line-through">
                      {req.IssuedLicenseKey}
                    </span>
                    <span className="text-[10px] font-semibold text-rose-600 dark:text-rose-400 bg-rose-500/15 px-1.5 py-0.5 rounded">
                      Revoked
                    </span>
                  </div>
                  {onRenew && (
                    <Button
                      size="sm"
                      variant="secondary"
                      className="h-7 px-2.5 text-xs bg-rose-500/10 text-rose-700 dark:text-rose-300 hover:bg-rose-500/20 border border-rose-500/20"
                      onClick={() => onRenew(req)}
                    >
                      Request New
                    </Button>
                  )}
                </div>
              )}

              {/* Action Toolbar for Pending Requests */}
              {req.Status === "Pending" && (
                <div className="flex items-center justify-end pt-2 border-t border-border/40">
                  <Button
                    size="sm"
                    variant="outline"
                    className="h-7 px-2.5 text-xs text-destructive hover:bg-destructive/10"
                    disabled={cancelMutation.isPending}
                    onClick={() => {
                      if (window.confirm("Are you sure you want to cancel this pending request?")) {
                        cancelMutation.mutate(req.RequestId);
                      }
                    }}
                  >
                    Cancel request
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Connected Devices & Machine Unbinding Modal */}
      <DeviceManagementModal
        request={managingRequest}
        onClose={() => setManagingRequest(null)}
      />
    </div>
  );
}

function DeviceManagementModal({
  request,
  onClose,
}: {
  request: CustomerLicenseRequest | null;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const serial = request?.IssuedLicenseKey;

  const { data, isLoading, refetch } = useQuery({
    queryKey: ["SerialDevices", serial],
    queryFn: () => (serial ? getSerialDevices(serial) : null),
    enabled: Boolean(serial),
  });

  const [unbindingId, setUnbindingId] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const handleUnbind = async (deviceId: string, hostname: string) => {
    if (!serial) return;
    if (
      !window.confirm(
        `Are you sure you want to release and unbind machine "${hostname}"?\n\n• The machine slot will be freed immediately.\n• You can then activate this license on a new PC.\n• Your paid subscription remains 100% active!`,
      )
    ) {
      return;
    }

    setUnbindingId(deviceId);
    try {
      await unbindCustomerLicenseDevice(serial, deviceId);
      setSuccessMsg(`Machine "${hostname}" was released. You can now activate on another PC.`);
      refetch();
      queryClient.invalidateQueries({ queryKey: ["SerialDevices"] });
      window.dispatchEvent(new Event("portal-history-updated"));
    } finally {
      setUnbindingId(null);
    }
  };

  const devices: BoundDevice[] = data?.boundDevices ?? [];
  const maxActivations = data?.maxActivations ?? 5;

  return (
    <Dialog open={Boolean(request)} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-lg p-6 bg-card border-border">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="rounded-md bg-primary/10 p-2 text-primary">
              <Laptop className="size-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-semibold text-foreground">
                Connected Devices & Hardware Binding
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                Serial: <span className="font-mono font-bold text-foreground">{serial}</span> • Plan: {request?.CategoryName} ({request?.TierName})
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="mt-2 flex items-center justify-between rounded-md bg-muted/60 px-3 py-2 text-xs">
          <span className="text-muted-foreground">Device Activation Slots</span>
          <span className="font-semibold text-foreground">
            {devices.length} / {maxActivations} Devices Active
          </span>
        </div>

        {successMsg && (
          <div className="mt-2 flex items-center gap-2 rounded-md bg-emerald-500/10 border border-emerald-500/20 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-300">
            <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
            <span>{successMsg}</span>
          </div>
        )}

        <div className="mt-3 flex flex-col gap-2.5">
          <h4 className="text-xs font-semibold text-foreground uppercase tracking-wider">
            Active Machines ({devices.length})
          </h4>

          {isLoading ? (
            <div className="py-6 text-center text-xs text-muted-foreground">Loading connected devices...</div>
          ) : devices.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border p-6 text-center">
              <Monitor className="size-8 text-muted-foreground/50 mx-auto mb-2" />
              <p className="text-xs font-medium text-foreground">No devices currently bound</p>
              <p className="text-[11px] text-muted-foreground mt-1 max-w-xs mx-auto">
                Open the client app on your PC, paste serial <span className="font-mono font-semibold">{serial}</span>, and click Activate to bind your device.
              </p>
            </div>
          ) : (
            devices.map((dev) => (
              <div
                key={dev.DeviceId}
                className="flex items-center justify-between rounded-lg border border-border bg-muted/30 p-3 hover:border-primary/30 transition-colors"
              >
                <div className="flex items-start gap-3">
                  <div className="rounded-md bg-background border border-border p-2 text-primary shadow-xs">
                    <Laptop className="size-4" />
                  </div>
                  <div className="flex flex-col">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-foreground">{dev.Hostname || "Client PC"}</span>
                      <span className="text-[10px] bg-muted px-1.5 py-0.2 rounded text-muted-foreground font-mono">
                        {dev.Os || "Windows"}
                      </span>
                    </div>
                    <span className="text-[11px] text-muted-foreground font-mono mt-0.5">
                      HWID: {dev.HardwareDigest || dev.DeviceId.slice(0, 16)}
                    </span>
                    <span className="text-[10px] text-muted-foreground mt-0.5">
                      Activated: {new Date(dev.ActivatedAt).toLocaleDateString()}
                    </span>
                  </div>
                </div>

                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-7 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 gap-1.5"
                  disabled={unbindingId === dev.DeviceId}
                  onClick={() => handleUnbind(dev.DeviceId, dev.Hostname)}
                >
                  <Unlink className="size-3" />
                  <span>{unbindingId === dev.DeviceId ? "Releasing..." : "Unbind Device"}</span>
                </Button>
              </div>
            ))
          )}
        </div>

        <div className="mt-3 rounded-md border border-primary/20 bg-primary/5 p-3 text-[11px] text-muted-foreground flex items-start gap-2">
          <Info className="size-4 text-primary shrink-0 mt-0.5" />
          <span>
            <strong>Device Transfer Notice:</strong> Releasing/unbinding a machine frees up the activation slot without terminating your subscription. Your paid access remains 100% active on all remaining days.
          </span>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function StatusBadge({ status }: { status: CustomerLicenseRequest["Status"] }) {
  if (status === "Approved") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
        <CheckCircle2 className="size-3" />
        Approved
      </span>
    );
  }
  if (status === "Denied") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-rose-500/15 px-2 py-0.5 text-xs font-medium text-rose-700 dark:text-rose-300">
        <XCircle className="size-3" />
        Denied
      </span>
    );
  }
  if (status === "Revoked") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-rose-500/15 px-2 py-0.5 text-xs font-medium text-rose-700 dark:text-rose-300">
        <Ban className="size-3" />
        Revoked
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
      <Clock className="size-3" />
      Pending
    </span>
  );
}
