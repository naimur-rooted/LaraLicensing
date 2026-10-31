import { useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import { FieldEmail, FieldPassword } from "./AuthFormFields";
import { CaptchaField } from "./CaptchaField";
import { RememberMeRow, LoginSubmitButton } from "./CustomerLoginActions";
import { useLoginForm } from "./use-login-form";

export function CustomerLoginForm(props: { redirectTo?: string }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const form = useLoginForm({
    onSuccess: async () => {
      await queryClient.resetQueries({ queryKey: ["LaraApi", "Users", "Me"] });
      if (props.redirectTo) window.location.href = props.redirectTo;
      else await navigate({ to: "/portal", replace: true });
    },
  });

  return (
    <form onSubmit={form.handleSubmit} className="space-y-4" aria-describedby="login-help">
      <FieldEmail value={form.email} onChange={form.setEmail} disabled={form.submitting} id="customer-email" />
      <FieldPassword
        value={form.password}
        onChange={form.setPassword}
        disabled={form.submitting}
        showPassword={form.showPassword}
        onToggleShow={() => form.setShowPassword((v) => !v)}
        onCapsLockChange={form.setCapsLockOn}
        inputRef={form.passwordRef}
        id="customer-password"
      />
      {form.capsLockOn && <p className="-mt-2 text-xs font-medium text-amber-500">Caps Lock is on.</p>}
      <RememberMeRow rememberMe={form.rememberMe} onChange={form.setRememberMe} disabled={form.submitting} />
      {form.captcha && (
        <CaptchaField
          challenge={form.captcha}
          answer={form.captchaAnswer}
          onChange={form.setCaptchaAnswer}
          onRefresh={form.refreshCaptcha}
          loading={form.captchaLoading}
          disabled={form.submitting}
          id="customer-captcha"
        />
      )}
      {form.errorMessage && (
        <div role="alert" className="rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-xs text-destructive">
          {form.errorMessage}
        </div>
      )}
      <LoginSubmitButton submitting={form.submitting} />
      <p id="login-help" className="text-center text-[11px] text-muted-foreground">
        Protected by rate-limits and HMAC-signed CAPTCHA.
      </p>
    </form>
  );
}
