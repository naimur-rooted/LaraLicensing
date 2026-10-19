import { useMutation } from "@tanstack/react-query";
import { useCallback, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { AlertTriangle, CheckCircle2, RefreshCw, Trash2 } from "lucide-react";
import { copyForErrorCode } from "@/lib/error-copy";
import { LaraApiError, ApiErrorCodeType } from "@/lib/lara-api-error";
import { verifySerial, type VerifySerialResult } from "@/lib/lara-serial";

/**
 * Reusable serial lookup panel per Plan 09 step 50.
 *
 * Extracted from portal.home.tsx so the same panel can be embedded in future
 * surfaces (reseller serial page, admin lookup drawer) without copy-pasting
 * the three-state result rendering, LaraApiError -> copy mapping, and the
 * recent-lookups list. Recent lookups persist to localStorage under the
 * canonical LicensingPortal namespace so a portal user coming back on the
 * same device sees the last 5 serials they checked (no server calls).
 */

const STORAGE_KEY = "LicensingPortal.portalRecentSerials";
const MAX_HISTORY = 5;

interface HistoryEntry {
  readonly Serial: string;
  readonly IsValid: boolean;
  readonly CheckedAt: string;
}

interface PanelState {
  readonly kind: "idle" | "success" | "error";
  readonly result?: VerifySerialResult;
  readonly serial?: string;
  readonly message?: string;
}

export interface SerialLookupPanelProps {
  readonly testIdPrefix?: string;
  readonly initialSerial?: string;
  readonly onRequestRenewal?: (serial: string) => void;
}

function loadHistory(): HistoryEntry[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (raw === null) return [];
    const parsed: unknown = JSON.parse(raw);
    if (Array.isArray(parsed) === false) return [];
    const entries: HistoryEntry[] = [];
    for (const item of parsed) {
      if (
        typeof item === "object" &&
        item !== null &&
        typeof (item as HistoryEntry).Serial === "string" &&
        typeof (item as HistoryEntry).IsValid === "boolean" &&
        typeof (item as HistoryEntry).CheckedAt === "string"
      ) {
        entries.push(item as HistoryEntry);
      }
    }

    return entries.slice(0, MAX_HISTORY);
  } catch (error) {
    console.warn("SerialLookupPanel: failed to parse recent-serials storage", error);

    return [];
  }
}

function persistHistory(entries: HistoryEntry[]): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(entries.slice(0, MAX_HISTORY)));
  } catch (error) {
    console.warn("SerialLookupPanel: failed to persist recent-serials storage", error);
  }
}

export function SerialLookupPanel({
  testIdPrefix = "serial-lookup",
  initialSerial = "",
  onRequestRenewal,
}: SerialLookupPanelProps) {
  const [serial, setSerial] = useState(initialSerial);
  const [state, setState] = useState<PanelState>({ kind: "idle" });
  const [history, setHistory] = useState<HistoryEntry[]>(() => loadHistory());

  const recordHistory = useCallback((entry: HistoryEntry) => {
    setHistory((current) => {
      const deduped = current.filter((row) => row.Serial.toUpperCase() !== entry.Serial.toUpperCase());
      const next = [entry, ...deduped].slice(0, MAX_HISTORY);
      persistHistory(next);

      return next;
    });
  }, []);

  const removeHistory = useCallback((targetSerial: string) => {
    setHistory((current) => {
      const next = current.filter(
        (row) => row.Serial.trim().toUpperCase() !== targetSerial.trim().toUpperCase(),
      );
      persistHistory(next);

      return next;
    });
  }, []);

  const clearAllHistory = useCallback(() => {
    persistHistory([]);
    setHistory([]);
  }, []);

  const mutation = useMutation({
    mutationFn: (value: string) => verifySerial(value),
    onSuccess: (result, value) => {
      setState({ kind: "success", result, serial: value });
      recordHistory({
        Serial: value,
        IsValid: result.IsValid,
        CheckedAt: new Date().toISOString(),
      });
    },
    onError: (error, value) => {
      const message =
        error instanceof LaraApiError
          ? copyForErrorCode(error.errorCode)
          : "Verification failed. Try again in a moment.";
      console.error("SerialLookupPanel.verifySerial failed", {
        serial: value,
        errorCode: error instanceof LaraApiError ? error.errorCode : "Unknown",
        status: error instanceof LaraApiError ? error.httpStatus : 0,
      });
      setState({ kind: "error", message, serial: value });

      // If serial is not found or revoked, remove it from recent lookups
      if (
        error instanceof LaraApiError &&
        (error.errorCode === ApiErrorCodeType.SerialNotFound ||
          error.errorCode === ApiErrorCodeType.LicenseNotFound ||
          error.errorCode === ApiErrorCodeType.LicenseRevoked ||
          error.errorCode === ApiErrorCodeType.SerialRevoked)
      ) {
        removeHistory(value);
      }
    },
  });

  useEffect(() => {
    // Auto-prune any revoked or non-existent serials from history
    const pruneRevokedHistory = async () => {
      try {
        const { read: readFromStore } = await import("@/lib/preview-store");
        const currentHistory = loadHistory();
        if (currentHistory.length === 0) return;

        const validEntries: HistoryEntry[] = [];
        let changed = false;

        for (const entry of currentHistory) {
          const serialKey = entry.Serial.trim().toUpperCase();
          const serialIndex = await readFromStore<{ Serial: string; LicenseId: string }>(
            "serials",
            serialKey,
          );
          if (serialIndex?.LicenseId) {
            const lic = await readFromStore<import("@/generated/api/schema").License>(
              "licenses",
              serialIndex.LicenseId,
            );
            if (lic && lic.Status === "active") {
              validEntries.push(entry);
              continue;
            }
          }
          changed = true;
        }

        if (changed) {
          persistHistory(validEntries);
          setHistory(validEntries);
        }
      } catch (e) {
        console.warn("SerialLookupPanel: failed to prune revoked history", e);
      }
    };

    void pruneRevokedHistory();

    const handleStorageUpdate = () => {
      setHistory(loadHistory());
    };
    window.addEventListener("storage", handleStorageUpdate);
    window.addEventListener("portal-history-updated", handleStorageUpdate);

    return () => {
      window.removeEventListener("storage", handleStorageUpdate);
      window.removeEventListener("portal-history-updated", handleStorageUpdate);
    };
  }, []);

  useEffect(() => {
    if (initialSerial.trim().length > 0) {
      mutation.mutate(initialSerial.trim());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialSerial]);

  return (
    <div className="flex flex-col gap-4" data-panel="serial-lookup">
      <form
        className="flex flex-col gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          const trimmed = serial.trim();
          if (trimmed.length === 0) {
            setState({ kind: "error", message: "Enter a serial to verify." });

            return;
          }
          setState({ kind: "idle" });
          mutation.mutate(trimmed);
        }}
      >
        <label className="flex flex-col gap-2 text-sm font-medium">
          <span style={{ fontFamily: "var(--font-sans)" }}>Serial</span>
          <Input
            value={serial}
            onChange={(event) => setSerial(event.target.value)}
            placeholder="XXXX-XXXX-XXXX-XXXX"
            autoComplete="off"
            spellCheck={false}
            aria-invalid={state.kind === "error"}
            data-testid={`${testIdPrefix}-input`}
          />
        </label>
        <div className="flex items-center gap-3">
          <Button
            type="submit"
            disabled={mutation.isPending}
            data-testid={`${testIdPrefix}-submit`}
          >
            {mutation.isPending ? "Verifying..." : "Verify serial"}
          </Button>
        </div>
      </form>
      <ResultPanel
        state={state}
        testIdPrefix={testIdPrefix}
        onRequestRenewal={onRequestRenewal}
      />
      <HistoryList
        entries={history}
        onReplay={(value) => {
          setSerial(value);
          setState({ kind: "idle" });
          mutation.mutate(value);
        }}
        onRemove={removeHistory}
        onClearAll={clearAllHistory}
        testIdPrefix={testIdPrefix}
      />
    </div>
  );
}

function ResultPanel({
  state,
  testIdPrefix,
  onRequestRenewal,
}: {
  readonly state: PanelState;
  readonly testIdPrefix: string;
  readonly onRequestRenewal?: (serial: string) => void;
}) {
  if (state.kind === "idle") {
    return (
      <EmptyState
        preset="search"
        headline="No serial verified yet"
        body="Verification results appear here after you submit a serial."
      />
    );
  }
  if (state.kind === "error") {
    return (
      <div
        role="alert"
        className="rounded-lg border border-destructive/40 bg-[color-mix(in_oklab,var(--color-destructive)_8%,transparent)] p-4 text-sm text-destructive"
        data-testid={`${testIdPrefix}-error`}
        style={{ fontFamily: "var(--font-sans)" }}
      >
        {state.message ?? "Verification failed."}
      </div>
    );
  }
  const result = state.result!;
  const isExpired =
    typeof result.ExpiresAt === "string" && new Date(result.ExpiresAt).getTime() < Date.now();
  const isAuthorized = result.IsValid && !isExpired;
  const tone = isAuthorized ? "success" : "destructive";

  return (
    <div
      className={`rounded-lg border p-4 text-sm ${
        tone === "success"
          ? "border-success/40 bg-[color-mix(in_oklab,var(--color-success)_8%,transparent)] text-foreground"
          : "border-destructive/40 bg-[color-mix(in_oklab,var(--color-destructive)_8%,transparent)] text-foreground"
      }`}
      data-testid={`${testIdPrefix}-result`}
      data-tone={tone}
      style={{ fontFamily: "var(--font-sans)" }}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          {isAuthorized ? (
            <>
              <CheckCircle2 className="size-5 text-success" />
              <span className="text-base font-semibold text-success">Serial is authorized</span>
              <Badge intent="success">Active</Badge>
            </>
          ) : isExpired ? (
            <>
              <AlertTriangle className="size-5 text-destructive" />
              <span className="text-base font-semibold text-destructive">License Expired</span>
              <Badge intent="destructive">Expired</Badge>
            </>
          ) : (
            <>
              <AlertTriangle className="size-5 text-destructive" />
              <span className="text-base font-semibold text-destructive">Serial is not authorized</span>
              <Badge intent="destructive">Inactive</Badge>
            </>
          )}
        </div>
      </div>

      {isExpired && (
        <div className="mt-3 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-xs">
          <p className="font-semibold text-destructive">Subscription Expired</p>
          <p className="mt-1 text-muted-foreground">
            This {result.Category.toLowerCase()} license expired on{" "}
            <span className="font-medium text-foreground">
              {new Date(result.ExpiresAt!).toLocaleDateString()}
            </span>
            . Software execution and workspace features are currently locked.
          </p>
          {onRequestRenewal && (
            <div className="mt-3">
              <Button
                type="button"
                size="sm"
                className="gap-1.5"
                onClick={() => onRequestRenewal(state.serial ?? "")}
              >
                <RefreshCw className="size-3.5" />
                Request Renewal
              </Button>
            </div>
          )}
        </div>
      )}

      <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
        <dt className="text-muted-foreground">Category</dt>
        <dd className="font-medium">{result.Category}</dd>
        <dt className="text-muted-foreground">Single-use</dt>
        <dd className="font-medium">{result.IsSingleUse ? "Yes" : "No"}</dd>
        {typeof result.ExpiresAt === "string" ? (
          <>
            <dt className="text-muted-foreground">Expires</dt>
            <dd className={isExpired ? "font-semibold text-destructive" : "font-medium"}>
              {new Date(result.ExpiresAt).toLocaleString()}
            </dd>
          </>
        ) : null}
      </dl>
    </div>
  );
}

function HistoryList({
  entries,
  onReplay,
  onRemove,
  onClearAll,
  testIdPrefix,
}: {
  readonly entries: HistoryEntry[];
  readonly onReplay: (serial: string) => void;
  readonly onRemove: (serial: string) => void;
  readonly onClearAll: () => void;
  readonly testIdPrefix: string;
}) {
  if (entries.length === 0) return null;

  return (
    <section
      aria-labelledby={`${testIdPrefix}-history-label`}
      className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-4"
      data-testid={`${testIdPrefix}-history`}
    >
      <div className="flex items-center justify-between">
        <h2
          id={`${testIdPrefix}-history-label`}
          className="text-sm font-semibold"
          style={{ fontFamily: "var(--font-display)" }}
        >
          Recent lookups
        </h2>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-6 px-2 text-xs text-muted-foreground hover:text-foreground"
          onClick={onClearAll}
          title="Clear all recent lookups"
        >
          Clear
        </Button>
      </div>
      <ul className="flex flex-col divide-y divide-border">
        {entries.map((entry) => (
          <li key={entry.Serial} className="flex items-center justify-between gap-3 py-2 text-xs">
            <div className="flex flex-col">
              <span className="font-mono">{entry.Serial}</span>
              <span className="text-muted-foreground">
                {entry.IsValid ? "Authorized" : "Not authorized"} -{" "}
                {new Date(entry.CheckedAt).toLocaleString()}
              </span>
            </div>
            <div className="flex items-center gap-1.5">
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-7 px-2.5 text-xs"
                onClick={() => onReplay(entry.Serial)}
                data-testid={`${testIdPrefix}-history-replay`}
              >
                Recheck
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                title="Remove from history"
                aria-label={`Remove ${entry.Serial} from history`}
                onClick={() => onRemove(entry.Serial)}
              >
                <Trash2 className="size-3.5" />
              </Button>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
