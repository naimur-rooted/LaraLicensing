import { useState, type FormEvent } from "react";
import { lookupSerial, type SerialLookup } from "../../lib/lara-serial";
import { formatLaraApiError } from "../../lib/lara-api-error";

export function SerialLookupPanel() {
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
    <div className="space-y-4">
      <form onSubmit={onSubmit} className="rounded-xl border border-border bg-card p-5 space-y-3">
        <label className="block text-sm">
          <span className="mb-1 block font-medium">Serial value</span>
          <input
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder="e.g. LARA-ABCD-1234"
            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-ring"
          />
        </label>
        {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : null}
        <button
          type="submit"
          disabled={submitting || value.trim() === ""}
          className="focus-ring inline-flex h-9 items-center rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
        >
          {submitting ? "Looking up..." : "Look up serial"}
        </button>
      </form>
      {result ? <LookupResultCard result={result} /> : null}
    </div>
  );
}

function LookupResultCard({ result }: { result: SerialLookup }) {
  return (
    <div className="rounded-xl border border-border bg-card p-5 space-y-3">
      <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Serial Result</p>
      <p className="font-mono text-sm break-all font-medium text-foreground">{result.SerialValue}</p>
      <dl className="grid grid-cols-2 gap-3 text-xs pt-2 border-t border-border">
        <div>
          <dt className="text-muted-foreground">License ID</dt>
          <dd className="font-semibold text-foreground">#{result.LicenseId}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Revocation Status</dt>
          <dd className={result.IsRevoked ? "text-destructive font-semibold" : "text-emerald-600 dark:text-emerald-400 font-semibold"}>
            {result.IsRevoked ? "Revoked" : "Active / Valid"}
          </dd>
        </div>
      </dl>
    </div>
  );
}
