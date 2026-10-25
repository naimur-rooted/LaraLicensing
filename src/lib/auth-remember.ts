const REMEMBER_ME_STORAGE_KEY = "lara.login.remember-me";
const REMEMBERED_EMAIL_STORAGE_KEY = "lara.login.remembered-email";

export function readRememberMeDefault(): boolean {
  if (typeof window === "undefined") return false;
  try {
    return window.localStorage.getItem(REMEMBER_ME_STORAGE_KEY) === "true";
  } catch {
    return false;
  }
}

export function readRememberedEmail(): string {
  if (typeof window === "undefined") return "";
  try {
    return window.localStorage.getItem(REMEMBERED_EMAIL_STORAGE_KEY) ?? "";
  } catch {
    return "";
  }
}

export function persistRememberChoice(remember: boolean, email: string): void {
  if (typeof window === "undefined") return;
  try {
    if (remember) {
      window.localStorage.setItem(REMEMBER_ME_STORAGE_KEY, "true");
      window.localStorage.setItem(REMEMBERED_EMAIL_STORAGE_KEY, email);
    } else {
      window.localStorage.removeItem(REMEMBER_ME_STORAGE_KEY);
      window.localStorage.removeItem(REMEMBERED_EMAIL_STORAGE_KEY);
    }
  } catch {
    // Storage may be blocked (private mode); login still succeeds.
  }
}
