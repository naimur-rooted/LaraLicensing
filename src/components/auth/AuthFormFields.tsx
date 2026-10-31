import type { MutableRefObject } from "react";
import { Mail, Lock, Eye, EyeOff } from "lucide-react";

interface FieldEmailProps {
  value: string;
  onChange: (v: string) => void;
  disabled: boolean;
  id?: string;
}

export function FieldEmail(props: FieldEmailProps) {
  const inputId = props.id ?? "auth-email";

  return (
    <div className="space-y-1.5">
      <label htmlFor={inputId} className="text-sm font-medium text-foreground">
        Email
      </label>
      <div className="relative">
        <Mail
          aria-hidden
          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
          id={inputId}
          type="email"
          required
          autoComplete="email"
          disabled={props.disabled}
          value={props.value}
          onChange={(event) => props.onChange(event.target.value)}
          placeholder="you@company.com"
          className="h-11 w-full rounded-lg border border-input bg-background pl-10 pr-3 text-sm text-foreground placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-60"
        />
      </div>
    </div>
  );
}

interface FieldPasswordProps {
  value: string;
  onChange: (v: string) => void;
  disabled: boolean;
  showPassword: boolean;
  onToggleShow: () => void;
  onCapsLockChange: (on: boolean) => void;
  inputRef: MutableRefObject<HTMLInputElement | null>;
  id?: string;
}

export function FieldPassword(props: FieldPasswordProps) {
  const inputId = props.id ?? "auth-password";

  return (
    <div className="space-y-1.5">
      <label htmlFor={inputId} className="text-sm font-medium text-foreground">
        Password
      </label>
      <div className="relative">
        <Lock
          aria-hidden
          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
          ref={props.inputRef}
          id={inputId}
          type={props.showPassword ? "text" : "password"}
          required
          minLength={6}
          maxLength={128}
          autoComplete="current-password"
          disabled={props.disabled}
          value={props.value}
          onChange={(event) => props.onChange(event.target.value)}
          onKeyUp={(event) => props.onCapsLockChange(event.getModifierState("CapsLock"))}
          onKeyDown={(event) => props.onCapsLockChange(event.getModifierState("CapsLock"))}
          className="h-11 w-full rounded-lg border border-input bg-background pl-10 pr-11 text-sm text-foreground placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-60"
        />
        <button
          type="button"
          onClick={props.onToggleShow}
          tabIndex={-1}
          aria-label={props.showPassword ? "Hide password" : "Show password"}
          aria-pressed={props.showPassword}
          className="absolute right-2 top-1/2 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          {props.showPassword ? <EyeOff aria-hidden className="size-4" /> : <Eye aria-hidden className="size-4" />}
        </button>
      </div>
    </div>
  );
}
