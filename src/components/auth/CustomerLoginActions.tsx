import { Link } from "@tanstack/react-router";
import { ArrowRight, Loader2 } from "lucide-react";

interface RememberMeRowProps {
  rememberMe: boolean;
  onChange: (val: boolean) => void;
  disabled: boolean;
}

export function RememberMeRow(props: RememberMeRowProps) {
  return (
    <div className="flex items-center justify-between text-xs">
      <label className="inline-flex cursor-pointer select-none items-center gap-2 text-foreground">
        <input
          type="checkbox"
          checked={props.rememberMe}
          onChange={(e) => props.onChange(e.target.checked)}
          disabled={props.disabled}
          className="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring"
        />
        Remember me for 30 days
      </label>
      <Link to="/forgot-password" className="text-primary hover:underline">
        Forgot password?
      </Link>
    </div>
  );
}

interface LoginSubmitButtonProps {
  submitting: boolean;
}

export function LoginSubmitButton(props: LoginSubmitButtonProps) {
  return (
    <button
      type="submit"
      disabled={props.submitting}
      className="group inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
    >
      {props.submitting ? (
        <>
          <Loader2 aria-hidden className="size-4 animate-spin" />
          <span>Signing in...</span>
        </>
      ) : (
        <>
          <span>Sign in</span>
          <ArrowRight aria-hidden className="size-4 transition-transform group-hover:translate-x-0.5" />
        </>
      )}
    </button>
  );
}
