import { RefreshCw } from "lucide-react";
import type { LaraCaptchaChallenge } from "@/lib/lara-auth";

interface CaptchaFieldProps {
  challenge: LaraCaptchaChallenge;
  answer: string;
  onChange: (v: string) => void;
  onRefresh: () => void;
  loading: boolean;
  disabled: boolean;
  id?: string;
}

export function CaptchaField(props: CaptchaFieldProps) {
  const inputId = props.id ?? "auth-captcha";

  return (
    <div className="space-y-1.5">
      <label htmlFor={inputId} className="text-sm font-medium text-foreground">
        Security check
      </label>
      <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/40 p-3">
        <div className="flex flex-1 items-center gap-3">
          <span
            aria-label="Captcha challenge"
            className="rounded-md bg-background px-3 py-2 font-mono text-base font-semibold tracking-wider text-foreground shadow-inner"
          >
            {props.challenge.Question} = ?
          </span>
          <input
            id={inputId}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            required
            disabled={props.disabled}
            value={props.answer}
            onChange={(event) => props.onChange(event.target.value)}
            placeholder="Answer"
            className="h-10 w-24 rounded-md border border-input bg-background px-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
        </div>
        <button
          type="button"
          onClick={props.onRefresh}
          disabled={props.loading || props.disabled}
          aria-label="Refresh challenge"
          className="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted disabled:opacity-60"
        >
          <RefreshCw aria-hidden className={`size-4 ${props.loading ? "animate-spin" : ""}`} />
        </button>
      </div>
    </div>
  );
}
