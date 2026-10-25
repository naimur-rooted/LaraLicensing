import "fake-indexeddb/auto";
import { beforeEach, describe, expect, it } from "vitest";
import { resetAll, write } from "@/lib/preview-store";
import {
  loginToLaraApi,
  registerDeveloperViaLara,
} from "@/lib/lara-auth";
import { clearLaraSession, getLaraAccessToken } from "@/lib/lara-api-session";
import { ApiErrorCodeType, LaraApiError } from "@/lib/lara-api-error";
import { freezeRuntimeMode } from "@/lib/runtime-mode";

describe("lara-auth preview credential verification", () => {
  beforeEach(async () => {
    await resetAll();
    clearLaraSession();
    freezeRuntimeMode({
      Mode: "preview",
      ApiBaseUrl: null,
      PreviewSeed: "default",
    });
  });

  it("rejects login for an account that does not exist", async () => {
    await expect(
      loginToLaraApi({
        Email: "unknown.user@doesnotexist.test",
        Password: "RandomPassword123!",
      }),
    ).rejects.toThrow(LaraApiError);

    try {
      await loginToLaraApi({
        Email: "unknown.user@doesnotexist.test",
        Password: "RandomPassword123!",
      });
    } catch (err) {
      const apiError = err as LaraApiError;
      expect(apiError.errorCode).toBe(ApiErrorCodeType.AuthInvalidCredentials);
      expect(apiError.httpStatus).toBe(401);
    }
  });

  it("rejects login with wrong password for seeded credential", async () => {
    await write<Record<string, string>>("auth", "credentials", {
      "seeded.dev@lara.local": "CorrectSecret123!",
    });

    await expect(
      loginToLaraApi({
        Email: "seeded.dev@lara.local",
        Password: "WrongPassword!",
      }),
    ).rejects.toThrow(LaraApiError);
  });

  it("accepts login with correct password for seeded credential", async () => {
    await write<Record<string, string>>("auth", "credentials", {
      "seeded.dev@lara.local": "CorrectSecret123!",
    });

    const result = await loginToLaraApi({
      Email: "seeded.dev@lara.local",
      Password: "CorrectSecret123!",
    });

    expect(result.AccessToken).toBeTruthy();
    expect(getLaraAccessToken()).toBe(result.AccessToken);
  });

  it("allows newly registered developer to log in with registered password", async () => {
    const regResult = await registerDeveloperViaLara({
      Email: "new.developer@studio.test",
      Password: "SuperSecurePassword123!",
      DisplayName: "New Dev",
      SelectedPlan: "PRO",
    });

    expect(regResult.Token).toBeTruthy();

    clearLaraSession();

    // Now log in with the new account
    const loginResult = await loginToLaraApi({
      Email: "new.developer@studio.test",
      Password: "SuperSecurePassword123!",
    });

    expect(loginResult.AccessToken).toBeTruthy();
    expect(getLaraAccessToken()).toBe(loginResult.AccessToken);
  });

  it("rejects duplicate developer registration in preview mode", async () => {
    await registerDeveloperViaLara({
      Email: "duplicate@studio.test",
      Password: "Password12345!",
      SelectedPlan: "STARTER",
    });

    await expect(
      registerDeveloperViaLara({
        Email: "duplicate@studio.test",
        Password: "Password12345!",
        SelectedPlan: "STARTER",
      }),
    ).rejects.toThrow(LaraApiError);
  });
});
