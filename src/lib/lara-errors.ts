import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";

import { apiClient } from "./api-client";
import { requestLaraApi } from "./lara-api-client";
import { getRuntimeMode } from "./runtime-mode";
import type { AdminErrorRow } from "@/generated/api/schema";

export const adminErrorRowSchema = z.object({
  RequestedAt: z.string(),
  HttpStatus: z.union([z.number(), z.string()]),
  Category: z.string(),
  ErrorCode: z.string(),
  RequestId: z.string(),
  ErrorId: z.string(),
});

export function adminErrorsQueryOptions() {
  return queryOptions<AdminErrorRow[]>({
    queryKey: ["admin", "errors"],
    queryFn: async ({ signal }) => {
      if (getRuntimeMode().Mode === "preview") {
        return (await apiClient.call("admin.errors.list", {}, { signal })) as unknown as AdminErrorRow[];
      }

      return requestLaraApi("/Admin/Errors", adminErrorRowSchema, { signal });
    },
    staleTime: 30_000,
  });
}
