import "fake-indexeddb/auto";
import { describe, it, expect } from "vitest";
import {
  submitCustomerLicenseRequest,
  listCustomerLicenseRequests,
  approveCustomerLicenseRequest,
  denyCustomerLicenseRequest,
} from "@/lib/lara-customer-request";

describe("Customer License Request Workflow", () => {
  it("allows a customer to submit a license request to a reseller", async () => {
    const created = await submitCustomerLicenseRequest({
      CustomerEmail: "test-user@domain.com",
      CustomerName: "John Doe",
      ResellerId: 1,
      LicenseCategoryId: 3, // Monthly
      LicenseTierId: 2, // Growth
      Notes: "Test license request",
    });

    expect(created.RequestId).toMatch(/^REQ-/);
    expect(created.Status).toBe("Pending");
    expect(created.CustomerEmail).toBe("test-user@domain.com");

    const resellerList = await listCustomerLicenseRequests({ resellerId: 1 });
    const found = resellerList.find((r) => r.RequestId === created.RequestId);
    expect(found).toBeDefined();
    expect(found?.Status).toBe("Pending");
  });

  it("allows reseller to approve the customer request and issue a serial key", async () => {
    const created = await submitCustomerLicenseRequest({
      CustomerEmail: "approve-test@domain.com",
      CustomerName: "Jane Doe",
      ResellerId: 1,
      LicenseCategoryId: 4, // Yearly
      LicenseTierId: 3, // Scale
    });

    const result = await approveCustomerLicenseRequest(created.RequestId);
    expect(result.request.Status).toBe("Approved");
    expect(result.licenseKey).toMatch(/^LARA-[A-Z0-9]{4}-0001$/);
    expect(result.request.IssuedLicenseKey).toBe(result.licenseKey);

    // Customer should see the approved key in their history
    const customerList = await listCustomerLicenseRequests({
      customerEmail: "approve-test@domain.com",
    });
    expect(customerList[0].Status).toBe("Approved");
    expect(customerList[0].IssuedLicenseKey).toBe(result.licenseKey);
  });

  it("allows reseller to deny a customer request", async () => {
    const created = await submitCustomerLicenseRequest({
      CustomerEmail: "deny-test@domain.com",
      CustomerName: "Denied User",
      ResellerId: 1,
      LicenseCategoryId: 1,
      LicenseTierId: 1,
    });

    const denied = await denyCustomerLicenseRequest(created.RequestId, "Invalid payment reference");
    expect(denied.Status).toBe("Denied");
    expect(denied.Notes).toContain("Denied: Invalid payment reference");
  });

  it("preserves the existing serial key when approving a renewal request", async () => {
    // 1. Initial purchase
    const initial = await submitCustomerLicenseRequest({
      CustomerEmail: "renew-test@domain.com",
      CustomerName: "Renew User",
      ResellerId: 1,
      LicenseCategoryId: 3, // Monthly
      LicenseTierId: 2,
    });
    const initialApproved = await approveCustomerLicenseRequest(initial.RequestId);
    const existingKey = initialApproved.licenseKey;

    // 2. Renewal request for the same serial
    const renewal = await submitCustomerLicenseRequest({
      CustomerEmail: "renew-test@domain.com",
      CustomerName: "Renew User",
      ResellerId: 1,
      LicenseCategoryId: 3, // Monthly
      LicenseTierId: 2,
      Notes: `Renewal for ${existingKey}`,
    });

    const renewalApproved = await approveCustomerLicenseRequest(renewal.RequestId);

    // The key MUST remain the same!
    expect(renewalApproved.licenseKey).toBe(existingKey);
    expect(renewalApproved.request.IssuedLicenseKey).toBe(existingKey);
  });

  it("automatically transitions approved requests to Revoked when the license is revoked", async () => {
    const created = await submitCustomerLicenseRequest({
      CustomerEmail: "revoked-test@domain.com",
      CustomerName: "Revoked Test User",
      ResellerId: 1,
      LicenseCategoryId: 3,
      LicenseTierId: 2,
    });

    const approved = await approveCustomerLicenseRequest(created.RequestId);
    expect(approved.request.Status).toBe("Approved");
    const issuedSerial = approved.licenseKey;

    // Simulate admin revoking the license (removes serial and license from preview store)
    const { remove } = await import("@/lib/preview-store");
    await remove("serials", issuedSerial);

    // When customer requests are listed, it must auto-sync to Revoked
    const list = await listCustomerLicenseRequests({ customerEmail: "revoked-test@domain.com" });
    const target = list.find((r) => r.RequestId === created.RequestId);
    expect(target).toBeDefined();
    expect(target?.Status).toBe("Revoked");
    expect(target?.Notes).toContain("Revoked by administrator");
  });

  it("allows a customer to deactivate an active license key", async () => {
    const { deactivateCustomerLicense } = await import("@/lib/lara-customer-request");
    const created = await submitCustomerLicenseRequest({
      CustomerEmail: "deactivate-user@domain.com",
      CustomerName: "Deactivate User",
      ResellerId: 1,
      LicenseCategoryId: 3,
      LicenseTierId: 2,
    });

    const approved = await approveCustomerLicenseRequest(created.RequestId);
    expect(approved.request.Status).toBe("Approved");

    const deactivated = await deactivateCustomerLicense(created.RequestId);
    expect(deactivated.Status).toBe("Revoked");
    expect(deactivated.Notes).toContain("Deactivated by user");
  });

  it("allows a customer to cancel a pending license request", async () => {
    const { cancelCustomerLicenseRequest } = await import("@/lib/lara-customer-request");
    const created = await submitCustomerLicenseRequest({
      CustomerEmail: "cancel-user@domain.com",
      CustomerName: "Cancel User",
      ResellerId: 1,
      LicenseCategoryId: 3,
      LicenseTierId: 2,
    });

    expect(created.Status).toBe("Pending");

    const cancelled = await cancelCustomerLicenseRequest(created.RequestId);
    expect(cancelled.Status).toBe("Denied");
    expect(cancelled.Notes).toContain("Cancelled by user");
  });
});
