import React, { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  ShieldAlert,
  ShieldCheck,
  Globe,
  Cpu,
  Ban,
  CheckCircle2,
  Clock,
  RotateCcw,
} from "lucide-react";

export interface SecurityEventItem {
  id: number;
  serialValue: string;
  clientIp: string;
  hardwareDigest: string;
  reasons: string[];
  createdAt: string;
  severity: "HIGH" | "MEDIUM" | "LOW";
  isQuarantined?: boolean;
}

export function FraudAlertCenter() {
  const [events, setEvents] = useState<SecurityEventItem[]>([]);

  const [quarantineSuccess, setQuarantineSuccess] = useState<string | null>(null);

  const handleQuarantine = (serial: string) => {
    setEvents((prev) =>
      prev.map((ev) =>
        ev.serialValue === serial ? { ...ev, isQuarantined: true } : ev
      )
    );
    setQuarantineSuccess(`License ${serial} has been quarantined and revoked.`);
    setTimeout(() => setQuarantineSuccess(null), 3000);
  };

  const handleReleaseHardware = (serial: string) => {
    setEvents((prev) => prev.filter((ev) => ev.serialValue !== serial));
    setQuarantineSuccess(`Hardware lock for ${serial} released.`);
    setTimeout(() => setQuarantineSuccess(null), 3000);
  };

  const quarantinedCount = events.filter((e) => e.isQuarantined).length;

  return (
    <div className="flex flex-col gap-6" data-component="fraud-alert-center">
      {/* Top Banner */}
      <div className="flex flex-col gap-4 rounded-xl border border-destructive/20 bg-destructive/5 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="flex size-10 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
            <ShieldAlert className="size-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-semibold text-foreground">Real-Time Fraud & Anomaly Guard</h3>
              <Badge variant="destructive" className="text-xs">
                {events.length} Suspicious Events Detected
              </Badge>
            </div>
            <p className="text-xs text-muted-foreground">
              Monitors impossible travel velocities, sudden HWID drifts, and multi-seat overflow violations.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant="outline" className="text-xs">
            {quarantinedCount} Currently Quarantined
          </Badge>
        </div>
      </div>

      {quarantineSuccess && (
        <div className="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-600 dark:text-emerald-400">
          <CheckCircle2 className="size-4 shrink-0" />
          <span>{quarantineSuccess}</span>
        </div>
      )}

      {/* Events Table / Card List */}
      <Card className="border-border/80 shadow-sm">
        <CardHeader className="pb-3">
          <CardTitle className="text-base font-semibold">Flagged Verification Attempts</CardTitle>
          <CardDescription className="text-xs">
            Review anomalous requests and take instant protective action.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {events.length === 0 ? (
            <div className="py-8 text-center text-xs text-muted-foreground flex flex-col items-center gap-2">
              <ShieldCheck className="size-8 text-emerald-500" />
              <span>No active anomalies or suspicious verification events. All systems nominal.</span>
            </div>
          ) : (
            events.map((ev) => (
              <div
                key={ev.id}
                className={`flex flex-col gap-3 rounded-lg border p-3.5 transition-colors sm:flex-row sm:items-center sm:justify-between ${
                  ev.isQuarantined ? "bg-muted/40 opacity-75 border-dashed" : "bg-card"
                }`}
              >
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2">
                    <span className="font-mono font-bold text-sm text-foreground">{ev.serialValue}</span>
                    <Badge
                      variant={ev.severity === "HIGH" ? "destructive" : "secondary"}
                      className="text-[10px]"
                    >
                      {ev.severity} RISK
                    </Badge>
                    {ev.isQuarantined && (
                      <Badge variant="outline" className="text-[10px] text-destructive border-destructive/40">
                        QUARANTINED
                      </Badge>
                    )}
                  </div>

                  <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground font-mono">
                    <span className="flex items-center gap-1">
                      <Globe className="size-3 text-primary" /> {ev.clientIp}
                    </span>
                    <span className="flex items-center gap-1">
                      <Cpu className="size-3 text-primary" /> {ev.hardwareDigest}
                    </span>
                    <span className="flex items-center gap-1 text-[11px]">
                      <Clock className="size-3" /> {new Date(ev.createdAt).toLocaleTimeString()}
                    </span>
                  </div>

                  <div className="flex flex-wrap gap-1.5 pt-1">
                    {ev.reasons.map((r, i) => (
                      <span
                        key={i}
                        className="rounded bg-destructive/10 px-1.5 py-0.5 text-[11px] font-medium text-destructive"
                      >
                        {r}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="flex items-center gap-2 self-end sm:self-center shrink-0">
                  {!ev.isQuarantined ? (
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => handleQuarantine(ev.serialValue)}
                      className="h-8 gap-1.5 text-xs"
                    >
                      <Ban className="size-3" />
                      1-Click Quarantine
                    </Button>
                  ) : (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleReleaseHardware(ev.serialValue)}
                      className="h-8 gap-1.5 text-xs text-muted-foreground"
                    >
                      <RotateCcw className="size-3" />
                      Release Lock
                    </Button>
                  )}
                </div>
              </div>
            ))
          )}
        </CardContent>
      </Card>
    </div>
  );
}
