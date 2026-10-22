import { Link } from "@tanstack/react-router";
import { Sparkles, Gauge, Cpu, ArrowUpRight } from "lucide-react";

export interface ResellerQuickActionsProps {
  resellerId: number;
}

export function ResellerQuickActions({ resellerId }: ResellerQuickActionsProps) {
  return (
    <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
      <div className="border-b border-border pb-4">
        <h3 className="font-semibold text-foreground">Quick Action Shortcuts</h3>
        <p className="text-xs text-muted-foreground">Jump directly to core reseller workflows</p>
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        <ActionLink
          to={`/reseller/${resellerId}/serials`}
          search={{ view: "mint" }}
          icon={Sparkles}
          title="Mint Serial"
          description="Generate branded serial keys with vanity prefixes and duration tiers."
        />
        <ActionLink
          to={`/reseller/${resellerId}/quota-requests`}
          icon={Gauge}
          title="Request Quota"
          description="Submit self-service requests for additional license capacity."
        />
        <ActionLink
          to={`/reseller/${resellerId}/serials`}
          search={{ view: "troubleshoot" }}
          icon={Cpu}
          title="Troubleshoot Device"
          description="Inspect bound hardware fingerprints and remotely release seats."
        />
      </div>
    </div>
  );
}

interface ActionLinkProps {
  to: string;
  search?: Record<string, string>;
  icon: typeof Sparkles;
  title: string;
  description: string;
}

function ActionLink(props: ActionLinkProps) {
  const Icon = props.icon;

  return (
    <Link
      to={props.to}
      search={props.search}
      className="group relative flex flex-col justify-between rounded-lg border border-border bg-background p-4 transition-all hover:border-primary/50 hover:bg-accent/40"
    >
      <div>
        <div className="flex items-center justify-between">
          <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
            <Icon className="size-4" />
          </div>
          <ArrowUpRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary" />
        </div>
        <h4 className="mt-3 text-sm font-semibold text-foreground">{props.title}</h4>
        <p className="mt-1 text-xs text-muted-foreground">{props.description}</p>
      </div>
    </Link>
  );
}
