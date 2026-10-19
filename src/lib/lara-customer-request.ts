import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { list, read, write } from "./preview-store";

export const CustomerLicenseRequestStatus = {
  Pending: "Pending",
  Approved: "Approved",
  Denied: "Denied",
  Revoked: "Revoked",
} as const;

export type CustomerLicenseRequestStatusType =
  (typeof CustomerLicenseRequestStatus)[keyof typeof CustomerLicenseRequestStatus];

export const customerLicenseRequestSchema = z.object({
  RequestId: z.string(),
  CustomerEmail: z.string().email(),
  CustomerName: z.string().min(1),
  ResellerId: z.number().int().positive(),
  ResellerName: z.string(),
  LicenseCategoryId: z.number().int().positive(),
  CategoryName: z.string(),
  LicenseTierId: z.number().int().positive(),
  TierName: z.string(),
  Status: z.enum(["Pending", "Approved", "Denied", "Revoked"]),
  Notes: z.string().nullable().optional(),
  IssuedLicenseKey: z.string().nullable().optional(),
  IsArchived: z.boolean().optional(),
  RequestedAt: z.string(),
  ResolvedAt: z.string().nullable().optional(),
});

export type CustomerLicenseRequest = z.infer<typeof customerLicenseRequestSchema>;

export const submitCustomerLicenseRequestInputSchema = z.object({
  CustomerEmail: z.string().email(),
  CustomerName: z.string().min(1),
  ResellerId: z.number().int().positive(),
  LicenseCategoryId: z.number().int().positive(),
  LicenseTierId: z.number().int().positive(),
  Notes: z.string().optional(),
});

export type SubmitCustomerLicenseRequestInput = z.infer<
  typeof submitCustomerLicenseRequestInputSchema
>;

const CATEGORY_MAP: Record<number, string> = {
  1: "Daily",
  2: "Weekly",
  3: "Monthly",
  4: "Yearly",
  5: "Lifetime",
  6: "Dev",
  7: "Key",
};

const TIER_MAP: Record<number, string> = {
  1: "Starter",
  2: "Growth",
  3: "Scale",
  4: "Enterprise",
};

export async function submitCustomerLicenseRequest(
  input: SubmitCustomerLicenseRequestInput,
): Promise<CustomerLicenseRequest> {
  const reqId = `REQ-${Date.now().toString().slice(-6)}`;
  const now = new Date().toISOString();
  
  const resellerName = input.ResellerId === 1 ? "Preview Reseller One" : `Reseller #${input.ResellerId}`;
  const categoryName = CATEGORY_MAP[input.LicenseCategoryId] ?? "Monthly";
  const tierName = TIER_MAP[input.LicenseTierId] ?? "Standard";

  const record: CustomerLicenseRequest = {
    RequestId: reqId,
    CustomerEmail: input.CustomerEmail,
    CustomerName: input.CustomerName,
    ResellerId: input.ResellerId,
    ResellerName: resellerName,
    LicenseCategoryId: input.LicenseCategoryId,
    CategoryName: categoryName,
    LicenseTierId: input.LicenseTierId,
    TierName: tierName,
    Status: "Pending",
    Notes: input.Notes ?? null,
    IssuedLicenseKey: null,
    RequestedAt: now,
    ResolvedAt: null,
  };

  await write<CustomerLicenseRequest>("customer-requests", reqId, record);
  return record;
}

export async function listCustomerLicenseRequests(
  filter?: { resellerId?: number; customerEmail?: string },
): Promise<CustomerLicenseRequest[]> {
  const rows = await list<CustomerLicenseRequest>("customer-requests");
  let items = rows.map(([, v]) => v);

  // Synchronize real-time revocation status
  const { read: readFromStore, write: writeToStore } = await import("./preview-store");
  for (const req of items) {
    if (req.Status === "Approved" && req.IssuedLicenseKey) {
      const serialKey = req.IssuedLicenseKey.trim().toUpperCase();
      const serialIndex = await readFromStore<{ Serial: string; LicenseId: string }>(
        "serials",
        serialKey,
      );

      let isStillActive = false;
      if (serialIndex?.LicenseId) {
        const lic = await readFromStore<import("@/generated/api/schema").License>(
          "licenses",
          serialIndex.LicenseId,
        );
        if (lic && lic.Status === "active") {
          isStillActive = true;
        }
      }

      if (!isStillActive) {
        req.Status = "Revoked";
        if (!req.Notes || !req.Notes.includes("Revoked")) {
          req.Notes = req.Notes ? `${req.Notes} | Revoked by administrator` : "Revoked by administrator";
        }
        await writeToStore("customer-requests", req.RequestId, req);
      }
    }
  }

  if (filter?.resellerId) {
    items = items.filter((r) => r.ResellerId === filter.resellerId);
  }
  if (filter?.customerEmail) {
    items = items.filter((r) => r.CustomerEmail.toLowerCase() === filter.customerEmail?.toLowerCase());
  }

  return items.sort((a, b) => b.RequestedAt.localeCompare(a.RequestedAt));
}

export function customerRequestsByResellerQueryOptions(resellerId: number) {
  return queryOptions({
    queryKey: ["CustomerRequests", "Reseller", resellerId],
    queryFn: () => listCustomerLicenseRequests({ resellerId }),
  });
}

export function customerRequestsByCustomerQueryOptions(customerEmail: string) {
  return queryOptions({
    queryKey: ["CustomerRequests", "Customer", customerEmail],
    queryFn: () => listCustomerLicenseRequests({ customerEmail }),
  });
}

export async function approveCustomerLicenseRequest(
  requestId: string,
): Promise<{ request: CustomerLicenseRequest; licenseKey: string }> {
  const current = await read<CustomerLicenseRequest>("customer-requests", requestId);
  if (!current) {
    throw new Error(`Customer request ${requestId} not found.`);
  }

  const now = new Date().toISOString();
  const { read: readFromStore, write: writeToStore } = await import("./preview-store");

  // Determine duration extension
  let durationDays = 30;
  if (current.CategoryName === "Daily") durationDays = 1;
  else if (current.CategoryName === "Weekly") durationDays = 7;
  else if (current.CategoryName === "Monthly") durationDays = 30;
  else if (current.CategoryName === "Yearly") durationDays = 365;

  // Check if this is a renewal request for an existing serial
  const renewalMatch = current.Notes?.match(/Renewal for\s+([A-Z0-9-]+)/i);
  const targetSerial = renewalMatch ? renewalMatch[1].trim().toUpperCase() : null;

  let licenseKey: string;
  let expiresAt: string | null = null;

  const existingIndex = targetSerial
    ? await readFromStore<{ Serial: string; LicenseId: string }>("serials", targetSerial)
    : null;

  if (targetSerial && existingIndex) {
    // True Renewal: keep the exact same license key and extend its expiration
    licenseKey = targetSerial;
    const existingLic = await readFromStore<import("@/generated/api/schema").License>(
      "licenses",
      existingIndex.LicenseId,
    );

    const baseDate =
      existingLic?.ExpiresAt && new Date(existingLic.ExpiresAt).getTime() > Date.now()
        ? new Date(existingLic.ExpiresAt)
        : new Date();
    baseDate.setDate(baseDate.getDate() + durationDays);
    expiresAt = current.CategoryName === "Lifetime" ? null : baseDate.toISOString();

    if (existingLic) {
      existingLic.ExpiresAt = expiresAt;
      existingLic.Status = "active";
      existingLic.UpdatedAt = now;
      (existingLic as Record<string, unknown>).Category = current.CategoryName;
      (existingLic as Record<string, unknown>).CategoryName = current.CategoryName;
      await writeToStore("licenses", existingLic.Id, existingLic);
    }
  } else {
    // Brand new license creation
    const chunk = Math.random().toString(36).slice(2, 6).toUpperCase().padEnd(4, "X");
    licenseKey = targetSerial ?? `LARA-${chunk}-0001`;
    const licenseId = `01H00000000000000LIC${Math.random().toString(16).slice(2, 7).toUpperCase()}`;

    const issueDate = new Date();
    issueDate.setDate(issueDate.getDate() + durationDays);
    expiresAt = current.CategoryName === "Lifetime" ? null : issueDate.toISOString();

    await writeToStore("licenses", licenseId, {
      Id: licenseId,
      Serial: licenseKey,
      Category: current.CategoryName,
      CategoryName: current.CategoryName,
      Status: "active",
      CustomerName: current.CustomerName,
      CustomerEmail: current.CustomerEmail,
      ResellerId: "01H000000000000000RSLLR1",
      IssuedAt: now,
      ExpiresAt: expiresAt,
      Features: ["core.reports", "core.dashboards"],
      MaxActivations: 5,
      ActiveActivations: 0,
      Version: 1,
      CreatedAt: now,
      UpdatedAt: now,
    });

    await writeToStore("serials", licenseKey, {
      Serial: licenseKey,
      LicenseId: licenseId,
      Category: current.CategoryName,
    });
  }

  const updated: CustomerLicenseRequest = {
    ...current,
    Status: "Approved",
    IssuedLicenseKey: licenseKey,
    ResolvedAt: now,
  };

  await write<CustomerLicenseRequest>("customer-requests", requestId, updated);

  try {
    fetch("http://127.0.0.1:8000/api/Serials/Register", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        SerialValue: licenseKey,
        Category: current.CategoryName,
        TierName: current.TierName,
        CustomerName: current.CustomerName,
        CustomerEmail: current.CustomerEmail,
        Status: "active",
        ExpiresAt: expiresAt,
        Features: ["core.reports", "core.dashboards", "addon.exports"],
        MaxActivations: 5,
      }),
    }).catch(() => {
      // Non-blocking in preview mode if server is not reachable
    });
  } catch {
    // Non-blocking
  }

  return { request: updated, licenseKey };
}

export async function denyCustomerLicenseRequest(
  requestId: string,
  reason?: string,
): Promise<CustomerLicenseRequest> {
  const current = await read<CustomerLicenseRequest>("customer-requests", requestId);
  if (!current) {
    throw new Error(`Customer request ${requestId} not found.`);
  }

  const updated: CustomerLicenseRequest = {
    ...current,
    Status: "Denied",
    Notes: reason ? `${current.Notes ? current.Notes + " | " : ""}Denied: ${reason}` : current.Notes,
    ResolvedAt: new Date().toISOString(),
  };

  await write<CustomerLicenseRequest>("customer-requests", requestId, updated);
  return updated;
}

export async function archiveCustomerLicenseRequest(
  requestId: string,
  isArchived = true,
): Promise<CustomerLicenseRequest> {
  const current = await read<CustomerLicenseRequest>("customer-requests", requestId);
  if (!current) {
    throw new Error(`Customer request ${requestId} not found.`);
  }

  const updated: CustomerLicenseRequest = {
    ...current,
    IsArchived: isArchived,
  };

  await write<CustomerLicenseRequest>("customer-requests", requestId, updated);
  return updated;
}

export async function deactivateCustomerLicense(
  requestId: string,
): Promise<CustomerLicenseRequest> {
  const current = await read<CustomerLicenseRequest>("customer-requests", requestId);
  if (!current) {
    throw new Error(`Customer request ${requestId} not found.`);
  }

  const { read: readFromStore, write: writeToStore } = await import("./preview-store");

  if (current.IssuedLicenseKey) {
    const serialKey = current.IssuedLicenseKey.trim().toUpperCase();
    const serialIndex = await readFromStore<{ Serial: string; LicenseId: string; IsRevoked?: boolean }>(
      "serials",
      serialKey,
    );
    if (serialIndex?.LicenseId) {
      const lic = await readFromStore<import("@/generated/api/schema").License>(
        "licenses",
        serialIndex.LicenseId,
      );
      if (lic) {
        lic.Status = "suspended";
        await writeToStore("licenses", lic.Id, lic);
      }
      await writeToStore("serials", serialKey, {
        ...serialIndex,
        IsRevoked: true,
      });
    }

    // Sync revocation to backend API and registered_serials.json
    try {
      fetch("http://127.0.0.1:8000/api/Serials/Revoke", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          SerialValue: serialKey,
          Reason: "Deactivated by user",
        }),
      }).catch(() => {
        // Non-blocking in preview mode
      });
    } catch {
      // Non-blocking
    }
  }

  const updated: CustomerLicenseRequest = {
    ...current,
    Status: "Revoked",
    Notes: current.Notes ? `${current.Notes} | Deactivated by user` : "Deactivated by user",
    ResolvedAt: new Date().toISOString(),
  };

  await write<CustomerLicenseRequest>("customer-requests", requestId, updated);
  return updated;
}

export async function cancelCustomerLicenseRequest(
  requestId: string,
): Promise<CustomerLicenseRequest> {
  const current = await read<CustomerLicenseRequest>("customer-requests", requestId);
  if (!current) {
    throw new Error(`Customer request ${requestId} not found.`);
  }

  const updated: CustomerLicenseRequest = {
    ...current,
    Status: "Denied",
    Notes: current.Notes ? `${current.Notes} | Cancelled by user` : "Cancelled by user",
    ResolvedAt: new Date().toISOString(),
  };

  await write<CustomerLicenseRequest>("customer-requests", requestId, updated);
  return updated;
}

export async function deleteCustomerLicenseRequest(requestId: string): Promise<void> {
  const { remove } = await import("./preview-store");
  await remove("customer-requests", requestId);
}

export async function clearArchivedCustomerRequests(customerEmail?: string): Promise<void> {
  const { list: listFromStore, remove: removeFromStore } = await import("./preview-store");
  const rows = await listFromStore<CustomerLicenseRequest>("customer-requests");
  for (const [id, req] of rows) {
    if (req.IsArchived) {
      if (!customerEmail || req.CustomerEmail.toLowerCase() === customerEmail.toLowerCase()) {
        await removeFromStore("customer-requests", id);
      }
    }
  }
}

export async function archiveAllResolvedCustomerRequests(resellerId?: number): Promise<void> {
  const { list: listFromStore, write: writeToStore } = await import("./preview-store");
  const rows = await listFromStore<CustomerLicenseRequest>("customer-requests");
  for (const [id, req] of rows) {
    if ((!resellerId || req.ResellerId === resellerId) && req.Status !== "Pending") {
      req.IsArchived = true;
      await writeToStore("customer-requests", id, req);
    }
  }
}

export interface BoundDevice {
  DeviceId: string;
  HardwareDigest: string;
  Hostname: string;
  Os: string;
  ActivatedAt: string;
  LastActiveAt?: string;
}

export async function getSerialDevices(serialValue: string): Promise<{
  boundDevices: BoundDevice[];
  maxActivations: number;
  status: string;
}> {
  const clean = serialValue.trim().toUpperCase();
  try {
    const res = await fetch(`http://127.0.0.1:8000/api/Serials/${encodeURIComponent(clean)}`);
    if (res.ok) {
      const json = await res.json();
      if (json.Data) {
        return {
          boundDevices: json.Data.BoundDevices ?? [],
          maxActivations: json.Data.MaxActivations ?? 5,
          status: json.Data.Status ?? "active",
        };
      }
    }
  } catch {
    // Non-blocking fallback
  }

  const { read: readFromStore } = await import("./preview-store");
  const index = await readFromStore<{ Serial: string; LicenseId: string; BoundDevices?: BoundDevice[] }>("serials", clean);
  return {
    boundDevices: index?.BoundDevices ?? [],
    maxActivations: 5,
    status: "active",
  };
}

export async function unbindCustomerLicenseDevice(serialValue: string, deviceId: string): Promise<boolean> {
  const clean = serialValue.trim().toUpperCase();
  try {
    await fetch(`http://127.0.0.1:8000/api/Serials/UnbindDevice`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ SerialValue: clean, DeviceId: deviceId }),
    });
  } catch {
    // Non-blocking fallback
  }

  try {
    const { read: readFromStore, write: writeToStore } = await import("./preview-store");
    const index = await readFromStore<{ Serial: string; LicenseId: string; BoundDevices?: BoundDevice[] }>("serials", clean);
    if (index) {
      const filtered = (index.BoundDevices ?? []).filter((d) => d.DeviceId !== deviceId && d.HardwareDigest !== deviceId);
      await writeToStore("serials", clean, { ...index, BoundDevices: filtered });
    }
  } catch {
    // Non-blocking
  }
  return true;
}

export async function cancelCustomerSubscriptionRenewal(requestId: string): Promise<CustomerLicenseRequest> {
  const current = await read<CustomerLicenseRequest>("customer-requests", requestId);
  if (!current) throw new Error(`Customer request ${requestId} not found.`);

  const updated: CustomerLicenseRequest = {
    ...current,
    Notes: current.Notes ? `${current.Notes} | Renewal cancelled (active until period end)` : "Renewal cancelled (active until period end)",
  };
  await write<CustomerLicenseRequest>("customer-requests", requestId, updated);

  if (current.IssuedLicenseKey) {
    try {
      fetch("http://127.0.0.1:8000/api/Serials/CancelRenewal", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ SerialValue: current.IssuedLicenseKey }),
      }).catch(() => {});
    } catch {
      // Non-blocking
    }
  }

  return updated;
}
