import { useState, type FormEvent } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2 } from "lucide-react";

import { RetryAfterBanner } from "../retry-after-banner";
import { useSubmitLock } from "../../lib/use-submit-lock";
import { formatLaraApiErrorOptional } from "../../lib/lara-api-error";
import { useLaraErrorToast } from "../../lib/use-lara-error-toast";
import { useAppToast } from "../../hooks/use-app-toast";
import {
  quotaRequestListQueryOptions,
  quotaRequestSubmitSchema,
  submitQuotaRequest,
  type QuotaRequestSubmitInput,
} from "../../lib/lara-quota";
import { LICENSE_CATEGORY_IDS } from "../../lib/lara-license";

const TIER_IDS = [1, 2, 3, 4] as const;

const CATEGORY_LABELS: Record<number, string> = {
  1: "1 - Daily (1 day)",
  2: "2 - Weekly (7 days)",
  3: "3 - Monthly (30 days)",
  4: "4 - Yearly (1 year)",
  5: "5 - Lifetime (Never expires)",
  6: "6 - Dev (Testing)",
  7: "7 - Key (Hardware)",
};

/**
 * POST /Resellers/{id}/QuotaRequests submit form (Step 47).
 * Emits an Idempotency-Key per AC-API-QR-003.
 */
export function QuotaRequestSubmitForm({ resellerId }: { resellerId: number }) {
  const [categoryId, setCategoryId] = useState<number>(1);
  const [tierId, setTierId] = useState<number>(1);
  const [delta, setDelta] = useState<string>("1");
  const [justification, setJustification] = useState<string>("");
  const [validationError, setValidationError] = useState<string | undefined>();
  const [successBanner, setSuccessBanner] = useState<string | null>(null);
  const toast = useAppToast();
  const client = useQueryClient();
  const mutation = useMutation({
    mutationFn: (input: QuotaRequestSubmitInput) =>
      submitQuotaRequest(resellerId, input, crypto.randomUUID()),
    onSuccess: () => {
      toast.success("Quota request submitted successfully!", {
        description: "Your request is now pending admin approval in 'Your requests' below.",
      });
      setSuccessBanner("Quota request submitted successfully! Pending admin approval.");
      setDelta("");
      setJustification("");
      setCategoryId(1);
      setTierId(1);
      void client.invalidateQueries({
        queryKey: quotaRequestListQueryOptions(resellerId).queryKey,
      });
    },
  });
  useLaraErrorToast(mutation.error, "Could not submit quota request");
  const apiError = formatLaraApiErrorOptional(mutation.error);
  const submitLock = useSubmitLock(mutation.error);

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setValidationError(undefined);
    setSuccessBanner(null);
    const parsed = quotaRequestSubmitSchema.safeParse({
      LicenseCategoryId: categoryId,
      LicenseTierId: tierId,
      RequestedDelta: Number(delta),
      Justification: justification.trim() ? justification.trim() : undefined,
    });
    const isFailed = !parsed.success;
    if (isFailed) {
      setValidationError(parsed.error.issues[0]?.message ?? "Invalid input");

      return;
    }
    mutation.mutate(parsed.data);
  };

  return (
    <form
      onSubmit={onSubmit}
      className="mt-4 grid gap-3 rounded-md border border-border bg-card p-4"
    >
      <div className="grid gap-3 md:grid-cols-3">
        <Field label="License Category">
          <select
            value={categoryId}
            onChange={(e) => setCategoryId(Number(e.target.value))}
            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
          >
            {LICENSE_CATEGORY_IDS.map((id) => (
              <option key={id} value={id}>
                {CATEGORY_LABELS[id] ?? id}
              </option>
            ))}
          </select>
        </Field>
        <Field label="License Tier">
          <select
            value={tierId}
            onChange={(e) => setTierId(Number(e.target.value))}
            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
          >
            {TIER_IDS.map((id) => (
              <option key={id} value={id}>
                Tier {id}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Requested Amount (Quantity)">
          <input
            value={delta}
            placeholder="e.g. 25"
            onChange={(e) => setDelta(e.target.value)}
            inputMode="numeric"
            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
          />
        </Field>
      </div>
      <Field label="Justification (optional)">
        <textarea
          value={justification}
          placeholder="Reason for request (e.g. Need stock for retail customers)"
          onChange={(e) => setJustification(e.target.value)}
          maxLength={1000}
          rows={2}
          className="w-full rounded-md border border-input bg-background px-2 py-1 text-sm"
        />
      </Field>
      {successBanner ? (
        <div
          role="status"
          className="flex items-center gap-2 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-600 dark:text-emerald-400"
        >
          <CheckCircle2 className="size-4 shrink-0" />
          <span>{successBanner}</span>
        </div>
      ) : null}
      <RetryAfterBanner error={mutation.error} onRetry={() => mutation.reset()} />
      {validationError ? (
        <p role="alert" className="text-sm text-destructive">
          {validationError}
        </p>
      ) : null}
      {apiError ? (
        <p role="alert" className="text-sm text-destructive">
          {apiError}
        </p>
      ) : null}
      <div>
        <button
          type="submit"
          disabled={mutation.isPending || submitLock.locked}
          aria-disabled={mutation.isPending || submitLock.locked}
          data-submit-locked={submitLock.locked ? "true" : "false"}
          className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
        >
          {submitLock.locked
            ? `Retry in ${submitLock.remainingSeconds}s`
            : mutation.isPending
              ? "Submitting..."
              : "Submit request"}
        </button>
      </div>
    </form>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium uppercase text-muted-foreground">
        {label}
      </span>
      {children}
    </label>
  );
}
