import { Card, CardContent } from "@/components/ui/card";
import type { OverviewData } from "./overview-types";

interface OverviewKpiCardsProps {
  readonly kpis: OverviewData["kpis"] | null;
}

export function OverviewKpiCards({ kpis }: OverviewKpiCardsProps) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
      <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
        <CardContent className="p-4 space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
            {kpis ? (
              <>
                {kpis.activations.current}
                <span className="text-muted-foreground/60 text-lg font-normal"> / {kpis.activations.max ?? 50}</span>
              </>
            ) : (
              "--"
            )}
          </div>
          <p className="text-xs font-medium text-muted-foreground">{kpis?.activations.label || "Activations"}</p>
        </CardContent>
      </Card>

      <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
        <CardContent className="p-4 space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
            {kpis ? (
              <>
                {kpis.trialActivations.current}
                <span className="text-muted-foreground/60 text-lg font-normal"> / {kpis.trialActivations.max ?? 20000}</span>
              </>
            ) : (
              "--"
            )}
          </div>
          <p className="text-xs font-medium text-muted-foreground">{kpis?.trialActivations.label || "Trial Activations"}</p>
        </CardContent>
      </Card>

      <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
        <CardContent className="p-4 space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
            {kpis?.licenses.current ?? 0}
          </div>
          <p className="text-xs font-medium text-muted-foreground">{kpis?.licenses.label || "Licenses"}</p>
        </CardContent>
      </Card>

      <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
        <CardContent className="p-4 space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
            {kpis ? (
              <>
                {kpis.users.current}
                <span className="text-muted-foreground/60 text-lg font-normal"> / {kpis.users.max ?? 50}</span>
              </>
            ) : (
              "--"
            )}
          </div>
          <p className="text-xs font-medium text-muted-foreground">{kpis?.users.label || "Users"}</p>
        </CardContent>
      </Card>

      <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors col-span-2 sm:col-span-1">
        <CardContent className="p-4 space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
            {kpis ? (
              <>
                {kpis.releases.current}
                <span className="text-muted-foreground/60 text-lg font-normal"> / {kpis.releases.max ?? 50}</span>
              </>
            ) : (
              "--"
            )}
          </div>
          <p className="text-xs font-medium text-muted-foreground">{kpis?.releases.label || "Releases"}</p>
        </CardContent>
      </Card>
    </div>
  );
}
