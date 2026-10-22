import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";

import { HttpMethodType, requestLaraApi } from "./lara-api-client";
import { getRuntimeMode } from "./runtime-mode";

export const MachineBindingStatusType = {
  Active: "Active",
  Cooldown: "Cooldown",
  Released: "Released",
} as const;

export type MachineBindingStatusValue =
  (typeof MachineBindingStatusType)[keyof typeof MachineBindingStatusType];

export const machineBindingSchema = z.object({
  MachineBindingId: z.number().int().positive(),
  LicenseId: z.number().int().positive(),
  FingerprintHash: z.string().min(1),
  ClientIp: z.string().optional(),
  FirstSeenAt: z.string(),
  LastSeenAt: z.string(),
  ReleasedAt: z.string().nullable().optional(),
  RebindCooldownUntil: z.string().nullable().optional(),
});

export type MachineBinding = z.infer<typeof machineBindingSchema>;

export function getBindingStatus(binding: MachineBinding): MachineBindingStatusValue {
  const now = Date.now();
  const hasCooldown = Boolean(
    binding.RebindCooldownUntil && new Date(binding.RebindCooldownUntil).getTime() > now,
  );
  if (hasCooldown) {
    return MachineBindingStatusType.Cooldown;
  }

  const isReleased = Boolean(binding.ReleasedAt);
  if (isReleased) {
    return MachineBindingStatusType.Released;
  }

  return MachineBindingStatusType.Active;
}

const previewBindingsStore = new Map<string, MachineBinding[]>();

function getInitialPreviewBindings(licenseKey: string): MachineBinding[] {
  const hashSeed = licenseKey.replace(/[^A-Za-z0-9]/g, "").toLowerCase();
  const baseHash = `${hashSeed}e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`.slice(0, 64);
  const now = new Date();
  const hourAgo = new Date(now.getTime() - 3600000).toISOString();
  const dayAgo = new Date(now.getTime() - 86400000).toISOString();

  return [
    {
      MachineBindingId: 101,
      LicenseId: 1,
      FingerprintHash: baseHash,
      ClientIp: "192.168.1.105",
      FirstSeenAt: dayAgo,
      LastSeenAt: hourAgo,
      ReleasedAt: null,
      RebindCooldownUntil: null,
    },
    {
      MachineBindingId: 102,
      LicenseId: 1,
      FingerprintHash: `${baseHash.slice(8)}1a2b3c4d`.padEnd(64, "f"),
      ClientIp: "10.0.4.22",
      FirstSeenAt: dayAgo,
      LastSeenAt: hourAgo,
      ReleasedAt: hourAgo,
      RebindCooldownUntil: new Date(now.getTime() + 10 * 60 * 1000).toISOString(),
    },
  ];
}

function fetchPreviewBindings(licenseKey: string): MachineBinding[] {
  const existing = previewBindingsStore.get(licenseKey);
  if (existing) {
    return existing;
  }

  const initial = getInitialPreviewBindings(licenseKey);
  previewBindingsStore.set(licenseKey, initial);

  return initial;
}

export function resellerLicenseBindingsQueryOptions(licenseKey: string) {
  return queryOptions({
    queryKey: ["LaraApi", "Reseller", "Licenses", licenseKey, "Bindings"],
    queryFn: ({ signal }) => {
      const isPreview = getRuntimeMode().Mode === "preview";
      if (isPreview) {
        return fetchPreviewBindings(licenseKey);
      }

      return requestLaraApi(
        `/Reseller/Licenses/${encodeURIComponent(licenseKey)}/Bindings`,
        machineBindingSchema,
        { signal },
      );
    },
    enabled: licenseKey.trim().length > 0,
    retry: false,
  });
}

function releasePreviewBinding(licenseKey: string, bindingId: number): MachineBinding {
  const list = fetchPreviewBindings(licenseKey);
  const now = new Date();
  const cooldown = new Date(now.getTime() + 15 * 60 * 1000).toISOString();
  const updatedList = list.map((binding) => {
    const isTarget = binding.MachineBindingId === bindingId;
    if (isTarget) {
      return {
        ...binding,
        ReleasedAt: now.toISOString(),
        RebindCooldownUntil: cooldown,
      };
    }

    return binding;
  });
  previewBindingsStore.set(licenseKey, updatedList);
  const target = updatedList.find((b) => b.MachineBindingId === bindingId);
  if (target) {
    return target;
  }

  throw new Error("Binding not found");
}

export async function releaseMachineBinding(
  licenseKey: string,
  bindingId: number,
  idempotencyKey?: string,
): Promise<MachineBinding> {
  const isPreview = getRuntimeMode().Mode === "preview";
  if (isPreview) {
    return releasePreviewBinding(licenseKey, bindingId);
  }

  const headers = idempotencyKey ? { "Idempotency-Key": idempotencyKey } : undefined;
  const [result] = await requestLaraApi(
    `/Reseller/Licenses/${encodeURIComponent(licenseKey)}/Bindings/${bindingId}/Release`,
    machineBindingSchema,
    { method: HttpMethodType.Post, headers },
  );

  return result;
}
