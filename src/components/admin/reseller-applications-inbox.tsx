import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, XCircle, Clock, Building, Globe, Mail, Tag, ArrowUpRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  resellerApplicationsQueryOptions,
  approveResellerApplication,
  denyResellerApplication,
  type ResellerApplication,
} from "@/lib/lara-reseller-application";
import { resellersQueryOptions } from "@/lib/lara-reseller";

export function ResellerApplicationsInbox() {
  const queryClient = useQueryClient();
  const { data: applications = [], isLoading } = useQuery(resellerApplicationsQueryOptions);
  const [processingId, setProcessingId] = useState<number | null>(null);

  const approveMutation = useMutation({
    mutationFn: (id: number) => approveResellerApplication(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["LaraApi", "Admin", "ResellerApplications"] });
      queryClient.invalidateQueries({ queryKey: resellersQueryOptions.queryKey });
    },
    onSettled: () => setProcessingId(null),
  });

  const denyMutation = useMutation({
    mutationFn: (id: number) => denyResellerApplication(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["LaraApi", "Admin", "ResellerApplications"] });
    },
    onSettled: () => setProcessingId(null),
  });

  const pendingApps = applications.filter((app) => app.Status === "Pending");

  if (isLoading || pendingApps.length === 0) {
    return null;
  }

  return (
    <div className="mb-6 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Clock className="size-4 text-amber-500 animate-pulse" />
          <h3 className="text-sm font-semibold text-foreground">
            Pending Reseller Applications ({pendingApps.length})
          </h3>
        </div>
        <span className="text-xs text-muted-foreground">Live real-time queue</span>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {pendingApps.map((app) => (
          <div
            key={app.ApplicationId}
            className="flex flex-col justify-between rounded-md border border-border bg-card p-3 shadow-xs"
          >
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-sm text-foreground flex items-center gap-1.5">
                  <Building className="size-3.5 text-primary" />
                  {app.CompanyName}
                </span>
                <Badge variant="outline" className="text-[10px] uppercase font-bold text-amber-600 bg-amber-50 border-amber-200">
                  {app.Status}
                </Badge>
              </div>

              <div className="text-xs text-muted-foreground space-y-1 pt-1">
                <div className="flex items-center gap-1.5 truncate">
                  <Mail className="size-3 text-muted-foreground shrink-0" />
                  <span className="truncate">{app.ContactEmail}</span>
                </div>
                {app.Website && (
                  <div className="flex items-center gap-1.5 truncate">
                    <Globe className="size-3 text-muted-foreground shrink-0" />
                    <a
                      href={app.Website.startsWith("http") ? app.Website : `https://${app.Website}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary hover:underline truncate"
                    >
                      {app.Website}
                    </a>
                  </div>
                )}
                {app.DesiredPrefix && (
                  <div className="flex items-center gap-1.5">
                    <Tag className="size-3 text-muted-foreground shrink-0" />
                    <span>Prefix: <strong className="text-foreground">{app.DesiredPrefix}</strong></span>
                  </div>
                )}
                <div className="text-[11px] text-muted-foreground/80">
                  Vol: {app.ExpectedVolume || "Standard"} • Submitted: {new Date(app.CreatedAt).toLocaleDateString()}
                </div>
              </div>
            </div>

            <div className="mt-3 pt-2.5 border-t border-border flex items-center justify-end gap-2">
              <Button
                size="sm"
                variant="ghost"
                disabled={processingId === app.ApplicationId}
                onClick={() => {
                  setProcessingId(app.ApplicationId);
                  denyMutation.mutate(app.ApplicationId);
                }}
                className="h-7 text-xs text-destructive hover:bg-destructive/10"
              >
                <XCircle className="size-3.5 mr-1" />
                Reject
              </Button>
              <Button
                size="sm"
                disabled={processingId === app.ApplicationId}
                onClick={() => {
                  setProcessingId(app.ApplicationId);
                  approveMutation.mutate(app.ApplicationId);
                }}
                className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium"
              >
                <CheckCircle2 className="size-3.5 mr-1" />
                {processingId === app.ApplicationId ? "Onboarding..." : "Approve & Onboard"}
              </Button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
