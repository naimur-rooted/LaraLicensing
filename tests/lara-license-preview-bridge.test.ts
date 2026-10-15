/**
 * Plan 17 Step 6: `licenseQueryOptions` preview bridge.
 *
 * Proves the legacy `LicenseId=1` route resolves through the id-map
 * primed by `loadDefaultSeed()` into the ULID-keyed preview handler
 * `admin.licenses.show`, and that the adapted `License` shape matches
 * the legacy `licenseSchema` (closed-set FK ids, positive numeric ids).
 */

import "fake-indexeddb/auto";
import { beforeEach, describe, expect, it } from "vitest";

import { loadDefaultSeed } from "@/lib/preview-seeds/default";
import {
  createLicense,
  deleteLicense,
  licenseQueryOptions,
  licenseSchema,
  updateLicense,
} from "@/lib/lara-license";
import { freezeRuntimeMode } from "@/lib/runtime-mode";
import { registerAllPreviewHandlers } from "@/lib/preview-fixtures";
import { resetIdMap } from "@/lib/preview-id-map";
import { resetAll as resetPreviewStore } from "@/lib/preview-store";

async function resetAll(): Promise<void> {
  await resetPreviewStore();
  await resetIdMap("admin-users");
  await resetIdMap("licenses");
  await resetIdMap("resellers");
}

describe("licenseQueryOptions preview bridge", () => {
  beforeEach(async () => {
    await resetAll();
    registerAllPreviewHandlers();
    freezeRuntimeMode({ Mode: "preview", ApiBaseUrl: null, PreviewSeed: "default" });
    await loadDefaultSeed();
  });

  it("resolves LicenseId=1 via id-map into the seeded LIC00001 preview record", async () => {
    const opts = licenseQueryOptions(1);
    const { license, etag } = await opts.queryFn!({} as never);
    expect(license.LicenseId).toBe(1);
    expect(license.IsActive).toBe(true); // LIC00001 seed is `status: "active"`
    expect(license.ProductVersion).toBe("LARA-AAAA-0001"); // Serial passthrough
    expect(etag).toBe("3"); // seed Version = 3
    // Adapted result must round-trip through the legacy schema.
    expect(() => licenseSchema.parse(license)).not.toThrow();
  });

  it("suspended license (LicenseId=2) round-trips with IsActive=false", async () => {
    const { license } = await licenseQueryOptions(2).queryFn!({} as never);
    expect(license.LicenseId).toBe(2);
    expect(license.IsActive).toBe(false); // LIC00002 seed is `status: "suspended"`
  });

  it("unmapped id throws with contextual message (no silent fallback)", async () => {
    await expect(licenseQueryOptions(999).queryFn!({} as never)).rejects.toThrow(
      /License 999 not found in preview id-map/,
    );
  });

  it("creates a new preview license and resolves it with query options", async () => {
    const created = await createLicense(
      {
        LicenseCategoryId: 3,
        LicenseTierId: 2,
        EnvironmentId: 1,
        ProductVersion: "v2.5.0",
        IsSingleUse: false,
        MachineCount: 10,
      },
      "test-idem-key",
    );

    expect(created.LicenseId).toBeGreaterThan(0);
    expect(created.ProductVersion).toBe("v2.5.0");
    expect(created.IsActive).toBe(true);
    expect(() => licenseSchema.parse(created)).not.toThrow();

    const { license, etag } = await licenseQueryOptions(created.LicenseId).queryFn!({} as never);
    expect(license.LicenseId).toBe(created.LicenseId);
    expect(etag).toBe("1");
  });

  it("updates and deletes a preview license", async () => {
    const created = await createLicense(
      {
        LicenseCategoryId: 1,
        LicenseTierId: 1,
        EnvironmentId: 1,
        ProductVersion: "v1.0.0",
        IsSingleUse: false,
      },
      "test-idem-2",
    );

    const updated = await updateLicense(
      created.LicenseId,
      { IsActive: false, ResellerId: 2 },
      "test-idem-update",
      "1",
    );
    expect(updated.IsActive).toBe(false);
    expect(updated.ResellerId).toBe(2);

    const deleted = await deleteLicense(created.LicenseId, "test-idem-del", "2");
    expect(deleted.IsDeleted).toBe(true);
  });
});
