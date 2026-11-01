import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";

import { requestLaraApi, HttpMethodType } from "./lara-api-client";

export const resellerApplicationSchema = z.object({
  ApplicationId: z.number().int().positive(),
  CompanyName: z.string(),
  ContactEmail: z.string(),
  Website: z.string().nullable().optional(),
  DesiredPrefix: z.string().nullable().optional(),
  ExpectedVolume: z.string().nullable().optional(),
  Status: z.enum(["Pending", "Approved", "Denied"]),
  TemporaryPassword: z.string().nullable().optional(),
  DecidedByUserId: z.number().nullable().optional(),
  DecidedAt: z.string().nullable().optional(),
  CreatedAt: z.string(),
  UpdatedAt: z.string(),
});

export type ResellerApplication = z.infer<typeof resellerApplicationSchema>;

export const resellerApplicationsQueryOptions = queryOptions({
  queryKey: ["LaraApi", "Admin", "ResellerApplications"],
  queryFn: async ({ signal }) => {
    return requestLaraApi<ResellerApplication>("/Admin/ResellerApplications", resellerApplicationSchema, {
      signal,
    });
  },
  staleTime: 3_000,
  refetchInterval: 5_000,
});

export interface ApprovedResellerResult {
  ResellerId: number;
  ResellerName: string;
  ResellerSlug: string;
  ContactEmail: string;
  LoginEmail: string;
  TemporaryPassword?: string;
}

export async function approveResellerApplication(applicationId: number): Promise<ApprovedResellerResult> {
  const res = await requestLaraApi<ApprovedResellerResult>(
    `/Admin/ResellerApplications/${applicationId}/Approve`,
    z.unknown(),
    { method: HttpMethodType.Post }
  );
  return res[0];
}

export async function denyResellerApplication(applicationId: number): Promise<void> {
  await requestLaraApi(
    `/Admin/ResellerApplications/${applicationId}/Deny`,
    z.unknown(),
    { method: HttpMethodType.Post }
  );
}
