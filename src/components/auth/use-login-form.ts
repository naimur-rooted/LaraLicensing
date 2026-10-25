import { useEffect, useRef, useState, type FormEvent } from "react";
import { ApiErrorCodeType, LaraApiError, formatLaraApiError } from "@/lib/lara-api-error";
import { fetchLoginCaptcha, loginToLaraApi, type LaraCaptchaChallenge } from "@/lib/lara-auth";
import { persistRememberChoice, readRememberedEmail, readRememberMeDefault } from "@/lib/auth-remember";

interface UseLoginFormOptions {
  onSuccess: () => Promise<void> | void;
}

export function useLoginForm(options: UseLoginFormOptions) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [rememberMe, setRememberMe] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [capsLockOn, setCapsLockOn] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>(undefined);
  const [captcha, setCaptcha] = useState<LaraCaptchaChallenge | undefined>(undefined);
  const [captchaAnswer, setCaptchaAnswer] = useState("");
  const [captchaLoading, setCaptchaLoading] = useState(false);
  const passwordRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    setEmail(readRememberedEmail());
    setRememberMe(readRememberMeDefault());
  }, []);

  async function refreshCaptcha() {
    setCaptchaLoading(true);
    try {
      const next = await fetchLoginCaptcha();
      setCaptcha(next);
      setCaptchaAnswer("");
    } catch (error) {
      setErrorMessage(formatLaraApiError(error));
    } finally {
      setCaptchaLoading(false);
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setErrorMessage(undefined);
    try {
      await loginToLaraApi({
        Email: email,
        Password: password,
        RememberMe: rememberMe,
        CaptchaChallengeId: captcha?.ChallengeId,
        CaptchaAnswer: captcha ? captchaAnswer : undefined,
      });
      persistRememberChoice(rememberMe, email);
      await options.onSuccess();
    } catch (error) {
      if (error instanceof LaraApiError && error.errorCode === ApiErrorCodeType.LoginCaptchaRequired) {
        await refreshCaptcha();
        setErrorMessage("Security check required. Solve challenge below.");
        return;
      }
      setErrorMessage(formatLaraApiError(error));
    } finally {
      setSubmitting(false);
    }
  }

  return {
    email,
    setEmail,
    password,
    setPassword,
    rememberMe,
    setRememberMe,
    showPassword,
    setShowPassword,
    capsLockOn,
    setCapsLockOn,
    submitting,
    errorMessage,
    captcha,
    captchaAnswer,
    setCaptchaAnswer,
    captchaLoading,
    passwordRef,
    refreshCaptcha,
    handleSubmit,
  };
}
