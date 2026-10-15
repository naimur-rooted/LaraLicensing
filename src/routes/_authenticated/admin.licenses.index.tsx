import { useSuspenseQuery } from "@tanstack/react-query";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import {
  ArrowRight,
  Barcode,
  Building2,
  ExternalLink,
  Info,
  KeyRound,
  Plus,
  Search,
} from "lucide-react";
import { useMemo, useState, type FormEvent } from "react";

import { PageActions } from "../../components/shell/AppShell";
import { PageHeader } from "../../components/shell/PageHeader";
import { RoutePending, RouteErrorState } from "../../components/shell/RouteFallbacks";
import {
  adminLicensesQueryOptions,
  type License,
} from "../../lib/lara-license";
import { resellersQueryOptions, type Reseller } from "../../lib/lara-reseller";

export const Route = createFileRoute("/_authenticated/admin/licenses/")({
  ssr: false,
  loader: async ({ context }) => {
    await Promise.all([
      context.queryClient.ensureQueryData(resellersQueryOptions),
      context.queryClient.ensureQueryData(adminLicensesQueryOptions()),
    ]);
  },
  head: () => ({
    meta: [
      { title: "Licenses | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  pendingComponent: () => (
    <RoutePending
      title="Licenses"
      description="Manage, issue, and inspect software licenses across reseller shards."
    />
  ),
  errorComponent: ({ error, reset }) => (
    <RouteErrorState title="Licenses" error={error} reset={reset} />
  ),
  component: AdminLicensesHubPage,
});

const PRIMARY_BTN_CLASS =
  "focus-ring inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90";

const CATEGORY_LABELS: Record<number, string> = {
  1: "Daily",
  2: "Weekly",
  3: "Monthly",
  4: "Yearly",
  5: "Lifetime",
  6: "Dev",
  7: "Key",
};

const TIER_LABELS: Record<number, string> = {
  1: "Tier 1",
  2: "Tier 2",
  3: "Tier 3",
  4: "Unlimited",
};

function formatLicenseDate(iso: string | null | undefined): string {
  if (!iso) return "Never";
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  } catch {
    return iso;
  }
}

function ResellerBadge({
  resellerId,
  resellerMap,
}: {
  resellerId: number | null | undefined;
  resellerMap: Map<number, string>;
}) {
  if (!resellerId) {
    return (
      <span className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
        Admin-issued
      </span>
    );
  }
  const name = resellerMap.get(resellerId) ?? `Reseller #${resellerId}`;

  return (
    <Link
      to="/reseller/$resellerId/licenses"
      params={{ resellerId }}
      className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
    >
      <Building2 className="size-3" />
      <span className="truncate max-w-[140px]">{name}</span>
    </Link>
  );
}

function matchesLicense(l: License, query: string, resellerFilter: string): boolean {
  if (resellerFilter === "admin" && l.ResellerId !== null) return false;
  if (resellerFilter && resellerFilter !== "all" && resellerFilter !== "admin") {
    if (String(l.ResellerId) !== resellerFilter) return false;
  }
  if (!query.trim()) return true;
  const q = query.trim().toLowerCase();

  return String(l.LicenseId).includes(q) || l.ProductVersion.toLowerCase().includes(q);
}

function LicenseRowItem({
  license,
  resellerMap,
}: {
  license: License;
  resellerMap: Map<number, string>;
}) {
  const cat = CATEGORY_LABELS[license.LicenseCategoryId] ?? "Key";
  const tier = TIER_LABELS[license.LicenseTierId] ?? "Tier 1";

  return (
    <tr key={license.LicenseId} className="border-b border-border/50 hover:bg-muted/30">
      <td className="px-4 py-3 font-semibold text-foreground">#{license.LicenseId}</td>
      <td className="px-4 py-3 font-mono text-xs">{license.ProductVersion}</td>
      <td className="px-4 py-3">
        <ResellerBadge resellerId={license.ResellerId} resellerMap={resellerMap} />
      </td>
      <td className="px-4 py-3 text-xs text-muted-foreground">
        {cat} · {tier}
      </td>
      <td className="px-4 py-3">
        <span
          className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${
            license.IsActive
              ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
              : "bg-muted text-muted-foreground"
          }`}
        >
          {license.IsActive ? "Active" : "Inactive"}
        </span>
      </td>
      <td className="px-4 py-3 text-xs text-muted-foreground">
        {formatLicenseDate(license.IssuedAt)}
      </td>
      <td className="px-4 py-3 text-right">
        <Link
          to="/admin/licenses/$licenseId"
          params={{ licenseId: license.LicenseId }}
          className="inline-flex items-center gap-1 rounded border border-border px-2 py-1 text-xs font-medium hover:bg-accent"
        >
          View
        </Link>
      </td>
    </tr>
  );
}

function LicenseTable({
  licenses,
  resellerMap,
}: {
  licenses: License[];
  resellerMap: Map<number, string>;
}) {
  if (licenses.length === 0) {
    return (
      <div className="rounded-lg border border-border bg-card p-8 text-center text-sm text-muted-foreground">
        No licenses found matching your current filter.
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
      <table className="w-full text-left text-sm">
        <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground">
          <tr>
            <th className="px-4 py-2.5">ID</th>
            <th className="px-4 py-2.5">Serial / Version</th>
            <th className="px-4 py-2.5">Reseller Shard</th>
            <th className="px-4 py-2.5">Category · Tier</th>
            <th className="px-4 py-2.5">Status</th>
            <th className="px-4 py-2.5">Issued</th>
            <th className="px-4 py-2.5 text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          {licenses.map((l) => (
            <LicenseRowItem key={l.LicenseId} license={l} resellerMap={resellerMap} />
          ))}
        </tbody>
      </table>
    </div>
  );
}

function LicenseFilterBar({
  query,
  onQueryChange,
  resellerFilter,
  onResellerChange,
  resellers,
}: {
  query: string;
  onQueryChange: (v: string) => void;
  resellerFilter: string;
  onResellerChange: (v: string) => void;
  resellers: Reseller[];
}) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <div className="relative flex-1 min-w-[200px]">
        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
        <input
          type="text"
          placeholder="Filter by License ID or Serial..."
          value={query}
          onChange={(e) => onQueryChange(e.target.value)}
          className="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-xs"
        />
      </div>
      <select
        value={resellerFilter}
        onChange={(e) => onResellerChange(e.target.value)}
        className="h-9 rounded-md border border-input bg-background px-2.5 text-xs"
      >
        <option value="all">All Shards & Admin</option>
        <option value="admin">Admin-issued (direct)</option>
        {resellers.map((r) => (
          <option key={r.ResellerId} value={String(r.ResellerId)}>
            {r.ResellerName}
          </option>
        ))}
      </select>
    </div>
  );
}

function ResellerShardCard({ reseller }: { reseller: Reseller }) {
  return (
    <div className="flex flex-col justify-between rounded-lg border border-border bg-card p-4 transition-all hover:border-primary/40 hover:shadow-sm">
      <div className="space-y-1.5">
        <div className="flex items-center justify-between">
          <span className="font-semibold text-sm text-foreground truncate">
            {reseller.ResellerName}
          </span>
          <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${
              reseller.IsActive
                ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                : "bg-muted text-muted-foreground"
            }`}
          >
            {reseller.IsActive ? "Active" : "Inactive"}
          </span>
        </div>
        <p className="text-xs text-muted-foreground font-mono truncate">
          Slug: {reseller.ResellerSlug}
        </p>
        <p className="text-[11px] text-muted-foreground truncate">{reseller.ContactEmail}</p>
      </div>
      <div className="mt-4 pt-3 border-t border-border/50 flex items-center justify-between">
        <Link
          to="/reseller/$resellerId/licenses"
          params={{ resellerId: reseller.ResellerId }}
          className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
        >
          View Licenses <ArrowRight className="size-3" />
        </Link>
        <Link
          to="/admin/licenses/new"
          search={{ resellerId: reseller.ResellerId }}
          className="text-xs font-medium text-primary hover:underline"
        >
          + Issue License
        </Link>
      </div>
    </div>
  );
}

function ResellerShardsSection({ resellers }: { resellers: Reseller[] }) {
  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">
          Browse by Reseller Shard ({resellers.length})
        </h2>
        <Link
          to="/admin/resellers"
          className="text-xs font-medium text-primary hover:underline flex items-center gap-1"
        >
          Manage Resellers <ExternalLink className="size-3" />
        </Link>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {resellers.map((r) => (
          <ResellerShardCard key={r.ResellerId} reseller={r} />
        ))}
      </div>
    </section>
  );
}

function LicenseOverviewCards() {
  const [quickSerial, setQuickSerial] = useState("");
  const navigate = useNavigate();

  function handleSerialSearch(e: FormEvent) {
    e.preventDefault();
    if (!quickSerial.trim()) return;
    void navigate({ to: "/admin/serials" });
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <div className="flex flex-col justify-between rounded-lg border border-border bg-card p-5 shadow-sm">
        <div className="space-y-2">
          <div className="flex items-center gap-2 text-primary font-medium">
            <KeyRound className="size-5" />
            <span>Issue New License</span>
          </div>
          <p className="text-xs text-muted-foreground">
            Launch the 5-step minting wizard: choose reseller shard, license tier, custom machine limits, and environment tags.
          </p>
        </div>
        <div className="mt-4 pt-3 border-t border-border/50">
          <Link
            to="/admin/licenses/new"
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
          >
            Open License Wizard <ArrowRight className="size-3.5" />
          </Link>
        </div>
      </div>

      <div className="flex flex-col justify-between rounded-lg border border-border bg-card p-5 shadow-sm">
        <div className="space-y-2">
          <div className="flex items-center gap-2 text-primary font-medium">
            <Barcode className="size-5" />
            <span>Serial Key Lookup</span>
          </div>
          <p className="text-xs text-muted-foreground">
            Quickly verify an issued license key, inspect bound hardware fingerprints, or release machine bindings.
          </p>
        </div>
        <form onSubmit={handleSerialSearch} className="mt-4 flex gap-2">
          <input
            type="text"
            placeholder="e.g. DEMO-PRO-2026-ABCD"
            value={quickSerial}
            onChange={(e) => setQuickSerial(e.target.value)}
            className="h-8 flex-1 rounded-md border border-input bg-background px-2.5 text-xs font-mono"
          />
          <Link
            to="/admin/serials"
            className="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-muted px-3 text-xs font-medium hover:bg-muted/80"
          >
            <Search className="size-3.5" />
            Lookup
          </Link>
        </form>
      </div>
    </div>
  );
}

function ArchitectureNote() {
  return (
    <div
      role="note"
      className="flex items-start gap-3 rounded-lg border border-primary/20 bg-primary/5 p-4 text-sm text-foreground"
    >
      <Info className="mt-0.5 size-4 shrink-0 text-primary" />
      <div className="space-y-1">
        <p className="font-semibold text-primary">Multi-Tenant Split-Shard Architecture</p>
        <p className="text-muted-foreground text-xs leading-relaxed">
          Licenses are stored inside each reseller's isolated tenant database shard. Use the 5-step wizard to issue new licenses, inspect all issued licenses below, or select a reseller shard to browse their specific tenant inventory.
        </p>
      </div>
    </div>
  );
}

function AdminLicensesHubPage() {
  const { data: resellers } = useSuspenseQuery(resellersQueryOptions);
  const { data: licenses } = useSuspenseQuery(adminLicensesQueryOptions());
  const [filterReseller, setFilterReseller] = useState("all");
  const [query, setQuery] = useState("");

  const resellerMap = useMemo(
    () => new Map(resellers.map((r) => [r.ResellerId, r.ResellerName])),
    [resellers],
  );

  const filtered = useMemo(
    () => licenses.filter((l) => matchesLicense(l, query, filterReseller)),
    [licenses, query, filterReseller],
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Licenses"
        breadcrumbs={[{ label: "Admin", to: "/admin" }, { label: "Licenses" }]}
        description="Central licensing operations: issue licenses, inspect serials, and browse tenant shards."
      />
      <PageActions>
        <Link to="/admin/licenses/new" className={PRIMARY_BTN_CLASS}>
          <Plus className="size-4" />
          Issue license
        </Link>
      </PageActions>
      <ArchitectureNote />
      <LicenseOverviewCards />
      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">
            All Issued Licenses ({filtered.length})
          </h2>
          <Link to="/admin/licenses/new" className="text-xs font-semibold text-primary hover:underline">
            + Issue License
          </Link>
        </div>
        <LicenseFilterBar
          query={query}
          onQueryChange={setQuery}
          resellerFilter={filterReseller}
          onResellerChange={setFilterReseller}
          resellers={resellers}
        />
        <LicenseTable licenses={filtered} resellerMap={resellerMap} />
      </section>
      <ResellerShardsSection resellers={resellers} />
    </div>
  );
}
