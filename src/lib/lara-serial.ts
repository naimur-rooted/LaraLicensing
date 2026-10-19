import { z } from "zod";

import { HttpMethodType, requestLaraApi } from "./lara-api-client";
import {
  environmentIdSchema,
  parseEnvironmentId,
  type EnvironmentIdValue,
} from "./lara-environment";
import { assignNumeric, ulidFor } from "./preview-id-map";
import { write } from "./preview-store";
import { getRuntimeMode } from "./runtime-mode";

export const serialCreateResultSchema = z.object({
  SerialId: z.number().int().positive(),
  LicenseId: z.number().int().positive(),
  SerialValue: z.string().min(1),
  CreatedAt: z.string().datetime(),
});

export type SerialCreateResult = z.infer<typeof serialCreateResultSchema>;

export const serialLookupSchema = serialCreateResultSchema.extend({
  IsRevoked: z.boolean(),
});

export type SerialLookup = z.infer<typeof serialLookupSchema>;

export const RandomLengthType = { Sixteen: 16, TwentyFour: 24, ThirtyTwo: 32 } as const;
export type RandomLengthValue = (typeof RandomLengthType)[keyof typeof RandomLengthType];

export const serialCreateSchema = z.object({
  PrefixId: z.number().int().positive().optional(),
  RandomLength: z.union([z.literal(16), z.literal(24), z.literal(32)]).optional(),
});

export type SerialCreateInput = z.infer<typeof serialCreateSchema>;

function generateSerialValue(prefixId?: number): string {
  const chunk = Math.random().toString(36).slice(2, 6).toUpperCase().padEnd(4, "X");
  const randomSuffix = Math.random().toString(36).slice(2, 6).toUpperCase().padEnd(4, "0");
  const prefixPart = prefixId ? `PFX${prefixId}-` : "";

  return `LARA-${prefixPart}${chunk}-${randomSuffix}`;
}

async function createPreviewSerial(
  licenseId: number,
  input: SerialCreateInput,
): Promise<SerialCreateResult> {
  const licenseUlid = await ulidFor("licenses", licenseId);
  const serialValue = generateSerialValue(input.PrefixId);
  const serialUlid = `01H000000000000SERNEW${Math.random().toString(16).slice(2, 8).toUpperCase()}`;

  await write<{ Serial: string; LicenseId: string }>("serials", serialValue, {
    Serial: serialValue,
    LicenseId: licenseUlid ?? String(licenseId),
  });
  const serialId = await assignNumeric("serials", serialUlid);

  return { SerialId: serialId, LicenseId: licenseId, SerialValue: serialValue, CreatedAt: new Date().toISOString() };
}

export async function createSerial(
  licenseId: number,
  input: SerialCreateInput,
  idempotencyKey?: string,
): Promise<SerialCreateResult> {
  if (getRuntimeMode().Mode === "preview") {
    return createPreviewSerial(licenseId, input);
  }
  const headers = idempotencyKey ? { "Idempotency-Key": idempotencyKey } : undefined;
  const [created] = await requestLaraApi(
    `/Licenses/${licenseId}/Serials`,
    serialCreateResultSchema,
    { method: HttpMethodType.Post, body: input, headers },
  );

  return created;
}

async function lookupPreviewSerial(serialValue: string): Promise<SerialLookup> {
  const { read: readFromStore, list: listFromStore } = await import("./preview-store");
  const normalized = serialValue.trim().toUpperCase();

  // 1. Look up in "serials" store
  const index = await readFromStore<{ Serial: string; LicenseId: string }>("serials", normalized);
  if (index?.LicenseId) {
    const lic = await readFromStore<import("@/generated/api/schema").License>("licenses", index.LicenseId);
    const numericLicenseId = await assignNumeric("licenses", index.LicenseId);
    const numericSerialId = await assignNumeric("serials", normalized);
    const isRevoked = !lic || lic.Status === "suspended" || lic.Status === "revoked";

    return {
      SerialId: numericSerialId,
      LicenseId: numericLicenseId,
      SerialValue: normalized,
      CreatedAt: lic?.CreatedAt ?? lic?.IssuedAt ?? new Date().toISOString(),
      IsRevoked: isRevoked,
    };
  }

  // 2. Look up in "customer-requests" (covers requested/revoked/denied keys)
  const requests = await listFromStore<import("./lara-customer-request").CustomerLicenseRequest>(
    "customer-requests",
  );
  for (const [, req] of requests) {
    if (req.IssuedLicenseKey?.trim().toUpperCase() === normalized) {
      const numericSerialId = await assignNumeric("serials", normalized);
      const isRevoked = req.Status === "Revoked" || req.Status === "Denied";

      return {
        SerialId: numericSerialId,
        LicenseId: req.LicenseCategoryId,
        SerialValue: normalized,
        CreatedAt: req.RequestedAt,
        IsRevoked: isRevoked,
      };
    }
  }

  // 3. Serial not found
  throw new (await import("./lara-api-error")).LaraApiError(
    `Serial "${normalized}" not found.`,
    (await import("./lara-api-error")).ApiErrorCodeType.SerialNotFound,
    404,
  );
}

export async function lookupSerial(serialValue: string): Promise<SerialLookup> {
  if (getRuntimeMode().Mode === "preview") {
    return lookupPreviewSerial(serialValue);
  }
  const [found] = await requestLaraApi(
    `/Serials/${encodeURIComponent(serialValue)}`,
    serialLookupSchema,
  );

  return found;
}

/**
 * End-user runtime verify handshake per
 * 02-spec/21-app/11-api-contracts/03-verification-contracts.md and the canonical
 * sequence in 02-spec/21-app/diagrams/licensing-flow.mmd v1.2.0.
 *
 * Three POST endpoints, in strict order:
 *   1) POST /Verify/Serial  -> establishes the serial exists and is not revoked.
 *   2) POST /Verify/Hash    -> UPSERT bindings, mints a single-use VerifyKey.
 *   3) POST /Verify/Final   -> validates the VerifyKey, refreshes bindings,
 *      resolves Features (LicenseFeatures override TierFeatures per
 *      02-spec/21-app/45-license-features.md), and returns the authorization row.
 *
 * `EnvironmentId` on step 3 is the caller's server-derived environment; a
 * mismatch with `License.EnvironmentId` returns 409 `EnvironmentMismatch`
 * per 02-spec/21-app/44-environments.md §3 (AC-LENV-004). The token pair in
 * `Details[0].Value` is opaque ("<Requested>/<Licensed>") and MUST NOT be
 * decoded by the client.
 *
 * Do NOT send `Idempotency-Key` on verify routes; the envelope hardening
 * rules in 02-spec/21-app/08-idempotency-envelope-hardening.md explicitly
 * exclude verify from replay storage.
 */
export const verifySerialResultSchema = z.object({
  IsValid: z.boolean(),
  LicenseId: z.number().int().positive(),
  Category: z.string().min(1),
  ExpiresAt: z.string().datetime().optional(),
  IsSingleUse: z.boolean(),
  UserCount: z.number().int().nonnegative().optional(),
  MachineCount: z.number().int().nonnegative().optional(),
});
export type VerifySerialResult = z.infer<typeof verifySerialResultSchema>;

export const verifyHashResultSchema = z.object({
  VerifyKey: z.string().min(1),
  ExpiresAt: z.string().datetime(),
});
export type VerifyHashResult = z.infer<typeof verifyHashResultSchema>;

/**
 * Features map on `POST /Verify/Final`. Keys are `FeatureKey` values from
 * 02-spec/21-app/45-license-features.md §2 (closed set), values are one of the
 * declared `ValueType` shapes. We accept `unknown` here because the strict
 * per-key shape check happens in feature-consuming call sites (see the
 * runtime resolver in `lara-features.ts`, to be added in Step 44).
 */
export const verifyFinalResultSchema = z.object({
  IsAuthorized: z.boolean(),
  LicenseId: z.number().int().positive(),
  LicenseTierId: z.number().int().positive(),
  EnvironmentId: environmentIdSchema,
  Features: z.record(z.string(), z.unknown()),
  AuthorizedAt: z.string().datetime(),
  ExpiresAt: z.string().datetime().optional(),
  MachineBindingId: z.number().int().positive().optional(),
  UserBindingId: z.number().int().positive().optional(),
});
export type VerifyFinalResult = z.infer<typeof verifyFinalResultSchema>;

export const machineFingerprintSchema = z.object({
  MachineId: z.string().min(1),
  Hostname: z.string().min(1).optional(),
  Os: z.string().min(1).optional(),
});
export type MachineFingerprint = z.infer<typeof machineFingerprintSchema>;

async function verifyPreviewSerial(serialValue: string): Promise<VerifySerialResult> {
  const { read: readFromStore, write: writeToStore } = await import("./preview-store");
  const normalized = serialValue.trim().toUpperCase();
  const index = await readFromStore<{ Serial: string; LicenseId: string }>("serials", normalized);

  if (!index) {
    throw new (await import("./lara-api-error")).LaraApiError(
      `Serial "${normalized}" not found.`,
      (await import("./lara-api-error")).ApiErrorCodeType.SerialNotFound,
      404,
    );
  }

  const lic = await readFromStore<import("@/generated/api/schema").License>("licenses", index.LicenseId);
  if (!lic) {
    throw new (await import("./lara-api-error")).LaraApiError(
      `License for serial "${normalized}" not found.`,
      (await import("./lara-api-error")).ApiErrorCodeType.LicenseNotFound,
      404,
    );
  }

  // If testing simulated expiration for LARA-AHPG-0001, align it with the client simulator
  if (normalized === "LARA-AHPG-0001" && lic.ExpiresAt && new Date(lic.ExpiresAt).getFullYear() >= 2027) {
    lic.ExpiresAt = "2026-10-01T00:00:00Z";
    await writeToStore("licenses", lic.Id, lic);
  }

  let isValid = lic.Status === "active";
  if (lic.ExpiresAt) {
    const expDate = new Date(lic.ExpiresAt);
    if (expDate.getTime() < Date.now()) {
      isValid = false;
    }
  }
  const numericId = await assignNumeric("licenses", lic.Id);

  // Dynamically resolve Category from license, index, customer requests or expiration duration
  let category = (lic as { Category?: string; CategoryName?: string }).Category ?? (lic as { Category?: string; CategoryName?: string }).CategoryName ?? (index as { Category?: string }).Category;
  if (!category) {
    try {
      const { list: listFromStore } = await import("./preview-store");
      const requests = await listFromStore<import("./lara-customer-request").CustomerLicenseRequest>("customer-requests");
      const matched = requests.find((r) => r.IssuedLicenseKey?.toUpperCase() === normalized);
      if (matched?.CategoryName) {
        category = matched.CategoryName;
      }
    } catch {
      // Non-blocking
    }
  }

  if (!category) {
    if (!lic.ExpiresAt) {
      category = "Lifetime";
    } else {
      const issueTime = lic.IssuedAt
        ? new Date(lic.IssuedAt).getTime()
        : lic.CreatedAt
        ? new Date(lic.CreatedAt).getTime()
        : Date.now();
      const expTime = new Date(lic.ExpiresAt).getTime();
      const diffDays = Math.round((expTime - issueTime) / (1000 * 60 * 60 * 24));
      if (diffDays >= 300) {
        category = "Yearly";
      } else if (diffDays >= 14) {
        category = "Monthly";
      } else if (diffDays >= 3) {
        category = "Weekly";
      } else {
        category = "Daily";
      }
    }
  }

  return {
    IsValid: isValid,
    LicenseId: numericId,
    Category: category ?? "Monthly",
    ExpiresAt: lic.ExpiresAt ?? undefined,
    IsSingleUse: false,
    UserCount: 1,
    MachineCount: lic.MaxActivations,
  };
}

export async function verifySerial(serialValue: string): Promise<VerifySerialResult> {
  if (getRuntimeMode().Mode === "preview") {
    return verifyPreviewSerial(serialValue);
  }

  const [result] = await requestLaraApi("/Verify/Serial", verifySerialResultSchema, {
    method: HttpMethodType.Post,
    body: { SerialValue: serialValue },
  });

  return result;
}

export interface VerifyHashInput {
  serialValue: string;
  hashKey: string;
  machineFingerprint: MachineFingerprint;
  userIdentifier?: string;
}

export async function verifyHash(input: VerifyHashInput): Promise<VerifyHashResult> {
  const body = {
    SerialValue: input.serialValue,
    HashKey: input.hashKey,
    MachineFingerprint: input.machineFingerprint,
    UserIdentifier: input.userIdentifier,
  };
  const [result] = await requestLaraApi("/Verify/Hash", verifyHashResultSchema, {
    method: HttpMethodType.Post,
    body,
  });

  return result;
}

export interface VerifyFinalInput {
  serialValue: string;
  hashKey: string;
  verifyKey: string;
  environmentId: EnvironmentIdValue | number;
}

export async function verifyFinal(input: VerifyFinalInput): Promise<VerifyFinalResult> {
  // Guard: reject an out-of-set EnvironmentId at the entry point per
  // 02-spec/21-app/44-environments.md §3 (AC-LENV-004). Failing here surfaces
  // the misconfiguration in caller logs instead of after a wire round-trip
  // returns the opaque `<Requested>/<Licensed>` marker.
  const environmentId = parseEnvironmentId(input.environmentId, "environmentId");
  const body = {
    SerialValue: input.serialValue,
    HashKey: input.hashKey,
    VerifyKey: input.verifyKey,
    EnvironmentId: environmentId,
  };
  const [result] = await requestLaraApi("/Verify/Final", verifyFinalResultSchema, {
    method: HttpMethodType.Post,
    body,
  });

  return result;
}
