/**
 * Plan 17 Step 8: `resellerQuotasQueryOptions` preview bridge.
 *
 * Proves the legacy `resellerId=1` route resolves through the id-map
 * primed by `loadDefaultSeed()` into `admin.quotas.list`, and that the
 * adapted rows match the legacy `resellerQuotaSchema` (positive-int FK
 * ids, LicensesGranted/Consumed/Remaining accounting).
 */

import "fake-indexeddb/auto";
import { beforeEach, describe, expect, it } from "vitest";

import { resellerQuotasQueryOptions, resellerQuotaSchema } from "@/lib/lara-quota";
import { loadDefaultSeed } from "@/lib/preview-seeds/default";
import { freezeRuntimeMode } from "@/lib/runtime-mode";
import { registerAllPreviewHandlers } from "@/lib/preview-fixtures";
import { resetIdMap } from "@/lib/preview-id-map";
import { resetAll as resetPreviewStore } from "@/lib/preview-store";

async function resetAll(): Promise<void> {
  await resetPreviewStore();
  await resetIdMap("licenses");
  await resetIdMap("resellers");
}

describe("resellerQuotasQueryOptions preview bridge", () => {
  beforeEach(async () => {
    await resetAll();
    registerAllPreviewHandlers();
    freezeRuntimeMode({ Mode: "preview", ApiBaseUrl: null, PreviewSeed: "default" });
    await loadDefaultSeed();
  });

  it("resolves resellerId=1 via id-map into the seeded 3 quota rows", async () => {
    const rows = await resellerQuotasQueryOptions(1).queryFn!({} as never);
    expect(rows).toHaveLength(3);
    for (const row of rows) {
      expect(() => resellerQuotaSchema.parse(row)).not.toThrow();
      expect(row.ResellerId).toBe(1);
      expect(row.LicensesRemaining).toBe(Math.max(0, row.LicensesGranted - row.LicensesConsumed));
    }
  });

  it("unmapped reseller id returns empty list (no throw)", async () => {
    const rows = await resellerQuotasQueryOptions(999).queryFn!({} as never);
    expect(rows).toEqual([]);
  });

  it("approveQuotaRequest updates the quota-requests preview store to Approved", async () => {
    const { approveQuotaRequest } = await import("@/lib/lara-quota");
    const { read } = await import("@/lib/preview-store");
    const res = await approveQuotaRequest(1001, "preview-reseller", { ApprovedDelta: 25 }, "idem-preview-approve");
    expect(res.Status).toBe("Approved");
    expect(res.ApprovedDelta).toBe(25);
    expect(res.DecidedByUserId).toBe(1);
    expect(res.DecidedAt).toBeTruthy();

    const stored = await read<any>("quota-requests", "1001");
    expect(stored?.Status).toBe("Approved");
    expect(stored?.ApprovedDelta).toBe(25);
  });

  it("denyQuotaRequest updates the quota-requests preview store to Denied", async () => {
    const { denyQuotaRequest } = await import("@/lib/lara-quota");
    const { read } = await import("@/lib/preview-store");
    const res = await denyQuotaRequest(1002, "preview-reseller", { Reason: "Out of scope" }, "idem-preview-deny");
    expect(res.Status).toBe("Denied");
    expect(res.DenialReason).toBe("Out of scope");
    expect(res.DecidedByUserId).toBe(1);
    expect(res.DecidedAt).toBeTruthy();

    const stored = await read<any>("quota-requests", "1002");
    expect(stored?.Status).toBe("Denied");
    expect(stored?.DenialReason).toBe("Out of scope");
  });
});
