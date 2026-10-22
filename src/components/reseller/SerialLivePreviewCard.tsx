import { useState } from "react";
import { Check, Copy, Sparkles } from "lucide-react";
import { toast } from "sonner";

export interface SerialLivePreviewCardProps {
  serialPreview: string;
  isMinted: boolean;
}

export function SerialLivePreviewCard({ serialPreview, isMinted }: SerialLivePreviewCardProps) {
  const [hasCopied, setHasCopied] = useState(false);

  async function handleCopy() {
    await navigator.clipboard.writeText(serialPreview);
    setHasCopied(true);
    toast.success("Serial key copied to clipboard");
    setTimeout(() => {
      setHasCopied(false);
    }, 2000);
  }

  return (
    <div className="rounded-xl border border-primary/20 bg-card p-6 shadow-sm">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Sparkles className="size-4 text-primary" />
          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            {isMinted ? "Minted License Serial" : "Live Serial Key Preview"}
          </span>
        </div>
        <span
          className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
            isMinted
              ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
              : "bg-primary/10 text-primary"
          }`}
        >
          {isMinted ? "Verified & Active" : "Format Spec: V1"}
        </span>
      </div>

      <div className="mt-4 flex flex-col items-center justify-between gap-4 rounded-lg border border-border bg-muted/40 p-4 sm:flex-row">
        <code className="font-mono text-base font-bold tracking-wider text-foreground sm:text-lg">
          {serialPreview}
        </code>
        <button
          type="button"
          onClick={handleCopy}
          className="focus-ring inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
        >
          {hasCopied ? (
            <>
              <Check className="size-3.5" />
              <span>Copied!</span>
            </>
          ) : (
            <>
              <Copy className="size-3.5" />
              <span>Copy Serial</span>
            </>
          )}
        </button>
      </div>
      <p className="mt-3 text-xs text-muted-foreground">
        Deterministic format: [PREFIX]-[CATEGORY]-[VERSION]-[ENTROPY]. Encodes tier & expiration.
      </p>
    </div>
  );
}
