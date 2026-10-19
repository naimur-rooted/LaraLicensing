import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";

import { apiClient } from "./api-client";
import { HttpMethodType, requestLaraApi } from "./lara-api-client";
import {
  EnvironmentIdType,
  ENVIRONMENT_IDS,
  environmentIdSchema,
  type EnvironmentIdValue,
} from "./lara-environment";
import { assignNumeric, numericFor, ulidFor } from "./preview-id-map";
import { getRuntimeMode } from "./runtime-mode";
import type { License as PreviewLicense } from "@/generated/api/schema";

export { EnvironmentIdType, ENVIRONMENT_IDS };
export type { EnvironmentIdValue };

/**
 * Closed sets are normative in:
 *   - 02-spec/21-app/05-license-categories.md §Canonical set (Daily..Key, ordinals 1..7; AC-CAT-005)
 *   - 02-spec/21-app/43-license-tiers.md §2 (Tier1..Unlimited, ordinals 1..4; AC-LT-002)
 *   - 02-spec/21-app/44-environments.md §2 (owned by src/lib/lara-environment.ts)
 * The FK id equals the ordinal per those owner files. The client MUST reject
 * out-of-set ids BEFORE POST /Licenses fires so the ValidationFailed (400)
 * response is observable on both sides of the wire without spending a server
 * round-trip.
 */
export const LicenseCategoryIdType = {
  Daily: 1,
  Weekly: 2,
  Monthly: 3,
  Yearly: 4,
  Lifetime: 5,
  Dev: 6,
  Key: 7,
} as const;
export type LicenseCategoryIdValue =
  (typeof LicenseCategoryIdType)[keyof typeof LicenseCategoryIdType];
export const LICENSE_CATEGORY_IDS = [1, 2, 3, 4, 5, 6, 7] as const;

export const LicenseTierIdType = { Tier1: 1, Tier2: 2, Tier3: 3, Unlimited: 4 } as const;
export type LicenseTierIdValue = (typeof LicenseTierIdType)[keyof typeof LicenseTierIdType];
export const LICENSE_TIER_IDS = [1, 2, 3, 4] as const;

const licenseCategoryIdSchema = z.union([
  z.literal(1),
  z.literal(2),
  z.literal(3),
  z.literal(4),
  z.literal(5),
  z.literal(6),
  z.literal(7),
]);

const licenseTierIdSchema = z.union([z.literal(1), z.literal(2), z.literal(3), z.literal(4)]);

export const licenseSchema = z.object({
  LicenseId: z.number().int().positive(),
  LicenseCategoryId: licenseCategoryIdSchema,
  LicenseTierId: licenseTierIdSchema,
  EnvironmentId: environmentIdSchema,
  LicensePackageId: z.number().int().positive().nullable().optional(),
  ResellerId: z.number().int().positive().nullable().optional(),
  IssuedByUserId: z.number().int().positive(),
  ProductVersion: z.string().min(1),
  IsActive: z.boolean(),
  IssuedAt: z.string().datetime(),
  ExpiresAt: z.string().datetime().nullable().optional(),
  UserCount: z.number().int().nonnegative().nullable().optional(),
  MachineCount: z.number().int().nonnegative().nullable().optional(),
  IsSingleUse: z.boolean(),
});

export type License = z.infer<typeof licenseSchema>;

export const licenseCreateSchema = z.object({
  LicenseCategoryId: licenseCategoryIdSchema,
  LicenseTierId: licenseTierIdSchema,
  EnvironmentId: environmentIdSchema,
  LicensePackageId: z.number().int().positive().optional(),
  ResellerId: z.number().int().positive().optional(),
  ProductVersion: z.string().trim().min(1).max(64),
  UserCount: z.number().int().positive().optional(),
  MachineCount: z.number().int().positive().optional(),
  IsSingleUse: z.boolean(),
});

export type LicenseCreateInput = z.infer<typeof licenseCreateSchema>;

/**
 * POST /Licenses is mutating; Idempotency-Key is REQUIRED per
 * 02-spec/21-app/11-api-contracts/08-idempotency-envelope-hardening.md so a
 * network retry cannot double-consume reseller quota.
 */
export async function createLicense(
  input: LicenseCreateInput,
  idempotencyKey: string,
): Promise<License> {
  if (getRuntimeMode().Mode === "preview") {
    return createPreviewLicense(input);
  }
  const [created] = await requestLaraApi("/Licenses", licenseSchema, {
    method: HttpMethodType.Post,
    body: input,
    headers: { "Idempotency-Key": idempotencyKey },
  });

  return created;
}

export const licenseUpdateSchema = z
  .object({
    LicenseCategoryId: licenseCategoryIdSchema.optional(),
    LicensePackageId: z.number().int().positive().nullable().optional(),
    ResellerId: z.number().int().positive().nullable().optional(),
    ProductVersion: z.string().trim().min(1).max(64).optional(),
    IsActive: z.boolean().optional(),
    ExpiresAt: z.string().datetime().nullable().optional(),
    UserCount: z.number().int().positive().nullable().optional(),
    MachineCount: z.number().int().positive().nullable().optional(),
    IsSingleUse: z.boolean().optional(),
  })
  .refine((value) => Object.keys(value).length > 0, {
    message: "At least one field is required.",
  });

export type LicenseUpdateInput = z.infer<typeof licenseUpdateSchema>;

/**
 * `DELETE /Licenses/{LicenseId}` response envelope.
 *
 * `QuotaRestored` and `RestoreSkippedReason` mirror the audit payload
 * fields defined by 02-spec/21-app/48-quota-restore-on-revoke.md §2 step 7
 * and §5. Both are optional so older deployments (pre-spec-48 servers)
 * that only return `{LicenseId, IsDeleted}` continue to parse. The
 * `RestoreSkippedReason` enum is closed and MUST match §1 verbatim; any
 * server value outside this set is a wire contract break, not a UI bug.
 */
export const licenseRestoreSkippedReasonSchema = z.enum([
  "AdminIssued",
  "ClosedPeriod",
  "TimeExpired",
  "AlreadyRestored",
]);
export type LicenseRestoreSkippedReason = z.infer<typeof licenseRestoreSkippedReasonSchema>;

export const licenseDeleteSchema = z.object({
  LicenseId: z.number().int().positive(),
  IsDeleted: z.literal(true),
  QuotaRestored: z.boolean().optional(),
  RestoreSkippedReason: licenseRestoreSkippedReasonSchema.optional(),
});

export type LicenseDeleteResult = z.infer<typeof licenseDeleteSchema>;

export interface LicenseWithEtag {
  license: License;
  /**
   * Quoted strong ETag exactly as emitted by `GET /Licenses/{LicenseId}`
   * per 02-spec/21-app/11-api-contracts/09-concurrency-control.md §ETag
   * shape. `undefined` only when the server omitted the header (older
   * deployments); mutations against a row with no ETag will surface
   * `428 PreconditionRequired` at the wire, which is the correct
   * behaviour: the client MUST NOT invent an ETag.
   */
  etag: string | undefined;
}

function hashUlid(id: string): number {
  let h = 0;
  for (let i = 0; i < id.length; i++) h = (h * 31 + id.charCodeAt(i)) | 0;

  return Math.abs(h);
}

interface PreviewClosedSetIds {
  LicenseCategoryId: LicenseCategoryIdValue;
  LicenseTierId: LicenseTierIdValue;
  EnvironmentId: EnvironmentIdValue;
}

function derivePreviewClosedSetIds(ulid: string): PreviewClosedSetIds {
  const h = hashUlid(ulid);

  return {
    LicenseCategoryId: ((h % 7) + 1) as LicenseCategoryIdValue,
    LicenseTierId: ((h % 4) + 1) as LicenseTierIdValue,
    EnvironmentId: ((h % 3) + 1) as EnvironmentIdValue,
  };
}

function buildAdaptedLicense(
  p: PreviewLicense,
  ids: { LicenseId: number; ResellerId: number | null; closed: PreviewClosedSetIds },
): License {
  return {
    LicenseId: ids.LicenseId,
    ...ids.closed,
    LicensePackageId: null,
    ResellerId: ids.ResellerId,
    IssuedByUserId: 1,
    ProductVersion: p.Serial,
    IsActive: p.Status === "active",
    IssuedAt: p.IssuedAt,
    ExpiresAt: p.ExpiresAt ?? null,
    UserCount: null,
    MachineCount: null,
    IsSingleUse: p.MaxActivations === 1,
  };
}

async function adaptPreviewLicense(p: PreviewLicense): Promise<License> {
  const LicenseId = await assignNumeric("licenses", p.Id);
  const ResellerId = p.ResellerId ? await assignNumeric("resellers", p.ResellerId) : null;
  const closed = derivePreviewClosedSetIds(p.Id);

  return buildAdaptedLicense(p, { LicenseId, ResellerId, closed });
}

function logPreviewBridge(license: License, ulid: string, version: number, status: string): void {
  console.info("lara-license:preview-bridge", {
    LicenseId: license.LicenseId,
    Ulid: ulid,
    Version: version,
    Status: status,
  });
}

async function fetchPreviewLicenseWithEtag(
  licenseId: number,
  signal?: AbortSignal,
): Promise<LicenseWithEtag> {
  const ulid = await ulidFor("licenses", licenseId);
  const isFailed = !ulid;
  if (isFailed) {
    console.warn("lara-license:preview-bridge:not-found", { LicenseId: licenseId });
    throw new Error(`License ${licenseId} not found in preview id-map.`);
  }
  const res = await apiClient.call("admin.licenses.show", { Id: ulid }, { signal });
  const license = await adaptPreviewLicense(res);
  logPreviewBridge(license, ulid, res.Version, res.Status);

  return { license, etag: String(res.Version) };
}

async function fetchLicenseWithEtag(
  licenseId: number,
  signal?: AbortSignal,
): Promise<LicenseWithEtag> {
  if (getRuntimeMode().Mode === "preview") return fetchPreviewLicenseWithEtag(licenseId, signal);
  let etag: string | undefined;
  const [license] = await requestLaraApi(`/Licenses/${licenseId}`, licenseSchema, {
    signal,
    onResponseHeaders: (headers) => {
      const value = headers.get("ETag");
      if (typeof value === "string" && value.length > 0) etag = value;
    },
  });

  return { license, etag };
}

const EXPIRY_DAYS_BY_CATEGORY: Partial<Record<LicenseCategoryIdValue, number>> = {
  [LicenseCategoryIdType.Daily]: 1,
  [LicenseCategoryIdType.Weekly]: 7,
  [LicenseCategoryIdType.Monthly]: 30,
  [LicenseCategoryIdType.Yearly]: 365,
};

function computeExpiresAt(categoryId: LicenseCategoryIdValue): string | null {
  const days = EXPIRY_DAYS_BY_CATEGORY[categoryId];
  if (!days) return null;

  return new Date(Date.now() + days * 24 * 60 * 60 * 1000).toISOString();
}

function resolveCustomerName(productVersion: string): string {
  const trimmed = productVersion.trim();

  return trimmed.length > 0 ? `License (${trimmed})` : "Standard License";
}

function overlayLicenseInput(adapted: License, input: LicenseCreateInput): License {
  return {
    ...adapted,
    LicenseCategoryId: input.LicenseCategoryId,
    LicenseTierId: input.LicenseTierId,
    EnvironmentId: input.EnvironmentId,
    LicensePackageId: input.LicensePackageId ?? null,
    ResellerId: input.ResellerId ?? null,
    ProductVersion: input.ProductVersion || adapted.ProductVersion,
    UserCount: input.UserCount ?? null,
    MachineCount: input.MachineCount ?? null,
    IsSingleUse: input.IsSingleUse,
  };
}

async function resolveResellerUlid(
  resellerId: number,
  signal?: AbortSignal,
): Promise<string | undefined> {
  const existing = await ulidFor("resellers", resellerId);
  if (existing) return existing;
  const list = await apiClient.call("admin.resellers.list", { Cursor: null }, { signal });
  for (const r of list.Items) {
    if ((await assignNumeric("resellers", r.Id)) === resellerId) return r.Id;
  }

  return undefined;
}

async function createPreviewLicense(input: LicenseCreateInput): Promise<License> {
  const resellerUlid = input.ResellerId ? await resolveResellerUlid(input.ResellerId) : null;
  const maxActivations = input.IsSingleUse ? 1 : (input.MachineCount ?? 5);
  const res = await apiClient.call("admin.licenses.create", {
    CustomerName: resolveCustomerName(input.ProductVersion),
    CustomerEmail: "licensee@example.com",
    ResellerId: resellerUlid ?? null,
    Features: ["core"],
    MaxActivations: maxActivations,
    ExpiresAt: computeExpiresAt(input.LicenseCategoryId),
  });
  const adapted = await adaptPreviewLicense(res);

  return overlayLicenseInput(adapted, input);
}

function overlayLicenseUpdate(adapted: License, input: LicenseUpdateInput): License {
  return {
    ...adapted,
    ...(input.LicenseCategoryId !== undefined && { LicenseCategoryId: input.LicenseCategoryId }),
    ...(input.LicenseTierId !== undefined && { LicenseTierId: input.LicenseTierId }),
    ...(input.LicensePackageId !== undefined && { LicensePackageId: input.LicensePackageId }),
    ...(input.ProductVersion !== undefined && { ProductVersion: input.ProductVersion }),
    ...(input.UserCount !== undefined && { UserCount: input.UserCount }),
    ...(input.MachineCount !== undefined && { MachineCount: input.MachineCount }),
    ...(input.IsSingleUse !== undefined && { IsSingleUse: input.IsSingleUse }),
    ...(input.ResellerId !== undefined && { ResellerId: input.ResellerId }),
  };
}

async function updatePreviewLicense(
  licenseId: number,
  input: LicenseUpdateInput,
  ifMatch: string,
): Promise<License> {
  const ulid = await ulidFor("licenses", licenseId);
  if (!ulid) throw new Error(`License ${licenseId} not found in preview id-map.`);
  const maxActs = input.IsSingleUse !== undefined ? (input.IsSingleUse ? 1 : 5) : input.MachineCount;
  const resellerUlid = input.ResellerId
    ? await resolveResellerUlid(input.ResellerId)
    : input.ResellerId === null
      ? null
      : undefined;
  const res = await apiClient.call("admin.licenses.update", {
    Id: ulid,
    IfMatch: ifMatch,
    ExpiresAt: input.ExpiresAt ?? undefined,
    MaxActivations: maxActs ?? undefined,
    Status: input.IsActive !== undefined ? (input.IsActive ? "active" : "suspended") : undefined,
    ...(resellerUlid !== undefined && { ResellerId: resellerUlid }),
  } as never);

  return overlayLicenseUpdate(await adaptPreviewLicense(res), input);
}

async function deletePreviewLicense(
  licenseId: number,
  ifMatch: string,
): Promise<LicenseDeleteResult> {
  const ulid = await ulidFor("licenses", licenseId);
  if (!ulid) throw new Error(`License ${licenseId} not found in preview id-map.`);

  const { read: readFromStore, list: listFromStore, write: writeToStore } = await import("./preview-store");
  const lic = await readFromStore<import("@/generated/api/schema").License>("licenses", ulid);

  await apiClient.call("admin.licenses.delete", { Id: ulid, IfMatch: ifMatch });

  // Cascade revocation to customer requests in preview store and prune from recent lookups
  if (lic?.Serial) {
    try {
      const requests = await listFromStore<import("./lara-customer-request").CustomerLicenseRequest>("customer-requests");
      for (const [reqId, req] of requests) {
        if (req.IssuedLicenseKey === lic.Serial) {
          req.Status = "Revoked";
          req.Notes = `${req.Notes ? req.Notes + " | " : ""}Revoked by administrator`;
          await writeToStore("customer-requests", reqId, req);
        }
      }
    } catch (err) {
      console.warn("deletePreviewLicense: failed to cascade to customer-requests", err);
    }

    if (typeof window !== "undefined") {
      try {
        const STORAGE_KEY = "LicensingPortal.portalRecentSerials";
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            const filtered = parsed.filter(
              (entry: { Serial?: string }) =>
                entry?.Serial?.trim().toUpperCase() !== lic.Serial.trim().toUpperCase(),
            );
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(filtered));
            window.dispatchEvent(new Event("portal-history-updated"));
          }
        }
      } catch (err) {
        console.warn("deletePreviewLicense: failed to prune recent-serials", err);
      }
    }
  }

  return { LicenseId: licenseId, IsDeleted: true };
}

// Reserved for the list-bridge in Plan 17 Step 7 (row-level `numericFor` lookups).
void numericFor;

export async function getLicense(licenseId: number, signal?: AbortSignal): Promise<License> {
  const { license } = await fetchLicenseWithEtag(licenseId, signal);

  return license;
}

/**
 * `PATCH /Licenses/{LicenseId}` is in-scope per
 * 02-spec/21-app/11-api-contracts/09-concurrency-control.md §Scope, so
 * `If-Match` is REQUIRED. The client MUST refuse to fire the request
 * without one; skipping it would guarantee a `428 PreconditionRequired`
 * round-trip and burn an `X-Request-Id` for a preventable failure.
 */
export async function updateLicense(
  licenseId: number,
  input: LicenseUpdateInput,
  idempotencyKey: string,
  ifMatch: string,
): Promise<License> {
  if (getRuntimeMode().Mode === "preview") {
    return updatePreviewLicense(licenseId, input, ifMatch);
  }
  const [updated] = await requestLaraApi(`/Licenses/${licenseId}`, licenseSchema, {
    method: HttpMethodType.Patch,
    body: input,
    headers: { "Idempotency-Key": idempotencyKey, "If-Match": ifMatch },
  });

  return updated;
}

/**
 * `DELETE /Licenses/{LicenseId}` (revoke) is in-scope per
 * 02-spec/21-app/11-api-contracts/09-concurrency-control.md §Scope, so
 * `If-Match` is REQUIRED. See the `updateLicense` docblock for the
 * client-side refusal rationale.
 */
export async function deleteLicense(
  licenseId: number,
  idempotencyKey: string,
  ifMatch: string,
): Promise<LicenseDeleteResult> {
  if (getRuntimeMode().Mode === "preview") {
    return deletePreviewLicense(licenseId, ifMatch);
  }
  const [deleted] = await requestLaraApi(`/Licenses/${licenseId}`, licenseDeleteSchema, {
    method: HttpMethodType.Delete,
    headers: { "Idempotency-Key": idempotencyKey, "If-Match": ifMatch },
  });

  return deleted;
}

export const licenseQueryOptions = (licenseId: number) =>
  queryOptions({
    queryKey: ["LaraApi", "License", licenseId],
    queryFn: ({ signal }) => fetchLicenseWithEtag(licenseId, signal),
    retry: false,
  });

export interface AdminLicenseFilter {
  ResellerId?: number;
  Query?: string;
  Status?: string;
}

async function fetchPreviewAdminLicenses(
  filter?: AdminLicenseFilter,
  signal?: AbortSignal,
): Promise<License[]> {
  const resellerUlid = filter?.ResellerId
    ? await resolveResellerUlid(filter.ResellerId, signal)
    : undefined;
  const res = await apiClient.call(
    "admin.licenses.list",
    {
      ResellerId: resellerUlid,
      Query: filter?.Query,
      Status: filter?.Status as never,
    },
    { signal },
  );

  return Promise.all(res.Items.map(adaptPreviewLicense));
}

async function fetchLiveAdminLicenses(
  filter?: AdminLicenseFilter,
  signal?: AbortSignal,
): Promise<License[]> {
  const path = filter?.ResellerId
    ? `/Reseller/Licenses?Limit=100`
    : `/Licenses?Limit=100`;

  return requestLaraApi(path, licenseSchema, { signal });
}

export async function fetchAdminLicenses(
  filter?: AdminLicenseFilter,
  signal?: AbortSignal,
): Promise<License[]> {
  if (getRuntimeMode().Mode === "preview") {
    return fetchPreviewAdminLicenses(filter, signal);
  }

  return fetchLiveAdminLicenses(filter, signal);
}

export function adminLicensesQueryOptions(filter?: AdminLicenseFilter) {
  return queryOptions({
    queryKey: ["LaraApi", "AdminLicenses", filter],
    queryFn: ({ signal }) => fetchAdminLicenses(filter, signal),
    retry: false,
  });
}
