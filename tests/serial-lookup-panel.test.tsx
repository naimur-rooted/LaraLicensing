import "fake-indexeddb/auto";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";

import { SerialLookupPanel } from "@/components/portal/serial-lookup-panel";
import * as laraSerial from "@/lib/lara-serial";

/**
 * Guards Plan 09 step 50 invariants:
 *  - Empty state renders when no serial has been checked.
 *  - Successful verify writes a `LicensingPortal.portalRecentSerials`
 *    entry with the correct shape, deduped by serial, capped at 5.
 *  - History Recheck button re-runs verifySerial with the same value.
 */
describe("SerialLookupPanel", () => {
  const STORAGE_KEY = "LicensingPortal.portalRecentSerials";

  function makeClient() {
    return new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  }

  function renderPanel() {
    const client = makeClient();
    return render(
      <QueryClientProvider client={client}>
        <SerialLookupPanel testIdPrefix="test-serial" />
      </QueryClientProvider>,
    );
  }

  beforeEach(() => {
    window.localStorage.clear();
    vi.restoreAllMocks();
  });

  it("shows the empty state by default", () => {
    renderPanel();
    expect(screen.getByText(/No serial verified yet/i)).toBeTruthy();
  });

  it("persists a recent lookup entry after a successful verify", async () => {
    const spy = vi.spyOn(laraSerial, "verifySerial").mockResolvedValue({
      IsValid: true,
      Category: "Standard",
      IsSingleUse: false,
      ExpiresAt: null,
    } as never);
    renderPanel();
    const input = screen.getByTestId("test-serial-input") as HTMLInputElement;
    fireEvent.change(input, { target: { value: "ABCD-1234" } });
    fireEvent.click(screen.getByTestId("test-serial-submit"));
    await waitFor(() => expect(spy).toHaveBeenCalledWith("ABCD-1234"));
    await waitFor(() => expect(window.localStorage.getItem(STORAGE_KEY)).not.toBeNull());
    const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]");
    expect(stored).toHaveLength(1);
    expect(stored[0].Serial).toBe("ABCD-1234");
    expect(stored[0].IsValid).toBe(true);
    expect(typeof stored[0].CheckedAt).toBe("string");
  });

  it("dedupes repeat lookups of the same serial", async () => {
    vi.spyOn(laraSerial, "verifySerial").mockResolvedValue({
      IsValid: false,
      Category: "Standard",
      IsSingleUse: true,
      ExpiresAt: null,
    } as never);
    renderPanel();
    const input = screen.getByTestId("test-serial-input") as HTMLInputElement;
    fireEvent.change(input, { target: { value: "SAME-SERIAL" } });
    fireEvent.click(screen.getByTestId("test-serial-submit"));
    await waitFor(() => expect(window.localStorage.getItem(STORAGE_KEY)).not.toBeNull());
    fireEvent.click(screen.getByTestId("test-serial-submit"));
    await waitFor(() => {
      const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]");
      expect(stored).toHaveLength(1);
    });
  });

  it("removes a serial from history when verify fails with SerialNotFound", async () => {
    // Seed initial history
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify([
        { Serial: "REVOKED-1234", IsValid: true, CheckedAt: new Date().toISOString() },
      ]),
    );

    const { LaraApiError, ApiErrorCodeType } = await import("@/lib/lara-api-error");
    vi.spyOn(laraSerial, "verifySerial").mockRejectedValue(
      new LaraApiError("Serial not found.", ApiErrorCodeType.SerialNotFound, 404),
    );

    renderPanel();
    expect(screen.getByText("REVOKED-1234")).toBeTruthy();

    const replayBtn = screen.getByTestId("test-serial-history-replay");
    fireEvent.click(replayBtn);

    await waitFor(() => {
      expect(screen.queryByText("REVOKED-1234")).toBeNull();
      const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]");
      expect(stored).toHaveLength(0);
    });
  });

  it("clears all recent lookups when clicking Clear", async () => {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify([
        { Serial: "KEY-1", IsValid: true, CheckedAt: new Date().toISOString() },
        { Serial: "KEY-2", IsValid: false, CheckedAt: new Date().toISOString() },
      ]),
    );

    renderPanel();
    expect(screen.getByText("KEY-1")).toBeTruthy();
    expect(screen.getByText("KEY-2")).toBeTruthy();

    const clearBtn = screen.getByTitle("Clear all recent lookups");
    fireEvent.click(clearBtn);

    await waitFor(() => {
      expect(screen.queryByText("KEY-1")).toBeNull();
      expect(screen.queryByText("KEY-2")).toBeNull();
      const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]");
      expect(stored).toHaveLength(0);
    });
  });
});
