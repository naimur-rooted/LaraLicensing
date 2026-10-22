import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";

import { HttpMethodType, requestLaraApi } from "./lara-api-client";
import { getRuntimeMode } from "./runtime-mode";

const PREFIX_LIMIT = 100;

export const prefixSchema = z.object({
  PrefixId: z.number().int().positive(),
  ResellerId: z.number().int().positive(),
  PrefixValue: z.string().regex(/^[A-Z0-9]{3,12}$/),
  IsActive: z.boolean(),
});

export type Prefix = z.infer<typeof prefixSchema>;

export const prefixCreateSchema = z.object({
  PrefixValue: z
    .string()
    .trim()
    .transform((value) => value.toUpperCase())
    .pipe(z.string().regex(/^[A-Z0-9]{3,12}$/, "3 to 12 uppercase letters or digits")),
});

export type PrefixCreateInput = z.infer<typeof prefixCreateSchema>;

function fetchPreviewPrefixes(resellerId: number): Prefix[] {
  return [
    { PrefixId: 1, ResellerId: resellerId, PrefixValue: "ALIM", IsActive: true },
    { PrefixId: 2, ResellerId: resellerId, PrefixValue: "NEXUS", IsActive: true },
    { PrefixId: 3, ResellerId: resellerId, PrefixValue: "CORP", IsActive: true },
  ];
}

export function resellerPrefixesQueryOptions(resellerId: number) {
  return queryOptions({
    queryKey: ["LaraApi", "Resellers", resellerId, "Prefixes", PREFIX_LIMIT],
    queryFn: ({ signal }) => {
      const isPreview = getRuntimeMode().Mode === "preview";
      if (isPreview) {
        return fetchPreviewPrefixes(resellerId);
      }

      return requestLaraApi(`/Resellers/${resellerId}/Prefixes?Limit=${PREFIX_LIMIT}`, prefixSchema, {
        signal,
      });
    },
    retry: false,
  });
}

export async function createResellerPrefix(
  resellerId: number,
  input: PrefixCreateInput,
  idempotencyKey: string,
): Promise<Prefix> {
  const isPreview = getRuntimeMode().Mode === "preview";
  if (isPreview) {
    return {
      PrefixId: Math.floor(Math.random() * 1000) + 10,
      ResellerId: resellerId,
      PrefixValue: input.PrefixValue,
      IsActive: true,
    };
  }

  const [created] = await requestLaraApi(`/Resellers/${resellerId}/Prefixes`, prefixSchema, {
    method: HttpMethodType.Post,
    body: input,
    headers: { "Idempotency-Key": idempotencyKey },
  });

  return created;
}

export async function deletePrefix(prefixId: number, idempotencyKey: string): Promise<void> {
  const isPreview = getRuntimeMode().Mode === "preview";
  if (isPreview) {
    return;
  }

  await requestLaraApi(`/Prefixes/${prefixId}`, z.unknown(), {
    method: HttpMethodType.Delete,
    headers: { "Idempotency-Key": idempotencyKey },
  });
}
