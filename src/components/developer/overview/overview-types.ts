export interface OverviewKpi {
  readonly current: number;
  readonly max: number | null;
  readonly label: string;
}

export interface OverviewTimelinePoint {
  readonly date: string;
  readonly shortDate: string;
  readonly activations: number;
  readonly trialActivations: number;
}

export interface OverviewCountryPoint {
  readonly country: string;
  readonly code: string;
  readonly count: number;
  readonly percentage: number;
}

export interface OverviewData {
  readonly kpis: {
    readonly activations: OverviewKpi;
    readonly trialActivations: OverviewKpi;
    readonly licenses: OverviewKpi;
    readonly users: OverviewKpi;
    readonly releases: OverviewKpi;
  };
  readonly timeline: readonly OverviewTimelinePoint[];
  readonly activationsByCountry: readonly OverviewCountryPoint[];
  readonly productsCount: number;
  readonly trialExpiresOn: string;
}

export interface OverviewProductOption {
  readonly slug: string;
  readonly name: string;
}

export const ZERO_STATE_OVERVIEW: OverviewData = {
  kpis: {
    activations: { current: 0, max: 50, label: "Activations" },
    trialActivations: { current: 0, max: 20000, label: "Trial Activations" },
    licenses: { current: 0, max: null, label: "Licenses" },
    users: { current: 0, max: 50, label: "Users" },
    releases: { current: 0, max: 50, label: "Releases" },
  },
  timeline: [],
  activationsByCountry: [],
  productsCount: 0,
  trialExpiresOn: "Active",
};

export const PREVIEW_OVERVIEW_DATA: OverviewData = ZERO_STATE_OVERVIEW;

export function mapOverviewData(raw: any): OverviewData {
  if (raw?.kpis) {
    return {
      kpis: {
        activations: {
          current: raw.kpis.activations?.current ?? 0,
          max: raw.kpis.activations?.max ?? 50,
          label: "Activations",
        },
        trialActivations: {
          current: raw.kpis.trialActivations?.current ?? 0,
          max: raw.kpis.trialActivations?.max ?? 20000,
          label: "Trial Activations",
        },
        licenses: { current: raw.kpis.licenses?.current ?? 0, max: null, label: "Licenses" },
        users: { current: raw.kpis.users?.current ?? 0, max: raw.kpis.users?.max ?? 50, label: "Users" },
        releases: { current: raw.kpis.releases?.current ?? 0, max: raw.kpis.releases?.max ?? 50, label: "Releases" },
      },
      timeline: raw.timeline ?? raw.chartData ?? [],
      activationsByCountry: raw.activationsByCountry ?? raw.topCountries ?? [],
      productsCount: raw.productsCount ?? 0,
      trialExpiresOn: raw.trialExpiresOn ?? "Nov 20, 2026",
    };
  }

  const s = raw?.summary;
  return {
    kpis: {
      activations: { current: s?.totalActivations ?? 0, max: s?.quotas?.activations?.total ?? 50, label: "Activations" },
      trialActivations: { current: s?.totalTrialActivations ?? 0, max: s?.quotas?.trialActivations?.total ?? 20000, label: "Trial Activations" },
      licenses: { current: s?.totalLicenses ?? 0, max: null, label: "Licenses" },
      users: { current: s?.totalUsers ?? 0, max: s?.quotas?.users?.total ?? 50, label: "Users" },
      releases: { current: s?.totalReleases ?? 0, max: s?.quotas?.releases?.total ?? 50, label: "Releases" },
    },
    timeline: raw?.chartData ?? [],
    activationsByCountry: raw?.topCountries ?? [],
    productsCount: raw?.productsCount ?? 0,
    trialExpiresOn: raw?.trialExpiresOn ?? "Nov 20, 2026",
  };
}

export function createOverviewAuthHeaders(token?: string): HeadersInit {
  const headers: Record<string, string> = { Accept: "application/json" };
  if (typeof token === "string" && token.length > 0) {
    headers["Authorization"] = `Bearer ${token}`;
  }
  return headers;
}
