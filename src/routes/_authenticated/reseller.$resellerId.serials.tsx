import { useState, type FormEvent } from "react";
import { createFileRoute } from "@tanstack/react-router";

import { PageHeader } from "../../components/shell/PageHeader";
import { formatLaraApiError } from "../../lib/lara-api-error";
import { lookupSerial, type SerialLookup } from "../../lib/lara-serial";

export const Route = createFileRoute("/_authenticated/reseller/$resellerId/serials")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Serial lookup | Reseller Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: ResellerSerialLookupPage,
});

const INPUT_CLASS = "h-9 w-full rounded-md border border-input bg-background px-3 text-sm";
const SUBMIT_CLASS =
  "focus-ring inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60";
const CARD_CLASS = "rounded-md border border-border bg-card p-6";

function ResellerSerialLookupPage() {
  const { resellerId } = Route.useParams();
  const [value, setValue] = useState("");
  const [result, setResult] = useState<SerialLookup | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setResult(null);
    setSubmitting(true);
    try {
      setResult(await lookupSerial(value.trim()));
    } catch (lookupError) {
      setError(formatLaraApiError(lookupError));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Serial key lookup"
        breadcrumbs={[
          { label: "Reseller", to: `/reseller/${resellerId}/licenses` },
          { label: "Serials" },
        ]}
        description="Verify an issued serial key and inspect machine binding status."
      />
      <SerialForm
        value={value}
        onChange={setValue}
        onSubmit={onSubmit}
        submitting={submitting}
        error={error}
      />
      {result ? <SerialResultCard result={result} /> : null}
    </div>
  );
}

interface SerialFormProps {
  value: string;
  onChange: (v: string) => void;
  onSubmit: (e: FormEvent<HTMLFormElement>) => void;
  submitting: boolean;
  error: string | null;
}

function SerialForm(props: SerialFormProps) {
  return (
    <form onSubmit={props.onSubmit} className={`${CARD_CLASS} max-w-3xl space-y-3`} noValidate>
      <label className="block text-sm">
        <span className="mb-1 block font-medium">Serial value</span>
        <input
          value={props.value}
          onChange={(e) => props.onChange(e.target.value)}
          placeholder="e.g. LARA-ABCD-1234"
          required
          className={INPUT_CLASS}
        />
      </label>
      {props.error ? (
        <p role="alert" className="text-sm text-destructive">
          {props.error}
        </p>
      ) : null}
      <button
        type="submit"
        disabled={props.submitting || props.value.trim() === ""}
        className={SUBMIT_CLASS}
      >
        {props.submitting ? "Looking up..." : "Look up serial"}
      </button>
    </form>
  );
}

function SerialResultCard({ result }: { result: SerialLookup }) {
  return (
    <div className={`${CARD_CLASS} max-w-3xl space-y-3`}>
      <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Serial Result</p>
      <p className="font-mono text-sm break-all font-medium text-foreground">{result.SerialValue}</p>
      <dl className="grid grid-cols-2 gap-3 text-sm pt-2 border-t border-border">
        <div>
          <dt className="text-muted-foreground text-xs">Serial ID</dt>
          <dd className="font-semibold text-foreground">#{result.SerialId}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">License ID</dt>
          <dd className="font-semibold text-foreground">#{result.LicenseId}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">Revoked Status</dt>
          <dd className={result.IsRevoked ? "text-destructive font-semibold" : "text-emerald-600 dark:text-emerald-400 font-semibold"}>
            {result.IsRevoked ? "Revoked" : "Active / Valid"}
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">Created At</dt>
          <dd className="text-foreground">{new Date(result.CreatedAt).toLocaleString()}</dd>
        </div>
      </dl>
    </div>
  );
}
