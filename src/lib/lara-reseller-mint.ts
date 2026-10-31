import { z } from "zod";

import { HttpMethodType, requestLaraApi } from "./lara-api-client";
import { resellerLicenseSchema, type ResellerLicense } from "./lara-reseller-license";
import { getRuntimeMode } from "./runtime-mode";

export const LicenseCategoryCodeType = {
  Daily: "D",
  Weekly: "W",
  Monthly: "M",
  Yearly: "Y",
  Lifetime: "L",
  "Standard Key": "K",
} as const;

export type LicenseCategoryName = keyof typeof LicenseCategoryCodeType;

export const licenseMintInputSchema = z.object({
  PrefixValue: z.string().min(3).max(12),
  TierName: z.enum(["Starter", "Growth", "Scale", "Enterprise"]),
  EnvironmentName: z.enum(["Production", "Staging", "Sandbox"]),
  LicenseCategory: z.enum([
    "Daily",
    "Weekly",
    "Monthly",
    "Yearly",
    "Lifetime",
    "Standard Key",
  ]),
  ProductSlug: z.string().optional(),
  ExpiresAt: z.string().nullable().optional(),
});

export type LicenseMintInput = z.infer<typeof licenseMintInputSchema>;

export function getCategoryCode(category: string): string {
  if (category in LicenseCategoryCodeType) {
    return LicenseCategoryCodeType[category as LicenseCategoryName];
  }

  return "M";
}

export function generateRandomEntropy(): string {
  const segment1 = Math.random().toString(36).slice(2, 6).toUpperCase();
  const segment2 = Math.random().toString(36).slice(2, 6).toUpperCase();
  const segment3 = Math.random().toString(36).slice(2, 6).toUpperCase();

  return `${segment1}-${segment2}-${segment3}`;
}

export function formatSerialPreview(
  prefix: string,
  category: string,
  version = "V1",
  entropy?: string,
): string {
  const safePrefix = prefix.trim().toUpperCase() || "ALIM";
  const catCode = getCategoryCode(category);
  const safeEntropy = entropy || "XXXX-XXXX-XXXX";

  return `${safePrefix}-${catCode}-${version}-${safeEntropy}`;
}

function mintPreviewLicense(input: LicenseMintInput): ResellerLicense {
  const entropy = generateRandomEntropy();
  const serial = formatSerialPreview(input.PrefixValue, input.LicenseCategory, "V1", entropy);
  const now = new Date().toISOString();

  return {
    LicenseId: Math.floor(Math.random() * 9000) + 1000,
    LicenseKey: serial,
    PrefixValue: input.PrefixValue.toUpperCase(),
    ResellerId: 1,
    IssuedByUserId: 1,
    IssuerActorType: "Reseller",
    TierName: input.TierName,
    EnvironmentName: input.EnvironmentName,
    ProductVersion: "1.0.0",
    Status: "Active",
    IssuedAt: now,
    ExpiresAt: input.ExpiresAt ?? "",
    RevokedAt: "",
    RevokeReason: "",
    Version: 1,
  };
}

export async function issueResellerLicense(
  input: LicenseMintInput,
  idempotencyKey?: string,
): Promise<ResellerLicense> {
  const isPreview = getRuntimeMode().Mode === "preview";
  if (isPreview) {
    return mintPreviewLicense(input);
  }

  const headers = idempotencyKey ? { "Idempotency-Key": idempotencyKey } : undefined;
  const [created] = await requestLaraApi("/Reseller/Licenses", resellerLicenseSchema, {
    method: HttpMethodType.Post,
    body: input,
    headers,
  });

  return created;
}
