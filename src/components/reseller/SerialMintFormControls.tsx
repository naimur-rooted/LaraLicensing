import { useQuery } from "@tanstack/react-query";
import { resellerPrefixesQueryOptions } from "../../lib/lara-prefix";
import type { LicenseCategoryName } from "../../lib/lara-reseller-mint";

const CATEGORIES: LicenseCategoryName[] = [
  "Daily",
  "Weekly",
  "Monthly",
  "Yearly",
  "Lifetime",
  "Standard Key",
];

export interface SerialMintFormControlsProps {
  resellerId: number;
  prefix: string;
  category: LicenseCategoryName;
  tier: "Starter" | "Growth" | "Scale" | "Enterprise";
  environment: "Production" | "Staging" | "Sandbox";
  onPrefixChange: (p: string) => void;
  onCategoryChange: (c: LicenseCategoryName) => void;
  onTierChange: (t: "Starter" | "Growth" | "Scale" | "Enterprise") => void;
  onEnvironmentChange: (e: "Production" | "Staging" | "Sandbox") => void;
}

export function SerialMintFormControls(props: SerialMintFormControlsProps) {
  const { data: prefixes = [] } = useQuery(resellerPrefixesQueryOptions(props.resellerId));

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div>
        <label className="mb-1 block text-xs font-medium text-foreground">Vanity Prefix</label>
        <select
          value={props.prefix}
          onChange={(e) => props.onPrefixChange(e.target.value)}
          className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus-ring"
        >
          {prefixes.map((p) => (
            <option key={p.PrefixId} value={p.PrefixValue}>
              {p.PrefixValue}
            </option>
          ))}
          {prefixes.length === 0 ? <option value="ALIM">ALIM (Default)</option> : null}
        </select>
      </div>

      <div>
        <label className="mb-1 block text-xs font-medium text-foreground">License Category</label>
        <select
          value={props.category}
          onChange={(e) => props.onCategoryChange(e.target.value as LicenseCategoryName)}
          className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus-ring"
        >
          {CATEGORIES.map((cat) => (
            <option key={cat} value={cat}>
              {cat}
            </option>
          ))}
        </select>
      </div>

      <div>
        <label className="mb-1 block text-xs font-medium text-foreground">Tier</label>
        <select
          value={props.tier}
          onChange={(e) => props.onTierChange(e.target.value as "Starter" | "Growth" | "Scale" | "Enterprise")}
          className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus-ring"
        >
          <option value="Starter">Starter (1 Seat)</option>
          <option value="Growth">Growth (3 Seats)</option>
          <option value="Scale">Scale (5 Seats)</option>
          <option value="Enterprise">Enterprise (10 Seats)</option>
        </select>
      </div>

      <div>
        <label className="mb-1 block text-xs font-medium text-foreground">Environment</label>
        <select
          value={props.environment}
          onChange={(e) => props.onEnvironmentChange(e.target.value as "Production" | "Staging" | "Sandbox")}
          className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus-ring"
        >
          <option value="Production">Production</option>
          <option value="Staging">Staging</option>
          <option value="Sandbox">Sandbox</option>
        </select>
      </div>
    </div>
  );
}
