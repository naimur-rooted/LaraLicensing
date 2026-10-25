import { z } from "zod";

import { HttpMethodType, requestLaraApi } from "./lara-api-client";
import { setLaraAccessToken, setLaraRefreshToken } from "./lara-api-session";
import { getRuntimeMode } from "./runtime-mode";
import { write } from "./preview-store";
import type { MeUser } from "@/generated/api/schema";

const tokenResultSchema = z.object({
  AccessToken: z.string().min(1),
  RefreshToken: z.string().min(1),
  TokenType: z.literal("Bearer"),
  ExpiresIn: z.number().int().positive(),
});

export type LaraTokenResult = z.infer<typeof tokenResultSchema>;

export interface LaraLoginRequest {
  Email: string;
  Password: string;
  RememberMe?: boolean;
  CaptchaChallengeId?: string;
  CaptchaAnswer?: string;
}

const captchaChallengeSchema = z.object({
  ChallengeId: z.string().min(1),
  Question: z.string().min(1),
  ExpiresAt: z.string().min(1),
});

export type LaraCaptchaChallenge = z.infer<typeof captchaChallengeSchema>;

async function createPreviewLoginSession(email: string): Promise<LaraTokenResult> {
  const clean = email.replace(/[^a-zA-Z0-9]/g, "_");
  const access = `seed_access_${clean}_${Date.now()}`;
  const refresh = `seed_refresh_${clean}_${Date.now()}`;
  setLaraAccessToken(access);
  setLaraRefreshToken(refresh);

  return { AccessToken: access, RefreshToken: refresh, TokenType: "Bearer", ExpiresIn: 3600 };
}

export async function loginToLaraApi(input: LaraLoginRequest): Promise<LaraTokenResult> {
  if (getRuntimeMode().Mode === "preview") {
    return createPreviewLoginSession(input.Email);
  }
  const [result] = await requestLaraApi("/Auth/Token", tokenResultSchema, {
    method: HttpMethodType.Post,
    body: input,
  });
  setLaraAccessToken(result.AccessToken);
  setLaraRefreshToken(result.RefreshToken);

  return result;
}

export async function fetchLoginCaptcha(): Promise<LaraCaptchaChallenge> {
  if (getRuntimeMode().Mode === "preview") {
    return {
      ChallengeId: "preview-captcha-challenge",
      Question: "5 + 3",
      ExpiresAt: new Date(Date.now() + 300000).toISOString(),
    };
  }
  const [result] = await requestLaraApi("/Auth/Captcha", captchaChallengeSchema, {
    method: HttpMethodType.Get,
  });

  return result;
}

const registerResultSchema = z.object({
  UserId: z.number().int().positive(),
  Email: z.string().email(),
  SessionId: z.string().uuid(),
  ExpiresAt: z.string().min(1),
  Token: z.string().min(1),
  Roles: z.array(z.string()).min(1),
});

export type LaraRegisterResult = z.infer<typeof registerResultSchema>;

export interface LaraRegisterRequest {
  Email: string;
  Password: string;
}

export async function registerViaLara(input: LaraRegisterRequest): Promise<LaraRegisterResult> {
  const [result] = await requestLaraApi("/Auth/Register", registerResultSchema, {
    method: HttpMethodType.Post,
    body: input,
  });
  setLaraAccessToken(result.Token);

  return result;
}

export interface LaraDeveloperRegisterRequest {
  Email: string;
  Password: string;
  DisplayName?: string;
  CompanyName?: string;
  SelectedPlan: string;
  BillingInterval?: string;
}

const developerRegisterResultSchema = z.object({
  UserId: z.number().int().positive(),
  Email: z.string().email(),
  Role: z.string(),
  SessionId: z.string(),
  ExpiresAt: z.string(),
  Token: z.string().min(1),
  Subscription: z.object({
    PlanCode: z.string(),
    PlanName: z.string(),
    BillingInterval: z.string(),
    MaxProducts: z.number(),
    DailyQuota: z.number(),
    HourlyQuota: z.number(),
    MaxMachineSeats: z.number(),
    Status: z.string(),
  }).optional(),
});

export type LaraDeveloperRegisterResult = z.infer<typeof developerRegisterResultSchema>;

async function writePreviewMeUser(email: string, displayName?: string): Promise<void> {
  try {
    await write<MeUser>("me", "current", {
      Id: "01H00000000000000DEV00001",
      Email: email,
      DisplayName: displayName ?? "Developer",
      Roles: ["Developer", "AppBuilder", "EndUser"],
      ResellerId: null,
      CreatedAt: new Date().toISOString(),
      UpdatedAt: new Date().toISOString(),
    });
  } catch {
    // preview store optional
  }
}

function buildPreviewDeveloperResult(email: string, token: string): LaraDeveloperRegisterResult {
  return {
    UserId: 999,
    Email: email,
    Role: "Developer",
    SessionId: "preview-developer-session",
    ExpiresAt: new Date(Date.now() + 86400000).toISOString(),
    Token: token,
  };
}

async function createPreviewDeveloperSession(
  input: LaraDeveloperRegisterRequest,
): Promise<LaraDeveloperRegisterResult> {
  const token = `seed_access_developer_${Date.now()}`;
  setLaraAccessToken(token);
  setLaraRefreshToken(`seed_refresh_developer_${Date.now()}`);
  await writePreviewMeUser(input.Email, input.DisplayName);
  return buildPreviewDeveloperResult(input.Email, token);
}

export async function registerDeveloperViaLara(
  input: LaraDeveloperRegisterRequest,
): Promise<LaraDeveloperRegisterResult> {
  if (getRuntimeMode().Mode === "preview") {
    return createPreviewDeveloperSession(input);
  }
  const [result] = await requestLaraApi(
    "/v1/auth/developer-register",
    developerRegisterResultSchema,
    {
      method: HttpMethodType.Post,
      body: input,
    },
  );
  setLaraAccessToken(result.Token);

  return result;
}
